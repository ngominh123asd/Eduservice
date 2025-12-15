<?php
// File: giaovien/api/create_class.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['name'])) {
        echo json_encode(['success' => false, 'message' => 'Tên lớp học không được để trống']);
        exit();
    }

    $name = $data['name'];
    $description = $data['description'] ?? '';
    $code = $data['code'] ?? null;
    $max_students = $data['max_students'] ?? 50;
    $teacher_id = $_SESSION['user_id'];

    // Check if class code exists
    if ($code) {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Mã lớp học đã tồn tại']);
            exit();
        }
    }

    // Insert new class using DATETIME('now') instead of NOW()
    $stmt = $pdo->prepare("
        INSERT INTO classes (
            class_name, 
            description, 
            code, 
            teacher_id, 
            max_students, 
            created_at,
            status
        ) VALUES (?, ?, ?, ?, ?, DATETIME('now'), 'active')
    ");

    $stmt->execute([
        $name,
        $description,
        $code,
        $teacher_id,
        $max_students
    ]);

    $class_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Tạo lớp học thành công',
        'class_id' => $class_id
    ]);

} catch(PDOException $e) {
    error_log('Database error in create_class: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi khi tạo lớp học: ' . $e->getMessage()
    ]);
}
?>