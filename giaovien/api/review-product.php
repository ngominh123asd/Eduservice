<?php
// filepath: d:\Eduservice\giaovien\api\review-product.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $product_id = intval($input['product_id'] ?? 0);
    $score = floatval($input['score'] ?? 0);
    $feedback = trim($input['feedback'] ?? '');
    $status = $input['status'] ?? 'reviewed';
    $teacher_id = $_SESSION['user_id'];
    
    if (!$product_id || !$feedback) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    // Validate score
    if ($score < 0 || $score > 10) {
        echo json_encode(['success' => false, 'message' => 'Invalid score']);
        exit();
    }
    
    try {
        // Verify teacher can access this product
        $stmt = $pdo->prepare("
            SELECT p.id, p.user_id, p.title, u.fullname as student_name, u.email as student_email, c.class_name
            FROM academic_products p
            JOIN classes c ON p.class_id = c.id
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ? 
            AND c.teacher_id = ?
            AND p.share_token IS NOT NULL
            AND p.share_token != ''
        ");
        $stmt->execute([$product_id, $teacher_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found or not shared']);
            exit();
        }
        
        // Update product with review
        $stmt = $pdo->prepare("
            UPDATE academic_products 
            SET score = ?, 
                feedback = ?, 
                status = ?, 
                reviewed_at = datetime('now', 'localtime'),
                updated_at = datetime('now', 'localtime')
            WHERE id = ?
        ");
        $stmt->execute([$score, $feedback, $status, $product_id]);
        
        // Create notification for student
        try {
            $notification_message = $status === 'reviewed' 
                ? "Giáo viên đã chấm điểm sản phẩm '{$product['title']}': {$score}/10"
                : "Giáo viên yêu cầu bạn sửa lại sản phẩm '{$product['title']}'";
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, message, link, created_at)
                VALUES (?, 'product_reviewed', ?, ?, datetime('now', 'localtime'))
            ");
            $stmt->execute([
                $product['user_id'],
                $notification_message,
                "/saudn/academic-products.php?id={$product_id}"
            ]);
        } catch (PDOException $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Đã lưu đánh giá thành công',
            'product' => [
                'id' => $product_id,
                'score' => $score,
                'status' => $status,
                'student_name' => $product['student_name']
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>