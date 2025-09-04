<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetOrders($pdo);
            break;
        case 'POST':
            handleCreateOrder($pdo);
            break;
        case 'PUT':
            handleUpdateOrder($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetOrders($pdo) {
    $user = requireAuth();
    $id = $_GET['id'] ?? null;
    $status = $_GET['status'] ?? null;
    
    if ($id) {
        // Get specific order
        $sql = "SELECT o.*, u.name as user_name, u.email as user_email 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
        $params = [$id];
        
        // If not admin, only allow users to see their own orders
        if ($user['role'] !== 'admin') {
            $sql .= " AND o.user_id = ?";
            $params[] = $user['id'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendError('Order not found', 404);
        }
        
        // Get order items
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$id]);
        $order['items'] = $stmt->fetchAll();
        
        sendResponse(['data' => $order]);
    } else {
        // Get orders list
        $sql = "SELECT o.*, u.name as user_name, u.email as user_email 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE 1=1";
        $params = [];
        
        // If not admin, only show user's own orders
        if ($user['role'] !== 'admin') {
            $sql .= " AND o.user_id = ?";
            $params[] = $user['id'];
        }
        
        if ($status) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        sendResponse(['data' => $orders]);
    }
}

function handleCreateOrder($pdo) {
    $user = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $shippingName = sanitizeInput($input['shipping_name'] ?? '');
    $shippingAddress = sanitizeInput($input['shipping_address'] ?? '');
    $shippingCity = sanitizeInput($input['shipping_city'] ?? '');
    $shippingState = sanitizeInput($input['shipping_state'] ?? '');
    $shippingZip = sanitizeInput($input['shipping_zip'] ?? '');
    $cardNumber = sanitizeInput($input['card_number'] ?? '');
    $expiryDate = sanitizeInput($input['expiry_date'] ?? '');
    $cvv = sanitizeInput($input['cvv'] ?? '');
    $couponCode = sanitizeInput($input['coupon_code'] ?? '');
    $items = $input['items'] ?? [];
    
    if (empty($shippingName) || empty($shippingAddress) || empty($shippingCity) || 
        empty($shippingState) || empty($shippingZip) || empty($items)) {
        sendError('Shipping information and items are required');
    }
    
    if (empty($cardNumber) || empty($expiryDate) || empty($cvv)) {
        sendError('Payment information is required');
    }
    
    // Validate items and calculate totals
    $subtotal = 0;
    $validItems = [];
    
    foreach ($items as $item) {
        $productId = intval($item['id']);
        $quantity = intval($item['quantity']);
        
        if ($productId <= 0 || $quantity <= 0) {
            continue;
        }
        
        // Get product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            sendError("Product with ID $productId not found");
        }
        
        if ($product['stock'] < $quantity) {
            sendError("Insufficient stock for product: {$product['name']}");
        }
        
        $itemTotal = $product['price'] * $quantity;
        $subtotal += $itemTotal;
        
        $validItems[] = [
            'product_id' => $productId,
            'product_name' => $product['name'],
            'product_price' => $product['price'],
            'quantity' => $quantity,
            'total_price' => $itemTotal
        ];
    }
    
    if (empty($validItems)) {
        sendError('No valid items in cart');
    }
    
    // Calculate shipping and tax
    $shippingAmount = 10.00;
    $taxAmount = $subtotal * 0.08; // 8% tax
    $discountAmount = 0.00;
    
    // Apply coupon if provided
    if (!empty($couponCode)) {
        $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
        $stmt->execute([$couponCode]);
        $promotion = $stmt->fetch();
        
        if ($promotion) {
            if ($promotion['type'] === 'percentage') {
                $discountAmount = $subtotal * ($promotion['value'] / 100);
                if ($promotion['max_discount_amount']) {
                    $discountAmount = min($discountAmount, $promotion['max_discount_amount']);
                }
            } elseif ($promotion['type'] === 'fixed') {
                $discountAmount = $promotion['value'];
            } elseif ($promotion['type'] === 'free_shipping') {
                $shippingAmount = 0.00;
            }
            
            // Update promotion usage
            $stmt = $pdo->prepare("UPDATE promotions SET used_count = used_count + 1 WHERE id = ?");
            $stmt->execute([$promotion['id']]);
        }
    }
    
    $totalAmount = $subtotal + $shippingAmount + $taxAmount - $discountAmount;
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Create order
        $orderNumber = generateOrderNumber();
        $sql = "INSERT INTO orders (order_number, user_id, shipping_name, shipping_address, shipping_city, shipping_state, shipping_zip, total_amount, tax_amount, shipping_amount, discount_amount, status, payment_status, payment_method) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paid', 'credit_card')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $orderNumber, $user['id'], $shippingName, $shippingAddress, $shippingCity, 
            $shippingState, $shippingZip, $totalAmount, $taxAmount, $shippingAmount, $discountAmount
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Create order items and update product stock
        foreach ($validItems as $item) {
            // Insert order item
            $sql = "INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total_price) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $orderId, $item['product_id'], $item['product_name'], 
                $item['product_price'], $item['quantity'], $item['total_price']
            ]);
            
            // Update product stock
            $sql = "UPDATE products SET stock = stock - ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        logActivity('order_created', "Order ID: $orderId, Order Number: $orderNumber, Total: $totalAmount");
        
        sendResponse([
            'message' => 'Order created successfully',
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $totalAmount
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleUpdateOrder($pdo) {
    $user = requireAdmin(); // Only admins can update orders
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Order ID is required');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $status = sanitizeInput($input['status'] ?? '');
    $paymentStatus = sanitizeInput($input['payment_status'] ?? '');
    $notes = sanitizeInput($input['notes'] ?? '');
    
    if (empty($status) && empty($paymentStatus)) {
        sendError('Status or payment status is required');
    }
    
    // Check if order exists
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        sendError('Order not found', 404);
    }
    
    $sql = "UPDATE orders SET ";
    $params = [];
    $updates = [];
    
    if (!empty($status)) {
        $updates[] = "status = ?";
        $params[] = $status;
    }
    
    if (!empty($paymentStatus)) {
        $updates[] = "payment_status = ?";
        $params[] = $paymentStatus;
    }
    
    if (!empty($notes)) {
        $updates[] = "notes = ?";
        $params[] = $notes;
    }
    
    $sql .= implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    logActivity('order_updated', "Order ID: $id, Status: $status, Payment Status: $paymentStatus");
    
    sendResponse(['message' => 'Order updated successfully']);
}
?>