<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetMessages($pdo);
            break;
        case 'POST':
            handleCreateMessage($pdo);
            break;
        case 'PUT':
            handleUpdateMessage($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetMessages($pdo) {
    $user = requireAdmin(); // Only admins can view messages
    
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    if ($id) {
        $sql = "SELECT * FROM contact_messages WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $message = $stmt->fetch();
        
        if (!$message) {
            sendError('Message not found', 404);
        }
        
        sendResponse(['data' => $message]);
    } else {
        $sql = "SELECT * FROM contact_messages WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
        
        sendResponse(['data' => $messages]);
    }
}

function handleCreateMessage($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitizeInput($input['name'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $subject = sanitizeInput($input['subject'] ?? '');
    $message = sanitizeInput($input['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        sendError('All fields are required');
    }
    
    if (!validateEmail($email)) {
        sendError('Invalid email format');
    }
    
    $sql = "INSERT INTO contact_messages (name, email, subject, message, status) VALUES (?, ?, ?, ?, 'new')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $email, $subject, $message]);
    
    $messageId = $pdo->lastInsertId();
    
    // Send email notification to admin (optional)
    try {
        sendContactEmail($name, $email, $subject, $message);
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Failed to send contact email: " . $e->getMessage());
    }
    
    logActivity('contact_message_created', "Message ID: $messageId, From: $email, Subject: $subject");
    
    sendResponse([
        'message' => 'Message sent successfully',
        'message_id' => $messageId
    ], 201);
}

function handleUpdateMessage($pdo) {
    $user = requireAdmin(); // Only admins can update messages
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Message ID is required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $status = sanitizeInput($input['status'] ?? '');
    
    if (empty($status)) {
        sendError('Status is required');
    }
    
    if (!in_array($status, ['new', 'read', 'replied'])) {
        sendError('Invalid status');
    }
    
    // Check if message exists
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        sendError('Message not found', 404);
    }
    
    $sql = "UPDATE contact_messages SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $id]);
    
    logActivity('contact_message_updated', "Message ID: $id, Status: $status");
    
    sendResponse(['message' => 'Message status updated successfully']);
}

function sendContactEmail($name, $email, $subject, $message) {
    // This is a basic email implementation
    // In production, you would use a proper email service like PHPMailer
    
    $to = ADMIN_EMAIL;
    $emailSubject = "New Contact Form Message: " . $subject;
    $emailBody = "
        <h2>New Contact Form Message</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Message:</strong></p>
        <p>" . nl2br($message) . "</p>
        <hr>
        <p><em>This message was sent from the Fishing Gear Store contact form.</em></p>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
        'Reply-To: ' . $email,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if (mail($to, $emailSubject, $emailBody, implode("\r\n", $headers))) {
        return true;
    } else {
        throw new Exception('Failed to send email');
    }
}
?>