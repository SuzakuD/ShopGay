<?php
require_once 'config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetReviews($pdo);
            break;
        case 'POST':
            handleCreateReview($pdo);
            break;
        case 'PUT':
            handleUpdateReview($pdo);
            break;
        case 'DELETE':
            handleDeleteReview($pdo);
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

function handleGetReviews($pdo) {
    $productId = $_GET['product_id'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $limit = $_GET['limit'] ?? 20;
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "SELECT r.*, u.name as user_name, p.name as product_name 
            FROM product_reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            LEFT JOIN products p ON r.product_id = p.id 
            WHERE 1=1";
    $params = [];
    
    if ($productId) {
        $sql .= " AND r.product_id = ?";
        $params[] = $productId;
    }
    
    if ($userId) {
        $sql .= " AND r.user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
    sendResponse(['data' => $reviews]);
}

function handleCreateReview($pdo) {
    $user = requireAuth();
    
    $productId = intval($_POST['product_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $review = sanitizeInput($_POST['review'] ?? '');
    
    if ($productId <= 0) {
        sendError('Valid product ID is required');
    }
    
    if ($rating < 1 || $rating > 5) {
        sendError('Rating must be between 1 and 5');
    }
    
    if (empty($title) || empty($review)) {
        sendError('Title and review text are required');
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        sendError('Product not found', 404);
    }
    
    // Check if user already reviewed this product
    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user['id'], $productId]);
    if ($stmt->fetch()) {
        sendError('You have already reviewed this product');
    }
    
    // Handle image uploads
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
                $images[] = uploadFile($file, 'reviews');
            }
        }
    }
    
    $imagesString = implode(',', $images);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert review
        $sql = "INSERT INTO product_reviews (product_id, user_id, rating, title, review, images) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$productId, $user['id'], $rating, $title, $review, $imagesString]);
        
        $reviewId = $pdo->lastInsertId();
        
        // Update product rating and review count
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM product_reviews WHERE product_id = ?");
        $stmt->execute([$productId]);
        $stats = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE products SET rating = ?, review_count = ? WHERE id = ?");
        $stmt->execute([round($stats['avg_rating'], 2), $stats['review_count'], $productId]);
        
        // Commit transaction
        $pdo->commit();
        
        logActivity('review_created', "Review ID: $reviewId, Product ID: $productId, Rating: $rating");
        
        sendResponse([
            'message' => 'Review created successfully',
            'review_id' => $reviewId
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleUpdateReview($pdo) {
    $user = requireAuth();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Review ID is required');
    }
    
    // Check if review exists and belongs to user
    $stmt = $pdo->prepare("SELECT * FROM product_reviews WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $review = $stmt->fetch();
    
    if (!$review) {
        sendError('Review not found or access denied', 404);
    }
    
    $rating = intval($_POST['rating'] ?? $review['rating']);
    $title = sanitizeInput($_POST['title'] ?? $review['title']);
    $reviewText = sanitizeInput($_POST['review'] ?? $review['review']);
    
    if ($rating < 1 || $rating > 5) {
        sendError('Rating must be between 1 and 5');
    }
    
    if (empty($title) || empty($reviewText)) {
        sendError('Title and review text are required');
    }
    
    // Handle image uploads
    $images = [];
    if (isset($_FILES['images'])) {
        // Delete old images
        if ($review['images']) {
            $oldImages = explode(',', $review['images']);
            foreach ($oldImages as $image) {
                deleteFile(trim($image));
            }
        }
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'tmp_name' => $tmpName,
                    'name' => $_FILES['images']['name'][$key],
                    'size' => $_FILES['images']['size'][$key],
                    'error' => $_FILES['images']['error'][$key]
                ];
                $images[] = uploadFile($file, 'reviews');
            }
        }
    } else {
        $images = $review['images'] ? explode(',', $review['images']) : [];
    }
    
    $imagesString = implode(',', $images);
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update review
        $sql = "UPDATE product_reviews SET rating = ?, title = ?, review = ?, images = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rating, $title, $reviewText, $imagesString, $id]);
        
        // Update product rating and review count
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM product_reviews WHERE product_id = ?");
        $stmt->execute([$review['product_id']]);
        $stats = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE products SET rating = ?, review_count = ? WHERE id = ?");
        $stmt->execute([round($stats['avg_rating'], 2), $stats['review_count'], $review['product_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        logActivity('review_updated', "Review ID: $id, Product ID: {$review['product_id']}, Rating: $rating");
        
        sendResponse(['message' => 'Review updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleDeleteReview($pdo) {
    $user = requireAuth();
    
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Review ID is required');
    }
    
    // Check if review exists and belongs to user (or user is admin)
    $stmt = $pdo->prepare("SELECT * FROM product_reviews WHERE id = ?");
    $stmt->execute([$id]);
    $review = $stmt->fetch();
    
    if (!$review) {
        sendError('Review not found', 404);
    }
    
    if ($review['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        sendError('Access denied', 403);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete review images
        if ($review['images']) {
            $images = explode(',', $review['images']);
            foreach ($images as $image) {
                deleteFile(trim($image));
            }
        }
        
        // Delete review
        $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = ?");
        $stmt->execute([$id]);
        
        // Update product rating and review count
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM product_reviews WHERE product_id = ?");
        $stmt->execute([$review['product_id']]);
        $stats = $stmt->fetch();
        
        $avgRating = $stats['avg_rating'] ? round($stats['avg_rating'], 2) : 0.00;
        $reviewCount = $stats['review_count'] ?: 0;
        
        $stmt = $pdo->prepare("UPDATE products SET rating = ?, review_count = ? WHERE id = ?");
        $stmt->execute([$avgRating, $reviewCount, $review['product_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        logActivity('review_deleted', "Review ID: $id, Product ID: {$review['product_id']}");
        
        sendResponse(['message' => 'Review deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>