<?php
/**
 * Knowledge Base Homepage
 * Public homepage displaying featured articles and navigation
 */

require_once 'includes/bootstrap.php';

try {
    // Get featured articles
    $featuredArticles = $db->fetchAll("
        SELECT a.id, a.title, a.slug, a.excerpt, a.views, a.created_at,
               c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published' AND a.featured = 1
        ORDER BY a.created_at DESC
        LIMIT 6
    ");
    
    // Get recent articles
    $recentArticles = $db->fetchAll("
        SELECT a.id, a.title, a.slug, a.excerpt, a.views, a.created_at,
               c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        ORDER BY a.created_at DESC
        LIMIT 8
    ");
    
    // Get popular articles
    $popularArticles = $db->fetchAll("
        SELECT a.id, a.title, a.slug, a.excerpt, a.views, a.created_at,
               c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published' AND a.views > 0
        ORDER BY a.views DESC
        LIMIT 6
    ");
    
    // Get categories with article counts
    $categories = $db->fetchAll("
        SELECT c.id, c.name, c.slug, c.description, COUNT(a.id) as article_count
        FROM categories c
        LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
        GROUP BY c.id
        HAVING article_count > 0
        ORDER BY c.name
        LIMIT 8
    ");
    
    // Get statistics
    $stats = [
        'total_articles' => $db->fetchOne("SELECT COUNT(*) as count FROM articles WHERE status = 'published'")['count'],
        'total_categories' => $db->fetchOne("SELECT COUNT(*) as count FROM categories")['count'],
        'total_views' => $db->fetchOne("SELECT SUM(views) as total FROM articles WHERE status = 'published'")['total'] ?? 0
    ];
    
} catch (Exception $e) {
    $featuredArticles = [];
    $recentArticles = [];
    $popularArticles = [];
    $categories = [];
    $stats = ['total_articles' => 0, 'total_categories' => 0, 'total_views' => 0];
}

$pageTitle = 'Knowledge Base - Find answers to your questions';
includeHeader($pageTitle);
?>

<!-- Hero Section -->
<div class="hero-section text-white py-5" style="margin-top: 76px;">
    <div class="container">
        <div class="row align-items-center hero-content">
            <div class="col-lg-8">
                <h1 class="hero-title">Welcome to Our Knowledge Base</h1>
                <p class="hero-subtitle">Find answers to your questions, explore helpful guides, and discover everything you need to know.</p>
                
                <!-- Search Form -->
                <form action="search.php" method="GET" class="d-flex hero-search">
                    <input type="text" 
                           name="q" 
                           class="form-control" 
                           placeholder="Search for articles, guides, or topics..."
                           value="<?php echo htmlspecialchars(getGet('q', '')); ?>">
                    <button type="submit" class="btn">
                        <i class="bi bi-search"></i> Search
                    </button>
                </form>
            </div>
            <div class="col-lg-4">
                <div class="row g-3">
                    <div class="col-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['total_articles']); ?></div>
                            <div class="stats-label">Articles</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['total_categories']); ?></div>
                            <div class="stats-label">Categories</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['total_views']); ?></div>
                            <div class="stats-label">Views</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <!-- Categories Section -->
    <?php if (!empty($categories)): ?>
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Browse by Category</h2>
                <a href="browse.php" class="btn btn-outline-primary">View All Categories</a>
            </div>
            
            <div class="row">
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card h-100 category-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-folder fs-1 text-primary"></i>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="card-text text-muted small">
                                    <?php echo $category['description'] ? truncateText(htmlspecialchars($category['description']), 80) : 'Browse articles in this category'; ?>
                                </p>
                                <div class="mt-auto">
                                    <span class="badge bg-primary"><?php echo number_format($category['article_count']); ?> articles</span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <a href="browse.php?category=<?php echo urlencode($category['slug']); ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    Browse Articles
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Featured Articles -->
    <?php if (!empty($featuredArticles)): ?>
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Featured Articles</h2>
                <a href="browse.php?featured=1" class="btn btn-outline-primary">View All Featured</a>
            </div>
            
            <div class="row">
                <?php foreach ($featuredArticles as $article): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 article-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <?php if ($article['category_name']): ?>
                                        <span class="badge category-badge">
                                            <?php echo htmlspecialchars($article['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-star"></i> Featured
                                    </span>
                                </div>
                                
                                <h5 class="card-title">
                                    <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($article['title']); ?>
                                    </a>
                                </h5>
                                
                                <p class="card-text text-muted">
                                    <?php echo $article['excerpt'] ? htmlspecialchars($article['excerpt']) : truncateText(strip_tags($article['content'] ?? ''), 120); ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                    <span>
                                        <i class="bi bi-calendar"></i> <?php echo formatDate($article['created_at']); ?>
                                    </span>
                                    <span>
                                        <i class="bi bi-eye"></i> <?php echo number_format($article['views']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    
    <div class="row">
        <!-- Recent Articles -->
        <div class="col-lg-8">
            <section class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Recent Articles</h2>
                    <a href="browse.php" class="btn btn-outline-primary">View All Articles</a>
                </div>
                
                <?php if (empty($recentArticles)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-file-text fs-1 text-muted"></i>
                        <p class="text-muted mt-2">No articles available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($recentArticles as $article): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 article-card">
                                    <div class="card-body">
                                        <?php if ($article['category_name']): ?>
                                            <div class="mb-2">
                                                <span class="badge category-badge">
                                                    <?php echo htmlspecialchars($article['category_name']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <h6 class="card-title">
                                            <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($article['title']); ?>
                                            </a>
                                        </h6>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo $article['excerpt'] ? truncateText(htmlspecialchars($article['excerpt']), 100) : truncateText(strip_tags($article['content'] ?? ''), 100); ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center text-muted small">
                                            <span>
                                                <i class="bi bi-calendar"></i> <?php echo formatDate($article['created_at']); ?>
                                            </span>
                                            <span>
                                                <i class="bi bi-eye"></i> <?php echo number_format($article['views']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Popular Articles -->
            <?php if (!empty($popularArticles)): ?>
                <section class="mb-4">
                    <h4>Popular Articles</h4>
                    <div class="list-group list-group-flush">
                        <?php foreach ($popularArticles as $article): ?>
                            <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($article['title']); ?></h6>
                                    <small class="text-muted"><?php echo number_format($article['views']); ?> views</small>
                                </div>
                                <?php if ($article['category_name']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($article['category_name']); ?></small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Quick Links -->
            <section class="mb-4">
                <h4>Quick Links</h4>
                <div class="list-group">
                    <a href="browse.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-grid"></i> Browse All Articles
                    </a>
                    <a href="search.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-search"></i> Advanced Search
                    </a>
                    <?php if (!empty($categories)): ?>
                        <?php foreach (array_slice($categories, 0, 3) as $category): ?>
                            <a href="browse.php?category=<?php echo urlencode($category['slug']); ?>" 
                               class="list-group-item list-group-item-action">
                                <i class="bi bi-folder"></i> <?php echo htmlspecialchars($category['name']); ?>
                                <span class="badge bg-secondary float-end"><?php echo $category['article_count']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Help Section -->
            <section class="mb-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title">Need Help?</h5>
                        <p class="card-text">Can't find what you're looking for? Try our search feature or browse by category.</p>
                        <a href="search.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-search"></i> Search Now
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
// Enhanced search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('form[action="search.php"]');
    const searchInput = searchForm.querySelector('input[name="q"]');
    
    // Focus search on key press
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            searchInput.focus();
        }
    });
    
    // Search suggestions (placeholder for future enhancement)
    searchInput.addEventListener('input', function() {
        // This could be enhanced with AJAX search suggestions
    });
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

<?php includeFooter(); ?>

