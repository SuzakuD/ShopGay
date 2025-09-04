<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetPromotions($pdo);
            break;
        case 'POST':
            handleCreatePromotion($pdo);
            break;
        case 'PUT':
            handleUpdatePromotion($pdo);
            break;
        case 'DELETE':
            handleDeletePromotion($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetPromotions($pdo) {
    $id = $_GET['id'] ?? null;
    $code = $_GET['code'] ?? null;
    $active = $_GET['active'] ?? null;
    
    if ($id) {
        $sql = "SELECT p.*, c.name as category_name, pr.name as product_name 
                FROM promotions p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN products pr ON p.product_id = pr.id 
                WHERE p.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $promotion = $stmt->fetch();
        
        if (!$promotion) {
            sendError('Promotion not found', 404);
        }
        
        sendResponse(['data' => $promotion]);
    } elseif ($code) {
        $sql = "SELECT p.*, c.name as category_name, pr.name as product_name 
                FROM promotions p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN products pr ON p.product_id = pr.id 
                WHERE p.code = ? AND p.is_active = 1 
                AND (p.start_date IS NULL OR p.start_date <= NOW()) 
                AND (p.end_date IS NULL OR p.end_date >= NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code]);
        $promotion = $stmt->fetch();
        
        if (!$promotion) {
            sendError('Invalid or expired promotion code', 404);
        }
        
        sendResponse(['data' => $promotion]);
    } else {
        $sql = "SELECT p.*, c.name as category_name, pr.name as product_name 
                FROM promotions p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN products pr ON p.product_id = pr.id 
                WHERE 1=1";
        $params = [];
        
        if ($active !== null) {
            $sql .= " AND p.is_active = ?";
            $params[] = $active ? 1 : 0;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $promotions = $stmt->fetchAll();
        
        sendResponse(['data' => $promotions]);
    }
}

function handleCreatePromotion($pdo) {
    $user = requireAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($input['name'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $type = sanitizeInput($input['type'] ?? '');
    $value = floatval($input['value'] ?? 0);
    $minOrderAmount = floatval($input['min_order_amount'] ?? 0);
    $maxDiscountAmount = floatval($input['max_discount_amount'] ?? null);
    $code = sanitizeInput($input['code'] ?? '');
    $categoryId = intval($input['category_id'] ?? null);
    $productId = intval($input['product_id'] ?? null);
    $usageLimit = intval($input['usage_limit'] ?? null);
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $isActive = $input['is_active'] ?? true;
    
    if (empty($name) || empty($type) || $value <= 0) {
        sendError('Name, type, and value are required');
    }
    
    if (!in_array($type, ['percentage', 'fixed', 'free_shipping'])) {
        sendError('Invalid promotion type');
    }
    
    if ($type === 'percentage' && $value > 100) {
        sendError('Percentage value cannot exceed 100');
    }
    
    // Check if code already exists
    if (!empty($code)) {
        $stmt = $pdo->prepare("SELECT id FROM promotions WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            sendError('Promotion code already exists');
        }
    }
    
    // Validate category and product IDs
    if ($categoryId) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            sendError('Invalid category ID');
        }
    }
    
    if ($productId) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            sendError('Invalid product ID');
        }
    }
    
    $sql = "INSERT INTO promotions (name, description, type, value, min_order_amount, max_discount_amount, code, category_id, product_id, usage_limit, start_date, end_date, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $name, $description, $type, $value, $minOrderAmount, $maxDiscountAmount, 
        $code, $categoryId, $productId, $usageLimit, $startDate, $endDate, $isActive
    ]);
    
    $promotionId = $pdo->lastInsertId();
    
    logActivity('promotion_created', "Promotion ID: $promotionId, Name: $name, Code: $code");
    
    sendResponse([
        'message' => 'Promotion created successfully',
        'promotion_id' => $promotionId
    ], 201);
}

function handleUpdatePromotion($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Promotion ID is required');
    }
    
    // Check if promotion exists
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([$id]);
    $promotion = $stmt->fetch();
    
    if (!$promotion) {
        sendError('Promotion not found', 404);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($input['name'] ?? $promotion['name']);
    $description = sanitizeInput($input['description'] ?? $promotion['description']);
    $type = sanitizeInput($input['type'] ?? $promotion['type']);
    $value = floatval($input['value'] ?? $promotion['value']);
    $minOrderAmount = floatval($input['min_order_amount'] ?? $promotion['min_order_amount']);
    $maxDiscountAmount = floatval($input['max_discount_amount'] ?? $promotion['max_discount_amount']);
    $code = sanitizeInput($input['code'] ?? $promotion['code']);
    $categoryId = intval($input['category_id'] ?? $promotion['category_id']);
    $productId = intval($input['product_id'] ?? $promotion['product_id']);
    $usageLimit = intval($input['usage_limit'] ?? $promotion['usage_limit']);
    $startDate = $input['start_date'] ?? $promotion['start_date'];
    $endDate = $input['end_date'] ?? $promotion['end_date'];
    $isActive = $input['is_active'] ?? $promotion['is_active'];
    
    if (!in_array($type, ['percentage', 'fixed', 'free_shipping'])) {
        sendError('Invalid promotion type');
    }
    
    if ($type === 'percentage' && $value > 100) {
        sendError('Percentage value cannot exceed 100');
    }
    
    // Check if code is already taken by another promotion
    if (!empty($code) && $code !== $promotion['code']) {
        $stmt = $pdo->prepare("SELECT id FROM promotions WHERE code = ? AND id != ?");
        $stmt->execute([$code, $id]);
        if ($stmt->fetch()) {
            sendError('Promotion code already exists');
        }
    }
    
    $sql = "UPDATE promotions SET name = ?, description = ?, type = ?, value = ?, min_order_amount = ?, max_discount_amount = ?, code = ?, category_id = ?, product_id = ?, usage_limit = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $name, $description, $type, $value, $minOrderAmount, $maxDiscountAmount, 
        $code, $categoryId, $productId, $usageLimit, $startDate, $endDate, $isActive, $id
    ]);
    
    logActivity('promotion_updated', "Promotion ID: $id, Name: $name, Code: $code");
    
    sendResponse(['message' => 'Promotion updated successfully']);
}

function handleDeletePromotion($pdo) {
    $user = requireAdmin();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Promotion ID is required');
    }
    
    // Check if promotion exists
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
    $stmt->execute([$id]);
    $promotion = $stmt->fetch();
    
    if (!$promotion) {
        sendError('Promotion not found', 404);
    }
    
    // Delete promotion
    $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = ?");
    $stmt->execute([$id]);
    
    logActivity('promotion_deleted', "Promotion ID: $id, Name: " . $promotion['name']);
    
    sendResponse(['message' => 'Promotion deleted successfully']);
}
?>