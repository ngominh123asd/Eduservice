<?php
// File: trangchusaudn.php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user_id'])) {
    header("Location: ../dangnhap/dangnhap.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';
try {
    // Use $pdo from db_config.php directly
    $user_id = $_SESSION['user_id'];
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Học tập | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/trangchusaudn.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="css/academic-products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div id="header-placeholder"></div>

    <div class="sidebar-trigger"></div>
    <div class="sidebar" id="sidebar">
        <div class="menu-container">
            <div class="menu-section">
                <div class="menu-header">
                    Học tập
                </div>
                <div class="menu-items">
                    <div class="menu-item" data-section="tasks">
                        <i class="fas fa-book"></i>
                        <span>Nhiệm vụ</span>
                    </div>
                    <div class="menu-item" data-section="submissions">
                        <i class="fas fa-file-alt"></i>
                        <span>Sản phẩm học thuật</span>
                    </div>
                    <div class="menu-item" data-section="classes">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Lớp học</span>
                    </div>
                </div>
            </div>

            <div class="menu-section">
                <div class="menu-header">
                    Thống kê
                </div>
                <div class="menu-items">
                    <div class="menu-item" data-section="progress">
                        <i class="fas fa-chart-line"></i>
                        <span>Tiến độ học tập</span>
                    </div>
                    <div class="menu-item" data-section="eportfolio">
                        <i class="fas fa-star"></i>
                        <span>E-portfolios</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nội dung chính -->
    <main class="main-container">
        <!-- Dashboard Section -->
        <section id="dashboard-section" class="content-section active">
            <div class="welcome-card">
                <h1>Chào mừng, <?php echo htmlspecialchars($_SESSION['user']); ?>!</h1>
                <p>Chúc bạn một ngày học tập hiệu quả và đầy năng lượng!</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="total-classes">0</h3>
                        <p>Lớp học</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="pending-tasks">0</h3>
                        <p>Nhiệm vụ chưa hoàn thành</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="completed-lessons">0</h3>
                        <p>Bài học đã hoàn thành</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="avg-score">0</h3>
                        <p>Điểm trung bình</p>
                    </div>
                </div>
            </div>

            <div class="recent-activity">
                <div class="activity-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="margin: 0; font-size: 24px; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-history"></i> Hoạt động gần đây
                    </h2>
                    
                    <!-- Filter Buttons -->
                    <div class="activity-filters">
                        <button class="activity-filter-btn active" data-filter="all" onclick="filterActivities('all')">
                            <i class="fas fa-th-large"></i>
                            <span>Tất cả</span>
                        </button>
                        <button class="activity-filter-btn" data-filter="lesson" onclick="filterActivities('lesson')">
                            <i class="fas fa-book-open"></i>
                            <span>Bài học</span>
                        </button>
                        <button class="activity-filter-btn" data-filter="submission" onclick="filterActivities('submission')">
                            <i class="fas fa-file-upload"></i>
                            <span>Nộp bài</span>
                        </button>
                        <button class="activity-filter-btn" data-filter="enrollment" onclick="filterActivities('enrollment')">
                            <i class="fas fa-user-plus"></i>
                            <span>Tham gia</span>
                        </button>
                    </div>
                </div>
                
                <div class="activity-list" id="recent-activity-list">
                    <!-- Activities will be loaded here by JavaScript -->
                </div>
            </div>
        </section>

        <!-- Tasks Section -->
        <section id="tasks-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-tasks"></i> Nhiệm vụ của tôi</h2>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">Tất cả</button>
                    <button class="filter-tab" data-filter="pending">Chưa hoàn thành</button>
                    <button class="filter-tab" data-filter="completed">Đã hoàn thành</button>
                    <button class="filter-tab" data-filter="overdue">Quá hạn</button>
                </div>
            </div>
            <div id="tasks-list" class="tasks-grid">
                <p class="empty-state">Đang tải...</p>
            </div>
        </section>

        <!-- Submissions Section -->
        <section id="submissions-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-file-alt"></i> Sản phẩm học thuật</h2>
                <button class="btn btn-primary" onclick="createNewProduct()">
                    <i class="fas fa-plus"></i> Tạo sản phẩm mới
                </button>
            </div>

            <div class="products-filter">
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">Tất cả</button>
                    <button class="filter-tab" data-filter="draft">Bản nháp</button>
                    <button class="filter-tab" data-filter="submitted">Đã nộp</button>
                    <button class="filter-tab" data-filter="reviewed">Đã chấm</button>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Tìm kiếm sản phẩm..." id="product-search">
                </div>
            </div>

            <div id="products-list" class="products-grid">
                <p class="empty-state">Đang tải...</p>
            </div>
        </section>

        <!-- Classes Section -->
        <section id="classes-section" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-book"></i> Lớp học của tôi</h2>
            </div>
            <div id="classes-list" class="classes-grid">
                <p class="empty-state">Đang tải...</p>
            </div>
        </section>

        <!-- E-Portfolio Section -->
        <section id="eportfolio-section" class="content-section">
            <!-- Content will be loaded dynamically -->
        </section>
    </main>

    <!-- Modal xem bài học - CẬP NHẬT -->
    <div id="lesson-modal" class="modal">
        <div class="modal-content lesson-viewer">
            <div class="modal-header">
                <div class="lesson-viewer-header">
                    <div class="lesson-viewer-header-left">
                        <h3 id="current-lesson-title">Tiêu đề bài học</h3>
                        <div class="lesson-meta-tags">
                            <span class="lesson-type-tag theory" id="current-lesson-type">
                                <i class="fas fa-book"></i>
                                Lý thuyết
                            </span>
                            <span class="lesson-time-tag">
                                <i class="far fa-clock"></i>
                                <span id="current-lesson-time">0:00</span>
                            </span>
                        </div>
                    </div>
                    <div class="lesson-viewer-header-right">
                        <button class="btn-toggle-ai" id="btn-toggle-ai" onclick="toggleAIAssistant()">
                            <i class="fas fa-robot"></i>
                            <span>AI Assistant</span>
                        </button>
                        <button class="btn-complete-lesson" id="btn-complete-lesson" onclick="completeLesson()">
                            <i class="fas fa-check"></i>
                            <span>Hoàn thành</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div class="lesson-info">
                    <span class="lesson-type" id="lesson-type-badge"></span>
                    <span class="lesson-time">Thời gian tối thiểu: <strong id="min-duration">0</strong> phút</span>
                    <span class="lesson-time">Thời gian đã học: <strong id="time-spent">0</strong> phút</span>
                </div>

                <div class="lesson-content-wrapper">
                    <!-- PDF Viewer -->
                    <div class="pdf-viewer" id="pdf-viewer-container">
                        <iframe id="pdf-frame" src="" frameborder="0"></iframe>
                    </div>

                    <!-- AI Lesson Assistant Panel -->
                    <div class="ai-lesson-assistant" id="ai-lesson-assistant">
                        <div class="ai-lesson-header">
                            <div class="ai-lesson-header-info">
                                <i class="fas fa-magic"></i>
                                <div>
                                    <h4>AI Assistant</h4>
                                    <span>Tóm tắt & Hỗ trợ học tập</span>
                                </div>
                            </div>
                            <button class="ai-lesson-close" id="ai-lesson-close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="ai-lesson-tabs">
                            <button class="ai-tab active" data-tab="summary">
                                <i class="fas fa-file-alt"></i> Tóm tắt
                            </button>
                            <button class="ai-tab" data-tab="highlights">
                                <i class="fas fa-star"></i> Điểm nổi bật
                            </button>
                            <button class="ai-tab" data-tab="quiz">
                                <i class="fas fa-question-circle"></i> Câu hỏi
                            </button>
                        </div>

                        <div class="ai-lesson-content" id="ai-lesson-content">
                            <!-- Tab Summary -->
                            <div class="ai-tab-content active" data-tab-content="summary">
                                <div class="ai-loading" id="ai-summary-loading">
                                    <div class="ai-loading-spinner"></div>
                                    <p>Đang phân tích nội dung...</p>
                                </div>
                                <div class="ai-summary-result" id="ai-summary-result" style="display: none;">
                                    <!-- AI summary will be inserted here -->
                                </div>
                            </div>

                            <!-- Tab Highlights -->
                            <div class="ai-tab-content" data-tab-content="highlights">
                                <div class="ai-highlights-list" id="ai-highlights-list">
                                    <!-- AI highlights will be inserted here -->
                                </div>
                            </div>

                            <!-- Tab Quiz -->
                            <div class="ai-tab-content" data-tab-content="quiz">
                                <div class="ai-quiz-container" id="ai-quiz-container">
                                    <!-- AI quiz will be inserted here -->
                                </div>
                            </div>
                        </div>

                        <div class="ai-lesson-footer">
                            <button class="btn-ai-regenerate" id="btn-ai-regenerate">
                                <i class="fas fa-sync-alt"></i> Tạo lại
                            </button>
                            <button class="btn-ai-export" id="btn-ai-export">
                                <i class="fas fa-download"></i> Xuất tóm tắt
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lesson-actions">
                    <button class="btn btn-success" id="complete-lesson-btn" disabled>
                        <i class="fas fa-check"></i> Hoàn thành bài học
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Chat Widget -->
    <div id="ai-chat-widget" class="ai-widget">
        <button class="ai-widget-toggle" id="ai-widget-toggle">
            <i class="fas fa-robot"></i>
            <span class="ai-pulse"></span>
        </button>

        <div class="ai-chat-container" id="ai-chat-container">
            <div class="ai-chat-header">
                <div class="ai-chat-header-info">
                    <div class="ai-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-header-text">
                        <h4>Trợ lý AI</h4>
                        <span class="ai-status">Sẵn sàng hỗ trợ</span>
                    </div>
                </div>
                <button class="ai-chat-close" id="ai-chat-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="ai-chat-messages" id="ai-chat-messages">
                <div class="ai-message ai-message-bot">
                    <div class="ai-message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-message-content">
                        <p>Xin chào! Tôi là trợ lý AI của bạn. Tôi có thể giúp bạn:</p>
                        <ul>
                            <li>Tìm kiếm thông tin về lớp học</li>
                            <li>Hỗ trợ làm bài tập</li>
                            <li>Trả lời câu hỏi về nội dung học</li>
                            <li>Theo dõi tiến độ học tập</li>
                        </ul>
                        <p>Bạn cần giúp gì không?</p>
                    </div>
                </div>
            </div>

            <div class="ai-chat-suggestions">
                <button class="ai-suggestion-btn" data-suggestion="Lớp học của tôi">
                    <i class="fas fa-book"></i> Lớp học của tôi
                </button>
                <button class="ai-suggestion-btn" data-suggestion="Nhiệm vụ chưa hoàn thành">
                    <i class="fas fa-tasks"></i> Nhiệm vụ
                </button>
                <button class="ai-suggestion-btn" data-suggestion="Điểm số của tôi">
                    <i class="fas fa-chart-line"></i> Điểm số
                </button>
            </div>

            <div class="ai-chat-input-wrapper">
                <div class="ai-typing-indicator" id="ai-typing-indicator">
                    <span></span><span></span><span></span>
                </div>
                <form class="ai-chat-input-form" id="ai-chat-form">
                    <input
                        type="text"
                        class="ai-chat-input"
                        id="ai-chat-input"
                        placeholder="Nhập câu hỏi của bạn..."
                        autocomplete="off">
                    <button type="submit" class="ai-chat-send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="footer-placeholder"></div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/learning-platform.js"></script>
    <script src="js/academic-products.js"></script>
    <script src="/components/darkmode.js"></script>
    <script src="js/eportfolio.js"></script>
    <script src="js/ai-chat.js"></script>
    <script src="js/ai-lesson-assistant.js"></script>
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

        // Add logout confirmation function
        function xacNhanDangXuat() {
            Swal.fire({
                title: 'Bạn có chắc chắn muốn đăng xuất?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Đăng xuất',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#2E7D32',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../dangnhap/dangxuat.php";
                }
            });
        }
    </script>
</body>
</html>