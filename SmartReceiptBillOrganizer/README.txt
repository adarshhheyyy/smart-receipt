========================================================================
SMARTRECEIPT - BILL & WARRANTY ORGANIZER
========================================================================
A modern, secure, and beautiful digital receipt organizer and warranty 
tracker built with PHP, MySQL, Vanilla CSS, and modern JavaScript.

1. TECH STACK
------------------------------------------------------------------------
- Backend: PHP 8.0+ (PDO with Prepared Statements, CSRF tokens, strict validation)
- Database: MySQL / MariaDB (InnoDB with foreign keys and cascade delete)
- Frontend: Semantic HTML5, Vanilla CSS3 (Design Tokens, Glassmorphism, Responsive)
- JavaScript: ES6 Vanilla JS (Receipt Lightbox Modal, Drag & Drop, Preset Helpers)
- Server: Apache (XAMPP / WAMP / LAMP)

2. FEATURES
------------------------------------------------------------------------
- Secure Authentication: Password hashing (BCRYPT), CSRF protection, session management.
- Digital Receipt Archiving: Store vendor, date, amount, category, notes, and image scans.
- Interactive Lightbox: Click any receipt thumbnail to view full-resolution preview.
- Drag & Drop Uploads: Support for JPG, PNG, and WEBP with client & server-side validation.
- Warranty Sentinel: Product warranty tracker with active, expiring-soon, and expired status badges.
- Dynamic Progress Lifecycles: Visual elapsed coverage timeline bar for every product.
- Expense Analytics: Automatic category breakdown bars and monthly spending metrics.
- Fast Search & Filter: Instantly search by store, product, notes, category, or month.
- Fully Responsive: Slide-out mobile navigation drawer with backdrop dimming.

3. QUICK INSTALLATION & SETUP (XAMPP)
------------------------------------------------------------------------
Step 1: Place this folder in your XAMPP web root:
        C:\xampp\htdocs\SmartReceiptBillOrganizer

Step 2: Start Apache and MySQL from your XAMPP Control Panel.

Step 3: Open phpMyAdmin:
        http://localhost/phpmyadmin/

Step 4: Create/Import database:
        - Import the included "database.sql" file.
        - This creates "smart_receipt_db" with "users", "receipts", and "warranties" tables.

Step 5: Verify configuration:
        - config/db.php: host=localhost, db=smart_receipt_db, user=root, pass=''
        - config/config.php: BASE_URL = '/SmartReceiptBillOrganizer'

Step 6: Open the application in your browser:
        http://localhost/SmartReceiptBillOrganizer/

Step 7: Create a free account on the Register page and begin organizing your receipts!

4. DIRECTORY STRUCTURE
------------------------------------------------------------------------
SmartReceiptBillOrganizer/
├── assets/
│   ├── css/
│   │   └── style.css          # Design system, glassmorphism, responsive grid
│   └── js/
│       └── app.js             # Lightbox modal, drag-and-drop, warranty presets
├── config/
│   ├── config.php             # Global constants, SVG icons, tokens, helpers
│   └── db.php                 # Secure PDO database connection
├── includes/
│   ├── header.php             # Unified authenticated shell & public topbar
│   └── footer.php             # Global footer & Receipt Lightbox container
├── uploads/
│   ├── .htaccess              # Prevents direct PHP script execution
│   └── .gitkeep               # Directory placeholder
├── add_receipt.php            # 3-step receipt & warranty creation form
├── dashboard.php              # Analytics, category bars, warranty watchlist
├── database.sql               # Database schema with InnoDB foreign keys
├── delete_receipt.php         # Secure CSRF-protected receipt deletion
├── edit_receipt.php           # Edit details, replace image, adjust warranties
├── index.php                  # Modern landing page with interactive mockups
├── login.php                  # User authentication with flash alerts
├── logout.php                 # Secure session destruction and cookie wipe
├── receipts.php               # Searchable receipt archive with category pills
├── register.php               # Account creation with validation checks
├── warranties.php             # Warranty Sentinel tracker with filter tabs
└── README.txt                 # Setup and documentation
========================================================================
