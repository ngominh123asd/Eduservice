<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db/db_config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $classId = $data['class_id'];
    $type = $data['type'];
    $students = $data['students'];
    
    // Verify class ownership
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$classId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Không có quyền truy cập lớp học này');
    }
    
    $addedCount = 0;
    $results = [];
    $pdo->beginTransaction();
    
    foreach ($students as $student) {
        $identifier = trim($student);
        
        if ($type === 'id') {
            // Kiểm tra user có tồn tại không
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $results[] = [
                    'status' => 'error',
                    'id' => $identifier,
                    'reason' => 'not_found',
                    'message' => 'Không tìm thấy học sinh'
                ];
                continue;
            }
            
            // Kiểm tra role
            if ($user['role'] === 'teacher') {
                $results[] = [
                    'status' => 'error',
                    'id' => $identifier,
                    'reason' => 'teacher',
                    'message' => 'Là tài khoản giáo viên'
                ];
                continue;
            }
            
            if ($user['role'] === 'admin') {
                $results[] = [
                    'status' => 'error',
                    'id' => $identifier,
                    'reason' => 'admin',
                    'message' => 'Là tài khoản admin'
                ];
                continue;
            }
            
            // Kiểm tra đã có trong lớp chưa
            $stmt = $pdo->prepare("SELECT 1 FROM class_enrollments WHERE class_id = ? AND user_id = ?");
            $stmt->execute([$classId, $user['id']]);
            if ($stmt->fetch()) {
                $results[] = [
                    'status' => 'error',
                    'id' => $identifier,
                    'reason' => 'already_enrolled',
                    'message' => 'Đã có trong lớp học'
                ];
                continue;
            }
            
            // Thêm học sinh vào lớp
            $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, user_id) VALUES (?, ?)");
            $stmt->execute([$classId, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $results[] = [
                    'status' => 'success',
                    'id' => $identifier,
                    'message' => 'Đã thêm thành công'
                ];
                $addedCount++;
            }
            
        } else {
            // Xử lý theo email
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $results[] = [
                    'status' => 'error',
                    'email' => $identifier,
                    'reason' => 'not_found',
                    'message' => 'Không tìm thấy học sinh'
                ];
                continue;
            }
            
            // Kiểm tra role
            if ($user['role'] === 'teacher') {
                $results[] = [
                    'status' => 'error',
                    'email' => $identifier,
                    'reason' => 'teacher',
                    'message' => 'Là tài khoản giáo viên'
                ];
                continue;
            }
            
            if ($user['role'] === 'admin') {
                $results[] = [
                    'status' => 'error',
                    'email' => $identifier,
                    'reason' => 'admin',
                    'message' => 'Là tài khoản admin'
                ];
                continue;
            }
            
            // Kiểm tra đã có trong lớp chưa
            $stmt = $pdo->prepare("SELECT 1 FROM class_enrollments WHERE class_id = ? AND user_id = ?");
            $stmt->execute([$classId, $user['id']]);
            if ($stmt->fetch()) {
                $results[] = [
                    'status' => 'error',
                    'email' => $identifier,
                    'reason' => 'already_enrolled',
                    'message' => 'Đã có trong lớp học'
                ];
                continue;
            }
            
            // Thêm học sinh vào lớp
            $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, user_id) VALUES (?, ?)");
            $stmt->execute([$classId, $user['id']]);
            
            if ($stmt->rowCount() > 0) {
                $results[] = [
                    'status' => 'success',
                    'email' => $identifier,
                    'message' => 'Đã thêm thành công'
                ];
                $addedCount++;
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'added_count' => $addedCount,
        'results' => $results,
        'message' => "Đã thêm $addedCount học sinh vào lớp"
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}