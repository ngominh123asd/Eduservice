<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $chapterId = $_GET['id'] ?? null;
    if (!$chapterId) {
        throw new Exception('Chapter ID is required');
    }

    $stmt = $pdo->prepare("
        SELECT c.*, cl.teacher_id 
        FROM chapters c
        JOIN classes cl ON c.class_id = cl.id
        WHERE c.id = ? AND cl.teacher_id = ?
    ");
    
    $stmt->execute([$chapterId, $_SESSION['user_id']]);
    $chapter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$chapter) {
        throw new Exception('Chapter not found');
    }

    echo json_encode([
        'success' => true,
        'chapter' => $chapter
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}