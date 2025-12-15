<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image uploaded']);
        exit();
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $file = $_FILES['image'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed']);
        exit();
    }
    
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5MB']);
        exit();
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
        exit();
    }
    
    try {
        // Create upload directory
        $upload_dir = __DIR__ . '/../uploads/products/' . $product_id;
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            
            // Optionally resize/optimize image here
            
            $image_url = '/saudn/uploads/products/' . $product_id . '/' . $filename;
            
            echo json_encode([
                'success' => true,
                'image_url' => $image_url,
                'filename' => $filename
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>