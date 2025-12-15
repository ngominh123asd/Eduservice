<?php

// Include session configuration
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Quản lý Người dùng";
$current_page = "users";

// Xử lý phân trang và tìm kiếm
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Allowed sort columns
$allowed_sorts = ['id', 'fullname', 'email', 'role', 'created_at', 'status'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

try {
    // Build query
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(fullname LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($role_filter) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Count total
    $count_sql = "SELECT COUNT(*) FROM users $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $limit);
    
    // Get users - SỬA: Không có last_login
    $sql = "SELECT id, fullname, email, role, status, avatar, created_at
            FROM users $where_clause 
            ORDER BY $sort_by $sort_order 
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $stats_sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
                    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
                  FROM users";
    $stats = $pdo->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi: " . $e->getMessage();
    $users = [];
    $total_users = 0;
    $total_pages = 0;
}

// Build current URL for sorting
function buildSortUrl($column) {
    global $sort_by, $sort_order, $search, $role_filter, $status_filter;
    $new_order = ($sort_by === $column && $sort_order === 'ASC') ? 'DESC' : 'ASC';
    $params = array_filter([
        'search' => $search,
        'role' => $role_filter,
        'status' => $status_filter,
        'sort' => $column,
        'order' => $new_order
    ]);
    return '?' . http_build_query($params);
}

function getSortIcon($column) {
    global $sort_by, $sort_order;
    if ($sort_by !== $column) return '<i class="fas fa-sort text-muted"></i>';
    return $sort_order === 'ASC' ? '<i class="fas fa-sort-up"></i>' : '<i class="fas fa-sort-down"></i>';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Admin - EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin-common.css">
    <link rel="stylesheet" href="css/users.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h1><i class="fas fa-users"></i> Quản lý Người dùng</h1>
                    <p>Quản lý tài khoản và quyền truy cập của người dùng</p>
                </div>
                <div class="page-header-right">
                    <button class="btn btn-outline" onclick="exportUsers()">
                        <i class="fas fa-file-export"></i> Xuất dữ liệu
                    </button>
                    <button class="btn btn-primary" onclick="openModal('add-user-modal')">
                        <i class="fas fa-plus"></i> Thêm người dùng
                    </button>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="user-stats">
                <div class="user-stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['total'] ?? 0; ?></span>
                        <span class="stat-label">Tổng người dùng</span>
                    </div>
                </div>
                <div class="user-stat-card">
                    <div class="stat-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['students'] ?? 0; ?></span>
                        <span class="stat-label">Sinh viên</span>
                    </div>
                </div>
                <div class="user-stat-card">
                    <div class="stat-icon teachers">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['teachers'] ?? 0; ?></span>
                        <span class="stat-label">Giảng viên</span>
                    </div>
                </div>
                <div class="user-stat-card">
                    <div class="stat-icon admins">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['admins'] ?? 0; ?></span>
                        <span class="stat-label">Quản trị viên</span>
                    </div>
                </div>
                <div class="user-stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['active'] ?? 0; ?></span>
                        <span class="stat-label">Đang hoạt động</span>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form" id="filter-form">
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Tìm theo tên hoặc email..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    </div>
                    
                    <div class="filter-group">
                        <select name="role" class="filter-select" onchange="this.form.submit()">
                            <option value="">Tất cả vai trò</option>
                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Sinh viên</option>
                            <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Giảng viên</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Quản trị viên</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Tạm ngưng</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                    
                    <?php if ($search || $role_filter || $status_filter): ?>
                        <a href="users.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-times"></i> Xóa bộ lọc
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-list"></i> 
                        Danh sách người dùng
                        <span class="badge badge-primary"><?php echo $total_users; ?></span>
                    </h3>
                    <div class="bulk-actions" id="bulk-actions" style="display: none;">
                        <span class="selected-count">Đã chọn: <strong id="selected-count">0</strong></span>
                        <button class="btn btn-sm btn-outline" onclick="bulkAction('activate')">
                            <i class="fas fa-check"></i> Kích hoạt
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="bulkAction('deactivate')">
                            <i class="fas fa-ban"></i> Vô hiệu hóa
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')">
                            <i class="fas fa-trash"></i> Xóa
                        </button>
                    </div>
                </div>
                <div class="card-body table-container">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle alert-icon"></i>
                            <span><?php echo $error_message; ?></span>
                        </div>
                    <?php elseif (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users-slash"></i>
                            <h3>Không tìm thấy người dùng</h3>
                            <p>Không có người dùng nào phù hợp với điều kiện tìm kiếm.</p>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                                    </th>
                                    <th>
                                        <a href="<?php echo buildSortUrl('id'); ?>" class="sort-link">
                                            ID <?php echo getSortIcon('id'); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo buildSortUrl('fullname'); ?>" class="sort-link">
                                            Người dùng <?php echo getSortIcon('fullname'); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo buildSortUrl('email'); ?>" class="sort-link">
                                            Email <?php echo getSortIcon('email'); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo buildSortUrl('role'); ?>" class="sort-link">
                                            Vai trò <?php echo getSortIcon('role'); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo buildSortUrl('status'); ?>" class="sort-link">
                                            Trạng thái <?php echo getSortIcon('status'); ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="<?php echo buildSortUrl('created_at'); ?>" class="sort-link">
                                            Ngày tạo <?php echo getSortIcon('created_at'); ?>
                                        </a>
                                    </th>
                                    <th width="120">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr data-user-id="<?php echo $user['id']; ?>">
                                        <td>
                                            <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td><code>#<?php echo $user['id']; ?></code></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm">
                                                    <?php if ($user['avatar']): ?>
                                                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                                                    <?php else: ?>
                                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=4CAF50&color=fff" alt="Avatar">
                                                    <?php endif; ?>
                                                </div>
                                                <span class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php
                                            $role_badges = [
                                                'student' => '<span class="badge badge-info"><i class="fas fa-user-graduate"></i> Sinh viên</span>',
                                                'teacher' => '<span class="badge badge-primary"><i class="fas fa-chalkboard-teacher"></i> Giảng viên</span>',
                                                'admin' => '<span class="badge badge-warning"><i class="fas fa-user-shield"></i> Admin</span>'
                                            ];
                                            echo $role_badges[$user['role']] ?? '<span class="badge">' . $user['role'] . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'active' => '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Hoạt động</span>',
                                                'inactive' => '<span class="badge badge-secondary"><i class="fas fa-minus-circle"></i> Không hoạt động</span>',
                                                'suspended' => '<span class="badge badge-danger"><i class="fas fa-ban"></i> Tạm ngưng</span>'
                                            ];
                                            echo $status_badges[$user['status']] ?? '<span class="badge">' . $user['status'] . '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo $user['created_at'] ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" title="Xem chi tiết" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-edit" title="Chỉnh sửa" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-action btn-delete" title="Xóa" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['fullname'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <div class="pagination-info">
                            Hiển thị <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_users); ?> 
                            trên tổng số <?php echo $total_users; ?> người dùng
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn" title="Trang đầu">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn" title="Trang trước">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn" title="Trang sau">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-btn" title="Trang cuối">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal-overlay" id="add-user-modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Thêm người dùng</h3>
                <button class="modal-close" onclick="closeModal('add-user-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Tabs -->
            <div class="modal-tabs">
                <button class="tab-btn active" onclick="switchTab('single')">
                    <i class="fas fa-user"></i> Thêm đơn lẻ
                </button>
                <button class="tab-btn" onclick="switchTab('bulk')">
                    <i class="fas fa-users"></i> Thêm hàng loạt
                </button>
            </div>
            
            <!-- Tab: Single User -->
            <div class="tab-content active" id="tab-single">
                <form id="add-user-form" onsubmit="return submitAddUser(event)">
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Họ và tên <span class="required">*</span></label>
                                <input type="text" name="fullname" class="form-input" required placeholder="Nhập họ và tên">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-input" required placeholder="email@vnu.edu.vn">
                                <small class="form-hint">Email phải có đuôi @vnu.edu.vn</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Vai trò <span class="required">*</span></label>
                                <select name="role" class="form-select" required>
                                    <option value="">Chọn vai trò</option>
                                    <option value="student">Sinh viên</option>
                                    <option value="teacher">Giảng viên</option>
                                    <option value="admin">Quản trị viên</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Mật khẩu <span class="required">*</span></label>
                            <div class="password-input-wrapper">
                                <input type="password" name="password" id="add-password" class="form-input" required placeholder="Nhập mật khẩu">
                                <button type="button" class="password-toggle" onclick="togglePassword('add-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-options">
                                <button type="button" class="btn btn-sm btn-outline" onclick="generatePassword()">
                                    <i class="fas fa-random"></i> Tạo mật khẩu ngẫu nhiên
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Ảnh đại diện (URL)</label>
                            <input type="url" name="avatar" class="form-input" placeholder="https://example.com/avatar.jpg">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('add-user-modal')">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Thêm người dùng
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tab: Bulk Import -->
            <div class="tab-content" id="tab-bulk">
                <form id="bulk-import-form" onsubmit="return submitBulkImport(event)">
                    <div class="modal-body">
                        <div class="bulk-info">
                            <div class="info-icon"><i class="fas fa-info-circle"></i></div>
                            <div class="info-text">
                                <strong>Hướng dẫn nhập hàng loạt:</strong>
                                <ul>
                                    <li>Mỗi dòng là một người dùng</li>
                                    <li>Định dạng: <code>Họ tên, Email, Vai trò</code></li>
                                    <li>Vai trò: <code>student</code>, <code>teacher</code>, hoặc <code>admin</code></li>
                                    <li>Mật khẩu sẽ được tạo tự động (12 ký tự)</li>
                                    <li>Trạng thái mặc định: <strong>Hoạt động</strong></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Dữ liệu người dùng <span class="required">*</span></label>
                            <textarea name="bulk_data" id="bulk-data" class="form-textarea" rows="10" required 
                                placeholder="Nguyễn Văn A, nguyenvana@vnu.edu.vn, student
Trần Thị B, tranthib@vnu.edu.vn, teacher
Lê Văn C, levanc@vnu.edu.vn, student"></textarea>
                            <small class="form-hint">
                                <i class="fas fa-lightbulb"></i> 
                                Mẹo: Có thể copy từ Excel (cột Tên, Email, Vai trò)
                            </small>
                        </div>
                        
                        <div class="bulk-preview" id="bulk-preview" style="display: none;">
                            <h4><i class="fas fa-eye"></i> Xem trước (<span id="preview-count">0</span> người dùng)</h4>
                            <div class="preview-table-wrapper">
                                <table class="preview-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Họ tên</th>
                                            <th>Email</th>
                                            <th>Vai trò</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody id="preview-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('add-user-modal')">Hủy</button>
                        <button type="button" class="btn btn-outline" onclick="previewBulkImport()">
                            <i class="fas fa-eye"></i> Xem trước
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Nhập hàng loạt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal-overlay" id="edit-user-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Chỉnh sửa người dùng</h3>
                <button class="modal-close" onclick="closeModal('edit-user-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="edit-user-form" onsubmit="return submitEditUser(event)">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Họ và tên <span class="required">*</span></label>
                            <input type="text" name="fullname" id="edit-fullname" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" id="edit-email" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Vai trò <span class="required">*</span></label>
                            <select name="role" id="edit-role" class="form-select" required>
                                <option value="student">Sinh viên</option>
                                <option value="teacher">Giảng viên</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="edit-status" class="form-select">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Không hoạt động</option>
                                <option value="suspended">Tạm ngưng</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password" id="edit-password" class="form-input" placeholder="Nhập mật khẩu mới">
                            <button type="button" class="password-toggle" onclick="togglePassword('edit-password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Ảnh đại diện (URL)</label>
                        <input type="url" name="avatar" id="edit-avatar" class="form-input" placeholder="https://example.com/avatar.jpg">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-user-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View User Modal -->
    <div class="modal-overlay" id="view-user-modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Chi tiết người dùng</h3>
                <button class="modal-close" onclick="closeModal('view-user-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="view-user-content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('view-user-modal')">Đóng</button>
                <button type="button" class="btn btn-primary" id="view-edit-btn">
                    <i class="fas fa-edit"></i> Chỉnh sửa
                </button>
            </div>
        </div>
    </div>
    
    <!-- Export Modal (Updated) -->
    <div class="modal-overlay" id="export-modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-file-export"></i> Xuất dữ liệu người dùng</h3>
                <button class="modal-close" onclick="closeModal('export-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Export Tabs -->
                <div class="export-tabs">
                    <button class="export-tab-btn active" onclick="switchExportTab('bulk')">
                        <i class="fas fa-users"></i> Xuất hàng loạt
                    </button>
                    <button class="export-tab-btn" onclick="switchExportTab('individual')">
                        <i class="fas fa-user"></i> Xuất cá nhân
                    </button>
                </div>
                
                <!-- Tab: Bulk Export -->
                <div class="export-tab-content active" id="export-tab-bulk">
                    <div class="export-filters">
                        <h4><i class="fas fa-filter"></i> Tùy chọn lọc</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Vai trò</label>
                                <select id="export-role" class="form-select">
                                    <option value="">Tất cả vai trò</option>
                                    <option value="student">Sinh viên</option>
                                    <option value="teacher">Giảng viên</option>
                                    <option value="admin">Quản trị viên</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Trạng thái</label>
                                <select id="export-status" class="form-select">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                    <option value="suspended">Tạm ngưng</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Từ ngày</label>
                                <input type="date" id="export-date-from" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Đến ngày</label>
                                <input type="date" id="export-date-to" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="export-include-password">
                                <span>Tạo mật khẩu mới và xuất kèm</span>
                            </label>
                            <small class="form-hint text-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Lưu ý: Tùy chọn này sẽ reset mật khẩu của tất cả người dùng được xuất!
                            </small>
                        </div>
                    </div>
                    
                    <div class="export-format-section">
                        <h4><i class="fas fa-file"></i> Chọn định dạng xuất</h4>
                        <div class="export-options">
                            <button class="export-option" onclick="doExport('csv')">
                                <i class="fas fa-file-csv"></i>
                                <span>CSV</span>
                            </button>
                            <button class="export-option" onclick="doExport('excel')">
                                <i class="fas fa-file-excel"></i>
                                <span>Excel</span>
                            </button>
                            <button class="export-option" onclick="doExport('pdf')">
                                <i class="fas fa-file-pdf"></i>
                                <span>PDF</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Individual Export -->
                <div class="export-tab-content" id="export-tab-individual">
                    <div class="individual-search-section">
                        <h4><i class="fas fa-search"></i> Tìm kiếm người dùng</h4>
                        
                        <div class="form-group">
                            <label class="form-label">Nhập tên, email hoặc ID</label>
                            <div class="search-input-wrapper">
                                <input type="text" id="individual-search" class="form-input" 
                                       placeholder="Ví dụ: Nguyễn Văn A, admin@vnu.edu.vn, #5..."
                                       oninput="searchIndividualUser()">
                                <div class="search-loading" id="search-loading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Search Results -->
                        <div class="search-results" id="search-results" style="display: none;">
                            <div class="search-results-header">
                                <span>Kết quả tìm kiếm (<span id="search-count">0</span>)</span>
                            </div>
                            <div class="search-results-list" id="search-results-list">
                                <!-- Results will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Selected Users -->
                        <div class="selected-users-section" id="selected-users-section" style="display: none;">
                            <h4><i class="fas fa-user-check"></i> Người dùng đã chọn (<span id="selected-users-count">0</span>)</h4>
                            <div class="selected-users-list" id="selected-users-list">
                                <!-- Selected users will be shown here -->
                            </div>
                            
                            <div class="form-group" style="margin-top: 16px;">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="individual-include-password">
                                    <span>Tạo mật khẩu mới và xuất kèm</span>
                                </label>
                                <small class="form-hint text-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Lưu ý: Tùy chọn này sẽ reset mật khẩu của người dùng được xuất!
                                </small>
                            </div>
                            
                            <div class="export-format-section">
                                <h4><i class="fas fa-file"></i> Chọn định dạng xuất</h4>
                                <div class="export-options">
                                    <button class="export-option" onclick="doIndividualExport('csv')">
                                        <i class="fas fa-file-csv"></i>
                                        <span>CSV</span>
                                    </button>
                                    <button class="export-option" onclick="doIndividualExport('excel')">
                                        <i class="fas fa-file-excel"></i>
                                        <span>Excel</span>
                                    </button>
                                    <button class="export-option" onclick="doIndividualExport('pdf')">
                                        <i class="fas fa-file-pdf"></i>
                                        <span>PDF</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="resetExportFilters()">
                    <i class="fas fa-undo"></i> Đặt lại
                </button>
                <button type="button" class="btn btn-outline" onclick="closeModal('export-modal')">Đóng</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/users.js"></script>
</body>
</html>
