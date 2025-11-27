<?php
/**
 * Category Management
 * CRUD operations for article categories
 */

require_once '../includes/bootstrap.php';

// Require admin authentication
$auth->requireAdmin();

$action = getGet('action', 'list');
$categoryId = getGet('id', 0);

// Handle form submissions
if (isPost()) {
    $postAction = getPost('action', '');
    $csrfToken = getPost('csrf_token', '');
    
    // Validate CSRF token
    if (!$auth->validateCSRF($csrfToken)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        redirect('categories.php');
    }
    
    switch ($postAction) {
        case 'create':
        case 'update':
            handleCategorySave($postAction);
            break;
        case 'delete':
            handleCategoryDelete();
            break;
    }
}

// Handle different actions
switch ($action) {
    case 'new':
        showCategoryForm();
        break;
    case 'edit':
        showCategoryForm($categoryId);
        break;
    default:
        showCategoryList();
        break;
}

/**
 * Handle category save (create/update)
 */
function handleCategorySave($action) {
    global $db;
    
    $name = validateText(getPost('name', ''), 1, 100);
    $description = validateText(getPost('description', ''), 0, 1000);
    $categoryId = getPost('category_id', 0);
    
    // Validation
    if (!$name) {
        setFlashMessage('error', 'Category name is required and must be between 1-100 characters.');
        redirect('categories.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $categoryId));
    }
    
    // Generate slug
    $slug = generateSlug($name);
    
    // Check for duplicate name or slug
    $checkSql = "SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id != ?";
    $existing = $db->fetchOne($checkSql, [$name, $slug, $categoryId]);
    if ($existing) {
        setFlashMessage('error', 'A category with this name already exists.');
        redirect('categories.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $categoryId));
    }
    
    try {
        if ($action === 'create') {
            // Create new category
            $sql = "INSERT INTO categories (name, description, slug) VALUES (?, ?, ?)";
            $newId = $db->insert($sql, [$name, $description, $slug]);
            setFlashMessage('success', 'Category created successfully.');
            redirect('categories.php?action=edit&id=' . $newId);
        } else {
            // Update existing category
            $sql = "UPDATE categories SET name = ?, description = ?, slug = ?, updated_at = NOW() WHERE id = ?";
            $db->execute($sql, [$name, $description, $slug, $categoryId]);
            setFlashMessage('success', 'Category updated successfully.');
            redirect('categories.php?action=edit&id=' . $categoryId);
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error saving category: ' . $e->getMessage());
        redirect('categories.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $categoryId));
    }
}

/**
 * Handle category deletion
 */
function handleCategoryDelete() {
    global $db;
    
    $categoryId = getPost('category_id', 0);
    
    if (!$categoryId) {
        setFlashMessage('error', 'Invalid category ID.');
        redirect('categories.php');
    }
    
    try {
        // Check if category has articles
        $articleCount = $db->fetchOne("SELECT COUNT(*) as count FROM articles WHERE category_id = ?", [$categoryId])['count'];
        
        if ($articleCount > 0) {
            setFlashMessage('error', 'Cannot delete category. It contains ' . $articleCount . ' article(s). Please move or delete the articles first.');
            redirect('categories.php');
        }
        
        $db->execute("DELETE FROM categories WHERE id = ?", [$categoryId]);
        setFlashMessage('success', 'Category deleted successfully.');
    } catch (Exception $e) {
        setFlashMessage('error', 'Error deleting category: ' . $e->getMessage());
    }
    
    redirect('categories.php');
}

/**
 * Show category list
 */
function showCategoryList() {
    global $db, $auth;
    
    // Get categories with article counts
    $sql = "SELECT c.*, COUNT(a.id) as article_count
            FROM categories c
            LEFT JOIN articles a ON c.id = a.category_id
            GROUP BY c.id
            ORDER BY c.name";
    
    $categories = $db->fetchAll($sql);
    
    $pageTitle = 'Manage Categories';
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
                        <a class="nav-link" href="articles.php">
                            <i class="bi bi-file-text"></i> Articles
                        </a>
                        <a class="nav-link active" href="categories.php">
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
                    <h1>Categories</h1>
                    <a href="categories.php?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Category
                    </a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Categories (<?php echo count($categories); ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-folder fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No categories found.</p>
                                <a href="categories.php?action=new" class="btn btn-primary">Create Your First Category</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Slug</th>
                                            <th>Articles</th>
                                            <th>Created</th>
                                            <th width="120">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($category['description'])): ?>
                                                        <?php echo truncateText(htmlspecialchars($category['description']), 100); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No description</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($category['slug']); ?></code>
                                                </td>
                                                <td>
                                                    <?php if ($category['article_count'] > 0): ?>
                                                        <a href="articles.php?category=<?php echo $category['id']; ?>" 
                                                           class="badge bg-primary text-decoration-none">
                                                            <?php echo number_format($category['article_count']); ?> articles
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">0 articles</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($category['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="categories.php?action=edit&id=<?php echo $category['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="../browse.php?category=<?php echo $category['slug']; ?>" 
                                                           class="btn btn-outline-success" title="View" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($category['article_count'] == 0): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteCategory(<?php echo $category['id']; ?>)" title="Delete">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-secondary" 
                                                                    title="Cannot delete - contains articles" disabled>
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Delete category
    function deleteCategory(id) {
        if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'categories.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'category_id';
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
 * Show category form (new/edit)
 */
function showCategoryForm($categoryId = 0) {
    global $db, $auth;
    
    $category = null;
    
    if ($categoryId) {
        // Get existing category
        $sql = "SELECT * FROM categories WHERE id = ?";
        $category = $db->fetchOne($sql, [$categoryId]);
        
        if (!$category) {
            setFlashMessage('error', 'Category not found.');
            redirect('categories.php');
        }
    }
    
    $pageTitle = $categoryId ? 'Edit Category' : 'New Category';
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
                        <a class="nav-link" href="articles.php">
                            <i class="bi bi-file-text"></i> Articles
                        </a>
                        <a class="nav-link active" href="categories.php">
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
                    <h1><?php echo $categoryId ? 'Edit Category' : 'New Category'; ?></h1>
                    <a href="categories.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Categories
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Category Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="categories.php" id="categoryForm">
                                    <?php echo $auth->getCSRFInput(); ?>
                                    <input type="hidden" name="action" value="<?php echo $categoryId ? 'update' : 'create'; ?>">
                                    <?php if ($categoryId): ?>
                                        <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name *</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name" 
                                               name="name" 
                                               value="<?php echo htmlspecialchars($category['name'] ?? ''); ?>"
                                               required 
                                               maxlength="100"
                                               placeholder="Enter category name">
                                        <div class="form-text">This will be displayed as the category name throughout the site.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" 
                                                  id="description" 
                                                  name="description" 
                                                  rows="4" 
                                                  maxlength="1000"
                                                  placeholder="Enter category description (optional)"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                                        <div class="form-text">A brief description of what this category contains.</div>
                                    </div>
                                    
                                    <?php if ($categoryId): ?>
                                        <div class="mb-3">
                                            <label for="slug" class="form-label">URL Slug</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="slug" 
                                                   value="<?php echo htmlspecialchars($category['slug'] ?? ''); ?>"
                                                   readonly>
                                            <div class="form-text">The URL-friendly version of the category name (auto-generated).</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> <?php echo $categoryId ? 'Update Category' : 'Create Category'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <?php if ($categoryId): ?>
                            <!-- Category Statistics -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Category Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $stats = $db->fetchOne("
                                        SELECT 
                                            COUNT(*) as total_articles,
                                            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_articles,
                                            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_articles,
                                            SUM(views) as total_views
                                        FROM articles 
                                        WHERE category_id = ?
                                    ", [$categoryId]);
                                    ?>
                                    <div class="row text-center">
                                        <div class="col-6 mb-3">
                                            <div class="h4 text-primary"><?php echo number_format($stats['total_articles']); ?></div>
                                            <small class="text-muted">Total Articles</small>
                                        </div>
                                        <div class="col-6 mb-3">
                                            <div class="h4 text-success"><?php echo number_format($stats['published_articles']); ?></div>
                                            <small class="text-muted">Published</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="h4 text-warning"><?php echo number_format($stats['draft_articles']); ?></div>
                                            <small class="text-muted">Drafts</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="h4 text-info"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                                            <small class="text-muted">Total Views</small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($stats['total_articles'] > 0): ?>
                                        <hr>
                                        <div class="d-grid">
                                            <a href="articles.php?category=<?php echo $categoryId; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-file-text"></i> View Articles
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Category Info -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Category Info</h5>
                                </div>
                                <div class="card-body">
                                    <small class="text-muted">
                                        <strong>Created:</strong> <?php echo formatDateTime($category['created_at']); ?><br>
                                        <strong>Updated:</strong> <?php echo formatDateTime($category['updated_at']); ?><br>
                                        <strong>ID:</strong> <?php echo $category['id']; ?>
                                    </small>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Help Card -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-info-circle"></i> About Categories
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="small">Categories help organize your knowledge base articles into logical groups.</p>
                                    <ul class="small">
                                        <li>Each article can belong to one category</li>
                                        <li>Categories appear in navigation menus</li>
                                        <li>Users can browse articles by category</li>
                                        <li>Category names should be descriptive and unique</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-generate slug preview for new categories
    <?php if (!$categoryId): ?>
    document.getElementById('name').addEventListener('input', function() {
        const name = this.value;
        const slug = name.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
        
        // Show slug preview
        let slugPreview = document.getElementById('slugPreview');
        if (!slugPreview) {
            slugPreview = document.createElement('div');
            slugPreview.id = 'slugPreview';
            slugPreview.className = 'form-text';
            this.parentNode.appendChild(slugPreview);
        }
        
        if (slug) {
            slugPreview.innerHTML = '<strong>URL slug will be:</strong> <code>' + slug + '</code>';
        } else {
            slugPreview.innerHTML = '';
        }
    });
    <?php endif; ?>
    
    // Form validation
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        
        if (!name) {
            e.preventDefault();
            alert('Category name is required.');
            document.getElementById('name').focus();
            return false;
        }
        
        if (name.length > 100) {
            e.preventDefault();
            alert('Category name must be 100 characters or less.');
            document.getElementById('name').focus();
            return false;
        }
    });
    </script>
    
    <?php
    includeFooter();
}
?>

