<?php
session_start();

// Check both user authentication and role
if (
    !isset($_SESSION['user']) ||
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'teacher'
) {
    header("Location: ../dangnhap/dangnhap.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';
try {
    $user_id = $_SESSION['user_id'];

    // Verify teacher role from database
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // If not a teacher, logout and redirect
        session_destroy();
        header("Location: ../dangnhap/dangnhap.php");
        exit();
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Giảng dạy | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/teacher-platform.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="../saudn/css/academic-products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div id="header-placeholder"></div>

    <!-- Sidebar trượt -->
    <div class="sidebar-trigger"></div>
    <div class="sidebar" id="sidebar">
        <div class="menu-container">
            <div class="menu-section">
                <div class="menu-header">Menu chính</div>
                <div class="menu-items">
                    <div class="menu-item active" data-section="dashboard">
                        <i class="fas fa-home"></i>
                        <span>Trang chủ</span>
                    </div>
                    <div class="menu-item" data-section="classes">
                        <i class="fas fa-chalkboard"></i>
                        <span>Lớp học của tôi</span>
                    </div>
                    <div class="menu-item" data-section="assignments">
                        <i class="fas fa-tasks"></i>
                        <span>Nhiệm vụ</span>
                    </div>
                    <div class="menu-item" data-section="academic-products">
                        <i class="fas fa-file-alt"></i>
                        <span>Sản phẩm học thuật</span>
                    </div>
                    <div class="menu-item" data-section="students">
                        <i class="fas fa-user-graduate"></i>
                        <span>Học sinh</span>
                    </div>
                    <div class="menu-item" data-section="statistics">
                        <i class="fas fa-chart-bar"></i>
                        <span>Thống kê</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nội dung chính -->
    <main class="main-container">
        <!-- Dashboard Section -->
        <section id="dashboard-section" class="content-section active">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-content">
                    <h1>Xin chào, Giáo viên <?php echo htmlspecialchars($_SESSION['user']); ?>!</h1>
                    <p>Chúc bạn một ngày giảng dạy hiệu quả và truyền cảm hứng!</p>
                </div>
                <div class="quick-actions">
                    <button class="quick-action-btn" onclick="showSection('classes')">
                        <i class="fas fa-plus-circle"></i> Tạo lớp học
                    </button>
                    <button class="quick-action-btn" onclick="showSection('assignments')">
                        <i class="fas fa-clipboard-list"></i> Giao nhiệm vụ
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-classes">0</h3>
                        <p>Lớp học đang dạy</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-students">0</h3>
                        <p>Tổng học sinh</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="pending-submissions">0</h3>
                        <p>Bài nộp chờ chấm</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-lessons">0</h3>
                        <p>Bài giảng đã tạo</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="recent-activity">
                    <div class="card-header">
                        <h2><i class="fas fa-bell"></i> Hoạt động gần đây</h2>
                    </div>
                    <div class="activity-list" id="recent-activity-list">
                        <p class="empty-state"><i class="fas fa-spinner fa-spin"></i> Đang tải...</p>
                    </div>
                </div>
                
                <div class="upcoming-deadlines">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-alt"></i> Hạn chót sắp tới</h2>
                    </div>
                    <div class="deadlines-list" id="upcoming-deadlines-list">
                        <p class="empty-state"><i class="fas fa-spinner fa-spin"></i> Đang tải...</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Classes Section -->
        <section id="classes-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-book"></i> Lớp học của tôi</h2>
                <button class="btn-primary" onclick="showCreateClassModal()">
                    <i class="fas fa-plus"></i> Tạo lớp học mới
                </button>
            </div>
            <div id="classes-list" class="classes-grid">
                <!-- Classes will be loaded here -->
            </div>
        </section>

        <!-- Class Detail Section -->
        <section id="class-detail-section" class="content-section">
            <div class="class-detail-view">
                <!-- Dynamic content will be loaded here -->
            </div>
        </section>

        <!-- Assignments Section -->
        <section id="assignments-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-tasks"></i> Quản lý Nhiệm vụ</h2>
                <button class="btn btn-primary" onclick="openCreateAssignmentModal()">
                    <i class="fas fa-plus"></i> Tạo nhiệm vụ mới
                </button>
            </div>
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">Tất cả</button>
                <button class="filter-tab" data-filter="active">Đang hoạt động</button>
                <button class="filter-tab" data-filter="upcoming">Sắp diễn ra</button>
                <button class="filter-tab" data-filter="expired">Đã hết hạn</button>
            </div>
            <div id="assignments-list" class="assignments-grid">
                <p class="empty-state">Đang tải...</p>
            </div>
        </section>

        <!-- Academic Products Section - THAY THẾ Submissions Section -->
        <section id="academic-products-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-file-alt"></i> Sản phẩm học thuật của học sinh</h2>
                <div class="filter-group">
                    <select id="products-class-filter" class="filter-select">
                        <option value="">Tất cả lớp học</option>
                    </select>
                    <select id="products-status-filter" class="filter-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="draft">Bản nháp</option>
                        <option value="submitted">Đã nộp</option>
                        <option value="reviewed">Đã chấm</option>
                        <option value="returned">Trả lại</option>
                    </select>
                    <select id="products-type-filter" class="filter-select">
                        <option value="">Tất cả loại</option>
                        <option value="essay">Bài tiểu luận</option>
                        <option value="report">Báo cáo</option>
                        <option value="research">Nghiên cứu</option>
                        <option value="presentation">Thuyết trình</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">Tất cả</button>
                <button class="filter-tab" data-filter="submitted">Chờ chấm</button>
                <button class="filter-tab" data-filter="reviewed">Đã chấm</button>
                <button class="filter-tab" data-filter="draft">Bản nháp</button>
            </div>
            
            <div id="teacher-products-list" class="products-grid">
                <p class="empty-state">Đang tải...</p>
            </div>
        </section>

        <!-- Statistics Section -->
        <section id="statistics-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Thống kê lớp học</h2>
                <select id="stats-class-filter" class="filter-select">
                    <option value="">Chọn lớp học</option>
                </select>
            </div>
            <div id="statistics-content" class="statistics-container">
                <p class="empty-state">Vui lòng chọn lớp học để xem thống kê</p>
            </div>
        </section>

        <!-- Students Section -->
        <section id="students-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Quản lý học sinh</h2>
                <select id="students-class-filter" class="filter-select">
                    <option value="">Chọn lớp học</option>
                </select>
            </div>
            <div id="students-list" class="students-container">
                <p class="empty-state">Vui lòng chọn lớp học để xem danh sách học sinh</p>
            </div>
        </section>
    </main>

    <!-- Modal tạo lớp học -->
    <div id="create-class-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Tạo lớp học mới</h3>
                <button class="modal-close" onclick="closeCreateClassModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-class-form">
                    <div class="form-group">
                        <label for="class-name">Tên lớp học *</label>
                        <input type="text" id="class-name" required placeholder="VD: Tình nguyện 2025">
                    </div>
                    <div class="form-group">
                        <label for="class-description">Mô tả</label>
                        <textarea id="class-description" rows="4" placeholder="Mô tả chi tiết về lớp học..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="class-code">Mã lớp (tùy chọn)</label>
                            <input type="text" id="class-code" placeholder="VD: TN2024">
                        </div>
                        <div class="form-group">
                            <label for="max-students">Số học sinh tối đa</label>
                            <input type="number" id="max-students" min="1" value="50">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="closeCreateClassModal()">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Tạo lớp học
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal tạo nhiệm vụ -->
    <div id="create-assignment-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-clipboard-list"></i> Tạo nhiệm vụ mới</h3>
                <button class="modal-close" onclick="closeCreateAssignmentModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="create-assignment-form">
                    <div class="form-group">
                        <label for="assignment-class">Lớp học *</label>
                        <select id="assignment-class" required>
                            <option value="">Chọn lớp học</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assignment-title">Tiêu đề *</label>
                        <input type="text" id="assignment-title" required placeholder="VD: Bài tập tuần 1">
                    </div>
                    <div class="form-group">
                        <label for="assignment-description">Mô tả</label>
                        <textarea id="assignment-description" rows="4" placeholder="Mô tả chi tiết nhiệm vụ..."></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="assignment-start">Ngày bắt đầu *</label>
                            <input type="datetime-local" id="assignment-start" required>
                        </div>
                        <div class="form-group">
                            <label for="assignment-deadline">Hạn nộp *</label>
                            <input type="datetime-local" id="assignment-deadline" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="assignment-max-score">Điểm tối đa</label>
                        <input type="number" id="assignment-max-score" min="0" value="10">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="closeCreateAssignmentModal()">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Tạo nhiệm vụ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal thêm chương -->
    <div id="add-chapter-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-book"></i> Thêm chương mới</h3>
                <button class="modal-close" onclick="closeAddChapterModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="add-chapter-form">
                    <input type="hidden" id="chapter-class-id">
                    <div class="form-group">
                        <label for="chapter-title">Tiêu đề chương *</label>
                        <input type="text" id="chapter-title" required placeholder="VD: Chương 1: Giới thiệu">
                    </div>
                    <div class="form-group">
                        <label for="chapter-description">Mô tả</label>
                        <textarea id="chapter-description" rows="3" placeholder="Mô tả ngắn gọn về chương..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="chapter-order">Thứ tự</label>
                        <input type="number" id="chapter-order" min="1" value="1">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="closeAddChapterModal()">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Thêm chương
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal thêm bài học -->
    <div id="add-lesson-modal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3><i class="fas fa-file-pdf"></i> Thêm bài học mới</h3>
                <button class="modal-close" onclick="closeAddLessonModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="add-lesson-form" enctype="multipart/form-data">
                    <input type="hidden" id="lesson-chapter-id">
                    <div class="form-group">
                        <label for="lesson-title">Tiêu đề bài học *</label>
                        <input type="text" id="lesson-title" required placeholder="VD: Tình nguyện viên Thái Nguyên">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lesson-type">Loại bài học *</label>
                            <select id="lesson-type" required>
                                <option value="">Chọn loại</option>
                                <option value="theory">Lý thuyết</option>
                                <option value="practice">Thực hành</option>
                                <option value="test">Kiểm tra</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="lesson-order">Thứ tự</label>
                            <input type="number" id="lesson-order" min="1" value="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lesson-min-duration">Thời gian tối thiểu (phút) *</label>
                            <input type="number" id="lesson-min-duration" min="1" value="5" required>
                            <small>Học sinh phải học tối thiểu thời gian này để hoàn thành</small>
                        </div>
                        <div class="form-group">
                            <label for="lesson-max-score">Điểm tối đa (nếu có)</label>
                            <input type="number" id="lesson-max-score" min="0" value="10">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lesson-start-date">Ngày bắt đầu</label>
                            <input type="datetime-local" id="lesson-start-date">
                        </div>
                        <div class="form-group">
                            <label for="lesson-end-date">Ngày kết thúc</label>
                            <input type="datetime-local" id="lesson-end-date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="lesson-description">Mô tả</label>
                        <textarea id="lesson-description" rows="3" placeholder="Mô tả ngắn gọn về bài học..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="lesson-file">Tải lên file PDF *</label>
                        <div class="file-upload-area" id="file-upload-area">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Kéo thả file PDF vào đây hoặc nhấn để chọn</p>
                            <input type="file" id="lesson-file" accept=".pdf" required>
                        </div>
                        <div id="file-preview" class="file-preview" style="display: none;">
                            <i class="fas fa-file-pdf"></i>
                            <span id="file-name"></span>
                            <button type="button" class="remove-file" onclick="removeFile()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="closeAddLessonModal()">Hủy</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Thêm bài học
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal chấm điểm -->
    <div id="grading-modal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Chấm điểm minh chứng</h3>
                <button class="modal-close" onclick="closeGradingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="grading-content">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal thêm học sinh -->
    <div id="add-students-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Thêm học sinh vào lớp</h3>
                <button class="modal-close" onclick="closeAddStudentsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="tab-container">
                    <div class="tab-headers">
                        <button class="tab-btn active" data-tab="id">Thêm bằng ID</button>
                        <button class="tab-btn" data-tab="email">Thêm bằng Email</button>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane active" id="id-tab">
                            <div class="form-group">
                                <label>Danh sách ID học sinh *</label>
                                <textarea id="student-ids" rows="4"
                                    placeholder="Nhập ID học sinh, mỗi ID một dòng hoặc ngăn cách bằng dấu phẩy"></textarea>
                                <small>VD: 123, 456, 789 hoặc mỗi ID một dòng</small>
                            </div>
                        </div>
                        <div class="tab-pane" id="email-tab">
                            <div class="form-group">
                                <label>Danh sách email học sinh *</label>
                                <textarea id="student-emails" rows="4"
                                    placeholder="Nhập email học sinh, mỗi email một dòng"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-outline" onclick="closeAddStudentsModal()">Hủy</button>
                    <button class="btn btn-primary" onclick="handleAddStudents()">
                        <i class="fas fa-save"></i> Thêm học sinh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="export-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-file-export"></i> Xuất danh sách học sinh</h3>
                <span class="close" onclick="closeExportModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Chọn loại xuất dữ liệu:</p>
                <div class="export-options">
                    <button class="btn btn-outline" onclick="handleExport('basic')">
                        <i class="fas fa-list"></i>
                        <span>Thông tin cơ bản</span>
                        <small>Tên, email, ngày tham gia</small>
                    </button>
                    <button class="btn btn-outline" onclick="handleExport('scores')">
                        <i class="fas fa-chart-bar"></i>
                        <span>Xuất kèm điểm</span>
                        <small>Bao gồm điểm TB và tiến độ</small>
                    </button>
                    <button class="btn btn-outline" onclick="handleExport('full')">
                        <i class="fas fa-file-excel"></i>
                        <span>Xuất đầy đủ</span>
                        <small>Tất cả thông tin + điểm từng bài</small>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="footer-placeholder"></div>

    <!-- Load đúng thứ tự -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Load header/footer trước -->
    <script>
        // Load header
        fetch('/components/header.php')
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const links = doc.head.getElementsByTagName('link');
                for (let link of links) {
                    if (!document.querySelector(`link[href="${link.getAttribute('href')}"]`)) {
                        document.head.appendChild(link.cloneNode(true));
                    }
                }
                document.getElementById('header-placeholder').innerHTML = doc.body.innerHTML;
            });

        // Load footer
        fetch('/components/footer.html')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            });
    </script>
    
    <!-- ✅ Load JS files SAU khi DOM ready -->
    <script src="js/teacher-platform.js"></script>
    <script src="../saudn/js/academic-products.js"></script>
    <script src="/components/darkmode.js"></script>

    <script>
        function xacNhanDangXuat() {
            Swal.fire({
                title: 'Xác nhận đăng xuất',
                text: 'Bạn có chắc chắn muốn đăng xuất?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Đăng xuất',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../dangnhap/dangxuat.php';
                }
            });
        }
    </script>
</body>
</html>