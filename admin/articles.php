<?php
/**
 * Article Management
 * CRUD operations for knowledge base articles
 */

require_once '../includes/bootstrap.php';

// Require admin authentication
$auth->requireAdmin();

$action = getGet('action', 'list');
$articleId = getGet('id', 0);
$currentUser = $auth->getCurrentUser();

// Handle form submissions
if (isPost()) {
    $postAction = getPost('action', '');
    $csrfToken = getPost('csrf_token', '');
    
    // Validate CSRF token
    if (!$auth->validateCSRF($csrfToken)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        redirect('articles.php');
    }
    
    switch ($postAction) {
        case 'create':
        case 'update':
            handleArticleSave($postAction);
            break;
        case 'delete':
            handleArticleDelete();
            break;
        case 'bulk_action':
            handleBulkAction();
            break;
    }
}

// Handle different actions
switch ($action) {
    case 'new':
        showArticleForm();
        break;
    case 'edit':
        showArticleForm($articleId);
        break;
    case 'view':
        showArticleView($articleId);
        break;
    default:
        showArticleList();
        break;
}

/**
 * Handle article save (create/update)
 */
function handleArticleSave($action) {
    global $db, $currentUser;
    
    $title = validateText(getPost('title', ''), 1, 255);
    $content = getPost('content', '');
    $excerpt = validateText(getPost('excerpt', ''), 0, 500);
    $categoryId = getPost('category_id', null);
    $status = getPost('status', 'draft');
    $featured = getPost('featured', 0) ? 1 : 0;
    $tags = getPost('tags', []);
    $articleId = getPost('article_id', 0);
    
    // Validation
    if (!$title) {
        setFlashMessage('error', 'Title is required and must be between 1-255 characters.');
        redirect('articles.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $articleId));
    }
    
    if (empty($content)) {
        setFlashMessage('error', 'Content is required.');
        redirect('articles.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $articleId));
    }
    
    if (!in_array($status, ['draft', 'published', 'archived'])) {
        $status = 'draft';
    }
    
    // Generate slug
    $slug = generateSlug($title);
    
    // Check for duplicate slug
    $slugCheckSql = "SELECT id FROM articles WHERE slug = ? AND id != ?";
    $existingSlug = $db->fetchOne($slugCheckSql, [$slug, $articleId]);
    if ($existingSlug) {
        $slug .= '-' . time();
    }
    
    // Generate excerpt if not provided
    if (empty($excerpt)) {
        $excerpt = generateExcerpt($content);
    }
    
    try {
        $db->beginTransaction();
        
        if ($action === 'create') {
            // Create new article
            $sql = "INSERT INTO articles (title, slug, content, excerpt, category_id, author_id, status, featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $articleId = $db->insert($sql, [$title, $slug, $content, $excerpt, $categoryId, $currentUser['id'], $status, $featured]);
            $message = 'Article created successfully.';
        } else {
            // Update existing article
            $sql = "UPDATE articles SET title = ?, slug = ?, content = ?, excerpt = ?, category_id = ?, status = ?, featured = ?, updated_at = NOW() 
                    WHERE id = ?";
            $db->execute($sql, [$title, $slug, $content, $excerpt, $categoryId, $status, $featured, $articleId]);
            $message = 'Article updated successfully.';
        }
        
        // Handle tags
        if ($articleId) {
            // Remove existing tags
            $db->execute("DELETE FROM article_tags WHERE article_id = ?", [$articleId]);
            
            // Add new tags
            if (!empty($tags)) {
                foreach ($tags as $tagId) {
                    if (is_numeric($tagId)) {
                        $db->execute("INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)", [$articleId, $tagId]);
                    }
                }
            }
        }
        
        $db->commit();
        setFlashMessage('success', $message);
        redirect('articles.php?action=edit&id=' . $articleId);
        
    } catch (Exception $e) {
        $db->rollback();
        setFlashMessage('error', 'Error saving article: ' . $e->getMessage());
        redirect('articles.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $articleId));
    }
}

/**
 * Handle article deletion
 */
function handleArticleDelete() {
    global $db;
    
    $articleId = getPost('article_id', 0);
    
    if (!$articleId) {
        setFlashMessage('error', 'Invalid article ID.');
        redirect('articles.php');
    }
    
    try {
        $db->execute("DELETE FROM articles WHERE id = ?", [$articleId]);
        setFlashMessage('success', 'Article deleted successfully.');
    } catch (Exception $e) {
        setFlashMessage('error', 'Error deleting article: ' . $e->getMessage());
    }
    
    redirect('articles.php');
}

/**
 * Handle bulk actions
 */
function handleBulkAction() {
    global $db;
    
    $bulkAction = getPost('bulk_action', '');
    $selectedIds = getPost('selected_articles', []);
    
    if (empty($selectedIds) || !is_array($selectedIds)) {
        setFlashMessage('error', 'No articles selected.');
        redirect('articles.php');
    }
    
    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
    
    try {
        switch ($bulkAction) {
            case 'publish':
                $db->execute("UPDATE articles SET status = 'published' WHERE id IN ($placeholders)", $selectedIds);
                setFlashMessage('success', count($selectedIds) . ' articles published.');
                break;
            case 'draft':
                $db->execute("UPDATE articles SET status = 'draft' WHERE id IN ($placeholders)", $selectedIds);
                setFlashMessage('success', count($selectedIds) . ' articles moved to draft.');
                break;
            case 'archive':
                $db->execute("UPDATE articles SET status = 'archived' WHERE id IN ($placeholders)", $selectedIds);
                setFlashMessage('success', count($selectedIds) . ' articles archived.');
                break;
            case 'delete':
                $db->execute("DELETE FROM articles WHERE id IN ($placeholders)", $selectedIds);
                setFlashMessage('success', count($selectedIds) . ' articles deleted.');
                break;
            default:
                setFlashMessage('error', 'Invalid bulk action.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error performing bulk action: ' . $e->getMessage());
    }
    
    redirect('articles.php');
}

/**
 * Show article list
 */
function showArticleList() {
    global $db, $auth;
    
    // Get filters
    $status = getGet('status', '');
    $category = getGet('category', '');
    $search = getGet('search', '');
    $page = max(1, getGet('page', 1));
    $perPage = ARTICLES_PER_PAGE;
    $offset = ($page - 1) * $perPage;
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($status)) {
        $whereConditions[] = "a.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
        $whereConditions[] = "a.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM articles a $whereClause";
    $totalArticles = $db->fetchOne($countSql, $params)['total'];
    
    // Get articles
    $sql = "SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            $whereClause
            ORDER BY a.updated_at DESC
            LIMIT $perPage OFFSET $offset";
    
    $articles = $db->fetchAll($sql, $params);
    
    // Get categories for filter
    $categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
    
    // Pagination
    $pagination = getPagination($page, $totalArticles, $perPage);
    
    $pageTitle = 'Manage Articles';
    includeHeader($pageTitle);
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="py-3">
                    <h6 class="text-muted px-3 mb-3">NAVIGATION</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="articles.php">
                            <i class="bi bi-file-text"></i> Articles
                        </a>
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-folder"></i> Categories
                        </a>
                        <a class="nav-link" href="tags.php">
                            <i class="bi bi-tags"></i> Tags
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content-area">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Articles</h1>
                    <a href="articles.php?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Article
                    </a>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="articles.php" class="row g-3">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select name="category" id="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search articles...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Articles Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">Articles (<?php echo number_format($totalArticles); ?>)</h5>
                            </div>
                            <div class="col-auto">
                                <form method="POST" action="articles.php" id="bulkForm">
                                    <?php echo $auth->getCSRFInput(); ?>
                                    <input type="hidden" name="action" value="bulk_action">
                                    <div class="input-group">
                                        <select name="bulk_action" class="form-select" required>
                                            <option value="">Bulk Actions</option>
                                            <option value="publish">Publish</option>
                                            <option value="draft">Move to Draft</option>
                                            <option value="archive">Archive</option>
                                            <option value="delete">Delete</option>
                                        </select>
                                        <button type="submit" class="btn btn-outline-secondary" onclick="return confirmBulkAction()">Apply</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($articles)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-file-text fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No articles found.</p>
                                <a href="articles.php?action=new" class="btn btn-primary">Create Your First Article</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Author</th>
                                            <th>Status</th>
                                            <th>Views</th>
                                            <th>Updated</th>
                                            <th width="120">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($articles as $article): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_articles[]" 
                                                           value="<?php echo $article['id']; ?>" 
                                                           class="form-check-input article-checkbox">
                                                </td>
                                                <td>
                                                    <div>
                                                        <a href="articles.php?action=edit&id=<?php echo $article['id']; ?>" 
                                                           class="text-decoration-none fw-bold">
                                                            <?php echo htmlspecialchars($article['title']); ?>
                                                        </a>
                                                        <?php if ($article['featured']): ?>
                                                            <i class="bi bi-star-fill text-warning ms-1" title="Featured"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo truncateText(strip_tags($article['excerpt'] ?: $article['content']), 80); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($article['category_name']): ?>
                                                        <span class="badge category-badge">
                                                            <?php echo htmlspecialchars($article['category_name']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Uncategorized</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $article['status'] === 'published' ? 'success' : ($article['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($article['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($article['views']); ?></td>
                                                <td><?php echo formatDate($article['updated_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="articles.php?action=edit&id=<?php echo $article['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="../article.php?id=<?php echo $article['id']; ?>" 
                                                           class="btn btn-outline-success" title="View" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteArticle(<?php echo $article['id']; ?>)" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($pagination['total_pages'] > 1): ?>
                                <div class="card-footer">
                                    <nav aria-label="Articles pagination">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php if ($pagination['has_previous']): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo buildUrl('articles.php', array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">Previous</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?php echo buildUrl('articles.php', array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($pagination['has_next']): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?php echo buildUrl('articles.php', array_merge($_GET, ['page' => $pagination['next_page']])); ?>">Next</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.article-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });
    
    // Confirm bulk actions
    function confirmBulkAction() {
        const selected = document.querySelectorAll('.article-checkbox:checked');
        if (selected.length === 0) {
            alert('Please select at least one article.');
            return false;
        }
        
        const action = document.querySelector('[name="bulk_action"]').value;
        if (action === 'delete') {
            return confirm('Are you sure you want to delete ' + selected.length + ' article(s)? This action cannot be undone.');
        }
        
        return confirm('Are you sure you want to perform this action on ' + selected.length + ' article(s)?');
    }
    
    // Delete single article
    function deleteArticle(id) {
        if (confirm('Are you sure you want to delete this article? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'articles.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'article_id';
            idInput.value = id;
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo $auth->getCSRFToken(); ?>';
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
    
    <?php
    includeFooter();
}

/**
 * Show article form (new/edit)
 */
function showArticleForm($articleId = 0) {
    global $db, $auth;
    
    $article = null;
    $selectedTags = [];
    
    if ($articleId) {
        // Get existing article
        $sql = "SELECT * FROM articles WHERE id = ?";
        $article = $db->fetchOne($sql, [$articleId]);
        
        if (!$article) {
            setFlashMessage('error', 'Article not found.');
            redirect('articles.php');
        }
        
        // Get article tags
        $tagsSql = "SELECT tag_id FROM article_tags WHERE article_id = ?";
        $articleTags = $db->fetchAll($tagsSql, [$articleId]);
        $selectedTags = array_column($articleTags, 'tag_id');
    }
    
    // Get categories and tags
    $categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
    $tags = $db->fetchAll("SELECT id, name FROM tags ORDER BY name");
    
    $pageTitle = $articleId ? 'Edit Article' : 'New Article';
    includeHeader($pageTitle);
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="py-3">
                    <h6 class="text-muted px-3 mb-3">NAVIGATION</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="articles.php">
                            <i class="bi bi-file-text"></i> Articles
                        </a>
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-folder"></i> Categories
                        </a>
                        <a class="nav-link" href="tags.php">
                            <i class="bi bi-tags"></i> Tags
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content-area">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><?php echo $articleId ? 'Edit Article' : 'New Article'; ?></h1>
                    <div>
                        <a href="articles.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Articles
                        </a>
                        <?php if ($articleId && $article['status'] === 'published'): ?>
                            <a href="../article.php?id=<?php echo $articleId; ?>" class="btn btn-outline-success" target="_blank">
                                <i class="bi bi-eye"></i> View Article
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form method="POST" action="articles.php" id="articleForm">
                    <?php echo $auth->getCSRFInput(); ?>
                    <input type="hidden" name="action" value="<?php echo $articleId ? 'update' : 'create'; ?>">
                    <?php if ($articleId): ?>
                        <input type="hidden" name="article_id" value="<?php echo $articleId; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Main Content -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Article Content</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title *</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="title" 
                                               name="title" 
                                               value="<?php echo htmlspecialchars($article['title'] ?? ''); ?>"
                                               required 
                                               maxlength="255"
                                               placeholder="Enter article title">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Content *</label>
                                        <textarea class="form-control auto-resize" 
                                                  id="content" 
                                                  name="content" 
                                                  rows="15" 
                                                  required
                                                  placeholder="Write your article content here..."><?php echo htmlspecialchars($article['content'] ?? ''); ?></textarea>
                                        <div class="form-text">You can use HTML tags for formatting.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="excerpt" class="form-label">Excerpt</label>
                                        <textarea class="form-control" 
                                                  id="excerpt" 
                                                  name="excerpt" 
                                                  rows="3" 
                                                  maxlength="500"
                                                  placeholder="Brief summary of the article (optional - will be auto-generated if left empty)"><?php echo htmlspecialchars($article['excerpt'] ?? ''); ?></textarea>
                                        <div class="form-text">Maximum 500 characters. Leave empty to auto-generate from content.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <!-- Publish Settings -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Publish Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="draft" <?php echo ($article['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo ($article['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                            <option value="archived" <?php echo ($article['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               id="featured" 
                                               name="featured" 
                                               value="1"
                                               <?php echo ($article['featured'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">
                                            Featured Article
                                        </label>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> <?php echo $articleId ? 'Update Article' : 'Create Article'; ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                                            <i class="bi bi-file-earmark"></i> Save as Draft
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Category -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Category</h5>
                                </div>
                                <div class="card-body">
                                    <select name="category_id" id="category_id" class="form-select">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" 
                                                    <?php echo ($article['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="mt-2">
                                        <a href="categories.php?action=new" class="btn btn-outline-primary btn-sm" target="_blank">
                                            <i class="bi bi-plus"></i> Add New Category
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tags -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Tags</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($tags)): ?>
                                        <p class="text-muted">No tags available.</p>
                                        <a href="tags.php" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-plus"></i> Create Tags
                                        </a>
                                    <?php else: ?>
                                        <div class="tag-checkboxes" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($tags as $tag): ?>
                                                <div class="form-check">
                                                    <input type="checkbox" 
                                                           class="form-check-input" 
                                                           id="tag_<?php echo $tag['id']; ?>" 
                                                           name="tags[]" 
                                                           value="<?php echo $tag['id']; ?>"
                                                           <?php echo in_array($tag['id'], $selectedTags) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="tag_<?php echo $tag['id']; ?>">
                                                        <?php echo htmlspecialchars($tag['name']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-2">
                                            <a href="tags.php" class="btn btn-outline-primary btn-sm" target="_blank">
                                                <i class="bi bi-plus"></i> Manage Tags
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($articleId): ?>
                                <!-- Article Info -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Article Info</h5>
                                    </div>
                                    <div class="card-body">
                                        <small class="text-muted">
                                            <strong>Created:</strong> <?php echo formatDateTime($article['created_at']); ?><br>
                                            <strong>Updated:</strong> <?php echo formatDateTime($article['updated_at']); ?><br>
                                            <strong>Views:</strong> <?php echo number_format($article['views']); ?><br>
                                            <strong>Slug:</strong> <?php echo htmlspecialchars($article['slug']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Save as draft function
    function saveDraft() {
        document.getElementById('status').value = 'draft';
        document.getElementById('articleForm').submit();
    }
    
    // Auto-save functionality (basic implementation)
    let autoSaveTimer;
    function autoSave() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // This is a placeholder for auto-save functionality
            // In a real implementation, you would save to localStorage or make an AJAX call
            console.log('Auto-save triggered');
        }, 30000); // Auto-save every 30 seconds
    }
    
    // Trigger auto-save on content changes
    document.getElementById('content').addEventListener('input', autoSave);
    document.getElementById('title').addEventListener('input', autoSave);
    
    // Warn about unsaved changes
    let formChanged = false;
    document.getElementById('articleForm').addEventListener('change', function() {
        formChanged = true;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    // Reset form changed flag on submit
    document.getElementById('articleForm').addEventListener('submit', function() {
        formChanged = false;
    });
    
    // Character counter for excerpt
    const excerptField = document.getElementById('excerpt');
    const excerptCounter = document.createElement('div');
    excerptCounter.className = 'form-text text-end';
    excerptField.parentNode.appendChild(excerptCounter);
    
    function updateExcerptCounter() {
        const length = excerptField.value.length;
        const maxLength = 500;
        excerptCounter.textContent = length + '/' + maxLength + ' characters';
        excerptCounter.className = 'form-text text-end ' + (length > maxLength ? 'text-danger' : '');
    }
    
    excerptField.addEventListener('input', updateExcerptCounter);
    updateExcerptCounter(); // Initial count
    </script>
    
    <?php
    includeFooter();
}

/**
 * Show article view (preview)
 */
function showArticleView($articleId) {
    global $db;
    
    if (!$articleId) {
        setFlashMessage('error', 'Invalid article ID.');
        redirect('articles.php');
    }
    
    // Get article with related data
    $sql = "SELECT a.*, c.name as category_name, u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            WHERE a.id = ?";
    
    $article = $db->fetchOne($sql, [$articleId]);
    
    if (!$article) {
        setFlashMessage('error', 'Article not found.');
        redirect('articles.php');
    }
    
    // Get article tags
    $tagsSql = "SELECT t.name FROM tags t 
                INNER JOIN article_tags at ON t.id = at.tag_id 
                WHERE at.article_id = ?";
    $tags = $db->fetchAll($tagsSql, [$articleId]);
    
    $pageTitle = 'Preview: ' . $article['title'];
    includeHeader($pageTitle);
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="py-3">
                    <h6 class="text-muted px-3 mb-3">NAVIGATION</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a class="nav-link active" href="articles.php">
                            <i class="bi bi-file-text"></i> Articles
                        </a>
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-folder"></i> Categories
                        </a>
                        <a class="nav-link" href="tags.php">
                            <i class="bi bi-tags"></i> Tags
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 content-area">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Article Preview</h1>
                    <div>
                        <a href="articles.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Articles
                        </a>
                        <a href="articles.php?action=edit&id=<?php echo $articleId; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit Article
                        </a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <!-- Article Header -->
                        <div class="mb-4">
                            <h1 class="display-5"><?php echo htmlspecialchars($article['title']); ?></h1>
                            
                            <div class="d-flex flex-wrap align-items-center gap-3 mt-3 text-muted">
                                <span>
                                    <i class="bi bi-person"></i> 
                                    <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                </span>
                                <span>
                                    <i class="bi bi-calendar"></i> 
                                    <?php echo formatDateTime($article['created_at']); ?>
                                </span>
                                <?php if ($article['category_name']): ?>
                                    <span>
                                        <i class="bi bi-folder"></i> 
                                        <?php echo htmlspecialchars($article['category_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <span>
                                    <i class="bi bi-eye"></i> 
                                    <?php echo number_format($article['views']); ?> views
                                </span>
                                <span class="badge bg-<?php echo $article['status'] === 'published' ? 'success' : ($article['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                    <?php echo ucfirst($article['status']); ?>
                                </span>
                                <?php if ($article['featured']): ?>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-star"></i> Featured
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($tags)): ?>
                                <div class="mt-3">
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="badge tag-badge me-1">
                                            <?php echo htmlspecialchars($tag['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Article Excerpt -->
                        <?php if (!empty($article['excerpt'])): ?>
                            <div class="alert alert-info">
                                <strong>Excerpt:</strong> <?php echo htmlspecialchars($article['excerpt']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Article Content -->
                        <div class="article-content">
                            <?php echo $article['content']; ?>
                        </div>
                        
                        <!-- Article Footer -->
                        <hr class="my-4">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Last updated:</strong> <?php echo formatDateTime($article['updated_at']); ?>
                                </small>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <small class="text-muted">
                                    <strong>Article ID:</strong> <?php echo $article['id']; ?> | 
                                    <strong>Slug:</strong> <?php echo htmlspecialchars($article['slug']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    includeFooter();
}
?>