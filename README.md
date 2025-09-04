# Fishing Gear Store - Complete SPA E-commerce Website

A comprehensive single-page application (SPA) e-commerce website for selling fishing equipment, built with HTML5, CSS3, JavaScript, PHP, and MySQL.

## Features

### 🎣 Product System
- Display products with images, titles, prices, stock, and descriptions
- Categories: Fishing Rods, Reels, Baits & Lures, Accessories
- Advanced filtering by price, category, brand, and stock availability
- Live search with auto-suggestions
- Multiple product images with zoom functionality
- Product reviews and ratings system

### 🛒 Shopping Cart
- Add, remove, and update product quantities
- Real-time cart total and item count
- Clear cart functionality
- Persistent cart storage (localStorage)

### 👤 User System
- User registration and login (modal popups)
- Secure password hashing
- User profile management
- Order history with detailed tracking
- Print receipt functionality

### 💳 Checkout System
- Simulated payment processing
- Order confirmation modals
- Invoice/receipt generation
- Order status tracking
- Coupon code support

### 🔧 Admin Panel
- Complete CRUD operations for products, categories, brands
- Order management and status updates
- User management
- Promotion and discount management
- Analytics dashboard with sales reports
- Stock management

### 🎯 Promotions & Discounts
- Percentage and fixed amount discounts
- Free shipping promotions
- Category and product-specific discounts
- Coupon code system
- Usage limits and expiration dates

### ⭐ Reviews & Ratings
- 5-star rating system
- Detailed product reviews
- Image uploads in reviews
- Review moderation (admin)

### 📞 Contact System
- Contact form with database storage
- Email notifications to admin
- Message status tracking

### 📊 Analytics & Reporting
- Sales dashboard with key metrics
- Product performance reports
- Customer analytics
- Order reports
- Export to CSV/PDF

### 🔒 Security Features
- Password hashing with PHP password_hash()
- SQL injection prevention with prepared statements
- CSRF protection
- Session management
- Admin role restrictions

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3.6
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Icons**: Font Awesome 6.4.0
- **Charts**: Chart.js
- **File Upload**: Native PHP file handling

## Installation & Setup

### 1. Database Setup
1. Create a MySQL database named `fishing_gear_store`
2. Import the SQL schema from `database/schema.sql`
3. Update database credentials in `api/config.php`

### 2. File Structure
```
/workspace/
├── index.html                 # Main SPA page
├── assets/
│   ├── css/
│   │   └── style.css         # Custom styles
│   ├── js/
│   │   └── app.js           # Main JavaScript application
│   └── images/              # Product images, categories, brands
├── api/                     # PHP API endpoints
│   ├── config.php          # Database and app configuration
│   ├── products.php        # Product management API
│   ├── categories.php      # Category management API
│   ├── brands.php          # Brand management API
│   ├── auth.php            # Authentication API
│   ├── orders.php          # Order management API
│   ├── reviews.php         # Review system API
│   ├── contact.php         # Contact form API
│   ├── promotions.php      # Promotion management API
│   ├── users.php           # User management API
│   └── analytics.php       # Analytics and reporting API
├── database/
│   └── schema.sql          # Database schema with sample data
└── README.md
```

### 3. Configuration
Update the following in `api/config.php`:
- Database connection details
- Email settings for contact form
- File upload paths
- Security keys

### 4. Web Server Setup
- Place files in your web server directory
- Ensure PHP has write permissions for uploads
- Configure virtual host if needed

### 5. Generate Images
1. Open `generate_images.html` in a browser
2. Download all generated placeholder images
3. Place them in the appropriate directories:
   - Product images: `assets/images/products/`
   - Category images: `assets/images/categories/`
   - Brand logos: `assets/images/brands/`

## Default Admin Account
- **Email**: admin@fishinggear.com
- **Password**: password
- **Role**: Admin

## API Endpoints

### Products
- `GET /api/products.php` - Get all products
- `GET /api/products.php?id={id}` - Get specific product
- `POST /api/products.php` - Create product (admin)
- `PUT /api/products.php?id={id}` - Update product (admin)
- `DELETE /api/products.php?id={id}` - Delete product (admin)

### Categories
- `GET /api/categories.php` - Get all categories
- `POST /api/categories.php` - Create category (admin)
- `PUT /api/categories.php?id={id}` - Update category (admin)
- `DELETE /api/categories.php?id={id}` - Delete category (admin)

### Authentication
- `POST /api/auth.php` - Login/Register/Logout
- `GET /api/auth.php?action=check` - Check authentication status

### Orders
- `GET /api/orders.php` - Get user orders
- `POST /api/orders.php` - Create new order
- `PUT /api/orders.php?id={id}` - Update order status (admin)

### Reviews
- `GET /api/reviews.php` - Get product reviews
- `POST /api/reviews.php` - Create review
- `PUT /api/reviews.php?id={id}` - Update review
- `DELETE /api/reviews.php?id={id}` - Delete review

### Analytics
- `GET /api/analytics.php?type=dashboard` - Get dashboard data
- `GET /api/analytics.php?type=sales` - Get sales report
- `GET /api/analytics.php?type=products` - Get product report
- `GET /api/analytics.php?type=customers` - Get customer report

## Features in Detail

### Responsive Design
- Mobile-first approach
- Bootstrap 5.3.6 grid system
- Responsive navigation with collapsible menu
- Touch-friendly interface

### Search & Filtering
- Real-time search with debouncing
- Multi-criteria filtering
- Sort by name, price, rating, date
- Category and brand filtering

### Shopping Cart
- Add/remove products
- Quantity updates
- Persistent storage
- Real-time total calculation

### Admin Panel
- Tabbed interface for different management areas
- Real-time data updates
- Bulk operations support
- Export functionality

### Security
- Prepared statements for SQL injection prevention
- Password hashing with PHP's password_hash()
- Session-based authentication
- Role-based access control
- Input sanitization

## Browser Support
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## License
This project is created for educational and demonstration purposes.

## Support
For questions or issues, please refer to the code comments or create an issue in the project repository.