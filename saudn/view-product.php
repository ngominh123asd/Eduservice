<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$user_id = $_SESSION['user_id'];
$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header("Location: academic-products.php");
    exit();
}

// Get product details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.class_name, c.class_code,
            u.full_name as author_name,
            (SELECT COUNT(*) FROM product_comments WHERE product_id = p.id) as comments_count,
            (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as reviews_count
        FROM academic_products p
        LEFT JOIN classes c ON p.class_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND (p.user_id = ? OR p.id IN (
            SELECT product_id FROM product_shares WHERE shared_with_user_id = ?
        ))
    ");
    $stmt->execute([$product_id, $user_id, $user_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: academic-products.php");
        exit();
    }
    
    $is_owner = ($product['user_id'] == $user_id);
    $page_title = $product['title'];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - VolunteerHub</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/academic-products.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="main-content">
                    
                    <!-- Product View Header -->
                    <div class="product-view-header">
                        <button class="btn-back" onclick="window.location.href='academic-products.php'">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        
                        <div class="product-view-info">
                            <h1><?php echo htmlspecialchars($product['title']); ?></h1>
                            <div class="product-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($product['author_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($product['created_at'])); ?></span>
                                <span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($product['class_name'] ?? 'N/A'); ?></span>
                                <span class="product-status-badge <?php echo $product['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'draft' => 'Nháp',
                                        'submitted' => 'Đã nộp',
                                        'reviewed' => 'Đã chấm',
                                        'returned' => 'Trả lại'
                                    ];
                                    echo $status_text[$product['status']] ?? $product['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="product-view-actions">
                            <?php if ($is_owner): ?>
                                <button class="btn btn-primary" onclick="openProductEditor(<?php echo $product_id; ?>)">
                                    <i class="fas fa-edit"></i> Chỉnh sửa
                                </button>
                                <button class="btn btn-secondary" onclick="shareProduct(<?php echo $product_id; ?>)">
                                    <i class="fas fa-share-alt"></i> Chia sẻ
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Product Content -->
                    <div class="product-view-content">
                        <div class="product-main-content">
                            <?php echo $product['content']; ?>
                        </div>
                        
                        <!-- Comments Section -->
                        <div class="product-comments-section">
                            <h3><i class="fas fa-comments"></i> Bình luận (<?php echo $product['comments_count']; ?>)</h3>
                            <div id="product-comments-list">
                                <!-- Comments will be loaded via JS -->
                            </div>
                        </div>
                        
                        <!-- Reviews Section (if reviewed) -->
                        <?php if ($product['reviews_count'] > 0): ?>
                        <div class="product-reviews-section">
                            <h3><i class="fas fa-star"></i> Nhận xét từ giáo viên (<?php echo $product['reviews_count']; ?>)</h3>
                            <div id="product-reviews-list">
                                <!-- Reviews will be loaded via JS -->
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/academic-products.js"></script>
    
    <script>
        const productId = <?php echo $product_id; ?>;
        
        // Load comments and reviews
        document.addEventListener('DOMContentLoaded', () => {
            loadComments(productId);
            <?php if ($product['reviews_count'] > 0): ?>
            loadReviews(productId);
            <?php endif; ?>
        });
    </script>
</body>
</html>