<?php
// filepath: d:\Volunteerhub\saudn\api\academic-products.php
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

// ==================== GET PRODUCTS ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    // Get single product
    if (isset($_GET['id'])) {
        $product_id = intval($_GET['id']);
        
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, c.class_name, c.code as class_code,
                    (SELECT COUNT(*) FROM product_comments WHERE product_id = p.id) as comments_count
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
            
            echo json_encode(['success' => true, 'product' => $product]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Get share link
    if (isset($_GET['action']) && $_GET['action'] === 'get_share_link') {
        $product_id = intval($_GET['product_id']);
        
        try {
            // Check if share link exists
            $stmt = $pdo->prepare("SELECT share_token FROM academic_products WHERE id = ? AND user_id = ?");
            $stmt->execute([$product_id, $user_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            // Generate share token if not exists
            if (empty($product['share_token'])) {
                $share_token = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("UPDATE academic_products SET share_token = ? WHERE id = ?");
                $stmt->execute([$share_token, $product_id]);
            } else {
                $share_token = $product['share_token'];
            }
            
            $share_link = 'http://' . $_SERVER['HTTP_HOST'] . '/saudn/share/product.php?token=' . $share_token;
            
            echo json_encode(['success' => true, 'share_link' => $share_link]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // Get products list
    $filter = $_GET['filter'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    try {
        $sql = "
            SELECT p.*, c.class_name, c.code as class_code,
                (SELECT COUNT(*) FROM product_comments WHERE product_id = p.id) as comments_count
            FROM academic_products p
            LEFT JOIN classes c ON p.class_id = c.id
            WHERE p.user_id = ?
        ";
        
        $params = [$user_id];
        
        // Apply filter
        if ($filter !== 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $filter;
        }
        
        // Apply search
        if (!empty($search)) {
            $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR c.class_name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.updated_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'products' => $products]);
        
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
            
            // Create new product
            case 'create':
                $title = $input['title'] ?? '';
                $class_id = intval($input['class_id'] ?? 0);
                $type = $input['type'] ?? 'other';
                $description = $input['description'] ?? '';
                
                if (empty($title) || !$class_id) {
                    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                    exit();
                }
                
                // ✅ SQLite: Use datetime('now', 'localtime')
                $stmt = $pdo->prepare("
                    INSERT INTO academic_products (user_id, class_id, title, type, description, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'draft', datetime('now', 'localtime'), datetime('now', 'localtime'))
                ");
                $stmt->execute([$user_id, $class_id, $title, $type, $description]);
                
                $product_id = $pdo->lastInsertId();
                
                echo json_encode(['success' => true, 'product_id' => $product_id]);
                break;
            
            // Update product
            case 'update':
                $product_id = intval($input['product_id'] ?? 0);
                $title = $input['title'] ?? '';
                $content = $input['content'] ?? '';
                $skip_version = $input['skip_version'] ?? false; // ✅ New flag
                
                if (!$product_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                    exit();
                }
                
                // Validate content
                $text = strip_tags($content);
                if (strlen(trim($text)) < 5) {
                    echo json_encode(['success' => false, 'message' => 'Content too short']);
                    exit();
                }
                
                // Calculate word count
                $word_count = str_word_count($text);
                
                // ✅ Only create version if not skipped and content exists
                if (!$skip_version) {
                    // Get current content
                    $stmt = $pdo->prepare("SELECT content FROM academic_products WHERE id = ? AND user_id = ?");
                    $stmt->execute([$product_id, $user_id]);
                    $current = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Only save version if current content exists and is not empty
                    if ($current && !empty($current['content'])) {
                        $currentText = strip_tags($current['content']);
                        if (strlen(trim($currentText)) >= 5) {
                            $stmt = $pdo->prepare("
                                INSERT INTO product_versions (product_id, user_id, title, content, word_count, created_at)
                                SELECT id, user_id, title, content, word_count, datetime('now', 'localtime')
                                FROM academic_products
                                WHERE id = ? AND user_id = ?
                            ");
                            $stmt->execute([$product_id, $user_id]);
                        }
                    }
                }
                
                // Update product
                $stmt = $pdo->prepare("
                    UPDATE academic_products 
                    SET title = ?, content = ?, word_count = ?, updated_at = datetime('now', 'localtime')
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$title, $content, $word_count, $product_id, $user_id]);
                
                echo json_encode(['success' => true]);
                break;
            
            // Submit product
            case 'submit':
                $product_id = intval($input['product_id'] ?? 0);
                
                if (!$product_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                    exit();
                }
                
                // ✅ SQLite datetime
                $stmt = $pdo->prepare("
                    UPDATE academic_products 
                    SET status = 'submitted', 
                        submitted_at = datetime('now', 'localtime'), 
                        updated_at = datetime('now', 'localtime')
                    WHERE id = ? AND user_id = ? AND status = 'draft'
                ");
                $stmt->execute([$product_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Cannot submit product']);
                }
                break;
            
            // Duplicate product
            case 'duplicate':
                $product_id = intval($input['product_id'] ?? 0);
                
                if (!$product_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                    exit();
                }
                
                // ✅ SQLite datetime and CONCAT alternative
                $stmt = $pdo->prepare("
                    INSERT INTO academic_products (user_id, class_id, title, type, description, content, status, created_at, updated_at)
                    SELECT user_id, class_id, title || ' (Bản sao)', type, description, content, 'draft', 
                           datetime('now', 'localtime'), datetime('now', 'localtime')
                    FROM academic_products
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$product_id, $user_id]);
                
                echo json_encode(['success' => true, 'new_product_id' => $pdo->lastInsertId()]);
                break;
            
            // Delete product
            case 'delete':
                $product_id = intval($input['product_id'] ?? 0);
                
                if (!$product_id) {
                    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                    exit();
                }
                
                // Delete comments first
                $stmt = $pdo->prepare("DELETE FROM product_comments WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                // Delete versions
                $stmt = $pdo->prepare("DELETE FROM product_versions WHERE product_id = ?");
                $stmt->execute([$product_id]);
                
                // Delete product
                $stmt = $pdo->prepare("DELETE FROM academic_products WHERE id = ? AND user_id = ?");
                $stmt->execute([$product_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                }
                break;
            
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
    } catch (PDOException $e) {
        error_log("Error in academic-products.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>