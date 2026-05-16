# ThgBook

A personal e-book platform built with PHP, MySQL, and vanilla JavaScript.

**Live demo:** [tahagenc.com/tools/ThgBook](https://tahagenc.com/tools/ThgBook)

To try the store, register an account and contact me for a free redeem code:
**info@tahagenc.com**

## Features

- EPUB and PDF reader with reading progress tracking
- Admin panel with book upload, redeem code and bundle system
- User library with personal book uploads
- Store with code-based book distribution
- Automatic metadata extraction from EPUB/PDF files
- Mobile-friendly with swipe navigation
- Account settings and user management

## Tech Stack

- **Backend:** PHP 8.1, PDO, MySQL 8.0
- **Frontend:** Vanilla HTML5, CSS3, JavaScript ES6+
- **Libraries:** epub.js, PDF.js, JSZip

## Setup

1. Clone the repo
2. Copy `includes/db.php.example` to `includes/db.php` and fill in your database credentials
3. Import `setup/schema.sql` into your MySQL database
4. Deploy to any PHP 8.1+ server 
