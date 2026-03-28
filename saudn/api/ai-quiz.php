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
    
    // Generate quiz questions
    $questions = [
        [
            'question' => 'Mục tiêu chính của bài học này là gì?',
            'options' => [
                'Hiểu các khái niệm cơ bản về chủ đề',
                'Biết cách áp dụng kiến thức vào thực tế',
                'Phát triển kỹ năng thực hành',
                'Tất cả các đáp án trên'
            ],
            'correct' => 3
        ],
        [
            'question' => 'Điểm quan trọng nhất cần ghi nhớ trong bài học là gì?',
            'options' => [
                'Nắm vững các định nghĩa và thuật ngữ',
                'Hiểu rõ nguyên lý hoạt động',
                'Biết cách giải quyết vấn đề thực tế',
                'Tất cả các đáp án trên'
            ],
            'correct' => 3
        ],
        [
            'question' => 'Để học tốt bài này, bạn cần làm gì?',
            'options' => [
                'Đọc kỹ tài liệu và ghi chú',
                'Thực hành các bài tập',
                'Áp dụng vào tình huống thực tế',
                'Tất cả các đáp án trên'
            ],
            'correct' => 3
        ],
        [
            'question' => 'Ví dụ minh họa trong bài giúp bạn hiểu được điều gì?',
            'options' => [
                'Cách áp dụng lý thuyết vào thực tế',
                'Các trường hợp đặc biệt cần lưu ý',
                'Phương pháp giải quyết vấn đề',
                'Tất cả các đáp án trên'
            ],
            'correct' => 3
        ],
        [
            'question' => 'Sau khi học xong bài này, bạn có thể làm được gì?',
            'options' => [
                'Giải thích các khái niệm cơ bản',
                'Thực hiện các bài tập liên quan',
                'Áp dụng kiến thức vào công việc',
                'Tất cả các đáp án trên'
            ],
            'correct' => 3
        ]
    ];
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'questions' => $questions
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
