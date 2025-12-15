<?php
// File: giaovien/api/delete_class.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $class_id = $data['class_id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$class_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID required']);
        exit();
    }
    
    // Verify teacher owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit();
    }
    
    // Delete class (cascade will handle related records)
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$class_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Class deleted successfully'
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>