<?php
session_start();

// Check if user is already logged in using user_id (more reliable)
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    error_log("User already logged in, redirecting based on role: " . $_SESSION['role']);
    
    switch ($_SESSION['role']) {
            
        case 'teacher':
            header("Location: ../giaovien/trangchu_giaovien.php");
            exit();
            
        case 'student':
            header("Location: ../saudn/trangchusaudn.php");
            exit();
    }
}

error_log("Showing login page");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Đăng nhập | EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dangnhap.css">
    <link rel="stylesheet" href="/components/header.css">
    <link rel="stylesheet" href="/components/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div id="header-placeholder"></div>
    
    <div class="page-content">
        <div class="login-container">
            <h2>ĐĂNG NHẬP TÀI KHOẢN</h2>
            <form id="loginForm" action="xulydangnhap.php" method="POST">
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
                        autocomplete="current-password"
                    >
                    <i class="fas fa-lock"></i>
                </div>
                <p class="error" id="errorMsg" style="display: none;">Email hoặc mật khẩu không hợp lệ.</p>
                <button type="submit">Đăng nhập</button>
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

        // Handle error messages - CẬP NHẬT thêm error messages
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        
        if (error) {
            const errorMessages = {
                'empty': 'Vui lòng nhập đầy đủ email và mật khẩu',
                'invalid_email': 'Email phải có đuôi @vnu.edu.vn',
                'wrong_password': 'Mật khẩu không đúng',
                'user_not_found': 'Không tìm thấy tài khoản với email này',
                'account_inactive': 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ quản trị viên.',
                'account_suspended': 'Tài khoản của bạn đã bị tạm ngưng. Vui lòng liên hệ quản trị viên.',
                'system': 'Có lỗi xảy ra, vui lòng thử lại sau'
            };
            
            const message = errorMessages[error] || 'Đăng nhập thất bại';
            
            Swal.fire({
                icon: 'error',
                title: 'Lỗi đăng nhập',
                text: message,
                confirmButtonColor: '#2E7D32'
            });
        }

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            console.log('Form submitting...', {email, password: '***'});

            if (!email || !password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Cảnh báo',
                    text: 'Vui lòng nhập đầy đủ thông tin',
                    confirmButtonColor: '#2E7D32'
                });
                return false;
            }

            // Show loading
            Swal.fire({
                title: 'Đang đăng nhập...',
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