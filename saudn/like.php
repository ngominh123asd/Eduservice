<?php
session_start();
require_once '../db/db_config.php';  // Fix the path to db_config.php

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baiviet_id = $_POST['baiviet_id'] ?? 0;
    
    if (!$baiviet_id) {
        echo json_encode(['success' => false, 'message' => 'ID bài viết không hợp lệ']);
        exit;
    }

    try {
        // Use $pdo directly from db_config.php instead of getDBConnection()
        $stmt = $pdo->prepare("UPDATE baiviet SET luotthich = luotthich + 1 WHERE id = ?");
        $stmt->execute([$baiviet_id]);
        
        // Lấy số lượt thích mới
        $stmt = $pdo->prepare("SELECT luotthich FROM baiviet WHERE id = ?");
        $stmt->execute([$baiviet_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'likes' => (int)$result['luotthich']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài viết']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
}
?>