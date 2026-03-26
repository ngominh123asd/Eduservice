<?php
session_start();

// Redirect if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'teacher') {
        header("Location: ../giaovien/trangchu_giaovien.php");
    } else {
        header("Location: ../saudn/trangchusaudn.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Đăng ký | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dangky.css">
    <link rel="stylesheet" href="/components/header.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div id="header-placeholder"></div>
    
    <div class="page-content">
        <div class="login-container">
            <h2>ĐĂNG KÝ TÀI KHOẢN (HỌC SINH)</h2>
            <form id="registerForm" action="xulydangky.php" method="POST">
                <div class="input-group">
                    <input 
                        type="text" 
                        id="fullname" 
                        name="fullname" 
                        placeholder="Họ và tên" 
                        required
                        autocomplete="name"
                    >
                    <i class="fas fa-user"></i>
                </div>
                <div class="input-group">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Email VNU (@vnu.edu.vn)" 
                        required
                        autocomplete="email"
                    >
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="input-group">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Mật khẩu" 
                        required
                        autocomplete="new-password"
                    >
                    <i class="fas fa-lock"></i>
                </div>
                <div class="input-group">
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Xác nhận mật khẩu" 
                        required
                        autocomplete="new-password"
                    >
                    <i class="fas fa-lock"></i>
                </div>
                <button type="submit">Đăng ký</button>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="/dangnhap/dangnhap.php" style="color: #2e7d32; text-decoration: none; font-weight: bold; font-size: 15px;">Đã có tài khoản? Đăng nhập ngay</a>
                </div>
            </form>
        </div>
    </div>

    <div id="footer-placeholder"></div>

    <script>
        // Load header and footer
        fetch('/components/header.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('header-placeholder').innerHTML = data;
            });

        fetch('/components/footer.html')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            });

        // Handle error messages
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        
        if (error) {
            const errorMessages = {
                'empty': 'Vui lòng điền đầy đủ thông tin.',
                'invalid_email': 'Email phải là địa chỉ @vnu.edu.vn.',
                'password_mismatch': 'Mật khẩu và xác nhận mật khẩu không khớp.',
                'email_exists': 'Email này đã được đăng ký. Vui lòng đăng nhập.',
                'system': 'Có lỗi hệ thống xảy ra, vui lòng thử lại sau.'
            };
            
            const message = errorMessages[error] || 'Đăng ký thất bại';
            
            Swal.fire({
                icon: 'error',
                title: 'Lỗi đăng ký',
                text: message,
                confirmButtonColor: '#2E7D32'
            });
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;

            if (!fullname || !email || !password || !confirm_password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Cảnh báo',
                    text: 'Vui lòng điền đầy đủ thông tin',
                    confirmButtonColor: '#2E7D32'
                });
                return false;
            }

            if (password !== confirm_password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Cảnh báo',
                    text: 'Mật khẩu xác nhận không khớp',
                    confirmButtonColor: '#2E7D32'
                });
                return false;
            }

            // Show loading
            Swal.fire({
                title: 'Đang xử lý...',
                text: 'Vui lòng đợi',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
        });
    </script>
</body>
</html>
