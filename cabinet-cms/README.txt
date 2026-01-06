╔═══════════════════════════════════════════════════════════════╗
║                     CABINET CMS v1.0                          ║
║         Portfolio Management System for Cabinet Makers        ║
╚═══════════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 TABLE OF CONTENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Installation Instructions
2. System Requirements
3. Quick Start Guide
4. Features Overview
5. Troubleshooting
6. Security Recommendations


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📦 1. INSTALLATION INSTRUCTIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

STEP 1: Upload Files
───────────────────
• Extract the ZIP file to your computer
• Upload ALL files and folders to your web server
• You can upload to root directory (public_html) or a subdirectory

STEP 2: Set Permissions
───────────────────────
Ensure these folders are writable (chmod 755 or 775):
• /uploads/
• /

STEP 3: Run Setup Wizard
────────────────────────
• Navigate to: http://yoursite.com/setup.php
• Follow the 2-step installation wizard:

  📝 Step 1: Database Configuration
     - Enter your MySQL database credentials
     - The installer will create the database if it doesn't exist
     - If tables already exist, you can choose to reinstall

  👤 Step 2: Admin Account & API Keys
     - Create your admin username and password
     - Enter your admin email (used for password resets)
     - Optionally add TinyMCE API key (get free at tiny.cloud)

• Click "Install Cabinet CMS"
• You'll be redirected to the admin login page

STEP 4: Login & Start Building
──────────────────────────────
• Login with your admin credentials
• Your site will be in "Construction Mode" by default
• Create your first page and set it as homepage
• Disable construction mode in Settings → Theme & Colors


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
⚙️ 2. SYSTEM REQUIREMENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Server Requirements:
───────────────────
✓ PHP 7.4 or higher (PHP 8.0+ recommended)
✓ MySQL 5.7 or higher
✓ Apache with mod_rewrite enabled
✓ GD Library (for image processing)

Required PHP Extensions:
───────────────────────
✓ PDO & PDO_MySQL
✓ GD (image manipulation)
✓ mbstring
✓ fileinfo

Recommended:
───────────
✓ SSL certificate (for HTTPS)
✓ At least 100MB disk space
✓ PHP memory_limit of 128MB or higher


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🚀 3. QUICK START GUIDE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

After installation, follow these steps:

1️⃣ CUSTOMIZE YOUR SITE
   • Go to Settings → Company Info
   • Add company name, contact email, phone, address
   • Upload logo in Settings → Theme & Colors
   • Customize colors to match your brand

2️⃣ CREATE YOUR FIRST PAGE
   • Click "Pages" in sidebar
   • Click "+ Add New Page"
   • Add title, content, and hero image
   • Check "Set as Homepage"
   • Click "Save Changes"

3️⃣ ADD A PORTFOLIO JOB
   • Click "Jobs" in sidebar
   • Click "+ Add New Job"
   • Enter job name and optional notes
   • Save the job
   • Upload images (drag & drop or click)
   • Assign categories to images
   • Drag to reorder images

4️⃣ ORGANIZE WITH CATEGORIES
   • Categories are created in Pages
   • Add categories to a page (e.g., "Kitchens", "Bathrooms")
   • Assign those categories to images in Jobs or Media Library
   • Images will appear on the page under their category

5️⃣ GO LIVE
   • Go to Settings → Theme & Colors
   • Turn OFF "Maintenance Mode"
   • Your site is now live!


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✨ 4. FEATURES OVERVIEW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📄 PAGES
   • Create unlimited pages (About, Contact, Services, etc.)
   • Rich text editor for content
   • Hero images
   • Set one page as homepage
   • Public/Private toggle
   • Category-based image galleries

💼 JOBS (Portfolio Projects)
   • Organize projects by job
   • Upload multiple images per job
   • Drag & drop image reordering
   • Assign multiple categories to each image
   • Private notes (not shown on frontend)

🖼️ MEDIA LIBRARY
   • Upload images not tied to a specific job
   • Assign categories to organize
   • Later link images to jobs if needed

🎨 THEME & BRANDING
   • Custom logo upload
   • Background, accent, and text colors
   • Company name displays if no logo
   • Colors update site-wide instantly

🏗️ CONSTRUCTION MODE
   • Show "Under Construction" page
   • Email signup form for launch notifications
   • Export signups to CSV
   • Custom construction message

📱 CATEGORIES
   • Create on-the-fly (no pre-management needed)
   • Multi-category image tagging
   • Each page can display specific categories
   • Category filtering on frontend

👥 USER MANAGEMENT
   • Multiple admin accounts
   • Primary admin (cannot be deleted)
   • Change password anytime
   • Forgot password email reset

🔒 SECURITY
   • Password requirements (8+ chars, special character)
   • Session-based authentication
   • "Remember me" option
   • SQL injection protection
   • Secure file uploads

🌐 SEO
   • Custom meta titles and descriptions
   • Keywords
   • Open Graph tags
   • Clean URLs (/about, /job/kitchen-remodel)
   • Auto-generated slugs

📧 SOCIAL MEDIA
   • Facebook, Instagram, TikTok, YouTube, LinkedIn
   • Icons auto-hide if URLs not provided

📊 DASHBOARD
   • Quick stats (jobs, images, pages)
   • Recent jobs and pages
   • Quick action buttons


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔧 5. TROUBLESHOOTING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

❌ Problem: "Database connection failed"
   ✅ Solution:
      • Check your database credentials in config.php
      • Verify database exists
      • Ensure MySQL server is running
      • Check database username has proper permissions

❌ Problem: Images not uploading
   ✅ Solution:
      • Check /uploads/ folder exists and is writable (755 or 775)
      • Check PHP upload_max_filesize in php.ini (increase to 20MB)
      • Check PHP post_max_size in php.ini (increase to 25MB)
      • Verify GD extension is installed

❌ Problem: "404 Not Found" on pages
   ✅ Solution:
      • Ensure .htaccess file exists in root directory
      • Verify mod_rewrite is enabled on Apache
      • Check AllowOverride is set to "All" in Apache config
      • For Nginx, you'll need custom rewrite rules

❌ Problem: Setup wizard keeps showing
   ✅ Solution:
      • Delete the .installed file and run setup again
      • Or manually create .installed file in root directory

❌ Problem: Rich text editor not working
   ✅ Solution:
      • Add TinyMCE API key in Settings → SEO
      • Get free key at: https://www.tiny.cloud/
      • Editor will fallback to plain textarea without key

❌ Problem: Email notifications not sending
   ✅ Solution:
      • Ensure PHP mail() function is enabled on server
      • Consider using an SMTP plugin or service
      • Check spam folder for test emails
      • Some shared hosts disable mail() - contact support


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔐 6. SECURITY RECOMMENDATIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Change Default Admin Credentials
   • After first login, change your password immediately
   • Use strong, unique passwords

2. Use HTTPS
   • Install SSL certificate (free with Let's Encrypt)
   • Force HTTPS in .htaccess (uncomment the lines)

3. Keep Backups
   • Regularly backup your database
   • Backup /uploads/ folder
   • Download backup of config.php

4. Restrict File Permissions
   • Set files to 644, folders to 755
   • Never use 777 permissions

5. Hide Setup File
   • After installation, delete or rename setup.php
   • Or move it outside web root

6. Regular Updates
   • Keep PHP and MySQL updated
   • Monitor error.log for issues

7. Protect Admin Area
   • Consider using .htaccess password protection on /admin/
   • Use IP whitelisting if you have static IP


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📁 FILE STRUCTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

/
├── admin/                  # Admin panel
│   ├── api/               # API endpoints
│   ├── includes/          # Header & sidebar
│   ├── *.php             # Admin pages
│   └── auth-check.php    # Authentication
├── assets/
│   ├── css/
│   │   ├── admin.css     # Admin styles
│   │   └── frontend.css  # Public site styles
│   └── js/
├── includes/
│   └── db.php            # Database connection
├── install/
│   └── schema.sql        # Database schema
├── uploads/              # User-uploaded images
│   ├── full_*.jpg        # Full-size images
│   └── thumb_*.jpg       # Thumbnails
├── views/                # Frontend templates
│   ├── header.php
│   ├── footer.php
│   ├── page.php
│   ├── job.php
│   ├── construction.php
│   └── 404.php
├── .htaccess             # URL rewriting
├── config.php            # Database config
├── index.php             # Frontend router
├── setup.php             # Installation wizard
└── README.txt            # This file


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📞 SUPPORT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

For issues or questions:
• Check error.log in root directory
• Review troubleshooting section above
• Ensure all system requirements are met


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ CHECKLIST FOR FIRST-TIME SETUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

□ Files uploaded to server
□ Ran setup.php
□ Created admin account
□ Logged into admin panel
□ Added company information
□ Uploaded logo or set company name
□ Customized theme colors
□ Created first page
□ Set page as homepage
□ Created first job with images
□ Assigned categories to images
□ Tested category filtering
□ Disabled construction mode
□ Tested public site
□ Changed default admin password
□ Backed up database

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Thank you for using Cabinet CMS!

Version: 1.0
Last Updated: 2026
