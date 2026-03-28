<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $product_id = intval($input['product_id'] ?? 0);
    $email = $input['email'] ?? '';
    $permission = $input['permission'] ?? 'view';
    $message = $input['message'] ?? '';
    
    if (!$product_id || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    try {
        // Verify product ownership
        $stmt = $pdo->prepare("SELECT id, title FROM academic_products WHERE id = ? AND user_id = ?");
        $stmt->execute([$product_id, $user_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
            exit();
        }
        
        // Find user by email
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $shared_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shared_user) {
            echo json_encode(['success' => false, 'message' => 'User with this email not found']);
            exit();
        }
        
        // Check if already shared
        $stmt = $pdo->prepare("
            SELECT id FROM product_shares 
            WHERE product_id = ? AND shared_with_user_id = ?
        ");
        $stmt->execute([$product_id, $shared_user['id']]);
        
        if ($stmt->fetch()) {
            // Update existing share
            $stmt = $pdo->prepare("
                UPDATE product_shares 
                SET permission = ?, message = ?, updated_at = NOW()
                WHERE product_id = ? AND shared_with_user_id = ?
            ");
            $stmt->execute([$permission, $message, $product_id, $shared_user['id']]);
        } else {
            // Create new share
            $stmt = $pdo->prepare("
                INSERT INTO product_shares (product_id, owner_user_id, shared_with_user_id, permission, message, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$product_id, $user_id, $shared_user['id'], $permission, $message]);
        }
        
        // Create notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, content, link, created_at)
            VALUES (?, 'product_share', ?, ?, ?, NOW())
        ");
        $notif_content = $_SESSION['full_name'] . " đã chia sẻ sản phẩm \"" . $product['title'] . "\" với bạn";
        $notif_link = "/saudn/view-product.php?id=" . $product_id;
        $stmt->execute([$shared_user['id'], 'Sản phẩm được chia sẻ', $notif_content, $notif_link]);
        
        // Send email notification (optional)
        // sendShareNotificationEmail($email, $product['title'], $message);
        
        echo json_encode(['success' => true, 'message' => 'Product shared successfully']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
