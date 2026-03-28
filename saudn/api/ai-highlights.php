<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $file_path = $input['file_path'] ?? '';
    
    if (empty($file_path)) {
        throw new Exception('File path is required');
    }
    
    // Generate highlights
    $highlights = [
        [
            'category' => 'Mục tiêu học tập',
            'text' => 'Nắm vững các khái niệm cơ bản và hiểu rõ cách áp dụng vào thực tế'
        ],
        [
            'category' => 'Khái niệm quan trọng',
            'text' => 'Các định nghĩa và thuật ngữ then chốt cần ghi nhớ trong bài học'
        ],
        [
            'category' => 'Điểm chính',
            'text' => 'Những nội dung cốt lõi mà bạn cần tập trung và hiểu thấu đáo'
        ],
        [
            'category' => 'Ví dụ minh họa',
            'text' => 'Các tình huống thực tế giúp hiểu rõ hơn về cách áp dụng kiến thức'
        ],
        [
            'category' => 'Lưu ý quan trọng',
            'text' => 'Những điểm cần chú ý đặc biệt để tránh nhầm lẫn khi học và thực hành'
        ],
        [
            'category' => 'Kỹ năng cần đạt',
            'text' => 'Các kỹ năng và năng lực cần phát triển sau khi học xong bài này'
        ]
    ];
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'highlights' => $highlights
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
