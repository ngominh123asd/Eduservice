<?php
session_start();
require_once '../db/db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST["fullname"]);
    $tieude = trim($_POST["tieude"]);
    $noidung = trim($_POST["noidung"]);

    $anhTen = null;

    // Xử lý ảnh nếu có upload
    if (!empty($_FILES["anh"]["name"])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $anhTen = time() . "_" . basename($_FILES["anh"]["name"]);
        $target_file = $target_dir . $anhTen;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed = ["jpg", "jpeg", "png", "gif"];

        if (in_array($imageFileType, $allowed)) {
            move_uploaded_file($_FILES["anh"]["tmp_name"], $target_file);
        } else {
            $anhTen = null;
        }
    }

    if ($tieude && $noidung && $fullname) {
        try {
            $sql = "INSERT INTO baiviet (tieude, noidung, fullname, anh) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tieude, $noidung, $fullname, $anhTen]);
            header("Location: congdong.php");
            exit;
        } catch(PDOException $e) {
            $error = "Lỗi khi đăng bài: " . $e->getMessage();
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin.";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng bài viết</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dangbaiviet.css">
    <link rel="stylesheet" href="/components/footer.css">
</head>
<body>
<div id="header-placeholder"></div>
<div class="container">
    <h2>📝 Đăng bài viết mới</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="dangbaiviet.php" method="POST" class="form-post" enctype="multipart/form-data">
        <label for="fullname">Tên người đăng:</label>
        <input type="text" name="fullname" id="fullname" required>

        <label for="tieude">Tiêu đề:</label>
        <input type="text" name="tieude" id="tieude" required>

        <label for="noidung">Nội dung:</label>
        <textarea name="noidung" id="noidung" rows="6" required></textarea>

        <label for="anh">Hình ảnh đính kèm (tuỳ chọn):</label>
        <input type="file" name="anh" id="anh" accept="image/*">

        <button type="submit" class="btn-dangbai">Đăng bài</button>
    </form>
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
