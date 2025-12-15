<?php
// filepath: d:\Eduservice\huongdan\huongdan_giaovien.php
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
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
    <title>Hướng dẫn Giáo viên | EDUSERVICE</title>
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
                <h1><i class="fas fa-chalkboard-teacher"></i> Hướng dẫn cho Giáo viên</h1>
                <p class="subtitle">Hướng dẫn chi tiết giúp bạn quản lý lớp học và giảng dạy hiệu quả trên EDUSERVICE</p>
                <div class="user-greeting">
                    <p>Xin chào, <strong><?php echo htmlspecialchars($fullname); ?></strong>! Chào mừng bạn đến với hệ thống quản lý giảng dạy.</p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <div class="nav-tab active" data-section="getting-started">
                    <i class="fas fa-rocket"></i>
                    <span>Bắt đầu</span>
                </div>
                <div class="nav-tab" data-section="class-management">
                    <i class="fas fa-school"></i>
                    <span>Quản lý lớp học</span>
                </div>
                <div class="nav-tab" data-section="assignments">
                    <i class="fas fa-tasks"></i>
                    <span>Bài tập & Dự án</span>
                </div>
                <div class="nav-tab" data-section="grading">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Chấm điểm</span>
                </div>
                <div class="nav-tab" data-section="reports">
                    <i class="fas fa-chart-line"></i>
                    <span>Báo cáo & Thống kê</span>
                </div>
                <div class="nav-tab" data-section="tips">
                    <i class="fas fa-lightbulb"></i>
                    <span>Mẹo hay</span>
                </div>
            </div>

            <!-- Getting Started Section -->
            <section id="getting-started-section" class="content-section active">
                <h2 class="section-title"><i class="fas fa-rocket"></i> Bắt đầu với EDUSERVICE</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3>1. Hoàn thiện hồ sơ</h3>
                        <div class="guide-content">
                            <p>Cập nhật thông tin cá nhân để học sinh dễ dàng liên hệ và tin tưởng:</p>
                            <ul>
                                <li><i class="fas fa-check"></i> Thêm ảnh đại diện chuyên nghiệp</li>
                                <li><i class="fas fa-check"></i> Điền đầy đủ môn dạy và chuyên môn</li>
                                <li><i class="fas fa-check"></i> Thêm mô tả ngắn về phong cách giảng dạy</li>
                                <li><i class="fas fa-check"></i> Cập nhật thông tin liên hệ</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Mẹo:</strong> Hồ sơ đầy đủ giúp tăng 70% sự tin tưởng từ học sinh và phụ huynh</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-school"></i>
                        </div>
                        <h3>2. Tạo lớp học đầu tiên</h3>
                        <div class="guide-content">
                            <p>Bắt đầu bằng cách tạo lớp học của bạn:</p>
                            <ol class="numbered-list">
                                <li>Nhấn vào menu <strong>"Lớp học"</strong></li>
                                <li>Click nút <strong>"+ Tạo lớp học"</strong></li>
                                <li>Điền thông tin: Tên lớp, môn học, mô tả</li>
                                <li>Thiết lập mã lớp hoặc để hệ thống tự tạo</li>
                                <li>Nhấn <strong>"Tạo lớp"</strong> để hoàn tất</li>
                            </ol>
                            <div class="guide-example">
                                <strong>Ví dụ:</strong> Toán 10A1 - Học kỳ 1 - Năm học 2024-2025
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h3>3. Thêm học sinh vào lớp</h3>
                        <div class="guide-content">
                            <p>Có 3 cách để thêm học sinh:</p>
                            <div class="method-cards">
                                <div class="method-card">
                                    <h4><i class="fas fa-qrcode"></i> Mã lớp học</h4>
                                    <p>Chia sẻ mã lớp để học sinh tự tham gia</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-envelope"></i> Email mời</h4>
                                    <p>Gửi email mời trực tiếp đến học sinh</p>
                                </div>
                                <div class="method-card">
                                    <h4><i class="fas fa-file-excel"></i> Import Excel</h4>
                                    <p>Tải lên danh sách từ file Excel</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3>4. Tổ chức nội dung giảng dạy</h3>
                        <div class="guide-content">
                            <p>Cấu trúc nội dung theo hệ thống phân cấp:</p>
                            <div class="hierarchy">
                                <div class="hierarchy-item level-1">
                                    <i class="fas fa-folder"></i> <strong>Chương</strong>
                                    <span class="desc">Nhóm các bài học theo chủ đề lớn</span>
                                </div>
                                <div class="hierarchy-arrow">↓</div>
                                <div class="hierarchy-item level-2">
                                    <i class="fas fa-file-alt"></i> <strong>Bài học</strong>
                                    <span class="desc">Nội dung giảng dạy cụ thể</span>
                                </div>
                                <div class="hierarchy-arrow">↓</div>
                                <div class="hierarchy-item level-3">
                                    <i class="fas fa-tasks"></i> <strong>Bài tập</strong>
                                    <span class="desc">Kiểm tra kiến thức đã học</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Start Video -->
                <div class="video-section">
                    <h3><i class="fas fa-play-circle"></i> Video hướng dẫn nhanh</h3>
                    <div class="video-grid">
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span class="video-duration">5:30</span>
                            </div>
                            <h4>Tạo lớp học đầu tiên</h4>
                            <p>Hướng dẫn từng bước tạo và thiết lập lớp học</p>
                        </div>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span class="video-duration">7:15</span>
                            </div>
                            <h4>Thêm học sinh và phân quyền</h4>
                            <p>Cách thêm học sinh và quản lý quyền truy cập</p>
                        </div>
                        <div class="video-card">
                            <div class="video-thumbnail">
                                <i class="fas fa-play-circle"></i>
                                <span class="video-duration">10:20</span>
                            </div>
                            <h4>Tạo bài giảng đầu tiên</h4>
                            <p>Hướng dẫn tạo và đăng tải bài giảng</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Class Management Section -->
            <section id="class-management-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-school"></i> Quản lý lớp học</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <h3>Tạo và cấu hình lớp học</h3>
                        <div class="guide-content">
                            <h4>Thông tin cơ bản:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> <strong>Tên lớp:</strong> Đặt tên rõ ràng, dễ nhớ</li>
                                <li><i class="fas fa-check"></i> <strong>Môn học:</strong> Chọn môn học phù hợp</li>
                                <li><i class="fas fa-check"></i> <strong>Mô tả:</strong> Thêm thông tin về mục tiêu, yêu cầu</li>
                                <li><i class="fas fa-check"></i> <strong>Ảnh bìa:</strong> Chọn ảnh đại diện cho lớp</li>
                            </ul>
                            <h4>Cài đặt nâng cao:</h4>
                            <ul>
                                <li><i class="fas fa-cog"></i> Cho phép học sinh tự tham gia</li>
                                <li><i class="fas fa-cog"></i> Yêu cầu xác nhận khi tham gia</li>
                                <li><i class="fas fa-cog"></i> Hiển thị điểm công khai</li>
                                <li><i class="fas fa-cog"></i> Bật/tắt tính năng thảo luận</li>
                            </ul>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h3>Quản lý học sinh</h3>
                        <div class="guide-content">
                            <h4>Danh sách học sinh:</h4>
                            <ul>
                                <li><i class="fas fa-eye"></i> Xem thông tin chi tiết từng học sinh</li>
                                <li><i class="fas fa-chart-bar"></i> Theo dõi tiến độ học tập</li>
                                <li><i class="fas fa-user-times"></i> Xóa học sinh khỏi lớp nếu cần</li>
                                <li><i class="fas fa-user-shield"></i> Phân quyền trợ giảng</li>
                            </ul>
                            <div class="guide-tip">
                                <i class="fas fa-lightbulb"></i>
                                <span><strong>Mẹo:</strong> Sử dụng bộ lọc để tìm kiếm học sinh theo tên, điểm, hoặc hoạt động</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-folder-plus"></i>
                        </div>
                        <h3>Tạo chương học</h3>
                        <div class="guide-content">
                            <p>Tổ chức nội dung theo chương để dễ quản lý:</p>
                            <ol class="numbered-list">
                                <li>Vào chi tiết lớp học</li>
                                <li>Chọn tab <strong>"Nội dung"</strong></li>
                                <li>Click <strong>"+ Thêm chương"</strong></li>
                                <li>Nhập tên chương và mô tả</li>
                                <li>Sắp xếp thứ tự hiển thị</li>
                            </ol>
                            <div class="guide-example">
                                <strong>Ví dụ chương:</strong>
                                <ul>
                                    <li>Chương 1: Căn bậc hai</li>
                                    <li>Chương 2: Hàm số bậc nhất</li>
                                    <li>Chương 3: Phương trình bậc hai</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Tạo bài học</h3>
                        <div class="guide-content">
                            <h4>Loại nội dung hỗ trợ:</h4>
                            <div class="content-types">
                                <div class="type-card">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>PDF</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-file-powerpoint"></i>
                                    <span>PowerPoint</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-file-word"></i>
                                    <span>Word</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-video"></i>
                                    <span>Video</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-link"></i>
                                    <span>Link</span>
                                </div>
                                <div class="type-card">
                                    <i class="fas fa-images"></i>
                                    <span>Hình ảnh</span>
                                </div>
                            </div>
                            <h4>Quy trình tạo bài:</h4>
                            <ol class="numbered-list">
                                <li>Chọn chương muốn thêm bài</li>
                                <li>Click <strong>"+ Thêm bài học"</strong></li>
                                <li>Nhập tiêu đề và mô tả</li>
                                <li>Tải lên tài liệu hoặc thêm link</li>
                                <li>Thiết lập thời gian mở/đóng (tùy chọn)</li>
                                <li>Lưu và xuất bản</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Assignments Section -->
            <section id="assignments-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-tasks"></i> Quản lý bài tập & Dự án</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-plus-square"></i>
                        </div>
                        <h3>Tạo bài tập mới</h3>
                        <div class="guide-content">
                            <h4>Các bước tạo bài tập:</h4>
                            <ol class="numbered-list">
                                <li>Vào menu <strong>"Bài tập"</strong></li>
                                <li>Click <strong>"+ Tạo bài tập"</strong></li>
                                <li>Chọn lớp học và chương</li>
                                <li>Nhập tiêu đề và mô tả yêu cầu</li>
                                <li>Đính kèm file hướng dẫn (nếu có)</li>
                                <li>Thiết lập deadline và điểm tối đa</li>
                                <li>Lưu và giao bài</li>
                            </ol>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Lưu ý:</strong> Nên giao bài trước deadline ít nhất 3-5 ngày</span>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <h3>Tạo dự án nhóm</h3>
                        <div class="guide-content">
                            <h4>Thiết lập dự án:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> Chọn loại <strong>"Dự án nhóm"</strong></li>
                                <li><i class="fas fa-check"></i> Thiết lập số thành viên tối đa/nhóm</li>
                                <li><i class="fas fa-check"></i> Cho phép học sinh tự chia nhóm hoặc giáo viên phân chia</li>
                                <li><i class="fas fa-check"></i> Thiết lập mốc thời gian (milestones)</li>
                            </ul>
                            <div class="guide-example">
                                <strong>Ví dụ mốc thời gian:</strong>
                                <ul>
                                    <li>Tuần 1: Nộp đề cương</li>
                                    <li>Tuần 3: Báo cáo tiến độ</li>
                                    <li>Tuần 5: Nộp sản phẩm cuối</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3>Theo dõi bài nộp</h3>
                        <div class="guide-content">
                            <h4>Dashboard bài tập:</h4>
                            <ul>
                                <li><i class="fas fa-chart-pie"></i> Xem tỷ lệ nộp bài</li>
                                <li><i class="fas fa-clock"></i> Theo dõi bài nộp muộn</li>
                                <li><i class="fas fa-download"></i> Tải xuống tất cả bài nộp</li>
                                <li><i class="fas fa-bell"></i> Gửi nhắc nhở tự động</li>
                            </ul>
                            <div class="progress-example">
                                <div class="progress-item completed">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Đã nộp: <strong>25/30</strong></span>
                                </div>
                                <div class="progress-item late">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Nộp muộn: <strong>2/30</strong></span>
                                </div>
                                <div class="progress-item missing">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Chưa nộp: <strong>3/30</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-redo"></i>
                        </div>
                        <h3>Cho phép nộp lại</h3>
                        <div class="guide-content">
                            <p>Học sinh có thể cần nộp lại bài:</p>
                            <ol class="numbered-list">
                                <li>Vào chi tiết bài tập</li>
                                <li>Chọn học sinh cần cho nộp lại</li>
                                <li>Click <strong>"Cho phép nộp lại"</strong></li>
                                <li>Thiết lập deadline mới (tùy chọn)</li>
                                <li>Thêm ghi chú lý do (tùy chọn)</li>
                            </ol>
                            <div class="guide-tip">
                                <i class="fas fa-lightbulb"></i>
                                <span><strong>Mẹo:</strong> Có thể trừ điểm cho bài nộp lại để khuyến khích nộp đúng hạn</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Grading Section -->
            <section id="grading-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Chấm điểm & Đánh giá</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h3>Chấm điểm bài tập</h3>
                        <div class="guide-content">
                            <h4>Quy trình chấm:</h4>
                            <ol class="numbered-list">
                                <li>Vào danh sách bài nộp</li>
                                <li>Click vào từng bài để xem chi tiết</li>
                                <li>Xem file đính kèm của học sinh</li>
                                <li>Nhập điểm số</li>
                                <li>Viết nhận xét chi tiết</li>
                                <li>Lưu và trả bài</li>
                            </ol>
                            <div class="grading-options">
                                <div class="option-card">
                                    <i class="fas fa-star"></i>
                                    <h4>Điểm số</h4>
                                    <p>Nhập điểm từ 0-10 hoặc A-F</p>
                                </div>
                                <div class="option-card">
                                    <i class="fas fa-comment-alt"></i>
                                    <h4>Nhận xét</h4>
                                    <p>Góp ý chi tiết để học sinh cải thiện</p>
                                </div>
                                <div class="option-card">
                                    <i class="fas fa-file-upload"></i>
                                    <h4>File đính kèm</h4>
                                    <p>Đính kèm file chấm chi tiết</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-magic"></i>
                        </div>
                        <h3>Rubric chấm điểm</h3>
                        <div class="guide-content">
                            <p>Tạo rubric để chấm điểm khách quan và nhất quán:</p>
                            <h4>Tạo rubric mới:</h4>
                            <ol class="numbered-list">
                                <li>Trong thiết lập bài tập, chọn <strong>"Tạo Rubric"</strong></li>
                                <li>Thêm các tiêu chí đánh giá</li>
                                <li>Thiết lập thang điểm cho mỗi tiêu chí</li>
                                <li>Thêm mô tả cho từng mức điểm</li>
                                <li>Lưu rubric để sử dụng</li>
                            </ol>
                            <div class="rubric-example">
                                <table>
                                    <tr>
                                        <th>Tiêu chí</th>
                                        <th>Điểm</th>
                                    </tr>
                                    <tr>
                                        <td>Nội dung</td>
                                        <td>40%</td>
                                    </tr>
                                    <tr>
                                        <td>Trình bày</td>
                                        <td>30%</td>
                                    </tr>
                                    <tr>
                                        <td>Sáng tạo</td>
                                        <td>20%</td>
                                    </tr>
                                    <tr>
                                        <td>Đúng hạn</td>
                                        <td>10%</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Phản hồi hiệu quả</h3>
                        <div class="guide-content">
                            <h4>Nguyên tắc viết nhận xét:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> <strong>Cụ thể:</strong> Chỉ rõ điểm tốt và cần cải thiện</li>
                                <li><i class="fas fa-check"></i> <strong>Xây dựng:</strong> Đưa ra gợi ý cải thiện</li>
                                <li><i class="fas fa-check"></i> <strong>Khuyến khích:</strong> Ghi nhận nỗ lực của học sinh</li>
                                <li><i class="fas fa-check"></i> <strong>Kịp thời:</strong> Trả bài trong vòng 3-5 ngày</li>
                            </ul>
                            <div class="guide-example">
                                <strong>Ví dụ nhận xét tốt:</strong>
                                <p class="good-feedback">"Bài làm của em đã trình bày rõ ràng các bước giải. Tuy nhiên, ở bước 3, em cần chú ý kiểm tra lại phép tính. Hãy thử làm thêm các bài tập tương tự để rèn luyện kỹ năng này nhé!"</p>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h3>Chấm điểm nhanh</h3>
                        <div class="guide-content">
                            <h4>Tính năng nâng cao:</h4>
                            <ul>
                                <li><i class="fas fa-list"></i> Chấm hàng loạt: Áp dụng cùng điểm cho nhiều bài</li>
                                <li><i class="fas fa-copy"></i> Sao chép nhận xét: Dùng lại nhận xét phổ biến</li>
                                <li><i class="fas fa-keyboard"></i> Phím tắt: Sử dụng phím tắt để chấm nhanh</li>
                                <li><i class="fas fa-robot"></i> AI hỗ trợ: Gợi ý điểm và nhận xét</li>
                            </ul>
                            <div class="shortcuts-list">
                                <div class="shortcut-item">
                                    <kbd>Ctrl + S</kbd>
                                    <span>Lưu và sang bài tiếp</span>
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Ctrl + Enter</kbd>
                                    <span>Lưu và trả bài</span>
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Ctrl + ←/→</kbd>
                                    <span>Chuyển bài trước/sau</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Reports Section -->
            <section id="reports-section" class="content-section">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Báo cáo & Thống kê</h2>
                
                <div class="feature-guides">
                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>Báo cáo lớp học</h3>
                        <div class="guide-content">
                            <h4>Các loại báo cáo:</h4>
                            <div class="report-types">
                                <div class="report-card">
                                    <i class="fas fa-users"></i>
                                    <h4>Tổng quan lớp học</h4>
                                    <ul>
                                        <li>Số lượng học sinh</li>
                                        <li>Tỷ lệ tham gia</li>
                                        <li>Điểm trung bình</li>
                                    </ul>
                                </div>
                                <div class="report-card">
                                    <i class="fas fa-user-check"></i>
                                    <h4>Báo cáo cá nhân</h4>
                                    <ul>
                                        <li>Chi tiết từng học sinh</li>
                                        <li>Tiến độ học tập</li>
                                        <li>Điểm các bài tập</li>
                                    </ul>
                                </div>
                                <div class="report-card">
                                    <i class="fas fa-tasks"></i>
                                    <h4>Báo cáo bài tập</h4>
                                    <ul>
                                        <li>Tỷ lệ hoàn thành</li>
                                        <li>Điểm trung bình</li>
                                        <li>Phân tích độ khó</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <h3>Xuất báo cáo</h3>
                        <div class="guide-content">
                            <h4>Định dạng hỗ trợ:</h4>
                            <div class="export-formats">
                                <div class="format-card">
                                    <i class="fas fa-file-excel"></i>
                                    <span>Excel</span>
                                </div>
                                <div class="format-card">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>PDF</span>
                                </div>
                                <div class="format-card">
                                    <i class="fas fa-file-csv"></i>
                                    <span>CSV</span>
                                </div>
                            </div>
                            <h4>Cách xuất:</h4>
                            <ol class="numbered-list">
                                <li>Vào phần <strong>"Báo cáo"</strong></li>
                                <li>Chọn loại báo cáo cần xuất</li>
                                <li>Thiết lập bộ lọc (thời gian, học sinh...)</li>
                                <li>Click <strong>"Xuất báo cáo"</strong></li>
                                <li>Chọn định dạng file</li>
                                <li>Tải xuống</li>
                            </ol>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3>Phân tích AI</h3>
                        <div class="guide-content">
                            <h4>AI cung cấp insights:</h4>
                            <ul>
                                <li><i class="fas fa-lightbulb"></i> Xác định học sinh cần hỗ trợ thêm</li>
                                <li><i class="fas fa-chart-line"></i> Dự đoán xu hướng điểm số</li>
                                <li><i class="fas fa-exclamation-triangle"></i> Cảnh báo học sinh có nguy cơ tụt hậu</li>
                                <li><i class="fas fa-trophy"></i> Gợi ý phương pháp cải thiện</li>
                            </ul>
                            <div class="ai-insight-example">
                                <div class="insight-card warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>Cảnh báo:</strong>
                                        <p>3 học sinh có điểm giảm liên tục trong 3 tuần qua</p>
                                    </div>
                                </div>
                                <div class="insight-card success">
                                    <i class="fas fa-chart-line"></i>
                                    <div>
                                        <strong>Xu hướng tích cực:</strong>
                                        <p>Điểm trung bình lớp tăng 15% sau bài giảng mới</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="guide-card">
                        <div class="guide-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Báo cáo định kỳ</h3>
                        <div class="guide-content">
                            <p>Thiết lập báo cáo tự động gửi email:</p>
                            <ol class="numbered-list">
                                <li>Vào <strong>"Cài đặt" → "Báo cáo tự động"</strong></li>
                                <li>Chọn tần suất: Hàng ngày/tuần/tháng</li>
                                <li>Chọn loại báo cáo</li>
                                <li>Nhập email nhận báo cáo</li>
                                <li>Kích hoạt</li>
                            </ol>
                            <div class="guide-tip">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>Mẹo:</strong> Báo cáo tuần giúp bạn theo dõi tiến độ đều đặn mà không mất thời gian</span>
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
                        <h3>Quản lý thời gian hiệu quả</h3>
                        <ul>
                            <li>Đặt lịch chấm bài cố định mỗi tuần</li>
                            <li>Sử dụng template cho nhận xét thường gặp</li>
                            <li>Ưu tiên chấm bài có deadline gần nhất</li>
                            <li>Dành 30 phút/ngày để trả lời thắc mắc</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Tăng tương tác với học sinh</h3>
                        <ul>
                            <li>Tạo thảo luận nhóm mỗi tuần</li>
                            <li>Sử dụng poll để thu thập ý kiến</li>
                            <li>Tổ chức office hours online</li>
                            <li>Khuyến khích học sinh đặt câu hỏi</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h3>Tạo động lực học tập</h3>
                        <ul>
                            <li>Trao huy hiệu cho thành tích xuất sắc</li>
                            <li>Công bố top học sinh tiến bộ nhất</li>
                            <li>Tổ chức mini game/quiz vui</li>
                            <li>Tạo cạnh tranh lành mạnh giữa các nhóm</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3>Bảo mật và An toàn</h3>
                        <ul>
                            <li>Không chia sẻ mã lớp công khai</li>
                            <li>Kiểm tra danh sách học sinh định kỳ</li>
                            <li>Sử dụng password mạnh</li>
                            <li>Bật xác thực 2 yếu tố</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Tối ưu cho Mobile</h3>
                        <ul>
                            <li>Tải app EDUSERVICE trên điện thoại</li>
                            <li>Bật thông báo push để không bỏ lỡ</li>
                            <li>Sử dụng chức năng quét QR</li>
                            <li>Chấm điểm nhanh ngay trên di động</li>
                        </ul>
                    </div>

                    <div class="tip-card">
                        <div class="tip-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Nội dung chất lượng</h3>
                        <ul>
                            <li>Chia nhỏ bài giảng thành phần ngắn</li>
                            <li>Sử dụng nhiều media: video, audio, slides</li>
                            <li>Thêm ví dụ thực tế</li>
                            <li>Cập nhật nội dung thường xuyên</li>
                        </ul>
                    </div>
                </div>

                <!-- Keyboard Shortcuts -->
                <div class="shortcuts-section">
                    <h3><i class="fas fa-keyboard"></i> Phím tắt hữu ích</h3>
                    <div class="shortcuts-grid">
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + K</div>
                            <div class="shortcut-desc">Tạo lớp học mới</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + N</div>
                            <div class="shortcut-desc">Tạo bài tập mới</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + U</div>
                            <div class="shortcut-desc">Upload tài liệu</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + G</div>
                            <div class="shortcut-desc">Chấm điểm nhanh</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + R</div>
                            <div class="shortcut-desc">Xem báo cáo</div>
                        </div>
                        <div class="shortcut-card">
                            <div class="shortcut-key">Ctrl + /</div>
                            <div class="shortcut-desc">Tìm kiếm</div>
                        </div>
                    </div>
                </div>

                <!-- FAQs for Teachers -->
                <div class="faq-section">
                    <h3><i class="fas fa-question-circle"></i> Câu hỏi thường gặp</h3>
                    <div class="faq-list">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Làm thế nào để thêm trợ giảng cho lớp?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Vào chi tiết lớp học → Cài đặt → Thêm trợ giảng → Nhập email → Gửi lời mời. Trợ giảng sẽ có quyền chấm bài và quản lý học sinh.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Có giới hạn số lượng học sinh trong lớp không?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Gói miễn phí: tối đa 50 học sinh/lớp. Gói Premium: không giới hạn. Gói Enterprise: không giới hạn + nhiều tính năng nâng cao.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Làm sao để phát hiện đạo văn?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Hệ thống tích hợp công cụ phát hiện đạo văn tự động. Khi chấm bài, click "Kiểm tra đạo văn" để quét nội dung. Kết quả sẽ hiện tỷ lệ trùng lặp.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Có thể sao lưu dữ liệu lớp học không?</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="faq-answer">
                                Có! Vào Cài đặt lớp → Sao lưu & Xuất dữ liệu → Chọn "Xuất toàn bộ". Hệ thống sẽ tạo file ZIP chứa tất cả tài liệu và dữ liệu.
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
                // Remove active class
                tabs.forEach(t => t.classList.remove('active'));
                sections.forEach(s => s.classList.remove('active'));

                // Add active class
                tab.classList.add('active');
                const sectionId = tab.dataset.section + '-section';
                document.getElementById(sectionId).classList.add('active');

                // Scroll to top of section
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                const isActive = faqItem.classList.contains('active');
                
                // Close all items
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Open clicked item if it wasn't active
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