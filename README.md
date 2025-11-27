# Knowledge Base CMS

A modern, secure, and user-friendly Content Management System for creating and managing knowledge base articles. Built with PHP and MySQL, featuring admin authentication, article management, and a clean public interface with modern design.

## 🚀 Project Overview

The Knowledge Base CMS is a comprehensive solution for organizations, businesses, and individuals who need to create, organize, and share knowledge articles. It provides an intuitive admin interface for content management and a beautiful public-facing website for users to browse and search through articles.

### What This Project Solves
- **Content Organization**: Easily categorize and tag articles for better navigation
- **Knowledge Sharing**: Provide a centralized location for company documentation, FAQs, and guides
- **Search Functionality**: Powerful search capabilities to help users find relevant information quickly
- **User Management**: Secure admin authentication with role-based access control
- **Modern Design**: Responsive, modern interface that works on all devices

## 🛠️ Technologies Used

### Backend Technologies
- **PHP 8.0+** - Server-side scripting language
- **MySQL 5.7+** - Relational database management system
- **PDO** - Database abstraction layer for secure database operations

### Frontend Technologies
- **HTML5** - Semantic markup
- **CSS3** - Modern styling with custom properties and gradients
- **JavaScript (ES6+)** - Interactive functionality
- **Bootstrap 5.3** - Responsive CSS framework
- **Bootstrap Icons** - Icon library

### Development & Deployment
- **Apache/Nginx** - Web server
- **Git** - Version control
- **Composer** - Dependency management (if needed)

### Security Technologies
- **Bcrypt** - Password hashing
- **CSRF Protection** - Cross-site request forgery prevention
- **Prepared Statements** - SQL injection prevention
- **Input Sanitization** - XSS protection

## ✨ Key Features

### 🔐 Security Features
- **Secure Authentication**: Bcrypt password hashing with salt
- **CSRF Protection**: Token-based protection for all forms
- **SQL Injection Prevention**: Prepared statements only
- **XSS Prevention**: Input sanitization and output escaping
- **Session Security**: Secure session configuration with HTTP-only cookies
- **Role-based Access Control**: Admin-only access to management features

### 📝 Content Management
- **Article Management**: Create, edit, delete, and organize knowledge base articles
- **Rich Text Editor**: WYSIWYG editor for article content
- **Category System**: Organize articles into hierarchical categories
- **Tag System**: Tag articles for improved searchability and organization
- **Featured Articles**: Highlight important articles on the homepage
- **Article Status**: Draft, published, and archived states
- **SEO-friendly URLs**: Clean, readable URLs for better search engine optimization

### 🔍 Search & Navigation
- **Full-text Search**: Search across titles, content, and excerpts
- **Advanced Filtering**: Filter by category, tags, and date
- **Sorting Options**: Sort by relevance, date, popularity, or alphabetical
- **Pagination**: Handle large numbers of articles efficiently
- **Breadcrumb Navigation**: Clear navigation paths
- **Related Articles**: Suggest related content

### 📱 User Experience
- **Responsive Design**: Mobile-first approach that works on all devices
- **Modern UI**: Clean, professional interface with smooth animations
- **Fast Loading**: Optimized for performance
- **Accessibility**: WCAG compliant design
- **Intuitive Navigation**: Easy-to-use interface for both admins and users

### 📊 Analytics & Insights
- **View Tracking**: Track article popularity
- **Search Analytics**: Monitor search queries and results
- **User Engagement**: Track user interactions with content

## 👥 User Roles

### Public Users (Unauthenticated)
- **Browse Articles**: View published articles and categories
- **Search Content**: Use the search functionality to find relevant articles
- **Read Articles**: Access full article content with proper formatting
- **Navigate Categories**: Browse articles by category
- **View Tags**: See article tags for better understanding

### Admin Users (Authenticated)
- **Full Content Management**: Create, edit, delete, and manage all articles
- **Category Management**: Create and manage article categories
- **Tag Management**: Create and manage article tags
- **User Management**: Manage admin accounts (future feature)
- **System Configuration**: Access to system settings and configuration
- **Analytics Access**: View usage statistics and analytics
- **Content Moderation**: Approve, reject, or archive articles

### Future Role Extensions
- **Editor Role**: Can create and edit articles but cannot delete
- **Moderator Role**: Can approve/reject articles and manage comments
- **Viewer Role**: Read-only access to admin panel

## 📁 Project Structure

```
knowledge-base-cms/
├── 📁 admin/                    # Admin panel files
│   ├── 📄 index.php            # Admin dashboard
│   ├── 📄 login.php            # Admin authentication
│   ├── 📄 articles.php         # Article management
│   ├── 📄 categories.php       # Category management
│   ├── 📄 tags.php             # Tag management
│   └── 📄 logout.php           # Logout functionality
├── 📁 includes/                 # Core application files
│   ├── 📄 bootstrap.php        # Application initialization
│   ├── 📄 Database.php         # Database connection and operations
│   ├── 📄 Auth.php             # Authentication and authorization
│   └── 📄 functions.php        # Utility functions and helpers
├── 📁 templates/                # HTML templates
│   ├── 📄 header.php           # Site header with navigation
│   └── 📄 footer.php           # Site footer with copyright
├── 📄 index.php                # Public homepage
├── 📄 article.php              # Individual article display
├── 📄 browse.php               # Article browsing and filtering
├── 📄 search.php               # Search functionality
├── 📄 config.php               # Application configuration
├── 📄 database.sql             # Database schema and initial data
├── 📄 README.md                # Project documentation
```

## 🚀 Setup Instructions

### Prerequisites
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.7 or higher (8.0+ recommended)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP Extensions**: PDO, PDO_MySQL, mbstring, session, json

### Download and Extract
```bash
# Clone or download the project
git clone https://github.com/soikot-shahriaar/knowledge-base-cms
cd knowledge-base-cms
```

### Database Setup
```bash
# Create database and user
mysql -u root -p
CREATE DATABASE knowledge_base CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'kb_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON knowledge_base.* TO 'kb_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import database schema
mysql -u kb_user -p knowledge_base < database.sql
```

### Configuration
```bash
# Edit configuration file
nano config.php
```

Update the database settings:
```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'knowledge_base');
define('DB_USER', 'kb_user');
define('DB_PASS', 'your_secure_password');
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'Your Knowledge Base');
define('SITE_URL', 'http://localhost/knowledge-base-cms');
define('ADMIN_EMAIL', 'admin@yoursite.com');
```

### Initial Access
1. Navigate to your website URL
2. Access admin panel at `/admin/`
3. Use default credentials:
   - **Username**: `admin`
   - **Password**: `admin123`
4. **Important**: Change default password immediately!

## 📖 Usage

### Admin Panel Access
Navigate to `/admin/` to access the admin panel:
```
https://yoursite.com/admin/
```

### Managing Articles

#### Creating New Articles
1. Log in to admin panel
2. Go to "Articles" → "New Article"
3. Fill in article details:
   - **Title**: Article title (required)
   - **Content**: Use the rich text editor
   - **Excerpt**: Brief summary (optional)
   - **Category**: Select appropriate category
   - **Tags**: Add relevant tags
   - **Status**: Set to "Published" to make visible
   - **Featured**: Check to highlight on homepage
4. Click "Save Article"

#### Editing Articles
1. Go to "Articles" in admin panel
2. Find the article you want to edit
3. Click the "Edit" button
4. Make your changes
5. Click "Update Article"

#### Managing Categories
1. Go to "Categories" in admin panel
2. Click "New Category"
3. Enter category name and description
4. Categories automatically get URL-friendly slugs

#### Managing Tags
1. Go to "Tags" in admin panel
2. Click "New Tag"
3. Enter tag name
4. Tags help with article organization and search

### Public Interface

#### Browsing Articles
- **Homepage**: Featured and recent articles
- **Browse**: All articles with category filtering
- **Search**: Full-text search with advanced options
- **Categories**: Browse by category

#### Search Features
- Full-text search across titles, content, and excerpts
- Category filtering
- Sort by relevance, date, popularity, or alphabetical
- Pagination for large result sets

### Configuration Options

#### Basic Settings
Edit `config.php` to customize:
```php
// Site Configuration
define('SITE_NAME', 'Your Knowledge Base');
define('SITE_URL', 'https://yoursite.com');
define('ADMIN_EMAIL', 'admin@yoursite.com');

// Pagination
define('ARTICLES_PER_PAGE', 12);
define('SEARCH_RESULTS_PER_PAGE', 10);

// Security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MIN_PASSWORD_LENGTH', 6);
```

#### Security Settings
```php
// CSRF Protection
define('CSRF_TOKEN_NAME', '_token');

// Session Security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
```

## 🎯 Intended Use

### Perfect For
- **Business Documentation**: Company policies, procedures, and guidelines
- **Product Documentation**: User manuals, FAQs, and troubleshooting guides
- **Educational Content**: Course materials, tutorials, and learning resources
- **Support Knowledge Base**: Customer support articles and solutions
- **Internal Wikis**: Team documentation and knowledge sharing
- **Community Forums**: Community-driven knowledge sharing platforms

### Use Cases
1. **Corporate Knowledge Management**: Centralize company knowledge and make it easily accessible to employees
2. **Customer Support**: Provide self-service support through comprehensive FAQs and guides
3. **Product Documentation**: Create detailed product documentation for users
4. **Training Materials**: Organize training content and educational resources
5. **Research Repository**: Store and organize research findings and reports
6. **Community Knowledge**: Build community-driven knowledge bases

### Benefits
- **Improved Efficiency**: Reduce time spent searching for information
- **Better User Experience**: Easy-to-navigate knowledge base
- **Reduced Support Load**: Self-service support through comprehensive documentation
- **Knowledge Retention**: Preserve institutional knowledge
- **Scalable Solution**: Easy to add new content and categories
- **SEO Friendly**: Optimized for search engines

## 🔧 Customization

### Styling Customization
The project uses CSS custom properties for easy theming:
```css
:root {
    --primary-color: #2563eb;
    --primary-dark: #1d4ed8;
    --secondary-color: #64748b;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
}
```

### Adding New Features
The modular structure makes it easy to add new features:
- Add new admin pages in the `admin/` directory
- Extend the Database class for new functionality
- Create new templates in the `templates/` directory

### Database Extensions
The database schema is designed to be extensible:
- Add new tables for additional features
- Extend existing tables with new columns
- Create new relationships between entities

## 🛡️ Security Considerations

### Security Features
- **Password Security**: Bcrypt hashing with salt
- **CSRF Protection**: Token-based protection for all forms
- **SQL Injection Prevention**: Prepared statements only
- **XSS Prevention**: Input sanitization and output escaping
- **Session Security**: Secure session configuration
- **Access Control**: Role-based authentication

### Security Best Practices
1. **Change Default Credentials**: Immediately after installation
2. **Use HTTPS**: Always use SSL/TLS in production
3. **Regular Updates**: Keep PHP and MySQL updated
4. **Strong Passwords**: Enforce strong password policies
5. **Backup Data**: Regular database backups
6. **Monitor Logs**: Check error logs regularly

## 🚀 Performance Optimization

### Database Optimization
- Add indexes for frequently queried columns
- Use LIMIT clauses for large result sets
- Optimize MySQL configuration

### Caching Strategies
Consider implementing:
- Page caching for static content
- Database query caching
- CDN for static assets

### Web Server Optimization
- Enable gzip compression
- Set proper cache headers
- Optimize images

## 📊 Maintenance

### Regular Tasks
- **Database Backups**: Weekly automated backups
- **Security Updates**: Monthly security patches
- **Performance Monitoring**: Regular performance checks
- **Content Review**: Quarterly content audits
- **User Training**: Regular admin training sessions

### Backup Procedures
```bash
# Database backup
mysqldump -u kb_user -p knowledge_base > backup_$(date +%Y%m%d).sql

# File backup
tar -czf kb_backup_$(date +%Y%m%d).tar.gz knowledge-base-cms/
```

## 🤝 Support

### Documentation
- [Installation Guide](INSTALL.md) - Detailed installation instructions
- [Security Guide](SECURITY.md) - Security best practices
- [API Documentation](API.md) - Developer documentation

### Getting Help
1. Check this README first
2. Review the troubleshooting section
3. Check existing issues on GitHub
4. Create a new issue with detailed information

### Reporting Issues
When reporting issues, please include:
- PHP version
- MySQL version
- Web server (Apache/Nginx)
- Error messages
- Steps to reproduce
- Expected vs actual behavior

## 📄 License

**License for RiverTheme**

RiverTheme makes this project available for demo, instructional, and personal use. You can ask for or buy a license from [RiverTheme.com](https://RiverTheme.com) if you want a pro website, sophisticated features, or expert setup and assistance. A Pro license is needed for production deployments, customizations, and commercial use.

**Disclaimer**

The free version is offered "as is" with no warranty and might not function on all devices or browsers. It might also have some coding or security flaws. For additional information or to get a Pro license, please get in touch with [RiverTheme.com](https://RiverTheme.com).

---