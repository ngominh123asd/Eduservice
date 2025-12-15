<?php
header('Content-Type: application/json');

// Include session configuration
require_once __DIR__ . '/../config/session.php';

// Check admin authentication
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getUser();
        break;
    case 'create':
        createUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'toggle_status':
        toggleStatus();
        break;
    case 'list':
        listUsers();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// Get single user
function getUser() {
    global $pdo;
    
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, fullname, email, role, status, avatar, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Create new user
function createUser() {
    global $pdo;
    
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $status = $_POST['status'] ?? 'active';
    $avatar = trim($_POST['avatar'] ?? '');
    
    // Validation
    if (empty($fullname) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
        return;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự']);
        return;
    }
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email đã được sử dụng']);
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, role, status, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))");
        $stmt->execute([$fullname, $email, $hashed_password, $role, $status, $avatar ?: null]);
        
        $userId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Tạo người dùng thành công',
            'user_id' => $userId
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Update user
function updateUser() {
    global $pdo;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $avatar = trim($_POST['avatar'] ?? '');
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    if (empty($fullname) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
        return;
    }
    
    try {
        // Check if email exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email đã được sử dụng']);
            return;
        }
        
        // Build update query
        if (!empty($password)) {
            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự']);
                return;
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, password = ?, role = ?, status = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$fullname, $email, $hashed_password, $role, $status, $avatar ?: null, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, role = ?, status = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$fullname, $email, $role, $status, $avatar ?: null, $id]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Cập nhật người dùng thành công']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Delete user
function deleteUser() {
    global $pdo;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        return;
    }
    
    // Don't allow deleting yourself
    if ($id == $_SESSION['user']['id']) {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa tài khoản của chính mình']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Xóa người dùng thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}

// Toggle user status
function toggleStatus() {
    global $pdo;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = $_POST['status'] ?? '';
    
    if (!$id || !in_array($status, ['active', 'inactive', 'suspended'])) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
        return;
    }
    
    // Don't allow changing own status
    if ($id == $_SESSION['user']['id']) {
        echo json_encode(['success' => false, 'message' => 'Không thể thay đổi trạng thái của chính mình']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
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

// List users with pagination
function listUsers() {
    global $pdo;
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
    $search = trim($_GET['search'] ?? '');
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $offset = ($page - 1) * $limit;
    
    try {
        $where_conditions = [];
        $params = [];
        
        if ($search) {
            $where_conditions[] = "(fullname LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if ($role) {
            $where_conditions[] = "role = ?";
            $params[] = $role;
        }
        
        if ($status) {
            $where_conditions[] = "status = ?";
            $params[] = $status;
        }
        
        $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Count total
        $count_sql = "SELECT COUNT(*) FROM users $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get users
        $sql = "SELECT id, fullname, email, role, status, avatar, created_at 
                FROM users $where_clause 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
    }
}
?>
