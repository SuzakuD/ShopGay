<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetBrands($pdo);
            break;
        case 'POST':
            handleCreateBrand($pdo);
            break;
        case 'PUT':
            handleUpdateBrand($pdo);
            break;
        case 'DELETE':
            handleDeleteBrand($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetBrands($pdo) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        $sql = "SELECT b.*, COUNT(p.id) as product_count 
                FROM brands b 
                LEFT JOIN products p ON b.id = p.brand_id 
                WHERE b.id = ? 
                GROUP BY b.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $brand = $stmt->fetch();
        
        if (!$brand) {
            sendError('Brand not found', 404);
        }
        
        sendResponse(['data' => $brand]);
    } else {
        $sql = "SELECT b.*, COUNT(p.id) as product_count 
                FROM brands b 
                LEFT JOIN products p ON b.id = p.brand_id 
                GROUP BY b.id 
                ORDER BY b.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $brands = $stmt->fetchAll();
        
        sendResponse(['data' => $brands]);
    }
}

function handleCreateBrand($pdo) {
    $user = requireAdmin();
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (empty($name)) {
        sendError('Brand name is required');
    }
    
    // Check if brand already exists
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        sendError('Brand with this name already exists');
    }
    
    // Handle logo upload
    $logo = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo = uploadFile($_FILES['logo'], 'brands');
    }
    
    $sql = "INSERT INTO brands (name, description, logo) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $logo]);
    
    $brandId = $pdo->lastInsertId();
    
    logActivity('brand_created', "Brand ID: $brandId, Name: $name");
    
    sendResponse(['message' => 'Brand created successfully', 'brand_id' => $brandId], 201);
}

function handleUpdateBrand($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Brand ID is required');
    }
    
    // Check if brand exists
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $brand = $stmt->fetch();
    
    if (!$brand) {
        sendError('Brand not found', 404);
    }
    
    $name = sanitizeInput($_POST['name'] ?? $brand['name']);
    $description = sanitizeInput($_POST['description'] ?? $brand['description']);
    
    // Check if name is already taken by another brand
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE name = ? AND id != ?");
    $stmt->execute([$name, $id]);
    if ($stmt->fetch()) {
        sendError('Brand with this name already exists');
    }
    
    // Handle logo upload
    $logo = $brand['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        if ($brand['logo']) {
            deleteFile($brand['logo']);
        }
        $logo = uploadFile($_FILES['logo'], 'brands');
    }
    
    $sql = "UPDATE brands SET name = ?, description = ?, logo = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $logo, $id]);
    
    logActivity('brand_updated', "Brand ID: $id, Name: $name");
    
    sendResponse(['message' => 'Brand updated successfully']);
}

function handleDeleteBrand($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Brand ID is required');
    }
    
    // Check if brand exists
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $brand = $stmt->fetch();
    
    if (!$brand) {
        sendError('Brand not found', 404);
    }
    
    // Check if brand has products
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendError('Cannot delete brand with existing products');
    }
    
    // Delete associated logo
    if ($brand['logo']) {
        deleteFile($brand['logo']);
    }
    
    // Delete brand
    $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('brand_deleted', "Brand ID: $id, Name: " . $brand['name']);
    
    sendResponse(['message' => 'Brand deleted successfully']);
}
?>