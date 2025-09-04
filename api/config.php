<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'fishing_gear_store');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'Fishing Gear Store');
define('APP_URL', 'http://localhost');
define('ADMIN_EMAIL', 'admin@fishinggear.com');

// Security settings
define('JWT_SECRET', 'your-secret-key-here');
define('PASSWORD_SALT', 'your-password-salt-here');

// File upload settings
define('UPLOAD_PATH', '../assets/images/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Email settings (for contact form)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@fishinggear.com');
define('SMTP_FROM_NAME', 'Fishing Gear Store');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection class
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $pdo;

    public function getConnection() {
        $this->pdo = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        
        return $this->pdo;
    }
}

// Utility functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateOrderNumber() {
    return 'FG' . date('Ymd') . rand(1000, 9999);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function sendError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => true, 'message' => $message]);
    exit();
}

function uploadFile($file, $directory = 'products') {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $fileSize = $file['size'];
    if ($fileSize > MAX_FILE_SIZE) {
        throw new Exception('File size too large');
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
        throw new Exception('Invalid file type');
    }
    
    $fileName = uniqid() . '.' . $fileExtension;
    $uploadPath = UPLOAD_PATH . $directory . '/' . $fileName;
    
    if (!is_dir(UPLOAD_PATH . $directory)) {
        mkdir(UPLOAD_PATH . $directory, 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    return 'assets/images/' . $directory . '/' . $fileName;
}

function deleteFile($filePath) {
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    return $stmt->fetch();
}

function requireAuth() {
    $user = getCurrentUser();
    if (!$user) {
        sendError('Authentication required', 401);
    }
    return $user;
}

function requireAdmin() {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        sendError('Admin access required', 403);
    }
    return $user;
}

function logActivity($action, $details = '') {
    $user = getCurrentUser();
    $userId = $user ? $user['id'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // You can implement logging to a file or database here
    error_log("Activity: $action, User: $userId, IP: $ip, Details: $details");
}

// Start session
session_start();
?>