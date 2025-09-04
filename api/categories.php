<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetCategories($pdo);
            break;
        case 'POST':
            handleCreateCategory($pdo);
            break;
        case 'PUT':
            handleUpdateCategory($pdo);
            break;
        case 'DELETE':
            handleDeleteCategory($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetCategories($pdo) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        $sql = "SELECT c.*, COUNT(p.id) as product_count 
                FROM categories c 
                LEFT JOIN products p ON c.id = p.category_id 
                WHERE c.id = ? 
                GROUP BY c.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            sendError('Category not found', 404);
        }
        
        sendResponse(['data' => $category]);
    } else {
        $sql = "SELECT c.*, COUNT(p.id) as product_count 
                FROM categories c 
                LEFT JOIN products p ON c.id = p.category_id 
                GROUP BY c.id 
                ORDER BY c.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        sendResponse(['data' => $categories]);
    }
}

function handleCreateCategory($pdo) {
    $user = requireAdmin();
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (empty($name)) {
        sendError('Category name is required');
    }
    
    // Check if category already exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        sendError('Category with this name already exists');
    }
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = uploadFile($_FILES['image'], 'categories');
    }
    
    $sql = "INSERT INTO categories (name, description, image) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $image]);
    
    $categoryId = $pdo->lastInsertId();
    
    logActivity('category_created', "Category ID: $categoryId, Name: $name");
    
    sendResponse(['message' => 'Category created successfully', 'category_id' => $categoryId], 201);
}

function handleUpdateCategory($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Category ID is required');
    }
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        sendError('Category not found', 404);
    }
    
    $name = sanitizeInput($_POST['name'] ?? $category['name']);
    $description = sanitizeInput($_POST['description'] ?? $category['description']);
    
    // Check if name is already taken by another category
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $stmt->execute([$name, $id]);
    if ($stmt->fetch()) {
        sendError('Category with this name already exists');
    }
    
    // Handle image upload
    $image = $category['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if ($category['image']) {
            deleteFile($category['image']);
        }
        $image = uploadFile($_FILES['image'], 'categories');
    }
    
    $sql = "UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $image, $id]);
    
    logActivity('category_updated', "Category ID: $id, Name: $name");
    
    sendResponse(['message' => 'Category updated successfully']);
}

function handleDeleteCategory($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Category ID is required');
    }
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        sendError('Category not found', 404);
    }
    
    // Check if category has products
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        sendError('Cannot delete category with existing products');
    }
    
    // Delete associated image
    if ($category['image']) {
        deleteFile($category['image']);
    }
    
    // Delete category
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('category_deleted', "Category ID: $id, Name: " . $category['name']);
    
    sendResponse(['message' => 'Category deleted successfully']);
}
?>