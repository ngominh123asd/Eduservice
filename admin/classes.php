<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Quản lý Lớp học";
$current_page = "classes";

// Pagination & Filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$teacher_filter = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;

$view_mode = isset($_GET['view']) ? $_GET['view'] : 'grid';

try {
    // Build query
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(class_name LIKE ? OR code LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status_filter) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if ($teacher_filter) {
        $where_conditions[] = "teacher_id = ?";
        $params[] = $teacher_filter;
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Count total
    $count_sql = "SELECT COUNT(*) FROM classes $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_classes = $stmt->fetchColumn();
    $total_pages = ceil($total_classes / $limit);
    
    // Get classes with teacher info and student count - SỬA: Sử dụng c.code thay vì c.class_code
    $sql = "SELECT c.id, c.code, c.class_name, c.description, c.status, c.created_at, c.teacher_id,
                   u.fullname as teacher_name,
                   (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
            FROM classes c
            LEFT JOIN users u ON c.teacher_id = u.id
            $where_clause
            ORDER BY c.created_at DESC
            LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teachers for filter
    $teachers = $pdo->query("SELECT id, fullname FROM users WHERE role = 'teacher' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
        FROM classes
    ")->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Lỗi: " . $e->getMessage();
    $classes = [];
    $stats = [];
    $teachers = [];
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
    <link rel="stylesheet" href="css/classes.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h1><i class="fas fa-chalkboard"></i> Quản lý Lớp học</h1>
                    <p>Xem và quản lý tất cả lớp học trong hệ thống</p>
                </div>
                <div class="page-header-right">
                    <button class="btn btn-outline" onclick="exportClasses()">
                        <i class="fas fa-file-export"></i> Xuất dữ liệu
                    </button>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="class-stats">
                <div class="class-stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['total'] ?? 0; ?></span>
                        <span class="stat-label">Tổng lớp học</span>
                    </div>
                </div>
                <div class="class-stat-card">
                    <div class="stat-icon active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['active'] ?? 0; ?></span>
                        <span class="stat-label">Đang hoạt động</span>
                    </div>
                </div>
                <div class="class-stat-card">
                    <div class="stat-icon archived">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['archived'] ?? 0; ?></span>
                        <span class="stat-label">Đã lưu trữ</span>
                    </div>
                </div>
                <div class="class-stat-card">
                    <div class="stat-icon draft">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['draft'] ?? 0; ?></span>
                        <span class="stat-label">Bản nháp</span>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form" id="filter-form">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                    
                    <div class="search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Tìm kiếm lớp học..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    </div>
                    
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Đã lưu trữ</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
                    </select>
                    
                    <select name="teacher" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tất cả giảng viên</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter"></i> Lọc
                    </button>
                    
                    <?php if ($search || $status_filter || $teacher_filter): ?>
                        <a href="classes.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-times"></i> Xóa bộ lọc
                        </a>
                    <?php endif; ?>
                    
                    <div class="view-toggle">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" 
                           class="view-btn <?php echo $view_mode === 'grid' ? 'active' : ''; ?>" title="Dạng lưới">
                            <i class="fas fa-th-large"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" 
                           class="view-btn <?php echo $view_mode === 'list' ? 'active' : ''; ?>" title="Dạng danh sách">
                            <i class="fas fa-list"></i>
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Classes Display -->
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php elseif (empty($classes)): ?>
                <div class="empty-state">
                    <i class="fas fa-chalkboard"></i>
                    <h3>Không tìm thấy lớp học</h3>
                    <p>Không có lớp học nào hoặc không có kết quả phù hợp với bộ lọc.</p>
                </div>
            <?php elseif ($view_mode === 'grid'): ?>
                <div class="classes-grid">
                    <?php foreach ($classes as $class): ?>
                        <div class="class-card" data-class-id="<?php echo $class['id']; ?>">
                            <div class="class-card-header">
                                <div class="class-icon" style="background: <?php echo getClassColor($class['id']); ?>">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="class-status status-<?php echo $class['status'] ?? 'draft'; ?>">
                                    <?php echo getStatusLabel($class['status'] ?? 'draft'); ?>
                                </div>
                            </div>
                            <div class="class-card-body">
                                <h3 class="class-name"><?php echo htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="class-code"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($class['code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="class-teacher"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($class['teacher_name'] ?? 'Chưa phân công', ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="class-meta">
                                    <span><i class="fas fa-users"></i> <?php echo $class['student_count'] ?? 0; ?> học sinh</span>
                                    <span><i class="fas fa-calendar"></i> <?php echo $class['created_at'] ? date('d/m/Y', strtotime($class['created_at'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                            <div class="class-card-footer">
                                <button class="btn-action" onclick="viewClass(<?php echo $class['id']; ?>)" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action" onclick="editClass(<?php echo $class['id']; ?>)" title="Chỉnh sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-danger" onclick="deleteClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES); ?>')" title="Xóa">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Mã lớp</th>
                                    <th>Tên lớp học</th>
                                    <th>Giảng viên</th>
                                    <th>Học sinh</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th width="120">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($class['code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Chưa phân công', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo $class['student_count'] ?? 0; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $class['status'] ?? 'draft'; ?>">
                                                <?php echo getStatusLabel($class['status'] ?? 'draft'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $class['created_at'] ? date('d/m/Y', strtotime($class['created_at'])) : 'N/A'; ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-action btn-view" onclick="viewClass(<?php echo $class['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-action btn-edit" onclick="editClass(<?php echo $class['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-action btn-delete" onclick="deleteClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name'] ?? '', ENT_QUOTES); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card">
                    <div class="card-footer">
                        <div class="pagination-info">
                            Hiển thị <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_classes); ?> 
                            trên tổng số <?php echo $total_classes; ?> lớp học
                        </div>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="pagination-btn">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- View Class Modal -->
    <div class="modal-overlay" id="view-class-modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Chi tiết lớp học</h3>
                <button class="modal-close" onclick="closeModal('view-class-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="view-class-content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('view-class-modal')">Đóng</button>
                <button type="button" class="btn btn-primary" id="view-edit-btn">
                    <i class="fas fa-edit"></i> Chỉnh sửa
                </button>
            </div>
        </div>
    </div>
    
    <!-- Edit Class Modal -->
    <div class="modal-overlay" id="edit-class-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Chỉnh sửa lớp học</h3>
                <button class="modal-close" onclick="closeModal('edit-class-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="edit-class-form" onsubmit="return submitEditClass(event)">
                <input type="hidden" name="class_id" id="edit-class-id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Mã lớp <span class="required">*</span></label>
                            <input type="text" name="class_code" id="edit-class-code" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tên lớp học <span class="required">*</span></label>
                            <input type="text" name="class_name" id="edit-class-name" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="edit-description" class="form-textarea" rows="4"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Giảng viên phụ trách</label>
                            <select name="teacher_id" id="edit-teacher" class="form-select">
                                <option value="">Chọn giảng viên</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="edit-status" class="form-select">
                                <option value="draft">Bản nháp</option>
                                <option value="active">Hoạt động</option>
                                <option value="archived">Lưu trữ</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-class-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/classes.js"></script>
</body>
</html>

<?php
function getClassColor($id) {
    $colors = ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#E91E63', '#00BCD4', '#795548', '#607D8B'];
    return $colors[$id % count($colors)];
}

function getStatusLabel($status) {
    $labels = [
        'active' => 'Hoạt động',
        'archived' => 'Lưu trữ',
        'draft' => 'Bản nháp'
    ];
    return $labels[$status] ?? $status;
}
?>
