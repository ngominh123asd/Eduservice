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

// ==================== GET VERSIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Get single version
    if (isset($_GET['version_id'])) {
        $version_id = intval($_GET['version_id']);
        
        try {
            $stmt = $pdo->prepare("
                SELECT v.*, u.full_name as user_name
                FROM product_versions v
                INNER JOIN users u ON v.user_id = u.id
                WHERE v.id = ? AND v.user_id = ?
            ");
            $stmt->execute([$version_id, $user_id]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$version) {
                echo json_encode(['success' => false, 'message' => 'Version not found']);
                exit();
            }
            
            echo json_encode(['success' => true, 'version' => $version]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Compare versions
    if (isset($_GET['action']) && $_GET['action'] === 'compare') {
        $version1_id = intval($_GET['version1'] ?? 0);
        $version2_id = intval($_GET['version2'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM product_versions WHERE id = ? AND user_id = ?");
            
            $stmt->execute([$version1_id, $user_id]);
            $version1 = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt->execute([$version2_id, $user_id]);
            $version2 = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$version1 || !$version2) {
                echo json_encode(['success' => false, 'message' => 'Versions not found']);
                exit();
            }
            
            // Calculate differences
            $text1 = strip_tags($version1['content']);
            $text2 = strip_tags($version2['content']);
            
            $stats = [
                'added' => strlen($text1) - strlen($text2),
                'removed' => strlen($text2) - strlen($text1)
            ];
            
            if ($stats['added'] < 0) {
                $stats['removed'] = abs($stats['added']);
                $stats['added'] = 0;
            } else {
                $stats['removed'] = 0;
            }
            
            echo json_encode([
                'success' => true,
                'version1' => $version1,
                'version2' => $version2,
                'stats' => $stats
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Get version history
    $product_id = intval($_GET['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT v.*, u.full_name as user_name
            FROM product_versions v
            INNER JOIN users u ON v.user_id = u.id
            WHERE v.product_id = ?
            ORDER BY v.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$product_id]);
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'versions' => $versions]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// ==================== POST ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            
            case 'restore':
                $version_id = intval($input['version_id'] ?? 0);
                $product_id = intval($input['product_id'] ?? 0);
                
                if (!$version_id || !$product_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                    exit();
                }
                
                // Get version data
                $stmt = $pdo->prepare("SELECT * FROM product_versions WHERE id = ? AND user_id = ?");
                $stmt->execute([$version_id, $user_id]);
                $version = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$version) {
                    echo json_encode(['success' => false, 'message' => 'Version not found']);
                    exit();
                }
                
                // Save current version before restoring
                $stmt = $pdo->prepare("
                    INSERT INTO product_versions (product_id, user_id, title, content, word_count, created_at)
                    SELECT id, user_id, title, content, word_count, NOW()
                    FROM academic_products
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$product_id, $user_id]);
                
                // Restore version
                $stmt = $pdo->prepare("
                    UPDATE academic_products 
                    SET title = ?, content = ?, word_count = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $version['title'],
                    $version['content'],
                    $version['word_count'],
                    $product_id,
                    $user_id
                ]);
                
                // Get updated product
                $stmt = $pdo->prepare("SELECT * FROM academic_products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'product' => $product]);
                break;
            
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>