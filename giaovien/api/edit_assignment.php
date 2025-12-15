<?php
// File: giaovien/api/edit_assignment.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    // Handle GET request to fetch assignment data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $assignment_id = $_GET['id'] ?? null;
        $teacher_id = $_SESSION['user_id'];
        
        if (!$assignment_id) {
            echo json_encode(['success' => false, 'message' => 'Assignment ID required']);
            exit();
        }
        
        $stmt = $pdo->prepare("
            SELECT a.* 
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            WHERE a.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$assignment_id, $teacher_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$assignment) {
            echo json_encode(['success' => false, 'message' => 'Assignment not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'assignment' => $assignment
        ]);
        exit();
    }
    
    // Handle POST request to update assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $teacher_id = $_SESSION['user_id'];
        
        $assignment_id = $data['assignment_id'] ?? null;
        $title = $data['title'] ?? '';
        $description = $data['description'] ?? null;
        $start_date = $data['start_date'] ?? null;
        $deadline = $data['deadline'] ?? null;
        $max_score = $data['max_score'] ?? 10;
        
        if (!$assignment_id || empty($title) || !$deadline) {
            echo json_encode(['success' => false, 'message' => 'Assignment ID, title and deadline are required']);
            exit();
        }
        
        // Verify teacher owns the class that contains this assignment
        $stmt = $pdo->prepare("
            SELECT a.id 
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            WHERE a.id = ? AND c.teacher_id = ?
        ");
        $stmt->execute([$assignment_id, $teacher_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Assignment not found']);
            exit();
        }
        
        // Convert empty start_date to null
        $start_date = !empty($start_date) ? $start_date : null;
        
        $stmt = $pdo->prepare("
            UPDATE assignments 
            SET title = ?, description = ?, start_date = ?, deadline = ?, max_score = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $start_date, $deadline, $max_score, $assignment_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Assignment updated successfully'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>