<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dangky.php');
    exit();
}

try {
    $db = new PDO('sqlite:../db/edservices.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header("Location: dangky.php?error=system");
    exit();
}

$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
    header('Location: dangky.php?error=empty');
    exit();
}

if (!preg_match('/^[\w\.-]+@vnu\.edu\.vn$/', $email)) {
    header('Location: dangky.php?error=invalid_email');
    exit();
}

if ($password !== $confirm_password) {
    header('Location: dangky.php?error=password_mismatch');
    exit();
}

try {
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: dangky.php?error=email_exists');
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'student'; // Set default role to student as requested
    $status = 'active';

    // Insert new user
    $insertStmt = $db->prepare("INSERT INTO users (fullname, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $insertStmt->execute([$fullname, $email, $hashed_password, $role, $status]);
    
    // Redirect to login page upon success
    header('Location: ../dangnhap/dangnhap.php?success=registered');
    exit();
} catch (PDOException $e) {
    error_log("Database error during registration: " . $e->getMessage());
    header("Location: dangky.php?error=system");
    exit();
}
?>
