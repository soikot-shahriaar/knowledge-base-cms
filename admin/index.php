<?php
/**
 * Admin Dashboard
 * Main admin panel with overview and navigation
 */

require_once '../includes/bootstrap.php';

// Require admin authentication
$auth->requireAdmin();

// Get dashboard statistics
try {
    $stats = [
        'total_articles' => $db->fetchOne("SELECT COUNT(*) as count FROM articles")['count'],
        'published_articles' => $db->fetchOne("SELECT COUNT(*) as count FROM articles WHERE status = 'published'")['count'],
        'draft_articles' => $db->fetchOne("SELECT COUNT(*) as count FROM articles WHERE status = 'draft'")['count'],
        'total_categories' => $db->fetchOne("SELECT COUNT(*) as count FROM categories")['count'],
        'total_tags' => $db->fetchOne("SELECT COUNT(*) as count FROM tags")['count'],
        'total_views' => $db->fetchOne("SELECT SUM(views) as total FROM articles")['total'] ?? 0
    ];
    
    // Get recent articles
    $recentArticles = $db->fetchAll("
        SELECT a.id, a.title, a.status, a.created_at, a.views,
               c.name as category_name, u.username as author_name
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    
    // Get popular articles
    $popularArticles = $db->fetchAll("
        SELECT a.id, a.title, a.views, a.status,
               c.name as category_name
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        ORDER BY a.views DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    setFlashMessage('error', 'Error loading dashboard data: ' . $e->getMessage());
    $stats = array_fill_keys(['total_articles', 'published_articles', 'draft_articles', 'total_categories', 'total_tags', 'total_views'], 0);
    $recentArticles = [];
    $popularArticles = [];
}

$pageTitle = 'Admin Dashboard';
includeHeader($pageTitle);
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar">
            <div class="py-3">
                <h6 class="text-muted px-3 mb-3">NAVIGATION</h6>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="articles.php">
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
                    <hr class="mx-3">
                    <a class="nav-link" href="../index.php" target="_blank">
                        <i class="bi bi-eye"></i> View Site
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Dashboard</h1>
                <div>
                    <a href="articles.php?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Article
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($stats['total_articles']); ?></h4>
                                    <p class="card-text">Total Articles</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-file-text fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($stats['published_articles']); ?></h4>
                                    <p class="card-text">Published</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($stats['draft_articles']); ?></h4>
                                    <p class="card-text">Drafts</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-pencil fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($stats['total_categories']); ?></h4>
                                    <p class="card-text">Categories</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-folder fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-white bg-secondary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($stats['total_tags']); ?></h4>
                                    <p class="card-text">Tags</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-tags fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-white bg-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="card-title"><?php echo number_format($stats['total_views']); ?></h4>
                                    <p class="card-text">Total Views</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-eye fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-clock-history"></i> Recent Articles
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentArticles)): ?>
                                <p class="text-muted">No articles found.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentArticles as $article): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold">
                                                    <a href="articles.php?action=edit&id=<?php echo $article['id']; ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($article['title']); ?>
                                                    </a>
                                                </div>
                                                <small class="text-muted">
                                                    by <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?> 
                                                    in <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                                                    <br>
                                                    <?php echo formatDateTime($article['created_at']); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $article['status'] === 'published' ? 'success' : ($article['status'] === 'draft' ? 'warning' : 'secondary'); ?> rounded-pill">
                                                <?php echo ucfirst($article['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="articles.php" class="btn btn-outline-primary btn-sm">
                                        View All Articles
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-graph-up"></i> Popular Articles
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($popularArticles)): ?>
                                <p class="text-muted">No published articles found.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($popularArticles as $article): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold">
                                                    <a href="articles.php?action=edit&id=<?php echo $article['id']; ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($article['title']); ?>
                                                    </a>
                                                </div>
                                                <small class="text-muted">
                                                    in <?php echo htmlspecialchars($article['category_name'] ?? 'Uncategorized'); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo number_format($article['views']); ?> views
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="../index.php" class="btn btn-outline-success btn-sm" target="_blank">
                                        <i class="bi bi-eye"></i> View Public Site
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-lightning"></i> Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <a href="articles.php?action=new" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-plus-circle"></i> New Article
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="categories.php?action=new" class="btn btn-outline-info w-100">
                                        <i class="bi bi-folder-plus"></i> New Category
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="tags.php" class="btn btn-outline-secondary w-100">
                                        <i class="bi bi-tags"></i> Manage Tags
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="../index.php" class="btn btn-outline-success w-100" target="_blank">
                                        <i class="bi bi-eye"></i> View Site
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php includeFooter(); ?>

