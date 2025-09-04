<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetProducts($pdo);
            break;
        case 'POST':
            handleCreateProduct($pdo);
            break;
        case 'PUT':
            handleUpdateProduct($pdo);
            break;
        case 'DELETE':
            handleDeleteProduct($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetProducts($pdo) {
    $id = $_GET['id'] ?? null;
    $categoryId = $_GET['category_id'] ?? null;
    $brandId = $_GET['brand_id'] ?? null;
    $search = $_GET['search'] ?? null;
    $minPrice = $_GET['min_price'] ?? null;
    $maxPrice = $_GET['max_price'] ?? null;
    $inStock = $_GET['in_stock'] ?? null;
    $sort = $_GET['sort'] ?? 'name';
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "SELECT p.*, c.name as category_name, b.name as brand_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN brands b ON p.brand_id = b.id 
            WHERE 1=1";
    $params = [];
    
    if ($id) {
        $sql .= " AND p.id = ?";
        $params[] = $id;
    }
    
    if ($categoryId) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }
    
    if ($brandId) {
        $sql .= " AND p.brand_id = ?";
        $params[] = $brandId;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR b.name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($minPrice !== null) {
        $sql .= " AND p.price >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice !== null) {
        $sql .= " AND p.price <= ?";
        $params[] = $maxPrice;
    }
    
    if ($inStock) {
        $sql .= " AND p.stock > 0";
    }
    
    // Add sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY p.created_at DESC";
            break;
        case 'rating':
            $sql .= " ORDER BY p.rating DESC";
            break;
        default:
            $sql .= " ORDER BY p.name ASC";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    if ($id && count($products) === 1) {
        sendResponse(['data' => $products[0]]);
    } else {
        sendResponse(['data' => $products]);
    }
}

function handleCreateProduct($pdo) {
    $user = requireAdmin();
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $brandId = intval($_POST['brand_id'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $dimensions = sanitizeInput($_POST['dimensions'] ?? '');
    $material = sanitizeInput($_POST['material'] ?? '');
    $color = sanitizeInput($_POST['color'] ?? '');
    
    if (empty($name) || $price <= 0) {
        sendError('Name and price are required');
    }
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = uploadFile($_FILES['image'], 'products');
    }
    
    // Handle multiple images
    $images = [];
    if (isset($_FILES['images'])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'tmp_name' => $tmpName,
                    'name' => $_FILES['images']['name'][$key],
                    'size' => $_FILES['images']['size'][$key],
                    'error' => $_FILES['images']['error'][$key]
                ];
                $images[] = uploadFile($file, 'products');
            }
        }
    }
    
    $imagesString = implode(',', $images);
    
    $sql = "INSERT INTO products (name, description, price, stock, category_id, brand_id, image, images, weight, dimensions, material, color) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $name, $description, $price, $stock, $categoryId, $brandId, 
        $image, $imagesString, $weight, $dimensions, $material, $color
    ]);
    
    $productId = $pdo->lastInsertId();
    
    logActivity('product_created', "Product ID: $productId, Name: $name");
    
    sendResponse(['message' => 'Product created successfully', 'product_id' => $productId], 201);
}

function handleUpdateProduct($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Product ID is required');
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    $name = sanitizeInput($_POST['name'] ?? $product['name']);
    $description = sanitizeInput($_POST['description'] ?? $product['description']);
    $price = floatval($_POST['price'] ?? $product['price']);
    $stock = intval($_POST['stock'] ?? $product['stock']);
    $categoryId = intval($_POST['category_id'] ?? $product['category_id']);
    $brandId = intval($_POST['brand_id'] ?? $product['brand_id']);
    $weight = floatval($_POST['weight'] ?? $product['weight']);
    $dimensions = sanitizeInput($_POST['dimensions'] ?? $product['dimensions']);
    $material = sanitizeInput($_POST['material'] ?? $product['material']);
    $color = sanitizeInput($_POST['color'] ?? $product['color']);
    
    // Handle image upload
    $image = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if ($product['image']) {
            deleteFile($product['image']);
        }
        $image = uploadFile($_FILES['image'], 'products');
    }
    
    $sql = "UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, brand_id = ?, image = ?, weight = ?, dimensions = ?, material = ?, color = ? WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $name, $description, $price, $stock, $categoryId, $brandId, 
        $image, $weight, $dimensions, $material, $color, $id
    ]);
    
    logActivity('product_updated', "Product ID: $id, Name: $name");
    
    sendResponse(['message' => 'Product updated successfully']);
}

function handleDeleteProduct($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Product ID is required');
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    // Delete associated files
    if ($product['image']) {
        deleteFile($product['image']);
    }
    
    if ($product['images']) {
        $images = explode(',', $product['images']);
        foreach ($images as $image) {
            deleteFile(trim($image));
        }
    }
    
    // Delete product
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('product_deleted', "Product ID: $id, Name: " . $product['name']);
    
    sendResponse(['message' => 'Product deleted successfully']);
}
?>