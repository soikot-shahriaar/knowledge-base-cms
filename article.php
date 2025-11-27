<?php
/**
 * Article View Page
 * Display individual knowledge base articles
 */

require_once 'includes/bootstrap.php';

// Get article by slug or ID
$slug = getGet('slug', '');
$articleId = getGet('id', 0);

if (empty($slug) && !$articleId) {
    setFlashMessage('error', 'Article not found.');
    redirect('index.php');
}

try {
    // Get article with related data
    if ($slug) {
        $sql = "SELECT a.*, c.name as category_name, c.slug as category_slug, u.username as author_name
                FROM articles a
                LEFT JOIN categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.author_id = u.id
                WHERE a.slug = ? AND a.status = 'published'";
        $article = $db->fetchOne($sql, [$slug]);
    } else {
        $sql = "SELECT a.*, c.name as category_name, c.slug as category_slug, u.username as author_name
                FROM articles a
                LEFT JOIN categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.author_id = u.id
                WHERE a.id = ? AND a.status = 'published'";
        $article = $db->fetchOne($sql, [$articleId]);
    }
    
    if (!$article) {
        setFlashMessage('error', 'Article not found or not published.');
        redirect('index.php');
    }
    
    // Update view count
    $db->execute("UPDATE articles SET views = views + 1 WHERE id = ?", [$article['id']]);
    $article['views']++; // Update local copy
    
    // Get article tags
    $tags = $db->fetchAll("
        SELECT t.name, t.slug 
        FROM tags t 
        INNER JOIN article_tags at ON t.id = at.tag_id 
        WHERE at.article_id = ?
        ORDER BY t.name
    ", [$article['id']]);
    
    // Get related articles (same category, excluding current article)
    $relatedArticles = [];
    if ($article['category_id']) {
        $relatedArticles = $db->fetchAll("
            SELECT a.id, a.title, a.slug, a.excerpt, a.views, a.created_at
            FROM articles a
            WHERE a.category_id = ? AND a.id != ? AND a.status = 'published'
            ORDER BY a.views DESC, a.created_at DESC
            LIMIT 5
        ", [$article['category_id'], $article['id']]);
    }
    
    // Get navigation (previous/next articles)
    $prevArticle = $db->fetchOne("
        SELECT id, title, slug 
        FROM articles 
        WHERE id < ? AND status = 'published' 
        ORDER BY id DESC 
        LIMIT 1
    ", [$article['id']]);
    
    $nextArticle = $db->fetchOne("
        SELECT id, title, slug 
        FROM articles 
        WHERE id > ? AND status = 'published' 
        ORDER BY id ASC 
        LIMIT 1
    ", [$article['id']]);
    
} catch (Exception $e) {
    setFlashMessage('error', 'Error loading article: ' . $e->getMessage());
    redirect('index.php');
}

$pageTitle = htmlspecialchars($article['title']);
includeHeader($pageTitle);
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="browse.php">Articles</a></li>
            <?php if ($article['category_name']): ?>
                <li class="breadcrumb-item">
                    <a href="browse.php?category=<?php echo urlencode($article['category_slug']); ?>">
                        <?php echo htmlspecialchars($article['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo truncateText($article['title'], 50); ?>
            </li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Main Article Content -->
        <div class="col-lg-8">
            <article class="card">
                <div class="card-body">
                    <!-- Article Header -->
                    <header class="mb-4">
                        <h1 class="display-5 mb-3"><?php echo htmlspecialchars($article['title']); ?></h1>
                        
                        <!-- Article Meta -->
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3 text-muted">
                            <span>
                                <i class="bi bi-person"></i> 
                                <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown Author'); ?>
                            </span>
                            <span>
                                <i class="bi bi-calendar"></i> 
                                <?php echo formatDateTime($article['created_at']); ?>
                            </span>
                            <?php if ($article['updated_at'] !== $article['created_at']): ?>
                                <span>
                                    <i class="bi bi-pencil"></i> 
                                    Updated <?php echo formatDate($article['updated_at']); ?>
                                </span>
                            <?php endif; ?>
                            <span>
                                <i class="bi bi-eye"></i> 
                                <?php echo number_format($article['views']); ?> views
                            </span>
                        </div>
                        
                        <!-- Category and Tags -->
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <?php if ($article['category_name']): ?>
                                <a href="browse.php?category=<?php echo urlencode($article['category_slug']); ?>" 
                                   class="badge category-badge text-decoration-none">
                                    <i class="bi bi-folder"></i> <?php echo htmlspecialchars($article['category_name']); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($article['featured']): ?>
                                <span class="badge bg-warning">
                                    <i class="bi bi-star"></i> Featured
                                </span>
                            <?php endif; ?>
                            
                            <?php foreach ($tags as $tag): ?>
                                <a href="search.php?q=<?php echo urlencode($tag['name']); ?>" 
                                   class="badge tag-badge text-decoration-none">
                                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($tag['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Article Excerpt -->
                        <?php if (!empty($article['excerpt'])): ?>
                            <div class="alert alert-info">
                                <strong>Summary:</strong> <?php echo htmlspecialchars($article['excerpt']); ?>
                            </div>
                        <?php endif; ?>
                    </header>
                    
                    <!-- Article Content -->
                    <div class="article-content">
                        <?php echo $article['content']; ?>
                    </div>
                    
                    <!-- Article Footer -->
                    <footer class="mt-5 pt-4 border-top">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <span class="me-3">Share this article:</span>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="copyToClipboard(window.location.href)" 
                                                title="Copy link">
                                            <i class="bi bi-link"></i>
                                        </button>
                                        <a href="mailto:?subject=<?php echo urlencode($article['title']); ?>&body=<?php echo urlencode('Check out this article: ' . getCurrentUrl()); ?>" 
                                           class="btn btn-outline-primary" title="Share via email">
                                            <i class="bi bi-envelope"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="window.print()" title="Print article">
                                            <i class="bi bi-printer"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <small class="text-muted">
                                    Article ID: <?php echo $article['id']; ?> | 
                                    Last updated: <?php echo formatDate($article['updated_at']); ?>
                                </small>
                            </div>
                        </div>
                    </footer>
                </div>
            </article>
            
            <!-- Article Navigation -->
            <?php if ($prevArticle || $nextArticle): ?>
                <div class="card mt-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if ($prevArticle): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-arrow-left me-2"></i>
                                        <div>
                                            <small class="text-muted">Previous Article</small>
                                            <div>
                                                <a href="article.php?slug=<?php echo urlencode($prevArticle['slug']); ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($prevArticle['title']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <?php if ($nextArticle): ?>
                                    <div class="d-flex align-items-center justify-content-md-end">
                                        <div class="text-md-end">
                                            <small class="text-muted">Next Article</small>
                                            <div>
                                                <a href="article.php?slug=<?php echo urlencode($nextArticle['slug']); ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($nextArticle['title']); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <i class="bi bi-arrow-right ms-2"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Table of Contents (if article has headings) -->
            <div class="card mb-4" id="tocCard" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list"></i> Table of Contents
                    </h5>
                </div>
                <div class="card-body">
                    <div id="tableOfContents"></div>
                </div>
            </div>
            
            <!-- Related Articles -->
            <?php if (!empty($relatedArticles)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-collection"></i> Related Articles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($relatedArticles as $related): ?>
                                <a href="article.php?slug=<?php echo urlencode($related['slug']); ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($related['title']); ?></h6>
                                        <small class="text-muted"><?php echo number_format($related['views']); ?> views</small>
                                    </div>
                                    <?php if ($related['excerpt']): ?>
                                        <p class="mb-1 small text-muted">
                                            <?php echo truncateText(htmlspecialchars($related['excerpt']), 80); ?>
                                        </p>
                                    <?php endif; ?>
                                    <small class="text-muted"><?php echo formatDate($related['created_at']); ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="browse.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-grid"></i> Browse All Articles
                        </a>
                        <a href="search.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-search"></i> Search Articles
                        </a>
                        <?php if ($article['category_name']): ?>
                            <a href="browse.php?category=<?php echo urlencode($article['category_slug']); ?>" 
                               class="btn btn-outline-info btn-sm">
                                <i class="bi bi-folder"></i> More in <?php echo htmlspecialchars($article['category_name']); ?>
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Article
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Article Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle"></i> Article Information
                    </h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Author:</strong> <?php echo htmlspecialchars($article['author_name'] ?? 'Unknown'); ?><br>
                        <strong>Published:</strong> <?php echo formatDateTime($article['created_at']); ?><br>
                        <strong>Last Updated:</strong> <?php echo formatDateTime($article['updated_at']); ?><br>
                        <strong>Views:</strong> <?php echo number_format($article['views']); ?><br>
                        <strong>Category:</strong> 
                        <?php if ($article['category_name']): ?>
                            <a href="browse.php?category=<?php echo urlencode($article['category_slug']); ?>">
                                <?php echo htmlspecialchars($article['category_name']); ?>
                            </a>
                        <?php else: ?>
                            Uncategorized
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Article-specific styles */
.article-content {
    line-height: 1.7;
    font-size: 1.1rem;
}

.article-content h1,
.article-content h2,
.article-content h3,
.article-content h4,
.article-content h5,
.article-content h6 {
    margin-top: 2rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.article-content h1:first-child,
.article-content h2:first-child,
.article-content h3:first-child {
    margin-top: 0;
}

.article-content p {
    margin-bottom: 1.2rem;
}

.article-content ul,
.article-content ol {
    margin-bottom: 1.2rem;
    padding-left: 2rem;
}

.article-content li {
    margin-bottom: 0.5rem;
}

.article-content blockquote {
    border-left: 4px solid var(--secondary-color);
    padding-left: 1rem;
    margin: 1.5rem 0;
    font-style: italic;
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0 5px 5px 0;
}

.article-content code {
    background-color: #f8f9fa;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-size: 0.9em;
}

.article-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
    overflow-x: auto;
    margin: 1.5rem 0;
}

.article-content img {
    max-width: 100%;
    height: auto;
    border-radius: 5px;
    margin: 1rem 0;
}

.article-content table {
    width: 100%;
    margin: 1.5rem 0;
    border-collapse: collapse;
}

.article-content table th,
.article-content table td {
    border: 1px solid #dee2e6;
    padding: 0.75rem;
    text-align: left;
}

.article-content table th {
    background-color: #f8f9fa;
    font-weight: bold;
}

@media print {
    .sidebar,
    .navbar,
    .footer,
    .breadcrumb,
    .btn {
        display: none !important;
    }
    
    .container {
        max-width: none !important;
    }
    
    .col-lg-8 {
        width: 100% !important;
    }
}
</style>

<script>
// Generate table of contents
document.addEventListener('DOMContentLoaded', function() {
    const articleContent = document.querySelector('.article-content');
    const tocContainer = document.getElementById('tableOfContents');
    const tocCard = document.getElementById('tocCard');
    
    if (articleContent && tocContainer) {
        const headings = articleContent.querySelectorAll('h1, h2, h3, h4, h5, h6');
        
        if (headings.length > 1) {
            const tocList = document.createElement('ul');
            tocList.className = 'list-unstyled';
            
            headings.forEach((heading, index) => {
                // Add ID to heading if it doesn't have one
                if (!heading.id) {
                    heading.id = 'heading-' + index;
                }
                
                const listItem = document.createElement('li');
                const link = document.createElement('a');
                link.href = '#' + heading.id;
                link.textContent = heading.textContent;
                link.className = 'text-decoration-none';
                
                // Add indentation based on heading level
                const level = parseInt(heading.tagName.charAt(1));
                if (level > 2) {
                    listItem.style.paddingLeft = (level - 2) * 1 + 'rem';
                    link.className += ' text-muted';
                }
                
                listItem.appendChild(link);
                tocList.appendChild(listItem);
            });
            
            tocContainer.appendChild(tocList);
            tocCard.style.display = 'block';
        }
    }
});

// Smooth scrolling for TOC links
document.addEventListener('click', function(e) {
    if (e.target.matches('#tableOfContents a[href^="#"]')) {
        e.preventDefault();
        const target = document.querySelector(e.target.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }
});

// Reading progress indicator
document.addEventListener('DOMContentLoaded', function() {
    const progressBar = document.createElement('div');
    progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background-color: var(--secondary-color);
        z-index: 9999;
        transition: width 0.3s ease;
    `;
    document.body.appendChild(progressBar);
    
    window.addEventListener('scroll', function() {
        const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        progressBar.style.width = scrolled + '%';
    });
});

// Highlight current section in TOC
document.addEventListener('scroll', function() {
    const headings = document.querySelectorAll('.article-content h1, .article-content h2, .article-content h3, .article-content h4, .article-content h5, .article-content h6');
    const tocLinks = document.querySelectorAll('#tableOfContents a');
    
    let current = '';
    headings.forEach(heading => {
        const rect = heading.getBoundingClientRect();
        if (rect.top <= 100) {
            current = heading.id;
        }
    });
    
    tocLinks.forEach(link => {
        link.classList.remove('fw-bold');
        if (link.getAttribute('href') === '#' + current) {
            link.classList.add('fw-bold');
        }
    });
});
</script>

<?php includeFooter(); ?>

