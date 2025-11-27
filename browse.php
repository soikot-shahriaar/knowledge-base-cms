<?php
/**
 * Browse Articles Page
 * Browse and filter knowledge base articles
 */

require_once 'includes/bootstrap.php';

// Get filters
$categorySlug = getGet('category', '');
$featured = getGet('featured', 0);
$sort = getGet('sort', 'recent'); // recent, popular, alphabetical
$page = max(1, getGet('page', 1));
$perPage = ARTICLES_PER_PAGE;
$offset = ($page - 1) * $perPage;

try {
    // Get selected category info
    $selectedCategory = null;
    if ($categorySlug) {
        $selectedCategory = $db->fetchOne("SELECT * FROM categories WHERE slug = ?", [$categorySlug]);
        if (!$selectedCategory) {
            setFlashMessage('error', 'Category not found.');
            redirect('browse.php');
        }
    }
    
    // Build WHERE clause
    $whereConditions = ["a.status = 'published'"];
    $params = [];
    
    if ($selectedCategory) {
        $whereConditions[] = "a.category_id = ?";
        $params[] = $selectedCategory['id'];
    }
    
    if ($featured) {
        $whereConditions[] = "a.featured = 1";
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Build ORDER BY clause
    $orderClause = match($sort) {
        'popular' => 'ORDER BY a.views DESC, a.created_at DESC',
        'alphabetical' => 'ORDER BY a.title ASC',
        default => 'ORDER BY a.created_at DESC'
    };
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM articles a $whereClause";
    $totalArticles = $db->fetchOne($countSql, $params)['total'];
    
    // Get articles
    $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.views, a.created_at, a.featured,
                   c.name as category_name, c.slug as category_slug,
                   u.username as author_name
            FROM articles a
            LEFT JOIN categories c ON a.category_id = c.id
            LEFT JOIN users u ON a.author_id = u.id
            $whereClause
            $orderClause
            LIMIT $perPage OFFSET $offset";
    
    $articles = $db->fetchAll($sql, $params);
    
    // Get all categories for filter
    $categories = $db->fetchAll("
        SELECT c.*, COUNT(a.id) as article_count
        FROM categories c
        LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
        GROUP BY c.id
        HAVING article_count > 0
        ORDER BY c.name
    ");
    
    // Get article tags for each article
    foreach ($articles as &$article) {
        $article['tags'] = $db->fetchAll("
            SELECT t.name, t.slug 
            FROM tags t 
            INNER JOIN article_tags at ON t.id = at.tag_id 
            WHERE at.article_id = ?
            ORDER BY t.name
            LIMIT 3
        ", [$article['id']]);
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Error loading articles: ' . $e->getMessage());
    $articles = [];
    $categories = [];
    $totalArticles = 0;
    $selectedCategory = null;
}

// Pagination
$pagination = getPagination($page, $totalArticles, $perPage);

// Page title
if ($selectedCategory) {
    $pageTitle = 'Browse: ' . $selectedCategory['name'];
} elseif ($featured) {
    $pageTitle = 'Featured Articles';
} else {
    $pageTitle = 'Browse Articles';
}

includeHeader($pageTitle);
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="browse.php">Browse</a></li>
            <?php if ($selectedCategory): ?>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo htmlspecialchars($selectedCategory['name']); ?>
                </li>
            <?php elseif ($featured): ?>
                <li class="breadcrumb-item active" aria-current="page">Featured Articles</li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page">All Articles</li>
            <?php endif; ?>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Categories -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-folder"></i> Categories
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="browse.php" 
                           class="list-group-item list-group-item-action <?php echo !$selectedCategory ? 'active' : ''; ?>">
                            <i class="bi bi-grid"></i> All Articles
                            <span class="badge bg-secondary float-end"><?php echo number_format($totalArticles); ?></span>
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a href="browse.php?category=<?php echo urlencode($category['slug']); ?>" 
                               class="list-group-item list-group-item-action <?php echo $selectedCategory && $selectedCategory['id'] == $category['id'] ? 'active' : ''; ?>">
                                <i class="bi bi-folder"></i> <?php echo htmlspecialchars($category['name']); ?>
                                <span class="badge bg-secondary float-end"><?php echo number_format($category['article_count']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-filter"></i> Quick Filters
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="browse.php?featured=1" 
                           class="btn <?php echo $featured ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm">
                            <i class="bi bi-star"></i> Featured Articles
                        </a>
                        <a href="browse.php?sort=popular" 
                           class="btn <?php echo $sort === 'popular' ? 'btn-success' : 'btn-outline-success'; ?> btn-sm">
                            <i class="bi bi-graph-up"></i> Most Popular
                        </a>
                        <a href="browse.php?sort=recent" 
                           class="btn <?php echo $sort === 'recent' ? 'btn-info' : 'btn-outline-info'; ?> btn-sm">
                            <i class="bi bi-clock"></i> Most Recent
                        </a>
                        <a href="browse.php?sort=alphabetical" 
                           class="btn <?php echo $sort === 'alphabetical' ? 'btn-secondary' : 'btn-outline-secondary'; ?> btn-sm">
                            <i class="bi bi-sort-alpha-down"></i> Alphabetical
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Search -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-search"></i> Search
                    </h5>
                </div>
                <div class="card-body">
                    <form action="search.php" method="GET">
                        <div class="input-group">
                            <input type="text" 
                                   name="q" 
                                   class="form-control" 
                                   placeholder="Search articles..."
                                   value="<?php echo htmlspecialchars(getGet('q', '')); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                    <div class="mt-2">
                        <a href="search.php" class="btn btn-outline-primary btn-sm w-100">
                            Advanced Search
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>
                        <?php if ($selectedCategory): ?>
                            <?php echo htmlspecialchars($selectedCategory['name']); ?>
                        <?php elseif ($featured): ?>
                            Featured Articles
                        <?php else: ?>
                            All Articles
                        <?php endif; ?>
                    </h1>
                    
                    <?php if ($selectedCategory && $selectedCategory['description']): ?>
                        <p class="text-muted"><?php echo htmlspecialchars($selectedCategory['description']); ?></p>
                    <?php endif; ?>
                    
                    <p class="text-muted">
                        Showing <?php echo number_format($totalArticles); ?> article<?php echo $totalArticles !== 1 ? 's' : ''; ?>
                        <?php if ($pagination['total_pages'] > 1): ?>
                            (Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>)
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Sort Options -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-sort-down"></i> Sort
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'recent' ? 'active' : ''; ?>" 
                               href="<?php echo buildUrl('browse.php', array_merge($_GET, ['sort' => 'recent', 'page' => 1])); ?>">
                                <i class="bi bi-clock"></i> Most Recent
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'popular' ? 'active' : ''; ?>" 
                               href="<?php echo buildUrl('browse.php', array_merge($_GET, ['sort' => 'popular', 'page' => 1])); ?>">
                                <i class="bi bi-graph-up"></i> Most Popular
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?php echo $sort === 'alphabetical' ? 'active' : ''; ?>" 
                               href="<?php echo buildUrl('browse.php', array_merge($_GET, ['sort' => 'alphabetical', 'page' => 1])); ?>">
                                <i class="bi bi-sort-alpha-down"></i> Alphabetical
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Articles Grid -->
            <?php if (empty($articles)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-text fs-1 text-muted"></i>
                    <h3 class="text-muted mt-3">No Articles Found</h3>
                    <p class="text-muted">
                        <?php if ($selectedCategory): ?>
                            No articles found in this category.
                        <?php elseif ($featured): ?>
                            No featured articles available.
                        <?php else: ?>
                            No articles available yet.
                        <?php endif; ?>
                    </p>
                    <div class="mt-3">
                        <a href="browse.php" class="btn btn-primary">Browse All Articles</a>
                        <a href="search.php" class="btn btn-outline-primary">Search Articles</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($articles as $article): ?>
                        <div class="col-md-6 col-xl-4 mb-4">
                            <div class="card h-100 article-card">
                                <div class="card-body">
                                    <!-- Article Badges -->
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <?php if ($article['category_name']): ?>
                                            <a href="browse.php?category=<?php echo urlencode($article['category_slug']); ?>" 
                                               class="badge category-badge text-decoration-none">
                                                <?php echo htmlspecialchars($article['category_name']); ?>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($article['featured']): ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-star"></i> Featured
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Article Title -->
                                    <h5 class="card-title">
                                        <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <!-- Article Excerpt -->
                                    <p class="card-text text-muted">
                                        <?php echo $article['excerpt'] ? htmlspecialchars($article['excerpt']) : truncateText(strip_tags($article['content'] ?? ''), 120); ?>
                                    </p>
                                    
                                    <!-- Article Tags -->
                                    <?php if (!empty($article['tags'])): ?>
                                        <div class="mb-2">
                                            <?php foreach ($article['tags'] as $tag): ?>
                                                <a href="search.php?q=<?php echo urlencode($tag['name']); ?>" 
                                                   class="badge tag-badge text-decoration-none me-1">
                                                    <?php echo htmlspecialchars($tag['name']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Article Meta -->
                                    <div class="d-flex justify-content-between align-items-center text-muted small mt-auto">
                                        <span>
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                        </span>
                                        <span>
                                            <i class="bi bi-calendar"></i> <?php echo formatDate($article['created_at']); ?>
                                        </span>
                                        <span>
                                            <i class="bi bi-eye"></i> <?php echo number_format($article['views']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                                       class="btn btn-outline-primary btn-sm w-100">
                                        Read Article <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Articles pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagination['has_previous']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildUrl('browse.php', array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $pagination['current_page'] - 2);
                            $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                            
                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildUrl('browse.php', array_merge($_GET, ['page' => 1])); ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildUrl('browse.php', array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $pagination['total_pages']): ?>
                                <?php if ($endPage < $pagination['total_pages'] - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildUrl('browse.php', array_merge($_GET, ['page' => $pagination['total_pages']])); ?>">
                                        <?php echo $pagination['total_pages']; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($pagination['has_next']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo buildUrl('browse.php', array_merge($_GET, ['page' => $pagination['next_page']])); ?>">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <!-- Page Info -->
                    <div class="text-center text-muted small mt-3">
                        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalArticles); ?> 
                        of <?php echo number_format($totalArticles); ?> articles
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Smooth scrolling for pagination
document.addEventListener('DOMContentLoaded', function() {
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Scroll to top of content area
            const contentArea = document.querySelector('.col-lg-9');
            if (contentArea) {
                contentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

// Filter persistence
document.addEventListener('DOMContentLoaded', function() {
    // Highlight active filters in URL
    const urlParams = new URLSearchParams(window.location.search);
    const activeFilters = [];
    
    if (urlParams.get('category')) activeFilters.push('Category: ' + urlParams.get('category'));
    if (urlParams.get('featured')) activeFilters.push('Featured Articles');
    if (urlParams.get('sort') && urlParams.get('sort') !== 'recent') {
        activeFilters.push('Sort: ' + urlParams.get('sort'));
    }
    
    if (activeFilters.length > 0) {
        const filterInfo = document.createElement('div');
        filterInfo.className = 'alert alert-info alert-dismissible fade show';
        filterInfo.innerHTML = `
            <strong>Active Filters:</strong> ${activeFilters.join(', ')}
            <a href="browse.php" class="btn btn-outline-primary btn-sm ms-2">Clear All</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const mainContent = document.querySelector('.col-lg-9');
        if (mainContent) {
            mainContent.insertBefore(filterInfo, mainContent.firstChild);
        }
    }
});
</script>

<?php includeFooter(); ?>

