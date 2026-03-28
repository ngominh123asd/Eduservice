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
    
    $product_id = intval($input['product_id'] ?? 0);
    $format = $input['format'] ?? 'pdf';
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    try {
        // Get product
        $stmt = $pdo->prepare("
            SELECT p.*, c.class_name
            FROM academic_products p
            LEFT JOIN classes c ON p.class_id = c.id
            WHERE p.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$product_id, $user_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        // Create exports directory if not exists
        $exports_dir = __DIR__ . '/../exports';
        if (!file_exists($exports_dir)) {
            mkdir($exports_dir, 0755, true);
        }
        
        // Generate filename
        $safe_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $product['title']);
        $filename = $safe_title . '_' . time();
        
        $export_path = '';
        
        switch ($format) {
            case 'pdf':
                $export_path = exportToPDF($product, $exports_dir, $filename);
                break;
            
            case 'docx':
                $export_path = exportToDOCX($product, $exports_dir, $filename);
                break;
            
            case 'html':
                $export_path = exportToHTML($product, $exports_dir, $filename);
                break;
            
            case 'txt':
                $export_path = exportToTXT($product, $exports_dir, $filename);
                break;
            
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid format']);
                exit();
        }
        
        if ($export_path) {
            $download_url = '/saudn/exports/' . basename($export_path);
            echo json_encode(['success' => true, 'download_url' => $download_url]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Export failed']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Export error: ' . $e->getMessage()]);
    }
    exit();
}

// ==================== EXPORT FUNCTIONS ====================

function exportToPDF($product, $dir, $filename) {
    // Requires TCPDF or similar library
    // This is a simplified version
    
    $html = generateHTMLContent($product);
    
    // For now, save as HTML and return path
    // In production, use proper PDF library
    $filepath = $dir . '/' . $filename . '.html';
    file_put_contents($filepath, $html);
    
    return $filepath;
}

function exportToDOCX($product, $dir, $filename) {
    // Requires PHPWord library
    // This is a simplified version
    
    $content = strip_tags($product['content']);
    $filepath = $dir . '/' . $filename . '.txt';
    
    $docContent = "TIÊU ĐỀ: " . $product['title'] . "\n\n";
    $docContent .= "LỚP: " . ($product['class_name'] ?? 'N/A') . "\n";
    $docContent .= "LOẠI: " . $product['type'] . "\n";
    $docContent .= "NGÀY TẠO: " . $product['created_at'] . "\n\n";
    $docContent .= str_repeat("=", 50) . "\n\n";
    $docContent .= $content;
    
    file_put_contents($filepath, $docContent);
    
    return $filepath;
}

function exportToHTML($product, $dir, $filename) {
    $html = generateHTMLContent($product);
    $filepath = $dir . '/' . $filename . '.html';
    file_put_contents($filepath, $html);
    return $filepath;
}

function exportToTXT($product, $dir, $filename) {
    $content = strip_tags($product['content']);
    $filepath = $dir . '/' . $filename . '.txt';
    
    $txtContent = "========================================\n";
    $txtContent .= $product['title'] . "\n";
    $txtContent .= "========================================\n\n";
    $txtContent .= "Lớp: " . ($product['class_name'] ?? 'N/A') . "\n";
    $txtContent .= "Loại: " . $product['type'] . "\n";
    $txtContent .= "Số từ: " . ($product['word_count'] ?? 0) . "\n";
    $txtContent .= "Ngày tạo: " . $product['created_at'] . "\n\n";
    $txtContent .= str_repeat("-", 40) . "\n\n";
    $txtContent .= $content;
    
    file_put_contents($filepath, $txtContent);
    return $filepath;
}

function generateHTMLContent($product) {
    $html = "<!DOCTYPE html>
    <html lang='vi'>
    <head>
        <meta charset='UTF-8'>
        <title>" . htmlspecialchars($product['title']) . "</title>
        <style>
            body {
                font-family: 'Times New Roman', serif;
                max-width: 800px;
                margin: 40px auto;
                padding: 40px;
                line-height: 1.8;
                color: #333;
            }
            h1 {
                color: #2E7D32;
                border-bottom: 3px solid #2E7D32;
                padding-bottom: 10px;
            }
            .meta {
                color: #666;
                font-size: 14px;
                margin-bottom: 30px;
                padding: 15px;
                background: #f5f5f5;
                border-radius: 5px;
            }
            .content {
                text-align: justify;
            }
            @media print {
                body { margin: 0; padding: 20px; }
            }
        </style>
    </head>
    <body>
        <h1>" . htmlspecialchars($product['title']) . "</h1>
        
        <div class='meta'>
            <p><strong>Lớp:</strong> " . htmlspecialchars($product['class_name'] ?? 'N/A') . "</p>
            <p><strong>Loại:</strong> " . htmlspecialchars($product['type']) . "</p>
            <p><strong>Số từ:</strong> " . ($product['word_count'] ?? 0) . "</p>
            <p><strong>Ngày tạo:</strong> " . $product['created_at'] . "</p>
            <p><strong>Cập nhật lần cuối:</strong> " . $product['updated_at'] . "</p>
        </div>
        
        <div class='content'>
            " . $product['content'] . "
        </div>
    </body>
    </html>";
    
    return $html;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
