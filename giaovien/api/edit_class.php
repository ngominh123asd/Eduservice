<?php
// File: giaovien/api/edit_class.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    // Handle GET request to fetch class data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $class_id = $_GET['id'] ?? null;
        $teacher_id = $_SESSION['user_id'];
        
        if (!$class_id) {
            echo json_encode(['success' => false, 'message' => 'Class ID required']);
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$class_id, $teacher_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$class) {
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'class' => $class
        ]);
        exit();
    }
    
    // Handle POST request to update class
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $teacher_id = $_SESSION['user_id'];
        
        $class_id = $data['class_id'] ?? null;
        $class_name = $data['name'] ?? '';
        $description = $data['description'] ?? null;
        $code = $data['code'] ?? null;
        $max_students = $data['max_students'] ?? 50;
        
        if (!$class_id || empty($class_name)) {
            echo json_encode(['success' => false, 'message' => 'Class ID and name are required']);
            exit();
        }
        
        // Verify teacher owns this class
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$class_id, $teacher_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Class not found']);
            exit();
        }
        
        // Check if code already exists for another class
        if (!empty($code)) {
            $stmt = $pdo->prepare("SELECT id FROM classes WHERE code = ? AND id != ?");
            $stmt->execute([$code, $class_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Class code already exists']);
                exit();
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE classes 
            SET class_name = ?, description = ?, code = ?, max_students = ?, updated_at = datetime('now')
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->execute([$class_name, $description, $code, $max_students, $class_id, $teacher_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Class updated successfully'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>