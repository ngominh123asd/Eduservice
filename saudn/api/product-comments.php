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

// ==================== GET COMMENTS ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $product_id = intval($_GET['product_id'] ?? 0);
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as user_name, u.avatar,
                (c.user_id = ?) as is_owner
            FROM product_comments c
            INNER JOIN users u ON c.user_id = u.id
            WHERE c.product_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id, $product_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'comments' => $comments]);
        
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
            
            case 'add':
                $product_id = intval($input['product_id'] ?? 0);
                $content = $input['content'] ?? '';
                $selected_text = $input['selected_text'] ?? null;
                $position = $input['position'] ?? null;
                
                if (!$product_id || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                    exit();
                }
                
                // Verify user has access to this product
                $stmt = $pdo->prepare("
                    SELECT id FROM academic_products 
                    WHERE id = ? AND (user_id = ? OR id IN (
                        SELECT product_id FROM product_shares WHERE shared_with_user_id = ?
                    ))
                ");
                $stmt->execute([$product_id, $user_id, $user_id]);
                
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit();
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO product_comments (product_id, user_id, content, selected_text, position, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$product_id, $user_id, $content, $selected_text, $position]);
                
                echo json_encode(['success' => true, 'comment_id' => $pdo->lastInsertId()]);
                break;
            
            case 'delete':
                $comment_id = intval($input['comment_id'] ?? 0);
                
                if (!$comment_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
                    exit();
                }
                
                $stmt = $pdo->prepare("DELETE FROM product_comments WHERE id = ? AND user_id = ?");
                $stmt->execute([$comment_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Comment not found or access denied']);
                }
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
