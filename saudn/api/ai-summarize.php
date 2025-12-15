<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    $lesson_id = $input['lesson_id'] ?? 0;
    
    if (empty($file_path)) {
        throw new Exception('File path is required');
    }
    
    // Get file full path
    $full_path = __DIR__ . '/../../' . $file_path;
    
    if (!file_exists($full_path)) {
        throw new Exception('File not found: ' . $file_path);
    }
    
    // Determine file type
    $file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    
    // Generate summary based on file type
    if ($file_extension === 'pdf') {
        $summary = generatePDFSummary($full_path, $file_path);
    } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $summary = generateImageSummary($full_path, $file_path);
    } else {
        throw new Exception('Unsupported file type: ' . $file_extension);
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'summary' => $summary
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log('AI Summarize error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generatePDFSummary($file_path, $relative_path) {
    // Get filename for context
    $filename = basename($relative_path);
    $title = pathinfo($filename, PATHINFO_FILENAME);
    
    return [
        'overview' => "Tài liệu \"$title\" cung cấp kiến thức chuyên sâu về chủ đề được đề cập. Đây là tài liệu quan trọng giúp người học nắm vững các khái niệm cơ bản và nâng cao.",
        'key_points' => [
            'Tài liệu chứa thông tin chi tiết và có hệ thống về nội dung học tập',
            'Bao gồm các khái niệm, định nghĩa và nguyên lý cơ bản',
            'Cung cấp ví dụ minh họa cụ thể để dễ hiểu',
            'Có phần bài tập và câu hỏi thực hành để củng cố kiến thức',
            'Hướng dẫn chi tiết về cách áp dụng kiến thức vào thực tế'
        ],
        'conclusion' => 'Đây là tài liệu học tập quan trọng và cần thiết. Hãy đọc kỹ, ghi chú các điểm chính và thực hành thường xuyên để nắm vững kiến thức.'
    ];
}

function generateImageSummary($file_path, $relative_path) {
    $filename = basename($relative_path);
    
    return [
        'overview' => "Hình ảnh \"$filename\" minh họa trực quan cho nội dung bài học. Đây là tài liệu hình ảnh quan trọng giúp bạn hiểu rõ hơn về các khái niệm được đề cập.",
        'key_points' => [
            'Hình ảnh minh họa trực quan giúp dễ dàng hình dung nội dung',
            'Chứa các thông tin quan trọng được trình bày bằng đồ họa',
            'Giúp ghi nhớ kiến thức tốt hơn thông qua hình ảnh',
            'Nên chụp lại hoặc ghi chú các điểm chính từ hình ảnh',
            'Quan sát kỹ các chi tiết trong hình để hiểu đầy đủ'
        ],
        'conclusion' => 'Hãy quan sát kỹ hình ảnh và ghi chú lại những điểm quan trọng. Kết hợp với tài liệu lý thuyết để hiểu sâu hơn về nội dung.'
    ];
}
?>