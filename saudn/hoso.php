<?php
session_start();
require_once '../db/db_config.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../dangnhap/xulydangnhap.php");
    exit();
}

try {
    $fullname = $_SESSION['user'];
    $stmt = $pdo->prepare("SELECT email, fullname, gender, birthday, avatar FROM users WHERE fullname = ?");
    $stmt->execute([$fullname]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "Không tìm thấy hồ sơ người dùng.";
        exit();
    }
} catch(PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hồ sơ người dùng - EDUSERVICE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/hoso.css">
    <link rel="stylesheet" href="/components/footer.css">
</head>
<body>
    <div id="header-placeholder"></div>

<div class="profile-container">
    <h2>Hồ sơ cá nhân</h2>
    <div class="profile-content">
    <div class="avatar">
        <img src="<?= (isset($user['avatar']) && !empty($user['avatar']) && file_exists($user['avatar'])) ? htmlspecialchars($user['avatar'], ENT_QUOTES, 'UTF-8') : 'images/default-avatar.png' ?>" alt="Ảnh đại diện">
    </div>
    <div class="profile-info">
    <div class="info-item"><span>Họ và tên:</span> <?= htmlspecialchars($user['fullname'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="info-item"><span>Email:</span> <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
    <div class="info-item"><span>Giới tính:</span> <?= htmlspecialchars($user['gender'] ?? 'Chưa cập nhật', ENT_QUOTES, 'UTF-8') ?></div>

        <?php
// Sửa lỗi DateTime::createFromFormat() với null
if (!empty($user['birthday'])) {
    $date = DateTime::createFromFormat('Y-m-d', $user['birthday']);
    $formattedDate = $date ? $date->format('d/m/Y') : 'Không xác định';
} else {
    $formattedDate = 'Chưa cập nhật';
}
?>
<div class="info-item"><span>Ngày sinh:</span> <?= $formattedDate ?></div>
</div>
    </div>
    <div class="edit-btn">
        <a href="edit_profile.php">Chỉnh sửa thông tin</a>
    </div>
</div>

<div id="footer-placeholder"></div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Load header
fetch('/components/header.php')
    .then(response => response.text())
    .then(data => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(data, 'text/html');
        
        // Copy CSS from header
        const links = doc.head.getElementsByTagName('link');
        for(let link of links) {
            if(!document.querySelector(`link[href="${link.getAttribute('href')}"]`)) {
                document.head.appendChild(link.cloneNode(true));
            }
        }
        
        // Insert header content
        document.getElementById('header-placeholder').innerHTML = doc.body.innerHTML;
    });

// Load footer
fetch('/components/footer.html')
    .then(response => response.text())
    .then(data => {
        document.getElementById('footer-placeholder').innerHTML = data;
    });

// Keep the existing xacNhanDangXuat function
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
<script src="/components/darkmode.js"></script>
</body>
</html>