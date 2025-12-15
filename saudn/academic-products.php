<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /../dangnhap/dangnhap.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$user_id = $_SESSION['user_id'];
$page_title = "Sản phẩm học tập";

// Get user info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EDUSERVICE</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/academic-products.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <!-- Simple Header -->
    <header class="app-header">
        <div class="header-content">
            <div class="header-left">
                <h1>EDUSERVICES</h1>
            </div>
            <div class="header-right">
                <span>Xin chào, <?php echo htmlspecialchars($user['fullname']); ?></span>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </div>
        </div>
    </header>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Simple Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="sidebar-content">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="home.php">
                                <i class="fas fa-home"></i> Trang chủ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="academic-products.php">
                                <i class="fas fa-file-alt"></i> Sản phẩm học tập
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="learning-platform.php">
                                <i class="fas fa-graduation-cap"></i> Học tập
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="main-content">
                    
                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="page-header-content">
                            <h1>
                                <i class="fas fa-file-alt"></i>
                                Sản phẩm học tập
                            </h1>
                            <p class="page-description">
                                Quản lý và theo dõi các sản phẩm học tập của bạn
                            </p>
                        </div>
                        <div class="page-header-actions">
                            <button class="btn btn-primary" onclick="createNewProduct()">
                                <i class="fas fa-plus"></i>
                                <span>Tạo sản phẩm mới</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="stats-cards">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="total-products">0</h3>
                                <p>Tổng sản phẩm</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #43A047 0%, #2E7D32 100%);">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="draft-products">0</h3>
                                <p>Đang soạn thảo</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #66BB6A 0%, #43A047 100%);">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="submitted-products">0</h3>
                                <p>Đã nộp</p>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #81C784 0%, #66BB6A 100%);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="reviewed-products">0</h3>
                                <p>Đã chấm</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products Filter & Search -->
                    <div class="products-filter">
                        <div class="filter-tabs">
                            <button class="filter-tab active" data-filter="all">
                                <i class="fas fa-list"></i> Tất cả
                            </button>
                            <button class="filter-tab" data-filter="draft">
                                <i class="fas fa-edit"></i> Nháp
                            </button>
                            <button class="filter-tab" data-filter="submitted">
                                <i class="fas fa-paper-plane"></i> Đã nộp
                            </button>
                            <button class="filter-tab" data-filter="reviewed">
                                <i class="fas fa-check-circle"></i> Đã chấm
                            </button>
                        </div>
                        
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="product-search" placeholder="Tìm kiếm sản phẩm...">
                        </div>
                    </div>
                    
                    <!-- Products List -->
                    <div id="products-list" class="products-grid">
                        <!-- Products will be loaded here via JavaScript -->
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Đang tải...</p>
                        </div>
                    </div>
                    
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create Product Modal (will be created dynamically) -->
    <div id="create-product-modal"></div>
    
    <!-- Product Editor Modal (will be created dynamically) -->
    <div id="product-editor-modal"></div>
    
    <!-- Include Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/academic-products.js"></script>
    
    <script>
        // Load initial stats
        async function loadStats() {
            try {
                const response = await fetch('api/academic-products.php');
                const data = await response.json();
                
                if (data.success && data.products) {
                    const products = data.products;
                    
                    document.getElementById('total-products').textContent = products.length;
                    document.getElementById('draft-products').textContent = 
                        products.filter(p => p.status === 'draft').length;
                    document.getElementById('submitted-products').textContent = 
                        products.filter(p => p.status === 'submitted').length;
                    document.getElementById('reviewed-products').textContent = 
                        products.filter(p => p.status === 'reviewed').length;
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        // Load stats on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
        });
    </script>
</body>
</html>