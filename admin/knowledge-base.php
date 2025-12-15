<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Knowledge Base";
$current_page = "knowledge";

$success_message = '';
$error_message = '';

// Khởi tạo bảng knowledge base nếu chưa có
try {
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kb_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            description TEXT,
            icon TEXT DEFAULT 'fa-folder',
            sort_order INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kb_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            content TEXT NOT NULL,
            article_type TEXT DEFAULT 'faq' CHECK(article_type IN ('faq', 'guide', 'video', 'wiki')),
            video_url TEXT,
            is_featured INTEGER DEFAULT 0,
            is_published INTEGER DEFAULT 1,
            view_count INTEGER DEFAULT 0,
            helpful_yes INTEGER DEFAULT 0,
            helpful_no INTEGER DEFAULT 0,
            author_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Insert default categories if empty
    $catCount = $pdo->query("SELECT COUNT(*) FROM kb_categories")->fetchColumn();
    if ($catCount == 0) {
        $defaultCategories = [
            ['Bắt đầu sử dụng', 'bat-dau', 'Hướng dẫn cho người mới', 'fa-rocket', 1],
            ['Tài khoản', 'tai-khoan', 'Quản lý tài khoản và bảo mật', 'fa-user-circle', 2],
            ['Lớp học', 'lop-hoc', 'Hướng dẫn về lớp học và khóa học', 'fa-chalkboard', 3],
            ['Thanh toán', 'thanh-toan', 'Câu hỏi về thanh toán và hóa đơn', 'fa-credit-card', 4],
            ['Kỹ thuật', 'ky-thuat', 'Hỗ trợ kỹ thuật và khắc phục sự cố', 'fa-tools', 5],
        ];
        
        foreach ($defaultCategories as $cat) {
            $pdo->prepare("INSERT INTO kb_categories (name, slug, description, icon, sort_order) VALUES (?, ?, ?, ?, ?)")->execute($cat);
        }
    }
    
} catch (PDOException $e) {
    error_log("KB init error: " . $e->getMessage());
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_article') {
            $title = trim($_POST['title'] ?? '');
            $content = $_POST['content'] ?? '';
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $article_type = $_POST['article_type'] ?? 'faq';
            $video_url = trim($_POST['video_url'] ?? '');
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_published = isset($_POST['is_published']) ? 1 : 0;
            
            if (empty($title) || empty($content)) {
                $error_message = "Vui lòng nhập tiêu đề và nội dung";
            } else {
                $slug = createSlug($title);
                
                // Check unique slug
                $checkSlug = $pdo->prepare("SELECT id FROM kb_articles WHERE slug = ?");
                $checkSlug->execute([$slug]);
                if ($checkSlug->fetch()) {
                    $slug .= '-' . time();
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO kb_articles (category_id, title, slug, content, article_type, video_url, is_featured, is_published, author_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([$category_id, $title, $slug, $content, $article_type, $video_url ?: null, $is_featured, $is_published, $_SESSION['user_id']]);
                
                $success_message = "Đã tạo bài viết thành công";
            }
            
        } elseif ($action === 'update_article') {
            $article_id = (int)($_POST['article_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = $_POST['content'] ?? '';
            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $article_type = $_POST['article_type'] ?? 'faq';
            $video_url = trim($_POST['video_url'] ?? '');
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_published = isset($_POST['is_published']) ? 1 : 0;
            
            if ($article_id > 0 && !empty($title)) {
                $stmt = $pdo->prepare("
                    UPDATE kb_articles 
                    SET category_id = ?, title = ?, content = ?, article_type = ?, video_url = ?, 
                        is_featured = ?, is_published = ?, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$category_id, $title, $content, $article_type, $video_url ?: null, $is_featured, $is_published, $article_id]);
                
                $success_message = "Đã cập nhật bài viết";
            }
            
        } elseif ($action === 'delete_article') {
            $article_id = (int)($_POST['article_id'] ?? 0);
            if ($article_id > 0) {
                $pdo->prepare("DELETE FROM kb_articles WHERE id = ?")->execute([$article_id]);
                $success_message = "Đã xóa bài viết";
            }
            
        } elseif ($action === 'create_category') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fa-folder');
            
            if (!empty($name)) {
                $slug = createSlug($name);
                $stmt = $pdo->prepare("INSERT INTO kb_categories (name, slug, description, icon) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $icon]);
                $success_message = "Đã tạo danh mục";
            }
            
        } elseif ($action === 'update_category') {
            $category_id = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fa-folder');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($category_id > 0 && !empty($name)) {
                $stmt = $pdo->prepare("UPDATE kb_categories SET name = ?, description = ?, icon = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $description, $icon, $is_active, $category_id]);
                $success_message = "Đã cập nhật danh mục";
            }
            
        } elseif ($action === 'delete_category') {
            $category_id = (int)($_POST['category_id'] ?? 0);
            if ($category_id > 0) {
                $pdo->prepare("UPDATE kb_articles SET category_id = NULL WHERE category_id = ?")->execute([$category_id]);
                $pdo->prepare("DELETE FROM kb_categories WHERE id = ?")->execute([$category_id]);
                $success_message = "Đã xóa danh mục";
            }
        }
        
    } catch (PDOException $e) {
        $error_message = "Lỗi: " . $e->getMessage();
    }
}

// Helper function - Tạo slug từ tiếng Việt
function createSlug($string) {
    $string = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/u', 'a', $string);
    $string = preg_replace('/[éèẻẽẹêếềểễệ]/u', 'e', $string);
    $string = preg_replace('/[íìỉĩị]/u', 'i', $string);
    $string = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/u', 'o', $string);
    $string = preg_replace('/[úùủũụưứừửữự]/u', 'u', $string);
    $string = preg_replace('/[ýỳỷỹỵ]/u', 'y', $string);
    $string = preg_replace('/[đ]/u', 'd', $string);
    $string = preg_replace('/[ÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬ]/u', 'A', $string);
    $string = preg_replace('/[ÉÈẺẼẸÊẾỀỂỄỆ]/u', 'E', $string);
    $string = preg_replace('/[ÍÌỈĨỊ]/u', 'I', $string);
    $string = preg_replace('/[ÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢ]/u', 'O', $string);
    $string = preg_replace('/[ÚÙỦŨỤƯỨỪỬỮỰ]/u', 'U', $string);
    $string = preg_replace('/[ÝỲỶỸỴ]/u', 'Y', $string);
    $string = preg_replace('/[Đ]/u', 'D', $string);
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return trim($string, '-');
}

// Lấy danh sách
$tab = $_GET['tab'] ?? 'articles';
$category_filter = $_GET['category'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

try {
    // Categories
    $categories = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM kb_articles WHERE category_id = c.id) as article_count
        FROM kb_categories c 
        ORDER BY c.sort_order, c.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Articles
    $where_conditions = [];
    $params = [];
    
    if ($category_filter) {
        $where_conditions[] = "a.category_id = ?";
        $params[] = $category_filter;
    }
    if ($type_filter) {
        $where_conditions[] = "a.article_type = ?";
        $params[] = $type_filter;
    }
    if ($search) {
        $where_conditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as category_name, u.fullname as author_name
        FROM kb_articles a
        LEFT JOIN kb_categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id
        $where_clause
        ORDER BY a.is_featured DESC, a.created_at DESC
    ");
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_articles,
            SUM(CASE WHEN article_type = 'faq' THEN 1 ELSE 0 END) as faq_count,
            SUM(CASE WHEN article_type = 'guide' THEN 1 ELSE 0 END) as guide_count,
            SUM(CASE WHEN article_type = 'video' THEN 1 ELSE 0 END) as video_count,
            SUM(view_count) as total_views,
            SUM(helpful_yes) as total_helpful
        FROM kb_articles
    ")->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $categories = [];
    $articles = [];
    $stats = ['total_articles' => 0, 'faq_count' => 0, 'guide_count' => 0, 'video_count' => 0, 'total_views' => 0, 'total_helpful' => 0];
}

$type_labels = ['faq' => 'FAQ', 'guide' => 'Hướng dẫn', 'video' => 'Video', 'wiki' => 'Wiki'];
$type_icons = ['faq' => 'fa-question-circle', 'guide' => 'fa-book', 'video' => 'fa-play-circle', 'wiki' => 'fa-file-alt'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Admin - EDUSERVICE</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin-common.css">
    <style>
        .kb-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
        }
        
        .stat-icon.total { background: #e3f2fd; color: #1976d2; }
        .stat-icon.faq { background: #e8f5e9; color: #388e3c; }
        .stat-icon.guide { background: #fff3e0; color: #f57c00; }
        .stat-icon.video { background: #fce4ec; color: #c2185b; }
        .stat-icon.views { background: #f3e5f5; color: #7b1fa2; }
        .stat-icon.helpful { background: #e0f2f1; color: #00897b; }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-card .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        /* Tabs */
        .kb-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .kb-tab {
            padding: 12px 24px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .kb-tab:hover { background: #f1f5f9; color: #1e293b; }
        .kb-tab.active { background: #4CAF50; color: white; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Articles Grid */
        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .article-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .article-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .article-card.featured {
            border: 2px solid #4CAF50;
        }
        
        .article-card-header {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .article-type-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .type-faq { background: #e8f5e9; color: #388e3c; }
        .type-guide { background: #fff3e0; color: #f57c00; }
        .type-video { background: #fce4ec; color: #c2185b; }
        .type-wiki { background: #e3f2fd; color: #1976d2; }
        
        .article-badges {
            display: flex;
            gap: 8px;
        }
        
        .featured-badge {
            padding: 4px 8px;
            background: #fff3e0;
            color: #f57c00;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .draft-badge {
            padding: 4px 8px;
            background: #f5f5f5;
            color: #757575;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .article-card-body {
            padding: 20px;
        }
        
        .article-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 8px;
            line-height: 1.4;
        }
        
        .article-category {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .article-excerpt {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .article-meta {
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: #94a3b8;
        }
        
        .article-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .article-card-footer {
            padding: 16px 20px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .article-actions {
            display: flex;
            gap: 8px;
        }
        
        .article-actions button {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }
        
        .btn-edit { background: #e3f2fd; color: #1976d2; }
        .btn-edit:hover { background: #bbdefb; }
        .btn-delete { background: #ffebee; color: #d32f2f; }
        .btn-delete:hover { background: #ffcdd2; }
        .btn-view { background: #e8f5e9; color: #388e3c; }
        .btn-view:hover { background: #c8e6c9; }
        
        /* Categories Tab */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .category-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .category-card.inactive {
            opacity: 0.6;
        }
        
        .category-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 16px;
        }
        
        .category-name {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .category-description {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        
        .category-stats {
            display: flex;
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        
        .category-stat {
            text-align: center;
        }
        
        .category-stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .category-stat-label {
            font-size: 11px;
            color: #94a3b8;
        }
        
        .category-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        
        /* Filters */
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .filters-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-wrapper {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        
        .search-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .search-input:focus { outline: none; border-color: #4CAF50; }
        
        .filter-select {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            min-width: 150px;
        }
        
        .filter-select:focus { outline: none; border-color: #4CAF50; }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header h3 i { color: #4CAF50; }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #94a3b8;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Form */
        .form-group { margin-bottom: 20px; }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .form-textarea {
            min-height: 200px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .checkbox-label input {
            width: 18px;
            height: 18px;
            accent-color: #4CAF50;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
        }
        
        .btn-secondary:hover { background: #e2e8f0; }
        
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        @media (max-width: 1200px) {
            .kb-stats { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .kb-stats { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns: 1fr; }
            .articles-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-wrapper">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="page-header-left">
                    <h1><i class="fas fa-book-open"></i> Knowledge Base</h1>
                    <p>Quản lý FAQ và tài liệu hướng dẫn</p>
                </div>
                <div class="page-header-right">
                    <button class="btn btn-primary" onclick="openModal('create-article-modal')">
                        <i class="fas fa-plus"></i> Thêm bài viết
                    </button>
                </div>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="kb-stats">
                <div class="stat-card">
                    <div class="stat-icon total"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-number"><?php echo $stats['total_articles'] ?? 0; ?></div>
                    <div class="stat-label">Tổng bài viết</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon faq"><i class="fas fa-question-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['faq_count'] ?? 0; ?></div>
                    <div class="stat-label">FAQ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon guide"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?php echo $stats['guide_count'] ?? 0; ?></div>
                    <div class="stat-label">Hướng dẫn</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon video"><i class="fas fa-play-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['video_count'] ?? 0; ?></div>
                    <div class="stat-label">Video</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon views"><i class="fas fa-eye"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                    <div class="stat-label">Lượt xem</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon helpful"><i class="fas fa-thumbs-up"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_helpful'] ?? 0); ?></div>
                    <div class="stat-label">Hữu ích</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="kb-tabs">
                <button class="kb-tab <?php echo $tab === 'articles' ? 'active' : ''; ?>" onclick="switchTab('articles')">
                    <i class="fas fa-file-alt"></i> Bài viết
                </button>
                <button class="kb-tab <?php echo $tab === 'categories' ? 'active' : ''; ?>" onclick="switchTab('categories')">
                    <i class="fas fa-folder"></i> Danh mục
                </button>
            </div>
            
            <!-- Tab: Articles -->
            <div class="tab-content <?php echo $tab === 'articles' ? 'active' : ''; ?>" id="tab-articles">
                <!-- Filters -->
                <div class="filters-card">
                    <form method="GET" class="filters-form">
                        <input type="hidden" name="tab" value="articles">
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="search-input" placeholder="Tìm kiếm bài viết..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <select name="category" class="filter-select" onchange="this.form.submit()">
                            <option value="">Tất cả danh mục</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="type" class="filter-select" onchange="this.form.submit()">
                            <option value="">Tất cả loại</option>
                            <?php foreach ($type_labels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 10px 16px;">
                            <i class="fas fa-filter"></i>
                        </button>
                    </form>
                </div>
                
                <?php if (empty($articles)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>Chưa có bài viết nào</h3>
                        <p>Tạo bài viết đầu tiên cho Knowledge Base</p>
                    </div>
                <?php else: ?>
                    <div class="articles-grid">
                        <?php foreach ($articles as $article): ?>
                            <div class="article-card <?php echo $article['is_featured'] ? 'featured' : ''; ?>">
                                <div class="article-card-header">
                                    <span class="article-type-badge type-<?php echo $article['article_type']; ?>">
                                        <i class="fas <?php echo $type_icons[$article['article_type']] ?? 'fa-file'; ?>"></i>
                                        <?php echo $type_labels[$article['article_type']] ?? $article['article_type']; ?>
                                    </span>
                                    <div class="article-badges">
                                        <?php if ($article['is_featured']): ?>
                                            <span class="featured-badge"><i class="fas fa-star"></i> Nổi bật</span>
                                        <?php endif; ?>
                                        <?php if (!$article['is_published']): ?>
                                            <span class="draft-badge"><i class="fas fa-eye-slash"></i> Nháp</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="article-card-body">
                                    <h4 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h4>
                                    <div class="article-category">
                                        <i class="fas fa-folder"></i>
                                        <?php echo htmlspecialchars($article['category_name'] ?? 'Chưa phân loại'); ?>
                                    </div>
                                    <p class="article-excerpt"><?php echo htmlspecialchars(strip_tags(substr($article['content'], 0, 150))); ?>...</p>
                                    <div class="article-meta">
                                        <span><i class="fas fa-eye"></i> <?php echo $article['view_count']; ?></span>
                                        <span><i class="fas fa-thumbs-up"></i> <?php echo $article['helpful_yes']; ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($article['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="article-card-footer">
                                    <span style="font-size: 12px; color: #94a3b8;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name'] ?? 'Hệ thống'); ?>
                                    </span>
                                    <div class="article-actions">
                                        <button class="btn-view" onclick="viewArticle(<?php echo $article['id']; ?>)" title="Xem">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-edit" onclick="editArticle(<?php echo $article['id']; ?>)" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Xóa bài viết này?')">
                                            <input type="hidden" name="action" value="delete_article">
                                            <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                            <button type="submit" class="btn-delete" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab: Categories -->
            <div class="tab-content <?php echo $tab === 'categories' ? 'active' : ''; ?>" id="tab-categories">
                <div style="margin-bottom: 20px; text-align: right;">
                    <button class="btn btn-primary" onclick="openModal('create-category-modal')">
                        <i class="fas fa-plus"></i> Thêm danh mục
                    </button>
                </div>
                
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>Chưa có danh mục nào</h3>
                        <p>Tạo danh mục để phân loại bài viết</p>
                    </div>
                <?php else: ?>
                    <div class="categories-grid">
                        <?php foreach ($categories as $cat): ?>
                            <div class="category-card <?php echo !$cat['is_active'] ? 'inactive' : ''; ?>">
                                <div class="category-icon">
                                    <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                </div>
                                <h4 class="category-name"><?php echo htmlspecialchars($cat['name']); ?></h4>
                                <p class="category-description"><?php echo htmlspecialchars($cat['description'] ?: 'Không có mô tả'); ?></p>
                                <div class="category-stats">
                                    <div class="category-stat">
                                        <div class="category-stat-number"><?php echo $cat['article_count']; ?></div>
                                        <div class="category-stat-label">Bài viết</div>
                                    </div>
                                </div>
                                <div class="category-actions">
                                    <button class="btn btn-secondary" style="flex: 1; padding: 8px;" onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cat['description'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cat['icon']); ?>', <?php echo $cat['is_active']; ?>)">
                                        <i class="fas fa-edit"></i> Sửa
                                    </button>
                                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Xóa danh mục này? Bài viết sẽ trở thành chưa phân loại.')">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn" style="width: 100%; padding: 8px; background: #ffebee; color: #d32f2f;">
                                            <i class="fas fa-trash"></i> Xóa
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Create Article Modal -->
    <div class="modal-overlay" id="create-article-modal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Thêm bài viết mới</h3>
                <button class="modal-close" onclick="closeModal('create-article-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_article">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tiêu đề <span style="color: #f44336;">*</span></label>
                        <input type="text" name="title" class="form-input" required placeholder="Nhập tiêu đề bài viết">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Danh mục</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- Chưa phân loại --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Loại bài viết</label>
                            <select name="article_type" class="form-select">
                                <option value="faq">FAQ - Câu hỏi thường gặp</option>
                                <option value="guide">Hướng dẫn</option>
                                <option value="video">Video</option>
                                <option value="wiki">Wiki</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">URL Video (nếu có)</label>
                        <input type="url" name="video_url" class="form-input" placeholder="https://youtube.com/watch?v=...">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nội dung <span style="color: #f44336;">*</span></label>
                        <textarea name="content" class="form-textarea" required placeholder="Nhập nội dung bài viết (hỗ trợ HTML)..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_featured">
                                <span>Bài viết nổi bật</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_published" checked>
                                <span>Xuất bản ngay</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-article-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tạo bài viết</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Category Modal -->
    <div class="modal-overlay" id="create-category-modal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-folder-plus"></i> Thêm danh mục mới</h3>
                <button class="modal-close" onclick="closeModal('create-category-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_category">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tên danh mục <span style="color: #f44336;">*</span></label>
                        <input type="text" name="name" class="form-input" required placeholder="Nhập tên danh mục">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Mô tả ngắn về danh mục"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon (Font Awesome)</label>
                        <input type="text" name="icon" class="form-input" value="fa-folder" placeholder="fa-folder">
                        <small style="color: #94a3b8; font-size: 12px;">Xem thêm tại: fontawesome.com/icons</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-category-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tạo danh mục</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div class="modal-overlay" id="edit-category-modal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Chỉnh sửa danh mục</h3>
                <button class="modal-close" onclick="closeModal('edit-category-modal')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_category">
                <input type="hidden" name="category_id" id="edit-cat-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tên danh mục <span style="color: #f44336;">*</span></label>
                        <input type="text" name="name" id="edit-cat-name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="edit-cat-description" class="form-textarea" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon</label>
                        <input type="text" name="icon" id="edit-cat-icon" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" id="edit-cat-active">
                            <span>Kích hoạt danh mục</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-category-modal')">Hủy</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const articles = <?php echo json_encode($articles); ?>;
        const categories = <?php echo json_encode($categories); ?>;
        
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function switchTab(tabName) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // Update tabs
            document.querySelectorAll('.kb-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        function editCategory(id, name, description, icon, isActive) {
            document.getElementById('edit-cat-id').value = id;
            document.getElementById('edit-cat-name').value = name;
            document.getElementById('edit-cat-description').value = description;
            document.getElementById('edit-cat-icon').value = icon;
            document.getElementById('edit-cat-active').checked = isActive == 1;
            openModal('edit-category-modal');
        }
        
        function viewArticle(id) {
            const article = articles.find(a => a.id == id);
            if (!article) return;
            
            Swal.fire({
                title: article.title,
                html: `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <div style="margin-bottom: 16px; padding: 12px; background: #f8fafc; border-radius: 8px;">
                            <span style="color: #64748b; font-size: 12px;">
                                <i class="fas fa-folder"></i> ${article.category_name || 'Chưa phân loại'} | 
                                <i class="fas fa-eye"></i> ${article.view_count} lượt xem
                            </span>
                        </div>
                        <div style="line-height: 1.8;">${article.content}</div>
                    </div>
                `,
                width: 700,
                showCloseButton: true,
                showConfirmButton: false
            });
        }
        
        function editArticle(id) {
            const article = articles.find(a => a.id == id);
            if (!article) return;
            
            // For simplicity, redirect to a separate edit page or use AJAX
            Swal.fire({
                title: 'Chỉnh sửa bài viết',
                html: `
                    <form id="edit-article-form" method="POST" style="text-align: left;">
                        <input type="hidden" name="action" value="update_article">
                        <input type="hidden" name="article_id" value="${article.id}">
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Tiêu đề</label>
                            <input type="text" name="title" value="${article.title}" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;" required>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Danh mục</label>
                            <select name="category_id" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="">-- Chưa phân loại --</option>
                                ${categories.map(c => `<option value="${c.id}" ${c.id == article.category_id ? 'selected' : ''}>${c.name}</option>`).join('')}
                            </select>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Loại</label>
                            <select name="article_type" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                <option value="faq" ${article.article_type === 'faq' ? 'selected' : ''}>FAQ</option>
                                <option value="guide" ${article.article_type === 'guide' ? 'selected' : ''}>Hướng dẫn</option>
                                <option value="video" ${article.article_type === 'video' ? 'selected' : ''}>Video</option>
                                <option value="wiki" ${article.article_type === 'wiki' ? 'selected' : ''}>Wiki</option>
                            </select>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Nội dung</label>
                            <textarea name="content" rows="8" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; resize: vertical;" required>${article.content}</textarea>
                        </div>
                        <div style="display: flex; gap: 20px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="is_featured" ${article.is_featured ? 'checked' : ''}> Nổi bật
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="is_published" ${article.is_published ? 'checked' : ''}> Xuất bản
                            </label>
                        </div>
                    </form>
                `,
                width: 600,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save"></i> Lưu',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#4CAF50',
                preConfirm: () => {
                    document.getElementById('edit-article-form').submit();
                }
            });
        }
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>