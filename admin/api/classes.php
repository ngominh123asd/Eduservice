<?php

header('Content-Type: application/json');

// Include session configuration
require_once __DIR__ . '/../config/session.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getClass();
        break;
    case 'create':
        createClass();
        break;
    case 'update':
        updateClass();
        break;
    case 'delete':
        deleteClass();
        break;
    case 'toggle_status':
        toggleStatus();
        break;
    case 'list':
        listClasses();
        break;
    case 'get_teachers':
        getTeachers();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// Get single class
function getClass() {
    global $pdo;
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.fullname as teacher_name,
                   (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($class) {
            echo json_encode(['success' => true, 'class' => $class]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lớp học']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Create new class
function createClass() {
    global $pdo;
    
    $class_name = trim($_POST['class_name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($class_name)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên lớp học']);
        return;
    }
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã lớp']);
        return;
    }
    
    try {
        // Check if code exists
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Mã lớp đã được sử dụng']);
            return;
        }
        
        // Insert class
        $stmt = $pdo->prepare("
            INSERT INTO classes (class_name, code, description, teacher_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))
        ");
        $stmt->execute([$class_name, $code, $description, $teacher_id ?: null, $status]);
        
        $classId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Tạo lớp học thành công',
            'class_id' => $classId
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Update class
function updateClass() {
    global $pdo;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $class_name = trim($_POST['class_name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $status = $_POST['status'] ?? '';
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    if (empty($class_name)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên lớp học']);
        return;
    }
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã lớp']);
        return;
    }
    
    try {
        // Check if code exists for another class
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE code = ? AND id != ?");
        $stmt->execute([$code, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Mã lớp đã được sử dụng']);
            return;
        }
        
        // Update class
        $stmt = $pdo->prepare("
            UPDATE classes 
            SET class_name = ?, code = ?, description = ?, teacher_id = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$class_name, $code, $description, $teacher_id ?: null, $status, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Cập nhật lớp học thành công']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Delete class
function deleteClass() {
    global $pdo;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    try {
        // Check if class has enrollments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ?");
        $stmt->execute([$id]);
        $enrollmentCount = $stmt->fetchColumn();
        
        if ($enrollmentCount > 0) {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa lớp học đã có học sinh đăng ký']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Xóa lớp học thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy lớp học']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Toggle class status
function toggleStatus() {
    global $pdo;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = $_POST['status'] ?? '';
    
    if (!$id || !in_array($status, ['active', 'archived', 'draft'])) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE classes SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không có thay đổi']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// List classes with pagination
function listClasses() {
    global $pdo;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    
    $offset = ($page - 1) * $limit;
    
    try {
        $where_conditions = [];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(class_name LIKE ? OR code LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($status) {
            $where_conditions[] = "c.status = ?";
            $params[] = $status;
        }
        
        if ($teacher_id) {
            $where_conditions[] = "c.teacher_id = ?";
            $params[] = $teacher_id;
        }
        
        $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM classes c $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get classes
        $sql = "SELECT c.*, u.fullname as teacher_name,
                       (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
                FROM classes c
                LEFT JOIN users u ON c.teacher_id = u.id
                $where_clause 
                ORDER BY c.created_at DESC 
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'classes' => $classes,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Get teachers for dropdown
function getTeachers() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, fullname FROM users WHERE role = 'teacher' ORDER BY fullname");
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}
?>
