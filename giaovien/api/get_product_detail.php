<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $product_id = intval($_GET['id'] ?? 0);
    $teacher_id = $_SESSION['user_id'];
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Missing product ID']);
        exit();
    }
    
    // Get product with teacher verification
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.fullname as student_name,
            u.email as student_email,
            c.class_name,
            c.code as class_code
        FROM academic_products p
        JOIN users u ON p.user_id = u.id
        JOIN classes c ON p.class_id = c.id
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
    
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>