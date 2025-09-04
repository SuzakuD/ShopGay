<?php
// Setup script for Fishing Gear Store

echo "Fishing Gear Store - Setup Script\n";
echo "================================\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die("Error: PHP 7.4 or higher is required. Current version: " . PHP_VERSION . "\n");
}

echo "✓ PHP version: " . PHP_VERSION . "\n";

// Check required extensions
$required_extensions = ['pdo', 'pdo_mysql', 'gd', 'json'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "✗ Missing required extensions: " . implode(', ', $missing_extensions) . "\n";
    echo "Please install these extensions and try again.\n";
    exit(1);
}

echo "✓ All required extensions are loaded\n";

// Check if config file exists
if (!file_exists('api/config.php')) {
    echo "✗ Config file not found. Please ensure api/config.php exists.\n";
    exit(1);
}

echo "✓ Config file found\n";

// Test database connection
try {
    require_once 'api/config.php';
    $db = new Database();
    $pdo = $db->getConnection();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in api/config.php\n";
    exit(1);
}

// Check if database tables exist
$tables = ['users', 'products', 'categories', 'brands', 'orders', 'order_items', 'product_reviews', 'promotions', 'contact_messages'];
$missing_tables = [];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        $missing_tables[] = $table;
    }
}

if (!empty($missing_tables)) {
    echo "✗ Missing database tables: " . implode(', ', $missing_tables) . "\n";
    echo "Please import the database schema from database/schema.sql\n";
    exit(1);
}

echo "✓ All database tables exist\n";

// Check if directories are writable
$directories = ['assets/images/products', 'assets/images/categories', 'assets/images/brands', 'assets/images/reviews'];
$unwritable_dirs = [];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $unwritable_dirs[] = $dir;
        }
    } elseif (!is_writable($dir)) {
        $unwritable_dirs[] = $dir;
    }
}

if (!empty($unwritable_dirs)) {
    echo "✗ Unwritable directories: " . implode(', ', $unwritable_dirs) . "\n";
    echo "Please set proper permissions for these directories (755 or 777)\n";
    exit(1);
}

echo "✓ All required directories are writable\n";

// Check if placeholder images exist
$placeholder_image = 'assets/images/placeholder.jpg';
if (!file_exists($placeholder_image)) {
    echo "⚠ Warning: Placeholder image not found at $placeholder_image\n";
    echo "You may need to generate placeholder images using generate_images.html\n";
} else {
    echo "✓ Placeholder image found\n";
}

// Check admin user
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "⚠ Warning: No admin users found in database\n";
        echo "Please create an admin user or check the database schema import\n";
    } else {
        echo "✓ Admin user(s) found\n";
    }
} catch (Exception $e) {
    echo "⚠ Warning: Could not check admin users: " . $e->getMessage() . "\n";
}

// Check products
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "⚠ Warning: No products found in database\n";
        echo "Please check the database schema import for sample data\n";
    } else {
        echo "✓ Products found: " . $result['count'] . "\n";
    }
} catch (Exception $e) {
    echo "⚠ Warning: Could not check products: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Setup completed successfully!\n";
echo "You can now access the Fishing Gear Store at your web server URL.\n";
echo "\n";
echo "Default admin credentials:\n";
echo "Email: admin@fishinggear.com\n";
echo "Password: password\n";
echo "\n";
echo "Next steps:\n";
echo "1. Open generate_images.html in your browser to create placeholder images\n";
echo "2. Configure email settings in api/config.php for contact form\n";
echo "3. Customize the store appearance and content\n";
echo "4. Test all functionality before going live\n";
?>