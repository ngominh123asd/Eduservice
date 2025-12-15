<?php
// File: giaovien/api/delete_chapter.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $chapter_id = $data['chapter_id'] ?? null;
    $teacher_id = $_SESSION['user_id'];
    
    if (!$chapter_id) {
        echo json_encode(['success' => false, 'message' => 'Chapter ID required']);
        exit();
    }
    
    // Verify teacher owns the class that contains this chapter
    $stmt = $pdo->prepare("
        SELECT ch.id 
        FROM chapters ch
        JOIN classes c ON ch.class_id = c.id
        WHERE ch.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$chapter_id, $teacher_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Chapter not found']);
        exit();
    }
    
    // Delete chapter (cascade will handle related lessons)
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
    $stmt->execute([$chapter_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Chapter deleted successfully'
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>