<?php
/**
 * Tag Management
 * CRUD operations for article tags
 */

require_once '../includes/bootstrap.php';

// Require admin authentication
$auth->requireAdmin();

$action = getGet('action', 'list');
$tagId = getGet('id', 0);

// Handle form submissions
if (isPost()) {
    $postAction = getPost('action', '');
    $csrfToken = getPost('csrf_token', '');
    
    // Validate CSRF token
    if (!$auth->validateCSRF($csrfToken)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        redirect('tags.php');
    }
    
    switch ($postAction) {
        case 'create':
        case 'update':
            handleTagSave($postAction);
            break;
        case 'delete':
            handleTagDelete();
            break;
        case 'bulk_delete':
            handleBulkDelete();
            break;
    }
}

// Handle different actions
switch ($action) {
    case 'new':
        showTagForm();
        break;
    case 'edit':
        showTagForm($tagId);
        break;
    default:
        showTagList();
        break;
}

/**
 * Handle tag save (create/update)
 */
function handleTagSave($action) {
    global $db;
    
    $name = validateText(getPost('name', ''), 1, 50);
    $tagId = getPost('tag_id', 0);
    
    // Validation
    if (!$name) {
        setFlashMessage('error', 'Tag name is required and must be between 1-50 characters.');
        redirect('tags.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $tagId));
    }
    
    // Generate slug
    $slug = generateSlug($name);
    
    // Check for duplicate name or slug
    $checkSql = "SELECT id FROM tags WHERE (name = ? OR slug = ?) AND id != ?";
    $existing = $db->fetchOne($checkSql, [$name, $slug, $tagId]);
    if ($existing) {
        setFlashMessage('error', 'A tag with this name already exists.');
        redirect('tags.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $tagId));
    }
    
    try {
        if ($action === 'create') {
            // Create new tag
            $sql = "INSERT INTO tags (name, slug) VALUES (?, ?)";
            $newId = $db->insert($sql, [$name, $slug]);
            setFlashMessage('success', 'Tag created successfully.');
            redirect('tags.php?action=edit&id=' . $newId);
        } else {
            // Update existing tag
            $sql = "UPDATE tags SET name = ?, slug = ? WHERE id = ?";
            $db->execute($sql, [$name, $slug, $tagId]);
            setFlashMessage('success', 'Tag updated successfully.');
            redirect('tags.php?action=edit&id=' . $tagId);
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error saving tag: ' . $e->getMessage());
        redirect('tags.php?action=' . ($action === 'create' ? 'new' : 'edit&id=' . $tagId));
    }
}

/**
 * Handle tag deletion
 */
function handleTagDelete() {
    global $db;
    
    $tagId = getPost('tag_id', 0);
    
    if (!$tagId) {
        setFlashMessage('error', 'Invalid tag ID.');
        redirect('tags.php');
    }
    
    try {
        $db->execute("DELETE FROM tags WHERE id = ?", [$tagId]);
        setFlashMessage('success', 'Tag deleted successfully.');
    } catch (Exception $e) {
        setFlashMessage('error', 'Error deleting tag: ' . $e->getMessage());
    }
    
    redirect('tags.php');
}

/**
 * Handle bulk tag deletion
 */
function handleBulkDelete() {
    global $db;
    
    $selectedIds = getPost('selected_tags', []);
    
    if (empty($selectedIds) || !is_array($selectedIds)) {
        setFlashMessage('error', 'No tags selected.');
        redirect('tags.php');
    }
    
    try {
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
        $db->execute("DELETE FROM tags WHERE id IN ($placeholders)", $selectedIds);
        setFlashMessage('success', count($selectedIds) . ' tags deleted successfully.');
    } catch (Exception $e) {
        setFlashMessage('error', 'Error deleting tags: ' . $e->getMessage());
    }
    
    redirect('tags.php');
}

/**
 * Show tag list
 */
function showTagList() {
    global $db, $auth;
    
    // Get tags with article counts
    $sql = "SELECT t.*, COUNT(at.article_id) as article_count
            FROM tags t
            LEFT JOIN article_tags at ON t.id = at.tag_id
            GROUP BY t.id
            ORDER BY t.name";
    
    $tags = $db->fetchAll($sql);
    
    $pageTitle = 'Manage Tags';
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
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-folder"></i> Categories
                        </a>
                        <a class="nav-link active" href="tags.php">
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
                    <h1>Tags</h1>
                    <a href="tags.php?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Tag
                    </a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h5 class="mb-0">All Tags (<?php echo count($tags); ?>)</h5>
                            </div>
                            <div class="col-auto">
                                <form method="POST" action="tags.php" id="bulkForm">
                                    <?php echo $auth->getCSRFInput(); ?>
                                    <input type="hidden" name="action" value="bulk_delete">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirmBulkDelete()">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($tags)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-tags fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No tags found.</p>
                                <a href="tags.php?action=new" class="btn btn-primary">Create Your First Tag</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="selectAll" class="form-check-input">
                                            </th>
                                            <th>Name</th>
                                            <th>Slug</th>
                                            <th>Articles</th>
                                            <th>Created</th>
                                            <th width="120">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tags as $tag): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_tags[]" 
                                                           value="<?php echo $tag['id']; ?>" 
                                                           class="form-check-input tag-checkbox">
                                                </td>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($tag['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($tag['slug']); ?></code>
                                                </td>
                                                <td>
                                                    <?php if ($tag['article_count'] > 0): ?>
                                                        <a href="articles.php?search=<?php echo urlencode($tag['name']); ?>" 
                                                           class="badge bg-primary text-decoration-none">
                                                            <?php echo number_format($tag['article_count']); ?> articles
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">0 articles</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($tag['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="tags.php?action=edit&id=<?php echo $tag['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="../search.php?q=<?php echo urlencode($tag['name']); ?>" 
                                                           class="btn btn-outline-success" title="View" target="_blank">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteTag(<?php echo $tag['id']; ?>)" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
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
    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.tag-checkbox');
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
    });
    
    // Confirm bulk delete
    function confirmBulkDelete() {
        const selected = document.querySelectorAll('.tag-checkbox:checked');
        if (selected.length === 0) {
            alert('Please select at least one tag.');
            return false;
        }
        return confirm('Are you sure you want to delete ' + selected.length + ' tag(s)? This action cannot be undone.');
    }
    
    // Delete single tag
    function deleteTag(id) {
        if (confirm('Are you sure you want to delete this tag? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'tags.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'tag_id';
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
 * Show tag form (new/edit)
 */
function showTagForm($tagId = 0) {
    global $db, $auth;
    
    $tag = null;
    
    if ($tagId) {
        // Get existing tag
        $sql = "SELECT * FROM tags WHERE id = ?";
        $tag = $db->fetchOne($sql, [$tagId]);
        
        if (!$tag) {
            setFlashMessage('error', 'Tag not found.');
            redirect('tags.php');
        }
    }
    
    $pageTitle = $tagId ? 'Edit Tag' : 'New Tag';
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
                        <a class="nav-link" href="categories.php">
                            <i class="bi bi-folder"></i> Categories
                        </a>
                        <a class="nav-link active" href="tags.php">
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
                    <h1><?php echo $tagId ? 'Edit Tag' : 'New Tag'; ?></h1>
                    <a href="tags.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Tags
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Tag Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="tags.php" id="tagForm">
                                    <?php echo $auth->getCSRFInput(); ?>
                                    <input type="hidden" name="action" value="<?php echo $tagId ? 'update' : 'create'; ?>">
                                    <?php if ($tagId): ?>
                                        <input type="hidden" name="tag_id" value="<?php echo $tagId; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Tag Name *</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name" 
                                               name="name" 
                                               value="<?php echo htmlspecialchars($tag['name'] ?? ''); ?>"
                                               required 
                                               maxlength="50"
                                               placeholder="Enter tag name">
                                        <div class="form-text">Tags help categorize and organize articles. Keep them short and descriptive.</div>
                                    </div>
                                    
                                    <?php if ($tagId): ?>
                                        <div class="mb-3">
                                            <label for="slug" class="form-label">URL Slug</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="slug" 
                                                   value="<?php echo htmlspecialchars($tag['slug'] ?? ''); ?>"
                                                   readonly>
                                            <div class="form-text">The URL-friendly version of the tag name (auto-generated).</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="tags.php" class="btn btn-outline-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> <?php echo $tagId ? 'Update Tag' : 'Create Tag'; ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <?php if ($tagId): ?>
                            <!-- Tag Statistics -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Tag Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $stats = $db->fetchOne("
                                        SELECT COUNT(*) as article_count
                                        FROM article_tags at
                                        INNER JOIN articles a ON at.article_id = a.id
                                        WHERE at.tag_id = ? AND a.status = 'published'
                                    ", [$tagId]);
                                    ?>
                                    <div class="text-center">
                                        <div class="h2 text-primary"><?php echo number_format($stats['article_count']); ?></div>
                                        <p class="text-muted">Published Articles</p>
                                    </div>
                                    
                                    <?php if ($stats['article_count'] > 0): ?>
                                        <hr>
                                        <div class="d-grid gap-2">
                                            <a href="articles.php?search=<?php echo urlencode($tag['name']); ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-file-text"></i> View Articles
                                            </a>
                                            <a href="../search.php?q=<?php echo urlencode($tag['name']); ?>" class="btn btn-outline-success btn-sm" target="_blank">
                                                <i class="bi bi-eye"></i> View on Site
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Tag Info -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Tag Info</h5>
                                </div>
                                <div class="card-body">
                                    <small class="text-muted">
                                        <strong>Created:</strong> <?php echo formatDateTime($tag['created_at']); ?><br>
                                        <strong>ID:</strong> <?php echo $tag['id']; ?>
                                    </small>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Help Card -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-info-circle"></i> About Tags
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="small">Tags help organize and categorize your articles for better discoverability.</p>
                                    <ul class="small">
                                        <li>Articles can have multiple tags</li>
                                        <li>Tags appear on article pages</li>
                                        <li>Users can search by tags</li>
                                        <li>Keep tag names short and descriptive</li>
                                        <li>Use consistent naming conventions</li>
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
    // Auto-generate slug preview for new tags
    <?php if (!$tagId): ?>
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
    document.getElementById('tagForm').addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        
        if (!name) {
            e.preventDefault();
            alert('Tag name is required.');
            document.getElementById('name').focus();
            return false;
        }
        
        if (name.length > 50) {
            e.preventDefault();
            alert('Tag name must be 50 characters or less.');
            document.getElementById('name').focus();
            return false;
        }
    });
    </script>
    
    <?php
    includeFooter();
}
?>

