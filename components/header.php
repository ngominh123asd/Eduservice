<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include menu helper
require_once __DIR__ . '/menu.php';
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/components/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>
    <header>
        <div class="logo">
            <a>EDUSERVICE</a>
        </div>
        <nav>
            <ul>
                <?php echo getMenu(); ?>
            </ul>
        </nav>
    </header>

    <script>
        // Toggle dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuTrigger = document.querySelector('.user-menu-trigger');
            if (userMenuTrigger) {
                userMenuTrigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdown = this.nextElementSibling;
                    dropdown.classList.toggle('show');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.user-dropdown')) {
                        const dropdowns = document.querySelectorAll('.dropdown-menu');
                        dropdowns.forEach(dd => dd.classList.remove('show'));
                    }
                });
            }
        });

        function xacNhanDangXuat() {
            if (confirm('Bạn có chắc muốn đăng xuất?')) {
                window.location.href = '/dangxuat/dangxuat.php';
            }
        }
    </script>
</body>

</html>