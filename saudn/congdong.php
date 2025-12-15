<?php
session_start();
require_once '../db/db_config.php';

// Change $db->query to $pdo->query
$sql = "SELECT * FROM baiviet ORDER BY thoigian DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cộng đồng tình nguyện</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/congdong.css">
    <link rel="stylesheet" href="/components/footer.css">
</head>
<body>
<div id="header-placeholder"></div>

<div class="container">
    <a href="dangbaiviet.php" class="btn-dangbai">➕ Đăng bài mới</a>
    <h2>📣 Diễn đàn cộng đồng tình nguyện</h2>
    <?php if (count($posts) > 0): ?>
        <?php foreach ($posts as $row): ?>
            <div class="post">
                <h3><?= htmlspecialchars($row["tieude"]) ?></h3>
                <div class="meta">
                    Đăng bởi <strong><?= htmlspecialchars($row["fullname"]) ?></strong> 
                    vào lúc <?= date("H:i d/m/Y", strtotime($row["thoigian"])) ?> | 
                    <button class="like-btn" data-id="<?= $row['id'] ?>">❤️ Thích</button>
                    <span class="like-count" id="like-count-<?= $row['id'] ?>"><?= (int)$row["luotthich"] ?></span>
                </div>

                <?php if (!empty($row["anh"])): ?>
                    <div class="post-image">
                        <img src="uploads/<?= htmlspecialchars($row["anh"]) ?>" alt="Ảnh bài viết" style="max-width:100%; border-radius:8px; margin:10px 0;">
                    </div>
                <?php endif; ?>

                <div class="content"><?= nl2br(htmlspecialchars($row["noidung"])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-posts">Chưa có bài viết nào.</div>
    <?php endif; ?>
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

document.querySelectorAll(".like-btn").forEach(btn => {
    btn.addEventListener("click", async function() {
        const postId = this.dataset.id;
        try {
            const response = await fetch('like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `baiviet_id=${postId}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                document.getElementById(`like-count-${postId}`).textContent = data.likes;
            } else {
                console.error('Error:', data.message);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });
});
</script>
<script src="/components/darkmode.js"></script>
</body>
</html>