<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: /dangnhap/dangnhap.html");
    exit();
}

$fullname = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hướng dẫn Học sinh | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="css/guide.css">
</head>
<body>
    <div id="header-placeholder"></div>

    <main>
        <div class="container">
            <!-- Header -->
            <div class="guide-header">
                <h1><i class="fas fa-user-graduate"></i> Hướng dẫn cho Học sinh</h1>
                <p class="subtitle">Hướng dẫn chi tiết giúp bạn sử dụng hiệu quả các tính năng của EDUSERVICE</p>
                <div class="user-greeting">
                    <p>Xin chào, <strong><?php echo htmlspecialchars($fullname); ?></strong>! Chúc bạn học tập hiệu quả.</p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <div class="nav-tab active" data-section="basics">
                    <i class="fas fa-rocket"></i>
                    <span>Bắt đầu</span>
                </div>
                <div class="nav-tab" data-section="classes">
                    <i class="fas fa-school"></i>
                    <span>Lớp học</span>
                </div>
                <div class="nav-tab" data-section="assignments">
                    <i class="fas fa-tasks"></i>
                    <span>Bài tập</span>
                </div>
                <div class="nav-tab" data-section="learning">
                    <i class="fas fa-book-reader"></i>
                    <span>Học tập</span>
                </div>
                <div class="nav-tab" data-section="eportfolio">
                    <i class="fas fa-folder-open"></i>
                    <span>E-Portfolio</span>
                </div>
                <div class="nav-tab" data-section="tips">
                    <i class="fas fa-lightbulb"></i>
                    <span>Mẹo hay</span>
                </div>
            </div>

            <!-- Basics Section -->
            <section id="basics-section" class="content-section active">
                <h2 class="section-title"><i class="fas fa-rocket"></i> Bắt đầu với EDUSERVICE</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3>1. Hoàn thiện hồ sơ</h3>
                        <div class="guide-content">
                            <p>Cập nhật thông tin cá nhân để giáo viên dễ dàng nhận biết bạn:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Thêm ảnh đại diện</li>
                                <li><i class="fas fa-check"></i> Điền đầy đủ họ tên và lớp</li>
                                <li><i class="fas fa-check"></i> Thêm email liên hệ</li>
                                <li><i class="fas fa-check"></i> Cập nhật sở thích và mục tiêu học tập</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Mẹo:</strong> Hồ sơ đầy đủ giúp giáo viên hiểu rõ bạn hơn và hỗ trợ tốt hơn</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-compass"></i>
                        </div>
                        <h3>2. Khám phá giao diện</h3>
                        <div class="guide-content">
                            <p>Làm quen với các phần chính của hệ thống:</p>
                            <div class="method-cards">
                                <div class="method-card">
                                    <h4><i class="fas fa-home"></i> Trang chủ</h4>
                                    <p>Xem tổng quan hoạt động và thông báo</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-school"></i> Lớp học</h4>
                                    <p>Truy cập các lớp đã tham gia</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-tasks"></i> Bài tập</h4>
                                    <p>Quản lý bài tập được giao</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>3. Thiết lập thông báo</h3>
                        <div class="guide-content">
                            <p>Bật thông báo để không bỏ lỡ thông tin quan trọng:</p>
                            <ol class="numbered-list">
                                <li>Vào <strong>Cài đặt → Thông báo</strong></li>
                                <li>Chọn loại thông báo muốn nhận</li>
                                <li>Thiết lập thời gian nhận thông báo</li>
                                <li>Bật thông báo email (nếu muốn)</li>
                            </ol>
                            <div class="guide-example">
                                <strong>Nên bật thông báo cho:</strong>
                                <ul>
                                    <li>Bài tập mới được giao</li>
                                    <li>Deadline sắp đến</li>
                                    <li>Giáo viên trả bài</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h3>4. Sử dụng AI Assistant</h3>
                        <div class="guide-content">
                            <p>AI Assistant giúp bạn học tập hiệu quả hơn:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Giải đáp thắc mắc về bài học</li>
                                <li><i class="fas fa-check"></i> Gợi ý cách giải bài tập</li>
                                <li><i class="fas fa-check"></i> Tóm tắt nội dung dài</li>
                                <li><i class="fas fa-check"></i> Kiểm tra văn bản</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-lightbulb"></i>
                                <span><strong>Phím tắt:</strong> Nhấn <kbd>Ctrl + K</kbd> để mở AI Assistant bất cứ lúc nào</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Start Video -->
                <div class="video-section">
                    <h3><i class="fas fa-play-circle"></i> Video hướng dẫn nhanh</h3>
                    <div class="video-grid">
                        <div class="video-card" onclick="playVideo('intro')">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span class="video-duration">3:45</span>
                            </div>
                            <h4>Giới thiệu EDUSERVICE</h4>
                            <p>Tổng quan về hệ thống và các tính năng chính</p>
                        </div>
                        <div class="video-card" onclick="playVideo('join-class')">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span class="video-duration">5:20</span>
                            </div>
                            <h4>Tham gia lớp học</h4>
                            <p>Hướng dẫn cách tham gia và sử dụng lớp học</p>
                        </div>
                        <div class="video-card" onclick="playVideo('submit')">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span class="video-duration">4:30</span>
                            </div>
                            <h4>Nộp bài tập</h4>
                            <p>Cách nộp bài và theo dõi kết quả</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Classes Section -->
            <section id="classes-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-school"></i> Quản lý Lớp học</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3>Tham gia lớp học</h3>
                        <div class="guide-content">
                            <h4>Có 2 cách để tham gia:</h4>
                            <div class="method-cards">
                                <div class="method-card">
                                    <h4><i class="fas fa-key"></i> Nhập mã lớp</h4>
                                    <p>Nhập mã do giáo viên cung cấp</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-envelope"></i> Email mời</h4>
                                    <p>Click vào link trong email mời</p>
                                </div>
                            </div>
                            <h4>Quy trình tham gia:</h4>
                            <ol class="numbered-list">
                                <li>Vào menu <strong>"Lớp học"</strong></li>
                                <li>Click <strong>"+ Tham gia lớp"</strong></li>
                                <li>Nhập mã lớp hoặc click link mời</li>
                                <li>Xác nhận thông tin</li>
                                <li>Hoàn tất</li>
                            </ol>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Xem nội dung bài học</h3>
                        <div class="guide-content">
                            <p>Truy cập và học các bài giảng:</p>
                            <ol class="numbered-list">
                                <li>Vào lớp học cần học</li>
                                <li>Chọn tab <strong>"Nội dung"</strong></li>
                                <li>Click vào chương muốn học</li>
                                <li>Chọn bài học cụ thể</li>
                                <li>Xem tài liệu và video</li>
                            </ol>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Lưu ý:</strong> Một số bài học có thể yêu cầu hoàn thành bài trước mới được mở</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Thảo luận trong lớp</h3>
                        <div class="guide-content">
                            <h4>Tham gia thảo luận:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> Đặt câu hỏi về bài học</li>
                                <li><i class="fas fa-check"></i> Trả lời câu hỏi bạn bè</li>
                                <li><i class="fas fa-check"></i> Chia sẻ tài liệu hữu ích</li>
                                <li><i class="fas fa-check"></i> Thảo luận nhóm dự án</li>
                            </ul>
                            <div class="guide-example">
                                <strong>Nguyên tắc thảo luận:</strong>
                                <ul>
                                    <li>Lịch sự và tôn trọng</li>
                                    <li>Không spam</li>
                                    <li>Nội dung liên quan đến bài học</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Theo dõi tiến độ</h3>
                        <div class="guide-content">
                            <p>Xem báo cáo học tập của bạn:</p>
                            <div class="progress-example">
                                <div class="progress-item completed">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Bài học hoàn thành: <strong>15/20</strong></span>
                                </div>
                                <div class="progress-item late">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Bài tập đang làm: <strong>3</strong></span>
                                </div>
                                <div class="progress-item missing">
                                    <i class="fas fa-star"></i>
                                    <span>Điểm trung bình: <strong>8.5/10</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Assignments Section -->
            <section id="assignments-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-tasks"></i> Quản lý Bài tập</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>Xem bài tập được giao</h3>
                        <div class="guide-content">
                            <p>Theo dõi tất cả bài tập của bạn:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Xem danh sách bài tập theo lớp</li>
                                <li><i class="fas fa-check"></i> Lọc theo trạng thái: Mới, Đang làm, Đã nộp</li>
                                <li><i class="fas fa-check"></i> Sắp xếp theo deadline</li>
                                <li><i class="fas fa-check"></i> Xem chi tiết yêu cầu bài tập</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-lightbulb"></i>
                                <span><strong>Mẹo:</strong> Ưu tiên làm bài có deadline gần nhất trước</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-file-upload"></i>
                        </div>
                        <h3>Nộp bài tập</h3>
                        <div class="guide-content">
                            <h4>Các bước nộp bài:</h4>
                            <ol class="numbered-list">
                                <li>Mở chi tiết bài tập</li>
                                <li>Click <strong>"Nộp bài"</strong></li>
                                <li>Chọn file từ máy tính (hoặc kéo thả)</li>
                                <li>Thêm ghi chú (nếu cần)</li>
                                <li>Kiểm tra lại</li>
                                <li>Click <strong>"Xác nhận nộp"</strong></li>
                            </ol>
                            <div class="content-types">
                                <div class="type-card">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>PDF</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-file-word"></i>
                                    <span>Word</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-file-powerpoint"></i>
                                    <span>PPT</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-file-archive"></i>
                                    <span>ZIP</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-images"></i>
                                    <span>Image</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-redo"></i>
                        </div>
                        <h3>Nộp lại bài tập</h3>
                        <div class="guide-content">
                            <p>Nếu giáo viên cho phép, bạn có thể nộp lại:</p>
                            <ol class="numbered-list">
                                <li>Vào bài tập đã nộp</li>
                                <li>Kiểm tra nhận xét của giáo viên</li>
                                <li>Cải thiện theo góp ý</li>
                                <li>Click <strong>"Nộp lại"</strong></li>
                                <li>Upload file mới</li>
                            </ol>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Lưu ý:</strong> Nộp lại có thể bị trừ điểm hoặc có deadline riêng</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Làm việc nhóm</h3>
                        <div class="guide-content">
                            <h4>Với bài tập nhóm:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> Tạo nhóm hoặc tham gia nhóm có sẵn</li>
                                <li><i class="fas fa-check"></i> Phân công công việc rõ ràng</li>
                                <li><i class="fas fa-check"></i> Sử dụng chat nhóm để thảo luận</li>
                                <li><i class="fas fa-check"></i> Một người nộp bài thay mặt nhóm</li>
                            </ul>
                            <div class="guide-example">
                                <strong>Mẹo làm việc nhóm hiệu quả:</strong>
                                <ul>
                                    <li>Họp nhóm định kỳ</li>
                                    <li>Chia sẻ tài liệu qua Google Drive</li>
                                    <li>Cập nhật tiến độ thường xuyên</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Learning Section -->
            <section id="learning-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-book-reader"></i> Công cụ Học tập</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-sticky-note"></i>
                        </div>
                        <h3>Ghi chú thông minh</h3>
                        <div class="guide-content">
                            <p>Tạo ghi chú ngay trong bài học:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Highlight văn bản quan trọng</li>
                                <li><i class="fas fa-check"></i> Thêm ghi chú riêng</li>
                                <li><i class="fas fa-check"></i> Đánh dấu trang</li>
                                <li><i class="fas fa-check"></i> Xuất ghi chú ra file</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-keyboard"></i>
                                <span><strong>Phím tắt:</strong> <kbd>Ctrl + H</kbd> để highlight, <kbd>Ctrl + N</kbd> để tạo ghi chú</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3>Flashcards học tập</h3>
                        <div class="guide-content">
                            <p>Tạo và ôn tập với flashcards:</p>
                            <ol class="numbered-list">
                                <li>Vào phần <strong>"Học tập"</strong></li>
                                <li>Chọn <strong>"Flashcards"</strong></li>
                                <li>Tạo bộ thẻ mới hoặc dùng có sẵn</li>
                                <li>Thêm thuật ngữ và định nghĩa</li>
                                <li>Ôn tập theo chế độ</li>
                            </ol>
                            <div class="method-cards">
                                <div class="method-card">
                                    <h4><i class="fas fa-random"></i> Luyện tập</h4>
                                    <p>Xem ngẫu nhiên</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-gamepad"></i> Trò chơi</h4>
                                    <p>Học qua mini game</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-clock"></i> Kiểm tra</h4>
                                    <p>Thi thử có giới hạn thời gian</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>Lịch học tập</h3>
                        <div class="guide-content">
                            <p>Quản lý thời gian học hiệu quả:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Xem lịch bài tập, deadline</li>
                                <li><i class="fas fa-check"></i> Đặt lịch nhắc nhở</li>
                                <li><i class="fas fa-check"></i> Tạo kế hoạch học tập</li>
                                <li><i class="fas fa-check"></i> Sync với Google Calendar</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-lightbulb"></i>
                                <span><strong>Mẹo:</strong> Đặt nhắc nhở trước deadline 1-2 ngày để có thời gian chuẩn bị</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3>Thống kê học tập</h3>
                        <div class="guide-content">
                            <p>Theo dõi tiến độ của bạn:</p>
                            <div class="report-types">
                                <div class="report-card">
                                    <i class="fas fa-trophy"></i>
                                    <h4>Thành tích</h4>
                                    <ul>
                                        <li>Huy hiệu đạt được</li>
                                        <li>Streak học tập</li>
                                        <li>Xếp hạng lớp</li>
                                    </ul>
                                </div>
                                <div class="report-card">
                                    <i class="fas fa-chart-line"></i>
                                    <h4>Tiến độ</h4>
                                    <ul>
                                        <li>Bài học hoàn thành</li>
                                        <li>Điểm trung bình</li>
                                        <li>Thời gian học</li>
                                    </ul>
                                </div>
                                <div class="report-card">
                                    <i class="fas fa-bullseye"></i>
                                    <h4>Mục tiêu</h4>
                                    <ul>
                                        <li>Đặt mục tiêu</li>
                                        <li>Theo dõi tiến trình</li>
                                        <li>Nhận phần thưởng</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- E-Portfolio Section -->
            <section id="eportfolio-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-folder-open"></i> E-Portfolio của bạn</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <h3>Tạo E-Portfolio</h3>
                        <div class="guide-content">
                            <p>Xây dựng hồ sơ học tập trực tuyến:</p>
                            <ol class="numbered-list">
                                <li>Vào menu <strong>"E-Portfolio"</strong></li>
                                <li>Click <strong>"Tạo Portfolio mới"</strong></li>
                                <li>Chọn template hoặc tạo từ đầu</li>
                                <li>Thêm thông tin cá nhân</li>
                                <li>Upload các dự án, chứng chỉ</li>
                                <li>Xuất bản</li>
                            </ol>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Lợi ích:</strong> Portfolio giúp bạn giới thiệu bản thân khi nộp đơn học bổng, xin việc</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <h3>Thêm dự án</h3>
                        <div class="guide-content">
                            <p>Showcase các dự án của bạn:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Upload ảnh minh họa</li>
                                <li><i class="fas fa-check"></i> Viết mô tả chi tiết</li>
                                <li><i class="fas fa-check"></i> Thêm link demo (nếu có)</li>
                                <li><i class="fas fa-check"></i> Tag kỹ năng sử dụng</li>
                            </ul>
                            <div class="guide-example">
                                <strong>Ví dụ dự án:</strong>
                                <ul>
                                    <li>Website cá nhân</li>
                                    <li>Bài thuyết trình xuất sắc</li>
                                    <li>Dự án khoa học</li>
                                    <li>Hoạt động tình nguyện</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h3>Quản lý chứng chỉ</h3>
                        <div class="guide-content">
                            <p>Lưu trữ các chứng chỉ, giải thưởng:</p>
                            <ol class="numbered-list">
                                <li>Vào tab <strong>"Chứng chỉ"</strong></li>
                                <li>Click <strong>"Thêm chứng chỉ"</strong></li>
                                <li>Upload file chứng chỉ</li>
                                <li>Điền thông tin: Tên, tổ chức, ngày cấp</li>
                                <li>Lưu lại</li>
                            </ol>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-share-alt"></i>
                        </div>
                        <h3>Chia sẻ Portfolio</h3>
                        <div class="guide-content">
                            <p>Chia sẻ hồ sơ của bạn:</p>
                            <div class="export-formats">
                                <div class="format-card">
                                    <i class="fas fa-link"></i>
                                    <span>Link</span>
                                </div>
                                <div class="format-card">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>PDF</span>
                                </div>
                                <div class="format-card">
                                    <i class="fas fa-qrcode"></i>
                                    <span>QR Code</span>
                                </div>
                            </div>
                            <div class="guide-tip">
                                <i class="fas fa-lightbulb"></i>
                                <span><strong>Mẹo:</strong> Thêm link portfolio vào CV của bạn để nổi bật hơn</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Tips Section -->
            <section id="tips-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-lightbulb"></i> Mẹo & Thủ thuật</h2>
                
                <div class="tips-grid">
                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>Quản lý thời gian</h3>
                        <ul>
                            <li>Lập kế hoạch học tập hàng tuần</li>
                            <li>Ưu tiên bài khó trước</li>
                            <li>Nghỉ ngơi 10 phút sau mỗi 50 phút học</li>
                            <li>Không trì hoãn đến phút chót</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3>Học hiệu quả</h3>
                        <ul>
                            <li>Ghi chép bằng tay giúp nhớ lâu hơn</li>
                            <li>Dạy lại cho người khác để hiểu sâu</li>
                            <li>Làm bài tập ngay sau khi học lý thuyết</li>
                            <li>Ôn tập đều đặn thay vì học dồn</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Học nhóm</h3>
                        <ul>
                            <li>Tìm nhóm học phù hợp</li>
                            <li>Chia sẻ ghi chú và tài liệu</li>
                            <li>Hỏi đáp lẫn nhau</li>
                            <li>Cùng làm dự án nhóm</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3>Chăm sóc bản thân</h3>
                        <ul>
                            <li>Ngủ đủ 7-8 tiếng mỗi ngày</li>
                            <li>Ăn uống lành mạnh</li>
                            <li>Tập thể dục đều đặn</li>
                            <li>Giảm căng thẳng qua thiền, yoga</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Học trên di động</h3>
                        <ul>
                            <li>Tải app EDUSERVICE trên điện thoại</li>
                            <li>Học mọi lúc mọi nơi</li>
                            <li>Dùng thời gian rảnh hiệu quả</li>
                            <li>Sync dữ liệu tự động</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Tự động lực</h3>
                        <ul>
                            <li>Đặt mục tiêu ngắn hạn, dài hạn</li>
                            <li>Thưởng cho bản thân khi đạt mục tiêu</li>
                            <li>Theo dõi tiến bộ hàng ngày</li>
                            <li>Tìm bạn học cùng để động viên nhau</li>
                        </ul>
                    </div>
                </div>

                <!-- Keyboard Shortcuts -->
                <div class="shortcuts-section">
                    <h3><i class="fas fa-keyboard"></i> Phím tắt hữu ích</h3>
                    <div class="shortcuts-grid">
                        <div class="shortcut-card">
                            <div class="shortcut-key">Alt + H</div>
                            <div class="shortcut-desc">Về trang chủ</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Alt + C</div>
                            <div class="shortcut-desc">Xem lớp học</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Alt + A</div>
                            <div class="shortcut-desc">Xem bài tập</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Alt + P</div>
                            <div class="shortcut-desc">Mở E-Portfolio</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + K</div>
                            <div class="shortcut-desc">Mở AI Assistant</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + /</div>
                            <div class="shortcut-desc">Tìm kiếm</div>
                        </div>
                    </div>
                </div>

                <!-- FAQs -->
                <div class="faq-section">
                    <h3><i class="fas fa-question-circle"></i> Câu hỏi thường gặp</h3>
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Tôi quên mật khẩu, phải làm sao?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Click "Quên mật khẩu" ở trang đăng nhập → Nhập email → Kiểm tra email và làm theo hướng dẫn để đặt lại mật khẩu.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Làm thế nào để nộp bài khi quá deadline?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Bạn cần liên hệ trực tiếp với giáo viên để xin phép nộp muộn. Giáo viên có thể cho phép nộp lại với điều kiện cụ thể.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>File nộp bài có giới hạn dung lượng không?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Mỗi file tối đa 50MB. Nếu file lớn hơn, bạn có thể nén lại hoặc upload lên Google Drive và gửi link.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Làm sao để liên hệ với giáo viên?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Bạn có thể gửi tin nhắn trực tiếp qua hệ thống, comment trong lớp học, hoặc gửi email (nếu giáo viên cung cấp).
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Contact -->
                <div class="support-section">
                    <div class="support-card">
                        <i class="fas fa-headset"></i>
                        <h3>Cần hỗ trợ thêm?</h3>
                        <p>Đội ngũ hỗ trợ của chúng tôi luôn sẵn sàng giúp đỡ bạn</p>
                        <div class="support-buttons">
                            <a href="mailto:support@eduservice.vn" class="support-btn">
                                <i class="fas fa-envelope"></i>
                                <span>Email hỗ trợ</span>
                            </a>
                            <a href="tel:1900xxxx" class="support-btn">
                                <i class="fas fa-phone"></i>
                                <span>Hotline: 1900-xxxx</span>
                            </a>
                            <a href="#" class="support-btn">
                                <i class="fas fa-comments"></i>
                                <span>Chat trực tuyến</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div id="footer-placeholder"></div>

    <script>
        // Load header
        fetch('/components/header.php')
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                
                const links = doc.head.getElementsByTagName('link');
                for(let link of links) {
                    if(!document.querySelector(`link[href="${link.getAttribute('href')}"]`)) {
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

        // Tab navigation
        const tabs = document.querySelectorAll('.nav-tab');
        const sections = document.querySelectorAll('.content-section');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                sections.forEach(s => s.classList.remove('active'));

                tab.classList.add('active');
                const sectionId = tab.dataset.section + '-section';
                document.getElementById(sectionId).classList.add('active');

                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                const isActive = faqItem.classList.contains('active');
                
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                if (!isActive) {
                    faqItem.classList.add('active');
                }
            });
        });

        // Logout confirmation
        function xacNhanDangXuat() {
            if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                window.location.href = '/dangnhap/dangxuat.php';
            }
        }
    </script>
    <script src="/components/darkmode.js"></script>
    <script src="js/student-guide.js"></script>
</body>
</html>