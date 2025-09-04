<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetUsers($pdo);
            break;
        case 'PUT':
            handleUpdateUser($pdo);
            break;
        case 'DELETE':
            handleDeleteUser($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetUsers($pdo) {
    $user = requireAdmin(); // Only admins can view users
    
    $id = $_GET['id'] ?? null;
    $role = $_GET['role'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    if ($id) {
        $sql = "SELECT id, name, email, role, phone, address, city, state, zip_code, created_at, updated_at 
                FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendError('User not found', 404);
        }
        
        sendResponse(['data' => $user]);
    } else {
        $sql = "SELECT id, name, email, role, phone, address, city, state, zip_code, created_at, updated_at 
                FROM users WHERE 1=1";
        $params = [];
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        sendResponse(['data' => $users]);
    }
}

function handleUpdateUser($pdo) {
    $currentUser = requireAuth();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('User ID is required');
    }
    
    // Users can only update their own profile, admins can update any user
    if ($currentUser['id'] !== $id && $currentUser['role'] !== 'admin') {
        sendError('Access denied', 403);
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('User not found', 404);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($input['name'] ?? $user['name']);
    $email = sanitizeInput($input['email'] ?? $user['email']);
    $phone = sanitizeInput($input['phone'] ?? $user['phone']);
    $address = sanitizeInput($input['address'] ?? $user['address']);
    $city = sanitizeInput($input['city'] ?? $user['city']);
    $state = sanitizeInput($input['state'] ?? $user['state']);
    $zipCode = sanitizeInput($input['zip_code'] ?? $user['zip_code']);
    $role = $input['role'] ?? $user['role'];
    
    if (empty($name) || empty($email)) {
        sendError('Name and email are required');
    }
    
    if (!validateEmail($email)) {
        sendError('Invalid email format');
    }
    
    // Check if email is already taken by another user
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            sendError('Email already registered');
        }
    }
    
    // Only admins can change roles
    if ($role !== $user['role'] && $currentUser['role'] !== 'admin') {
        $role = $user['role'];
    }
    
    if (!in_array($role, ['user', 'admin'])) {
        sendError('Invalid role');
    }
    
    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, role = ? WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $phone, $address, $city, $state, $zipCode, $role, $id]);
    
    logActivity('user_updated', "User ID: $id, Name: $name, Role: $role");
    
    sendResponse(['message' => 'User updated successfully']);
}

function handleDeleteUser($pdo) {
    $user = requireAdmin(); // Only admins can delete users
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('User ID is required');
    }
    
    // Prevent admin from deleting themselves
    if ($user['id'] === $id) {
        sendError('Cannot delete your own account');
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $userToDelete = $stmt->fetch();
    
    if (!$userToDelete) {
        sendError('User not found', 404);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete user's cart items
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // Delete user's reviews
        $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // Delete user's wishlist items
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // Update orders to remove user reference (set user_id to NULL)
        $stmt = $pdo->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$id]);
        
        // Delete user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $pdo->commit();
        
        logActivity('user_deleted', "User ID: $id, Name: " . $userToDelete['name']);
        
        sendResponse(['message' => 'User deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>