<?php
/**
 * Search Articles Page
 * Search and filter knowledge base articles
 */

require_once 'includes/bootstrap.php';

// Get search parameters
$query = trim(getGet('q', ''));
$category = getGet('category', '');
$sort = getGet('sort', 'relevance'); // relevance, recent, popular, alphabetical
$page = max(1, getGet('page', 1));
$perPage = SEARCH_RESULTS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$articles = [];
$totalResults = 0;
$searchPerformed = false;

try {
    // Get categories for filter
    $categories = $db->fetchAll("
        SELECT c.*, COUNT(a.id) as article_count
        FROM categories c
        LEFT JOIN articles a ON c.id = a.category_id AND a.status = 'published'
        GROUP BY c.id
        HAVING article_count > 0
        ORDER BY c.name
    ");
    
    // Perform search if query is provided
    if (!empty($query)) {
        $searchPerformed = true;
        
        // Build WHERE clause
        $whereConditions = ["a.status = 'published'"];
        $params = [];
        
        // Add search conditions
        $searchTerms = explode(' ', $query);
        $searchConditions = [];
        
        foreach ($searchTerms as $term) {
            $term = trim($term);
            if (!empty($term)) {
                $searchConditions[] = "(a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)";
                $params[] = "%$term%";
                $params[] = "%$term%";
                $params[] = "%$term%";
            }
        }
        
        if (!empty($searchConditions)) {
            $whereConditions[] = '(' . implode(' AND ', $searchConditions) . ')';
        }
        
        // Add category filter
        if (!empty($category)) {
            $whereConditions[] = "a.category_id = ?";
            $params[] = $category;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Build ORDER BY clause
        $orderClause = match($sort) {
            'recent' => 'ORDER BY a.created_at DESC',
            'popular' => 'ORDER BY a.views DESC, a.created_at DESC',
            'alphabetical' => 'ORDER BY a.title ASC',
            default => 'ORDER BY 
                        (CASE WHEN a.title LIKE ? THEN 1 ELSE 0 END) DESC,
                        (CASE WHEN a.title LIKE ? THEN 1 ELSE 0 END) DESC,
                        a.views DESC, a.created_at DESC'
        };
        
        // Add relevance parameters for default sort
        if ($sort === 'relevance') {
            array_unshift($params, "%$query%", "$query%");
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM articles a $whereClause";
        $countParams = $params;
        if ($sort === 'relevance') {
            // Remove relevance parameters for count query
            $countParams = array_slice($params, 2);
        }
        $totalResults = $db->fetchOne($countSql, $countParams)['total'];
        
        // Get search results
        if ($totalResults > 0) {
            $sql = "SELECT a.id, a.title, a.slug, a.excerpt, a.content, a.views, a.created_at, a.featured,
                           c.name as category_name, c.slug as category_slug,
                           u.username as author_name
                    FROM articles a
                    LEFT JOIN categories c ON a.category_id = c.id
                    LEFT JOIN users u ON a.author_id = u.id
                    $whereClause
                    $orderClause
                    LIMIT $perPage OFFSET $offset";
            
            $articles = $db->fetchAll($sql, $params);
            
            // Get tags for each article
            foreach ($articles as &$article) {
                $article['tags'] = $db->fetchAll("
                    SELECT t.name, t.slug 
                    FROM tags t 
                    INNER JOIN article_tags at ON t.id = at.tag_id 
                    WHERE at.article_id = ?
                    ORDER BY t.name
                    LIMIT 3
                ", [$article['id']]);
                
                // Generate search excerpt
                $article['search_excerpt'] = generateSearchExcerpt($article['content'], $query);
            }
        }
    }
    
} catch (Exception $e) {
    setFlashMessage('error', 'Search error: ' . $e->getMessage());
    $articles = [];
    $totalResults = 0;
    $categories = [];
}

// Pagination
$pagination = getPagination($page, $totalResults, $perPage);

$pageTitle = !empty($query) ? 'Search Results for "' . htmlspecialchars($query) . '"' : 'Search Articles';
includeHeader($pageTitle);

/**
 * Generate search excerpt with highlighted terms
 */
function generateSearchExcerpt($content, $query, $length = 200) {
    $text = strip_tags($content);
    $text = preg_replace('/\s+/', ' ', $text);
    
    if (empty($query)) {
        return truncateText($text, $length);
    }
    
    // Find the position of the search term
    $pos = stripos($text, $query);
    
    if ($pos !== false) {
        // Extract text around the search term
        $start = max(0, $pos - $length / 2);
        $excerpt = substr($text, $start, $length);
        
        // Ensure we don't cut words
        if ($start > 0) {
            $spacePos = strpos($excerpt, ' ');
            if ($spacePos !== false) {
                $excerpt = substr($excerpt, $spacePos + 1);
            }
        }
        
        if (strlen($text) > $start + $length) {
            $lastSpace = strrpos($excerpt, ' ');
            if ($lastSpace !== false) {
                $excerpt = substr($excerpt, 0, $lastSpace) . '...';
            }
        }
        
        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }
        
        return $excerpt;
    }
    
    return truncateText($text, $length);
}
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Search</li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Search Sidebar -->
        <div class="col-lg-3">
            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-search"></i> Search Articles
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="search.php" id="searchForm">
                        <div class="mb-3">
                            <label for="q" class="form-label">Search Terms</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="q" 
                                   name="q" 
                                   value="<?php echo htmlspecialchars($query); ?>"
                                   placeholder="Enter keywords..."
                                   autofocus>
                            <div class="form-text">Search in titles, content, and excerpts</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select name="category" id="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['article_count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sort" class="form-label">Sort By</label>
                            <select name="sort" id="sort" class="form-select">
                                <option value="relevance" <?php echo $sort === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                                <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                                <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                <option value="alphabetical" <?php echo $sort === 'alphabetical' ? 'selected' : ''; ?>>Alphabetical</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Search
                            </button>
                            <a href="search.php" class="btn btn-outline-secondary btn-sm">
                                Clear Search
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Search Tips -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightbulb"></i> Search Tips
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Use multiple keywords for better results</li>
                        <li>Search looks through titles, content, and excerpts</li>
                        <li>Filter by category to narrow results</li>
                        <li>Sort by relevance for best matches</li>
                        <li>Try different keywords if no results found</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Search Results -->
        <div class="col-lg-9">
            <?php if (!$searchPerformed): ?>
                <!-- Search Landing -->
                <div class="text-center py-5">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <h2 class="mt-3">Search Our Knowledge Base</h2>
                    <p class="text-muted lead">Find answers to your questions by searching through our articles.</p>
                    
                    <!-- Quick Search -->
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-8">
                            <form action="search.php" method="GET" class="d-flex">
                                <input type="text" 
                                       name="q" 
                                       class="form-control form-control-lg me-2" 
                                       placeholder="What are you looking for?"
                                       autofocus>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-search"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Popular Searches -->
                    <div class="mt-4">
                        <h5>Popular Searches:</h5>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a href="search.php?q=getting+started" class="badge bg-primary text-decoration-none">Getting Started</a>
                            <a href="search.php?q=troubleshooting" class="badge bg-primary text-decoration-none">Troubleshooting</a>
                            <a href="search.php?q=tutorial" class="badge bg-primary text-decoration-none">Tutorial</a>
                            <a href="search.php?q=guide" class="badge bg-primary text-decoration-none">Guide</a>
                            <a href="search.php?q=faq" class="badge bg-primary text-decoration-none">FAQ</a>
                        </div>
                    </div>
                    
                    <!-- Browse Options -->
                    <div class="mt-5">
                        <h5>Or browse by category:</h5>
                        <div class="row">
                            <?php foreach (array_slice($categories, 0, 4) as $cat): ?>
                                <div class="col-md-3 mb-3">
                                    <a href="browse.php?category=<?php echo urlencode($cat['slug']); ?>" 
                                       class="card text-decoration-none h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-folder fs-2 text-primary"></i>
                                            <h6 class="mt-2"><?php echo htmlspecialchars($cat['name']); ?></h6>
                                            <small class="text-muted"><?php echo $cat['article_count']; ?> articles</small>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="browse.php" class="btn btn-outline-primary">View All Categories</a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Search Results Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1>Search Results</h1>
                        <p class="text-muted mb-0">
                            <?php if ($totalResults > 0): ?>
                                Found <?php echo number_format($totalResults); ?> result<?php echo $totalResults !== 1 ? 's' : ''; ?> 
                                for "<strong><?php echo htmlspecialchars($query); ?></strong>"
                                <?php if ($pagination['total_pages'] > 1): ?>
                                    (Page <?php echo $pagination['current_page']; ?> of <?php echo $pagination['total_pages']; ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                No results found for "<strong><?php echo htmlspecialchars($query); ?></strong>"
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- Search Time -->
                    <small class="text-muted">
                        Search completed in <?php echo number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?> seconds
                    </small>
                </div>
                
                <?php if ($totalResults === 0): ?>
                    <!-- No Results -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-search fs-1 text-muted"></i>
                            <h3 class="mt-3">No Results Found</h3>
                            <p class="text-muted">We couldn't find any articles matching your search.</p>
                            
                            <div class="mt-4">
                                <h5>Try these suggestions:</h5>
                                <ul class="list-unstyled">
                                    <li>• Check your spelling</li>
                                    <li>• Use different keywords</li>
                                    <li>• Try more general terms</li>
                                    <li>• Browse by category instead</li>
                                </ul>
                            </div>
                            
                            <div class="mt-4">
                                <a href="search.php" class="btn btn-primary me-2">New Search</a>
                                <a href="browse.php" class="btn btn-outline-primary">Browse Articles</a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Search Results -->
                    <div class="search-results">
                        <?php foreach ($articles as $article): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <!-- Article Header -->
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex flex-wrap gap-2">
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
                                        
                                        <small class="text-muted">
                                            <?php echo number_format($article['views']); ?> views
                                        </small>
                                    </div>
                                    
                                    <!-- Article Title -->
                                    <h5 class="card-title">
                                        <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                                           class="text-decoration-none">
                                            <?php echo highlightSearchTerms(htmlspecialchars($article['title']), $query); ?>
                                        </a>
                                    </h5>
                                    
                                    <!-- Article Excerpt -->
                                    <p class="card-text">
                                        <?php echo highlightSearchTerms(htmlspecialchars($article['search_excerpt']), $query); ?>
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
                                    <div class="d-flex justify-content-between align-items-center text-muted small">
                                        <div>
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?>
                                            <span class="mx-2">•</span>
                                            <i class="bi bi-calendar"></i> <?php echo formatDate($article['created_at']); ?>
                                        </div>
                                        <a href="article.php?slug=<?php echo urlencode($article['slug']); ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            Read Article <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <nav aria-label="Search results pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildUrl('search.php', array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $pagination['current_page'] - 2);
                                $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo buildUrl('search.php', array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildUrl('search.php', array_merge($_GET, ['page' => $pagination['next_page']])); ?>">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <!-- Results Info -->
                        <div class="text-center text-muted small mt-3">
                            Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalResults); ?> 
                            of <?php echo number_format($totalResults); ?> results
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Search-specific styles */
.search-results mark {
    background-color: #fff3cd;
    padding: 0.1em 0.2em;
    border-radius: 2px;
}

.search-results .card {
    transition: box-shadow 0.2s ease;
}

.search-results .card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.badge.bg-primary {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.badge.bg-primary:hover {
    background-color: #0056b3 !important;
}
</style>

<script>
// Search form enhancements
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('q');
    
    // Auto-submit on filter change
    const filterSelects = searchForm.querySelectorAll('select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            if (searchInput.value.trim()) {
                searchForm.submit();
            }
        });
    });
    
    // Search suggestions (placeholder for future enhancement)
    let suggestionTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(suggestionTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            suggestionTimeout = setTimeout(function() {
                // This could be enhanced with AJAX search suggestions
                console.log('Search suggestions for:', query);
            }, 300);
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Focus search with '/' key
        if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            searchInput.focus();
        }
        
        // Submit search with Ctrl+Enter
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && document.activeElement === searchInput) {
            searchForm.submit();
        }
    });
});

// Highlight search terms in results
document.addEventListener('DOMContentLoaded', function() {
    const query = '<?php echo addslashes($query); ?>';
    if (query) {
        // Additional highlighting could be added here
    }
});
</script>

<?php includeFooter(); ?>

