<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Quản lý Nội dung";
$current_page = "content";

// Khởi tạo bảng bổ sung nếu chưa có
try {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    // Bảng documents
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            file_path TEXT NOT NULL,
            file_size INTEGER DEFAULT 0,
            file_type TEXT,
            mime_type TEXT,
            uploaded_by INTEGER,
            class_id INTEGER,
            lesson_id INTEGER,
            download_count INTEGER DEFAULT 0,
            is_public INTEGER DEFAULT 0,
            is_safe INTEGER DEFAULT 1,
            scan_status TEXT DEFAULT 'pending' CHECK(scan_status IN ('pending', 'clean', 'suspicious', 'infected')),
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'archived', 'deleted')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id),
            FOREIGN KEY (class_id) REFERENCES classes(id),
            FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        )
    ");
    
    // Bảng lesson_views
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lesson_views (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lesson_id INTEGER NOT NULL,
            user_id INTEGER,
            duration INTEGER DEFAULT 0,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (lesson_id) REFERENCES lessons(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    
    // Bảng assignment_submissions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS assignment_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            assignment_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            content TEXT,
            file_path TEXT,
            score REAL,
            feedback TEXT,
            status TEXT DEFAULT 'submitted' CHECK(status IN ('submitted', 'grading', 'graded', 'returned')),
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            graded_at DATETIME,
            graded_by INTEGER,
            FOREIGN KEY (assignment_id) REFERENCES assignments(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (graded_by) REFERENCES users(id)
        )
    ");
    
    // Thêm cột cho lessons nếu chưa có
    try {
        $pdo->exec("ALTER TABLE lessons ADD COLUMN status TEXT DEFAULT 'published' CHECK(status IN ('draft', 'pending', 'published', 'rejected'))");
    } catch (PDOException $e) {}
    
    try {
        $pdo->exec("ALTER TABLE lessons ADD COLUMN view_count INTEGER DEFAULT 0");
    } catch (PDOException $e) {}
    
    $pdo->exec('PRAGMA foreign_keys = ON');
    
} catch (PDOException $e) {
    error_log("Content init error: " . $e->getMessage());
}

// Tab hiện tại
$tab = $_GET['tab'] ?? 'lessons';

// Lấy dữ liệu thống kê
try {
    // LESSONS Stats
    $lessons_stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(view_count) as total_views
        FROM lessons
    ")->fetch(PDO::FETCH_ASSOC);
    
    // ASSIGNMENTS Stats
    $assignments_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_assignments,
            (SELECT COUNT(*) FROM assignment_submissions) as total_submissions,
            (SELECT COUNT(*) FROM assignment_submissions WHERE status = 'submitted') as pending_grading,
            (SELECT COUNT(*) FROM assignment_submissions WHERE status = 'graded') as graded,
            (SELECT AVG(score) FROM assignment_submissions WHERE status = 'graded') as avg_score
        FROM assignments
    ")->fetch(PDO::FETCH_ASSOC);
    
    // DOCUMENTS Stats
    $upload_dir = dirname(__DIR__) . '/uploads';
    $total_storage = 0;
    if (is_dir($upload_dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $total_storage += $file->getSize();
        }
    }
    
    $documents_stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(file_size) as total_size,
            SUM(download_count) as total_downloads,
            SUM(CASE WHEN scan_status = 'pending' THEN 1 ELSE 0 END) as pending_scan,
            SUM(CASE WHEN scan_status = 'suspicious' OR scan_status = 'infected' THEN 1 ELSE 0 END) as unsafe
        FROM documents
    ")->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Content stats error: " . $e->getMessage());
    $lessons_stats = ['total' => 0, 'published' => 0, 'pending' => 0, 'rejected' => 0, 'total_views' => 0];
    $assignments_stats = ['total_assignments' => 0, 'total_submissions' => 0, 'pending_grading' => 0, 'graded' => 0, 'avg_score' => 0];
    $documents_stats = ['total' => 0, 'total_size' => 0, 'total_downloads' => 0, 'pending_scan' => 0, 'unsafe' => 0];
    $total_storage = 0;
}

// Helper function
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

$max_storage = 10 * 1024 * 1024 * 1024; // 10GB
$storage_percent = $max_storage > 0 ? ($total_storage / $max_storage) * 100 : 0;
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
    <link rel="stylesheet" href="css/content.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h1><i class="fas fa-book-open"></i> Quản lý Nội dung</h1>
                    <p>Quản lý bài học, bài tập và tài liệu học tập</p>
                </div>
            </div>
            
            <!-- Alert Container -->
            <div id="alert-container"></div>
            
            <!-- Tabs -->
            <div class="content-tabs">
                <a href="?tab=lessons" class="content-tab <?php echo $tab === 'lessons' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Bài học</span>
                    <?php if (($lessons_stats['pending'] ?? 0) > 0): ?>
                        <span class="badge badge-warning"><?php echo $lessons_stats['pending']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=assignments" class="content-tab <?php echo $tab === 'assignments' ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Bài tập</span>
                    <?php if (($assignments_stats['pending_grading'] ?? 0) > 0): ?>
                        <span class="badge badge-warning"><?php echo $assignments_stats['pending_grading']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=documents" class="content-tab <?php echo $tab === 'documents' ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Kho tài liệu</span>
                    <?php if (($documents_stats['pending_scan'] ?? 0) > 0): ?>
                        <span class="badge badge-warning"><?php echo $documents_stats['pending_scan']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <?php if ($tab === 'lessons'): ?>
                <!-- LESSONS TAB -->
                <div class="content-stats">
                    <div class="stat-card">
                        <div class="stat-icon lessons"><i class="fas fa-book"></i></div>
                        <div class="stat-number"><?php echo $lessons_stats['total'] ?? 0; ?></div>
                        <div class="stat-label">Tổng bài học</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon published"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo $lessons_stats['published'] ?? 0; ?></div>
                        <div class="stat-label">Đã xuất bản</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo $lessons_stats['pending'] ?? 0; ?></div>
                        <div class="stat-label">Chờ duyệt</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon views"><i class="fas fa-eye"></i></div>
                        <div class="stat-number"><?php echo number_format($lessons_stats['total_views'] ?? 0); ?></div>
                        <div class="stat-label">Lượt xem</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon rejected"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-number"><?php echo $lessons_stats['rejected'] ?? 0; ?></div>
                        <div class="stat-label">Bị từ chối</div>
                    </div>
                </div>
                
                <!-- Pending Lessons -->
                <div class="content-card" id="pending-lessons-card">
                    <div class="content-card-header">
                        <h3><i class="fas fa-clock"></i> Bài học chờ duyệt</h3>
                        <span class="badge badge-warning" id="pending-count"><?php echo $lessons_stats['pending'] ?? 0; ?></span>
                    </div>
                    <div class="content-card-body" id="pending-lessons-list">
                        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
                    </div>
                </div>
                
                <!-- All Lessons -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3><i class="fas fa-list"></i> Tất cả bài học</h3>
                        <div class="header-actions">
                            <input type="text" class="search-input-sm" id="lessons-search" placeholder="Tìm kiếm...">
                            <select class="filter-select-sm" id="lessons-status-filter">
                                <option value="">Tất cả trạng thái</option>
                                <option value="published">Đã xuất bản</option>
                                <option value="pending">Chờ duyệt</option>
                                <option value="rejected">Bị từ chối</option>
                                <option value="draft">Bản nháp</option>
                            </select>
                        </div>
                    </div>
                    <div class="content-card-body" id="all-lessons-list">
                        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
                    </div>
                </div>
                
            <?php elseif ($tab === 'assignments'): ?>
                <!-- ASSIGNMENTS TAB -->
                <div class="content-stats">
                    <div class="stat-card">
                        <div class="stat-icon lessons"><i class="fas fa-tasks"></i></div>
                        <div class="stat-number"><?php echo $assignments_stats['total_assignments'] ?? 0; ?></div>
                        <div class="stat-label">Tổng bài tập</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon views"><i class="fas fa-file-upload"></i></div>
                        <div class="stat-number"><?php echo $assignments_stats['total_submissions'] ?? 0; ?></div>
                        <div class="stat-label">Bài nộp</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon grading"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-number"><?php echo $assignments_stats['pending_grading'] ?? 0; ?></div>
                        <div class="stat-label">Chờ chấm</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon published"><i class="fas fa-check-double"></i></div>
                        <div class="stat-number"><?php echo $assignments_stats['graded'] ?? 0; ?></div>
                        <div class="stat-label">Đã chấm</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending"><i class="fas fa-star-half-alt"></i></div>
                        <div class="stat-number"><?php echo number_format($assignments_stats['avg_score'] ?? 0, 1); ?></div>
                        <div class="stat-label">Điểm TB</div>
                    </div>
                </div>
                
                <!-- Pending Submissions -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3><i class="fas fa-hourglass-half"></i> Bài nộp chờ chấm điểm</h3>
                        <span class="badge badge-warning"><?php echo $assignments_stats['pending_grading'] ?? 0; ?></span>
                    </div>
                    <div class="content-card-body" id="pending-submissions-list">
                        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
                    </div>
                </div>
                
            <?php elseif ($tab === 'documents'): ?>
                <!-- DOCUMENTS TAB -->
                <?php
                $storage_class = $storage_percent > 90 ? 'danger' : ($storage_percent > 70 ? 'warning' : '');
                ?>
                
                <!-- Storage Card -->
                <div class="storage-card">
                    <div class="storage-header">
                        <h4><i class="fas fa-hdd"></i> Dung lượng lưu trữ</h4>
                        <span class="storage-text"><?php echo formatSize($total_storage); ?> / <?php echo formatSize($max_storage); ?></span>
                    </div>
                    <div class="storage-bar">
                        <div class="storage-bar-fill <?php echo $storage_class; ?>" style="width: <?php echo min($storage_percent, 100); ?>%"></div>
                    </div>
                    <div class="storage-details">
                        <span>Đã sử dụng <?php echo number_format($storage_percent, 1); ?>%</span>
                        <span>Còn trống <?php echo formatSize($max_storage - $total_storage); ?></span>
                    </div>
                    <div class="storage-breakdown">
                        <div class="storage-type">
                            <div class="storage-type-icon text-red"><i class="fas fa-file-pdf"></i></div>
                            <div class="storage-type-size">-</div>
                            <div class="storage-type-label">Tài liệu</div>
                        </div>
                        <div class="storage-type">
                            <div class="storage-type-icon text-purple"><i class="fas fa-file-image"></i></div>
                            <div class="storage-type-size">-</div>
                            <div class="storage-type-label">Hình ảnh</div>
                        </div>
                        <div class="storage-type">
                            <div class="storage-type-icon text-pink"><i class="fas fa-file-video"></i></div>
                            <div class="storage-type-size">-</div>
                            <div class="storage-type-label">Video</div>
                        </div>
                        <div class="storage-type">
                            <div class="storage-type-icon text-gray"><i class="fas fa-file"></i></div>
                            <div class="storage-type-size">-</div>
                            <div class="storage-type-label">Khác</div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="actions-bar">
                    <button class="btn btn-warning" onclick="Content.cleanupFiles()">
                        <i class="fas fa-broom"></i> Dọn dẹp file rác
                    </button>
                    <button class="btn btn-danger" onclick="Content.scanAllFiles()">
                        <i class="fas fa-shield-virus"></i> Quét Virus toàn bộ
                    </button>
                </div>
                
                <!-- Documents Stats -->
                <div class="content-stats" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="stat-card">
                        <div class="stat-icon storage"><i class="fas fa-file"></i></div>
                        <div class="stat-number"><?php echo $documents_stats['total'] ?? 0; ?></div>
                        <div class="stat-label">Tổng file</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon views"><i class="fas fa-download"></i></div>
                        <div class="stat-number"><?php echo number_format($documents_stats['total_downloads'] ?? 0); ?></div>
                        <div class="stat-label">Lượt tải</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending"><i class="fas fa-search"></i></div>
                        <div class="stat-number"><?php echo $documents_stats['pending_scan'] ?? 0; ?></div>
                        <div class="stat-label">Chờ quét</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon rejected"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-number"><?php echo $documents_stats['unsafe'] ?? 0; ?></div>
                        <div class="stat-label">Nghi ngờ</div>
                    </div>
                </div>
                
                <!-- Documents List -->
                <div class="content-card">
                    <div class="content-card-header">
                        <h3><i class="fas fa-folder-open"></i> Kho tài liệu</h3>
                        <div class="header-actions">
                            <input type="text" class="search-input-sm" id="documents-search" placeholder="Tìm kiếm...">
                            <select class="filter-select-sm" id="documents-type-filter">
                                <option value="">Tất cả loại file</option>
                                <option value="pdf">PDF</option>
                                <option value="doc">Word</option>
                                <option value="xls">Excel</option>
                                <option value="image">Hình ảnh</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                    </div>
                    <div class="content-card-body" id="documents-list">
                        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Preview Modal -->
    <div class="modal-overlay" id="preview-modal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> <span id="preview-title">Xem trước</span></h3>
                <button class="modal-close" onclick="Content.closeModal('preview-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="preview-content">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Content.closeModal('preview-modal')">Đóng</button>
            </div>
        </div>
    </div>
    
    <!-- Grade Modal -->
    <div class="modal-overlay" id="grade-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Chấm điểm bài nộp</h3>
                <button class="modal-close" onclick="Content.closeModal('grade-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="grade-form" onsubmit="return Content.submitGrade(event)">
                <input type="hidden" name="submission_id" id="grade-submission-id">
                <div class="modal-body">
                    <div class="submission-info" id="grade-submission-info"></div>
                    <div class="form-group">
                        <label class="form-label">Điểm số (0-10)</label>
                        <input type="number" name="score" id="grade-score" class="form-input" min="0" max="10" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nhận xét</label>
                        <textarea name="feedback" id="grade-feedback" class="form-textarea" rows="4" placeholder="Nhập nhận xét cho học sinh..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Content.closeModal('grade-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Xác nhận chấm điểm
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal-overlay" id="reject-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Từ chối bài học</h3>
                <button class="modal-close" onclick="Content.closeModal('reject-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="reject-form" onsubmit="return Content.submitReject(event)">
                <input type="hidden" name="lesson_id" id="reject-lesson-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Lý do từ chối <span class="required">*</span></label>
                        <textarea name="reason" id="reject-reason" class="form-textarea" rows="4" required placeholder="Nhập lý do từ chối bài học..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Content.closeModal('reject-modal')">Hủy</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Xác nhận từ chối
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const currentTab = '<?php echo $tab; ?>';
    </script>
    <script src="js/content.js"></script>
</body>
</html>
