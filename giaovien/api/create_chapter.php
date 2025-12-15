<?php
// File: giaovien/api/create_chapter.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $teacher_id = $_SESSION['user_id'];
    
    $class_id = $data['class_id'] ?? null;
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? null;
    $order_index = $data['order_index'] ?? 1;
    
    if (!$class_id || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Class ID and title are required']);
        exit();
    }
    
    // Verify teacher owns this class
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit();
    }
    
    // Changed NOW() to DATETIME('now') for SQLite compatibility
    $stmt = $pdo->prepare("
        INSERT INTO chapters (class_id, title, description, order_index, created_at) 
        VALUES (?, ?, ?, ?, DATETIME('now'))
    ");
    $stmt->execute([$class_id, $title, $description, $order_index]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Chapter created successfully',
        'chapter_id' => $pdo->lastInsertId()
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>