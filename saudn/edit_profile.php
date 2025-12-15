<?php
session_start();
require_once '../db/db_config.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../dangnhap/xulydangnhap.php");
    exit();
}

$fullname = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $avatar_path = null;
    $delete_avatar = isset($_POST['delete_avatar']) ? true : false;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = uniqid('avatar_', true) . '.' . $fileExtension;
            $uploadFileDir = 'uploads/avatars/';
            $dest_path = $uploadFileDir . $newFileName;

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $avatar_path = $dest_path;
            } else {
                $error = "Lỗi khi lưu ảnh.";
            }
        } else {
            $error = "Chỉ chấp nhận file ảnh JPG, JPEG, PNG hoặc GIF.";
        }
    }

    $email = $_POST['email'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthday = $_POST['birthday'] ?? null;

    try {
        if ($avatar_path !== null || $delete_avatar) {
            $avatar_to_set = $delete_avatar ? null : $avatar_path;
            $sql = "UPDATE users SET email = :email, gender = :gender, birthday = :birthday, avatar = :avatar WHERE fullname = :fullname";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':email' => $email,
                ':gender' => $gender,
                ':birthday' => $birthday,
                ':avatar' => $avatar_to_set,
                ':fullname' => $fullname
            ]);
        } else {
            $sql = "UPDATE users SET email = :email, gender = :gender, birthday = :birthday WHERE fullname = :fullname";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':email' => $email,
                ':gender' => $gender,
                ':birthday' => $birthday,
                ':fullname' => $fullname
            ]);
        }

        if (!empty($success) && $success) {
            // Cập nhật avatar trong session nếu thay đổi
            if (isset($avatar_to_set)) {
                $_SESSION['avatar'] = $avatar_to_set;
            } elseif ($delete_avatar) {
                unset($_SESSION['avatar']);
            }
            header("Location: hoso.php");
            exit();
        } else {
            $error = "Cập nhật thất bại.";
        }
    } catch (PDOException $e) {
        $error = "Lỗi: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->prepare("SELECT email, gender, birthday, avatar FROM users WHERE fullname = :fullname");
    $stmt->execute([':fullname' => $fullname]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "Không tìm thấy người dùng.";
        exit();
    }
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chỉnh sửa hồ sơ</title>
    <link rel="stylesheet" href="css/edit_profile.css">
</head>
<body>
<div id="header-placeholder"></div>
<div class="edit-form-container">
    <h2>Chỉnh sửa thông tin</h2>
    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

        <label>Giới tính</label>
        <select name="gender" required>
            <option value="Nam" <?= $user['gender'] == "Nam" ? "selected" : "" ?>>Nam</option>
            <option value="Nữ" <?= $user['gender'] == "Nữ" ? "selected" : "" ?>>Nữ</option>
            <option value="Khác" <?= $user['gender'] == "Khác" ? "selected" : "" ?>>Khác</option>
        </select>

        <label>Ngày sinh</label>
        <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday']) ?>" required>

        <label>Ảnh đại diện hiện tại</label>
        <?php if (!empty($user['avatar'])): ?>
            <div class="avatar-preview">
                <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Ảnh đại diện hiện tại">
                <label class="delete-avatar">
                    <input type="checkbox" name="delete_avatar"> 🗑 Xóa ảnh
                </label>
            </div>
        <?php endif; ?>
        <input type="file" name="avatar" accept="image/*">

        <button type="submit">Lưu thay đổi</button>
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
