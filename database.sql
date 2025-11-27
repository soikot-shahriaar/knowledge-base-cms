-- Knowledge Base CMS Database Schema
-- Created for PHP/MySQL Knowledge Base Management System

-- Create database
CREATE DATABASE IF NOT EXISTS knowledge_base;
USE knowledge_base;

-- Users table for admin authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table for organizing articles
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tags table for article tagging
CREATE TABLE tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Articles table for knowledge base content
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    category_id INT,
    author_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_featured (featured),
    FULLTEXT(title, content, excerpt)
);

-- Junction table for article-tag relationships (many-to-many)
CREATE TABLE article_tags (
    article_id INT,
    tag_id INT,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@example.com', '$2y$10$y/VJS9ZU3KUfGjvCKVjJHePMtFN7MiiQJfzVY3kwtz6Y/Qt5vJQzq', 'admin');

-- Insert default categories
INSERT INTO categories (name, description, slug) VALUES 
('General', 'General knowledge and information', 'general'),
('Technical', 'Technical documentation and guides', 'technical'),
('FAQ', 'Frequently Asked Questions', 'faq'),
('Tutorials', 'Step-by-step tutorials and how-to guides', 'tutorials');

-- Insert sample tags
INSERT INTO tags (name, slug) VALUES 
('Getting Started', 'getting-started'),
('Troubleshooting', 'troubleshooting'),
('Best Practices', 'best-practices'),
('Advanced', 'advanced'),
('Beginner', 'beginner');

-- Insert sample articles
INSERT INTO articles (title, slug, content, excerpt, category_id, author_id, status, featured) VALUES 
('Welcome to the Knowledge Base', 'welcome-to-knowledge-base', 
'<h2>Welcome to Our Knowledge Base</h2>
<p>This knowledge base contains helpful articles, tutorials, and documentation to help you get the most out of our services.</p>
<h3>How to Use This Knowledge Base</h3>
<p>You can browse articles by category using the navigation menu, or use the search function to find specific information.</p>
<h3>Getting Help</h3>
<p>If you cannot find what you are looking for, please contact our support team for assistance.</p>', 
'Learn how to navigate and use our knowledge base effectively.', 
1, 1, 'published', TRUE),

('Getting Started Guide', 'getting-started-guide',
'<h2>Getting Started</h2>
<p>This guide will help you get started with our platform quickly and easily.</p>
<h3>Step 1: Account Setup</h3>
<p>First, create your account and verify your email address.</p>
<h3>Step 2: Initial Configuration</h3>
<p>Configure your basic settings and preferences.</p>
<h3>Step 3: First Steps</h3>
<p>Begin using the platform with these essential first steps.</p>',
'A comprehensive guide to help new users get started with our platform.',
4, 1, 'published', TRUE);

-- Link sample articles with tags
INSERT INTO article_tags (article_id, tag_id) VALUES 
(1, 1), -- Welcome article with Getting Started tag
(2, 1), -- Getting Started Guide with Getting Started tag
(2, 5); -- Getting Started Guide with Beginner tag

