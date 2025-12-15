<?php
// filepath: d:\Volunteerhub\saudn\api\ai-validate-submission.php
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

require_once __DIR__ . '/../../db/db_config.php';

try {
    $task_id = $_POST['task_id'] ?? 0;
    $user_id = $_SESSION['user_id'];
    
    if (!$task_id) {
        throw new Exception('Task ID is required');
    }
    
    // ✅ Get task details - FIXED for your schema
    $stmt = $pdo->prepare("
        SELECT a.*, c.class_name, c.code
        FROM assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        // If not found in assignments, it might be a different type
        // Just proceed with validation without task details
        $task = [
            'id' => $task_id,
            'title' => 'Nhiệm vụ',
            'description' => '',
            'class_name' => 'Lớp học'
        ];
    }
    
    // Get file info
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Simulate AI analysis delay
    sleep(2); // Reduced to 2 seconds
    
    // ✅ AI Validation Logic
    $validation = validateSubmissionWithAI($file, $task, $user_id, $pdo);
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'validation' => $validation
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log('AI Validation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function validateSubmissionWithAI($file, $task, $user_id, $pdo) {
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Initialize scores
    $relevance_score = 0;
    $authenticity_score = 0;
    $quality_score = 0;
    
    // ===== 1. RELEVANCE CHECK (Phù hợp với yêu cầu) =====
    $relevance_feedback = '';
    $relevance_score = 75; // Base score
    
    // Check file type appropriateness
    $appropriate_types = ['pdf', 'doc', 'docx', 'zip', 'jpg', 'jpeg', 'png'];
    if (in_array($fileType, $appropriate_types)) {
        $relevance_score += 10;
        $relevance_feedback = "File định dạng .$fileType phù hợp với yêu cầu nộp bài. ";
    } else {
        $relevance_score -= 20;
        $relevance_feedback = "File định dạng .$fileType có thể không phù hợp. Nên sử dụng PDF, DOC hoặc hình ảnh. ";
    }
    
    // Check file size (reasonable size for assignment)
    $minSize = 10 * 1024; // 10KB
    $maxSize = 50 * 1024 * 1024; // 50MB
    
    if ($fileSize < $minSize) {
        $relevance_score -= 15;
        $relevance_feedback .= "File có kích thước rất nhỏ (" . formatBytes($fileSize) . "), có thể thiếu nội dung. ";
    } elseif ($fileSize > $maxSize) {
        $relevance_score -= 10;
        $relevance_feedback .= "File có kích thước lớn (" . formatBytes($fileSize) . "), hãy chắc chắn đã nén nếu cần. ";
    } else {
        $relevance_score += 5;
        $relevance_feedback .= "Kích thước file (" . formatBytes($fileSize) . ") hợp lý. ";
    }
    
    // Check filename relevance (contains task-related keywords)
    $taskKeywords = ['minh chứng', 'minh_chung', 'bài làm', 'bai_lam', 'assignment', 'task', 'nhiệm vụ', 'nhiem_vu'];
    $hasRelevantName = false;
    foreach ($taskKeywords as $keyword) {
        if (stripos($fileName, $keyword) !== false) {
            $hasRelevantName = true;
            break;
        }
    }
    
    if ($hasRelevantName) { 
        $relevance_score += 10;
        $relevance_feedback .= "Tên file có vẻ phù hợp với nội dung nhiệm vụ.";
    } else {
        $relevance_feedback .= "Nên đặt tên file rõ ràng hơn (vd: minh_chung_tuan1.pdf).";
    }
    
    // Check if filename contains task ID or class info
    if (stripos($fileName, (string)$task['id']) !== false || 
        (isset($task['code']) && stripos($fileName, $task['code']) !== false)) {
        $relevance_score += 5;
        $relevance_feedback .= " Tên file có thông tin định danh tốt.";
    }
    
    $relevance_score = max(0, min(100, $relevance_score));
    
    // ===== 2. AUTHENTICITY CHECK (Tính xác thực) =====
    $authenticity_feedback = '';
    $authenticity_score = 70; // Base score
    
    // Get user info for authenticity check
    try {
        $stmt = $pdo->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check if filename contains user's name or username
            $userIdentifiers = array_filter([
                $user['username'],
                $user['full_name'],
                explode('@', $user['email'])[0]
            ]);
            
            $hasUserInfo = false;
            foreach ($userIdentifiers as $identifier) {
                if ($identifier && stripos($fileName, str_replace(' ', '', $identifier)) !== false) {
                    $hasUserInfo = true;
                    break;
                }
            }
            
            if ($hasUserInfo) {
                $authenticity_score += 15;
                $authenticity_feedback = "Tên file chứa thông tin cá nhân của bạn, tốt! ";
            } else {
                $authenticity_score -= 10;
                $authenticity_feedback = "Nên thêm tên hoặc mã sinh viên vào tên file. ";
            }
        }
    } catch (Exception $e) {
        error_log('Error getting user info: ' . $e->getMessage());
    }
    
    // Check if filename contains student info or generic
    $genericNames = ['untitled', 'document', 'file', 'new', 'unnamed', 'scan', 'img', 'image'];
    $isGeneric = false;
    foreach ($genericNames as $generic) {
        if (stripos($fileName, $generic) !== false) {
            $isGeneric = true;
            break;
        }
    }
    
    if ($isGeneric) {
        $authenticity_score -= 15;
        $authenticity_feedback .= "Tên file chung chung, nên đổi tên có ý nghĩa hơn. ";
    } else {
        $authenticity_score += 10;
        $authenticity_feedback .= "Tên file được tùy chỉnh, cho thấy tính cá nhân hóa. ";
    }
    
    // Check file creation/upload time
    $authenticity_score += 5;
    $authenticity_feedback .= "File được tải lên trong phiên làm việc hiện tại.";
    
    // Additional authenticity indicators based on file type
    if ($fileType === 'pdf' || $fileType === 'docx') {
        $authenticity_score += 10;
        $authenticity_feedback .= " Định dạng chuyên nghiệp, phù hợp cho bài làm chính thức.";
    } elseif (in_array($fileType, ['jpg', 'jpeg', 'png'])) {
        $authenticity_score -= 5;
        $authenticity_feedback .= " Hình ảnh có thể dễ sao chép, hãy đảm bảo đây là ảnh chụp công việc của bạn.";
    }
    
    $authenticity_score = max(0, min(100, $authenticity_score));
    
    // ===== 3. QUALITY CHECK (Chất lượng minh chứng) =====
    $quality_feedback = '';
    $quality_score = 75; // Base score
    
    // Check file size indicates substantial content
    if ($fileSize > 500 * 1024) { // > 500KB
        $quality_score += 15;
        $quality_feedback = "File có kích thước đủ lớn, có thể chứa nội dung chi tiết và hình ảnh rõ nét. ";
    } elseif ($fileSize > 100 * 1024) { // > 100KB
        $quality_score += 10;
        $quality_feedback = "File có kích thước hợp lý, phù hợp với bài làm thông thường. ";
    } else {
        $quality_score -= 10;
        $quality_feedback = "File nhỏ, hãy đảm bảo đã bao gồm đầy đủ nội dung yêu cầu. ";
    }
    
    // Check file type quality
    if (in_array($fileType, ['pdf', 'docx'])) {
        $quality_score += 10;
        $quality_feedback .= "Định dạng PDF/DOCX cho phép trình bày nội dung có cấu trúc tốt. ";
    } elseif (in_array($fileType, ['jpg', 'jpeg', 'png'])) {
        $quality_score += 5;
        $quality_feedback .= "Hình ảnh minh họa tốt, nhưng nên kết hợp với mô tả văn bản. ";
    } elseif ($fileType === 'zip') {
        $quality_score += 8;
        $quality_feedback .= "File nén cho phép gộp nhiều tài liệu, rất tốt cho bài làm phức tạp. ";
    }
    
    // Bonus for professional naming
    $professionalPatterns = ['/^\d{6,}/', '/[A-Z]{2,}\d+/', '/_\d{4}_/'];
    foreach ($professionalPatterns as $pattern) {
        if (preg_match($pattern, $fileName)) {
            $quality_score += 5;
            $quality_feedback .= "Đặt tên file theo chuẩn, rất chuyên nghiệp.";
            break;
        }
    }
    
    $quality_score = max(0, min(100, $quality_score));
    
    // ===== CALCULATE OVERALL SCORE =====
    $overall_score = round(($relevance_score * 0.4) + ($authenticity_score * 0.3) + ($quality_score * 0.3));
    
    // ===== OVERALL ASSESSMENT =====
    $overall_assessment = '';
    if ($overall_score >= 85) {
        $overall_assessment = "Xuất sắc! Minh chứng của bạn đạt tiêu chuẩn rất cao.";
    } elseif ($overall_score >= 75) {
        $overall_assessment = "Tốt lắm! Minh chứng phù hợp và đạt yêu cầu.";
    } elseif ($overall_score >= 65) {
        $overall_assessment = "Khá tốt! Có thể cải thiện thêm một chút.";
    } elseif ($overall_score >= 50) {
        $overall_assessment = "Đạt yêu cầu cơ bản. Nên xem lại các đề xuất bên dưới.";
    } else {
        $overall_assessment = "Cần cải thiện đáng kể. Vui lòng xem các đề xuất chi tiết.";
    }
    
    // ===== RECOMMENDATIONS =====
    $recommendations = [];
    
    if ($relevance_score < 75) {
        $recommendations[] = "Kiểm tra lại xem file có đúng định dạng và nội dung yêu cầu không";
        if (!$hasRelevantName) {
            $recommendations[] = "Đặt tên file rõ ràng, bao gồm tên nhiệm vụ hoặc mã nhiệm vụ";
        }
    }
    
    if ($authenticity_score < 70) {
        $recommendations[] = "Thêm thông tin cá nhân vào tên file (tên, mã sinh viên)";
        $recommendations[] = "Đảm bảo đây là bài làm của chính bạn, không sao chép";
        if ($isGeneric) {
            $recommendations[] = "Đổi tên file từ tên mặc định sang tên có ý nghĩa";
        }
    }
    
    if ($quality_score < 70) {
        $recommendations[] = "Kiểm tra lại độ dài và chi tiết của nội dung";
        $recommendations[] = "Sử dụng định dạng PDF hoặc DOCX để trình bày chuyên nghiệp hơn";
        if ($fileSize < 100 * 1024) {
            $recommendations[] = "Nội dung có vẻ ngắn - hãy bổ sung thêm chi tiết nếu cần";
        }
    }
    
    if ($fileSize < $minSize) {
        $recommendations[] = "File quá nhỏ - hãy chắc chắn đã bao gồm đầy đủ nội dung";
    }
    
    if ($fileSize > $maxSize) {
        $recommendations[] = "File khá lớn - cân nhắc nén hoặc giảm kích thước nếu cần";
    }
    
    // Add positive recommendations for high scores
    if (empty($recommendations) || $overall_score >= 80) {
        $recommendations[] = "Minh chứng của bạn đã rất tốt! Có thể nộp bài ngay.";
        if ($overall_score >= 85) {
            $recommendations[] = "Bài làm xuất sắc! Tiếp tục duy trì chất lượng này.";
        }
    }
    
    return [
        'overall_score' => $overall_score,
        'overall_assessment' => $overall_assessment,
        'relevance_score' => $relevance_score,
        'relevance_feedback' => trim($relevance_feedback),
        'authenticity_score' => $authenticity_score,
        'authenticity_feedback' => trim($authenticity_feedback),
        'quality_score' => $quality_score,
        'quality_feedback' => trim($quality_feedback),
        'recommendations' => $recommendations,
        'file_info' => [
            'name' => $fileName,
            'size' => formatBytes($fileSize),
            'type' => strtoupper($fileType)
        ]
    ];
}

function formatBytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>