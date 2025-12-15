<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    // Handle GET request to fetch chapter data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $chapter_id = $_GET['id'] ?? null;
        $teacher_id = $_SESSION['user_id'];
        
        if (!$chapter_id) {
            echo json_encode(['success' => false, 'message' => 'Chapter ID required']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT ch.* 
            FROM chapters ch
            JOIN classes c ON ch.class_id = c.id
            WHERE ch.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$chapter_id, $teacher_id]);
        $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$chapter) {
            echo json_encode(['success' => false, 'message' => 'Chapter not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'chapter' => $chapter
        ]);
        exit();
    }
    
    // Handle POST request to update chapter
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $teacher_id = $_SESSION['user_id'];
        
        $chapter_id = $data['chapter_id'] ?? null;
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? null;
        $order_index = $data['order_index'] ?? 1;
        
        if (!$chapter_id || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Chapter ID and title are required']);
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
        
        $stmt = $pdo->prepare("
            UPDATE chapters 
            SET title = ?, description = ?, order_index = ?, updated_at = DATETIME('now')
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $order_index, $chapter_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Chapter updated successfully'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>