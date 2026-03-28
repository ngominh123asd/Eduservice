<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    $text = $input['text'] ?? '';
    $product_id = intval($input['product_id'] ?? 0);
    
    if (empty($text)) {
        echo json_encode(['success' => false, 'message' => 'No text provided']);
        exit();
    }
    
    try {
        // Simulate AI processing (Replace with actual AI API calls)
        $result = '';
        
        switch ($action) {
            case 'improve':
                $result = improveWriting($text);
                break;
            
            case 'grammar':
                $result = checkGrammar($text);
                break;
            
            case 'expand':
                $result = expandContent($text);
                break;
            
            case 'summarize':
                $result = summarizeContent($text);
                break;
            
            case 'custom':
                $prompt = $input['prompt'] ?? '';
                $result = customAIRequest($text, $prompt);
                break;
            
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit();
        }
        
        // Log AI usage
        $stmt = $pdo->prepare("
            INSERT INTO ai_usage_log (user_id, product_id, action_type, input_length, output_length, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $product_id, $action, strlen($text), strlen($result)]);
        
        echo json_encode(['success' => true, 'result' => $result]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'AI processing error: ' . $e->getMessage()]);
    }
    exit();
}

// ==================== AI FUNCTIONS (Simulated) ====================
// NOTE: Replace these with actual AI API calls (OpenAI, Claude, etc.)

function improveWriting($text) {
    // Simulate AI improving writing style
    $improvements = [
        'Văn phong đã được cải thiện với các từ ngữ học thuật phù hợp hơn.',
        'Cấu trúc câu được tối ưu hóa để dễ đọc và mạch lạc hơn.',
        'Sử dụng các liên từ phù hợp để nối các ý tưởng một cách logic.'
    ];
    
    return "<div class='ai-improved-text'>" 
        . "<h4>Văn bản đã cải thiện:</h4>"
        . "<p>" . nl2br(htmlspecialchars($text)) . "</p>"
        . "<div class='ai-suggestions'>"
        . "<h5>Các cải thiện đã áp dụng:</h5>"
        . "<ul><li>" . implode("</li><li>", $improvements) . "</li></ul>"
        . "</div></div>";
}

function checkGrammar($text) {
    // Simulate grammar checking
    $errors = [
        ['position' => 10, 'error' => 'Sai chính tả', 'suggestion' => 'Sửa lại thành...'],
        ['position' => 25, 'error' => 'Thiếu dấu câu', 'suggestion' => 'Thêm dấu phẩy'],
    ];
    
    $output = "<div class='ai-grammar-check'>";
    $output .= "<h4>Kiểm tra ngữ pháp và chính tả:</h4>";
    
    if (count($errors) > 0) {
        $output .= "<div class='grammar-errors'>";
        $output .= "<p>Tìm thấy " . count($errors) . " lỗi:</p>";
        $output .= "<ul>";
        foreach ($errors as $error) {
            $output .= "<li><strong>" . $error['error'] . ":</strong> " . $error['suggestion'] . "</li>";
        }
        $output .= "</ul>";
        $output .= "</div>";
    } else {
        $output .= "<p class='success'>✓ Không tìm thấy lỗi ngữ pháp hoặc chính tả!</p>";
    }
    
    $output .= "<div class='corrected-text'>";
    $output .= "<h5>Văn bản đã sửa:</h5>";
    $output .= "<p>" . nl2br(htmlspecialchars($text)) . "</p>";
    $output .= "</div></div>";
    
    return $output;
}

function expandContent($text) {
    // Simulate content expansion
    $expanded = $text . "\n\n" 
        . "Thêm vào đó, có thể phát triển ý tưởng này bằng cách xem xét các khía cạnh khác nhau. "
        . "Ví dụ, chúng ta có thể phân tích nguyên nhân, hệ quả và các giải pháp khả thi. "
        . "Điều này giúp làm rõ hơn vấn đề và cung cấp góc nhìn toàn diện hơn cho người đọc.\n\n"
        . "Ngoài ra, việc bổ sung các dẫn chứng cụ thể, số liệu thống kê hoặc trích dẫn từ các nguồn "
        . "đáng tin cậy sẽ làm tăng tính thuyết phục của bài viết.";
    
    return "<div class='ai-expanded-text'>"
        . "<h4>Nội dung đã được mở rộng:</h4>"
        . "<div class='expanded-content'>"
        . "<p>" . nl2br(htmlspecialchars($expanded)) . "</p>"
        . "</div>"
        . "<p class='ai-note'><em>Lưu ý: Nội dung được mở rộng tự động. Bạn nên xem xét và chỉnh sửa để phù hợp với ngữ cảnh.</em></p>"
        . "</div>";
}

function summarizeContent($text) {
    // Simulate content summarization
    $words = explode(' ', $text);
    $wordCount = count($words);
    $summaryLength = min(50, (int)($wordCount * 0.3));
    
    $summary = implode(' ', array_slice($words, 0, $summaryLength)) . '...';
    
    return "<div class='ai-summary'>"
        . "<h4>Tóm tắt nội dung:</h4>"
        . "<div class='summary-stats'>"
        . "<span><strong>Độ dài gốc:</strong> $wordCount từ</span>"
        . "<span><strong>Độ dài tóm tắt:</strong> $summaryLength từ</span>"
        . "<span><strong>Tỷ lệ nén:</strong> " . round(($summaryLength / $wordCount) * 100) . "%</span>"
        . "</div>"
        . "<div class='summary-content'>"
        . "<h5>Nội dung tóm tắt:</h5>"
        . "<p>" . nl2br(htmlspecialchars($summary)) . "</p>"
        . "</div>"
        . "<p class='ai-note'><em>Tóm tắt tự động dựa trên nội dung chính. Kiểm tra lại để đảm bảo độ chính xác.</em></p>"
        . "</div>";
}

function customAIRequest($text, $prompt) {
    // Simulate custom AI request
    return "<div class='ai-custom-result'>"
        . "<h4>Kết quả xử lý:</h4>"
        . "<div class='custom-request'>"
        . "<p><strong>Yêu cầu của bạn:</strong> " . htmlspecialchars($prompt) . "</p>"
        . "</div>"
        . "<div class='custom-response'>"
        . "<h5>Phản hồi từ AI:</h5>"
        . "<p>Dựa trên yêu cầu của bạn, tôi đã phân tích văn bản và đưa ra các gợi ý sau:</p>"
        . "<ul>"
        . "<li>Cải thiện cấu trúc và logic của nội dung</li>"
        . "<li>Bổ sung thêm các ví dụ minh họa cụ thể</li>"
        . "<li>Điều chỉnh giọng văn cho phù hợp với văn phong học thuật</li>"
        . "</ul>"
        . "<p>" . nl2br(htmlspecialchars($text)) . "</p>"
        . "</div>"
        . "</div>";
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
