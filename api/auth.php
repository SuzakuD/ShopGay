<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        sendError('Method not allowed', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'login':
            handleLogin($pdo, $input);
            break;
        case 'register':
            handleRegister($pdo, $input);
            break;
        case 'logout':
            handleLogout();
            break;
        case 'check':
            handleCheckAuth();
            break;
        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleLogin($pdo, $input) {
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendError('Email and password are required');
    }
    
    if (!validateEmail($email)) {
        sendError('Invalid email format');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        sendError('Invalid email or password', 401);
    }
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    
    // Remove password from response
    unset($user['password']);
    
    logActivity('user_login', "User ID: {$user['id']}, Email: $email");
    
    sendResponse([
        'message' => 'Login successful',
        'user' => $user
    ]);
}

function handleRegister($pdo, $input) {
    $name = sanitizeInput($input['name'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        sendError('Name, email, and password are required');
    }
    
    if (!validateEmail($email)) {
        sendError('Invalid email format');
    }
    
    if (strlen($password) < 6) {
        sendError('Password must be at least 6 characters long');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendError('Email already registered');
    }
    
    $hashedPassword = hashPassword($password);
    
    $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $hashedPassword]);
    
    $userId = $pdo->lastInsertId();
    
    logActivity('user_registered', "User ID: $userId, Email: $email");
    
    sendResponse([
        'message' => 'Registration successful',
        'user_id' => $userId
    ], 201);
}

function handleLogout() {
    if (isset($_SESSION['user_id'])) {
        logActivity('user_logout', "User ID: {$_SESSION['user_id']}");
    }
    
    session_destroy();
    
    sendResponse(['message' => 'Logout successful']);
}

function handleCheckAuth() {
    $user = getCurrentUser();
    
    if ($user) {
        unset($user['password']);
        sendResponse(['authenticated' => true, 'user' => $user]);
    } else {
        sendResponse(['authenticated' => false]);
    }
}
?>