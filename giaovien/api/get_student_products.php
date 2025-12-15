<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $teacher_id = $_SESSION['user_id'];
    $class_filter = $_GET['class_id'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $type_filter = $_GET['type'] ?? '';
    $filter = $_GET['filter'] ?? 'all';
    
    // Build query - CHỈ LẤY SẢN PHẨM ĐÃ ĐƯỢC CHIA SẺ
    $sql = "
        SELECT 
            p.*,
            u.fullname as student_name,
            u.email as student_email,
            u.avatar as student_avatar,
            c.class_name,
            c.code as class_code,
            (SELECT COUNT(*) FROM product_comments WHERE product_id = p.id) as comments_count
        FROM academic_products p
        JOIN users u ON p.user_id = u.id
        JOIN classes c ON p.class_id = c.id
        WHERE c.teacher_id = ?
        AND p.share_token IS NOT NULL
        AND p.share_token != ''
    ";
    
    $params = [$teacher_id];
    
    // Apply filters
    if (!empty($class_filter)) {
        $sql .= " AND p.class_id = ?";
        $params[] = $class_filter;
    }
    
    if ($filter !== 'all') {
        $sql .= " AND p.status = ?";
        $params[] = $filter;
    } elseif (!empty($status_filter)) {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($type_filter)) {
        $sql .= " AND p.type = ?";
        $params[] = $type_filter;
    }
    
    $sql .= " ORDER BY 
        CASE WHEN p.status = 'submitted' THEN 1 ELSE 2 END,
        p.updated_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>