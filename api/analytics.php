<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $user = requireAdmin(); // Only admins can access analytics
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
    }
    
    $type = $_GET['type'] ?? 'dashboard';
    
    switch ($type) {
        case 'dashboard':
            handleDashboard($pdo);
            break;
        case 'sales':
            handleSalesReport($pdo);
            break;
        case 'products':
            handleProductReport($pdo);
            break;
        case 'customers':
            handleCustomerReport($pdo);
            break;
        case 'orders':
            handleOrderReport($pdo);
            break;
        default:
            sendError('Invalid report type', 400);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleDashboard($pdo) {
    // Get basic statistics
    $stats = [];
    
    // Total sales
    $stmt = $pdo->prepare("SELECT SUM(total_amount) as total_sales FROM orders WHERE payment_status = 'paid'");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_sales'] = $result['total_sales'] ?: 0;
    
    // Total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_orders'] = $result['total_orders'];
    
    // Total products
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_products FROM products");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_products'] = $result['total_products'];
    
    // Total customers
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_customers FROM users WHERE role = 'user'");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_customers'] = $result['total_customers'];
    
    // Recent orders
    $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name 
                          FROM orders o 
                          LEFT JOIN users u ON o.user_id = u.id 
                          ORDER BY o.created_at DESC 
                          LIMIT 10");
    $stmt->execute();
    $stats['recent_orders'] = $stmt->fetchAll();
    
    // Top selling products
    $stmt = $pdo->prepare("SELECT p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as total_revenue
                          FROM order_items oi
                          JOIN products p ON oi.product_id = p.id
                          JOIN orders o ON oi.order_id = o.id
                          WHERE o.payment_status = 'paid'
                          GROUP BY p.id
                          ORDER BY total_sold DESC
                          LIMIT 10");
    $stmt->execute();
    $stats['top_products'] = $stmt->fetchAll();
    
    // Sales by month (last 12 months)
    $stmt = $pdo->prepare("SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          COUNT(*) as order_count,
                          SUM(total_amount) as total_sales
                          FROM orders 
                          WHERE payment_status = 'paid' 
                          AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                          ORDER BY month");
    $stmt->execute();
    $stats['monthly_sales'] = $stmt->fetchAll();
    
    // Low stock products
    $stmt = $pdo->prepare("SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 10");
    $stmt->execute();
    $stats['low_stock'] = $stmt->fetchAll();
    
    sendResponse(['data' => $stats]);
}

function handleSalesReport($pdo) {
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
    $format = $_GET['format'] ?? 'json'; // json, csv, pdf
    
    $stmt = $pdo->prepare("SELECT 
                          DATE(created_at) as date,
                          COUNT(*) as order_count,
                          SUM(total_amount) as total_sales,
                          AVG(total_amount) as avg_order_value
                          FROM orders 
                          WHERE payment_status = 'paid' 
                          AND DATE(created_at) BETWEEN ? AND ?
                          GROUP BY DATE(created_at)
                          ORDER BY date");
    $stmt->execute([$startDate, $endDate]);
    $salesData = $stmt->fetchAll();
    
    if ($format === 'csv') {
        generateCSV($salesData, 'sales_report.csv');
    } elseif ($format === 'pdf') {
        generatePDF($salesData, 'Sales Report', 'sales_report.pdf');
    } else {
        sendResponse(['data' => $salesData]);
    }
}

function handleProductReport($pdo) {
    $categoryId = $_GET['category_id'] ?? null;
    $format = $_GET['format'] ?? 'json';
    
    $sql = "SELECT 
            p.id, p.name, p.price, p.stock, p.rating, p.review_count,
            c.name as category_name, b.name as brand_name,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.total_price), 0) as total_revenue
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'paid'
            WHERE 1=1";
    $params = [];
    
    if ($categoryId) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }
    
    $sql .= " GROUP BY p.id ORDER BY total_sold DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productData = $stmt->fetchAll();
    
    if ($format === 'csv') {
        generateCSV($productData, 'product_report.csv');
    } elseif ($format === 'pdf') {
        generatePDF($productData, 'Product Report', 'product_report.pdf');
    } else {
        sendResponse(['data' => $productData]);
    }
}

function handleCustomerReport($pdo) {
    $format = $_GET['format'] ?? 'json';
    
    $stmt = $pdo->prepare("SELECT 
                          u.id, u.name, u.email, u.created_at,
                          COUNT(o.id) as total_orders,
                          COALESCE(SUM(o.total_amount), 0) as total_spent,
                          COALESCE(AVG(o.total_amount), 0) as avg_order_value
                          FROM users u
                          LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'paid'
                          WHERE u.role = 'user'
                          GROUP BY u.id
                          ORDER BY total_spent DESC");
    $stmt->execute();
    $customerData = $stmt->fetchAll();
    
    if ($format === 'csv') {
        generateCSV($customerData, 'customer_report.csv');
    } elseif ($format === 'pdf') {
        generatePDF($customerData, 'Customer Report', 'customer_report.pdf');
    } else {
        sendResponse(['data' => $customerData]);
    }
}

function handleOrderReport($pdo) {
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    $status = $_GET['status'] ?? null;
    $format = $_GET['format'] ?? 'json';
    
    $sql = "SELECT 
            o.*, u.name as customer_name, u.email as customer_email,
            COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    
    if ($status) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }
    
    $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orderData = $stmt->fetchAll();
    
    if ($format === 'csv') {
        generateCSV($orderData, 'order_report.csv');
    } elseif ($format === 'pdf') {
        generatePDF($orderData, 'Order Report', 'order_report.pdf');
    } else {
        sendResponse(['data' => $orderData]);
    }
}

function generateCSV($data, $filename) {
    if (empty($data)) {
        sendError('No data to export');
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, array_keys($data[0]));
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

function generatePDF($data, $title, $filename) {
    // This is a basic PDF generation
    // In production, you would use a library like TCPDF or FPDF
    
    $html = "<h1>$title</h1>";
    $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    
    if (!empty($data)) {
        // Headers
        $html .= "<tr>";
        foreach (array_keys($data[0]) as $header) {
            $html .= "<th style='padding: 8px; background-color: #f2f2f2;'>" . ucwords(str_replace('_', ' ', $header)) . "</th>";
        }
        $html .= "</tr>";
        
        // Data rows
        foreach ($data as $row) {
            $html .= "<tr>";
            foreach ($row as $cell) {
                $html .= "<td style='padding: 8px;'>" . htmlspecialchars($cell) . "</td>";
            }
            $html .= "</tr>";
        }
    }
    
    $html .= "</table>";
    
    // For now, just return the HTML
    // In production, you would convert this to PDF
    sendResponse(['html' => $html, 'title' => $title]);
}
?>