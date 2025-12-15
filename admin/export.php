<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Export Dữ liệu";
$current_page = "export";

// Thư mục gốc dự án
$project_root = dirname(__DIR__);
$export_dir = $project_root . '/uploads/exports';

// Tạo thư mục nếu chưa có
if (!is_dir($export_dir)) {
    mkdir($export_dir, 0755, true);
}

$error_message = '';

// Xử lý export
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    try {
        if ($action === 'export_project') {
            $selected_folders = $_POST['folders'] ?? [];
            $selected_files = $_POST['files'] ?? [];
            $include_db = isset($_POST['include_db']);
            $include_uploads = isset($_POST['include_uploads']);
            
            if (empty($selected_folders) && empty($selected_files) && !$include_db) {
                throw new Exception("Vui lòng chọn ít nhất một thành phần để export");
            }
            
            $export_name = 'eduservice_export_' . date('Y-m-d_H-i-s') . '.zip';
            $export_path = $export_dir . '/' . $export_name;
            
            $zip = new ZipArchive();
            if ($zip->open($export_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Không thể tạo file ZIP");
            }
            
            $manifest = [
                'name' => 'EDUSERVICE Export',
                'date' => date('Y-m-d H:i:s'),
                'version' => '1.0',
                'folders' => $selected_folders,
                'individual_files' => $selected_files,
                'include_db' => $include_db,
                'files' => []
            ];
            
            // Export database
            if ($include_db) {
                $db_path = $project_root . '/db/edservices.db';
                if (file_exists($db_path)) {
                    $zip->addFile($db_path, 'db/edservices.db');
                    $manifest['database'] = 'db/edservices.db';
                }
            }
            
            // Export selected folders
            foreach ($selected_folders as $folder) {
                $folder_path = $project_root . '/' . $folder;
                if (is_dir($folder_path)) {
                    addFolderToZip($zip, $folder_path, $folder, $manifest, $include_uploads);
                }
            }
            
            // Export individual files
            foreach ($selected_files as $file) {
                $file_path = $project_root . '/' . $file;
                if (is_file($file_path)) {
                    $real_path = realpath($file_path);
                    $real_root = realpath($project_root);
                    if (strpos($real_path, $real_root) === 0) {
                        $zip->addFile($file_path, $file);
                        $manifest['files'][] = [
                            'path' => $file,
                            'size' => filesize($file_path)
                        ];
                    }
                }
            }
            // Add manifest
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $zip->close();
            
            // Download file - SỬA LẠI PHẦN NÀY
            if (file_exists($export_path)) {
                // Clear any output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($export_name) . '"');
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                header('Content-Length: ' . filesize($export_path));
                
                readfile($export_path);
                
                // Xóa file tạm sau khi download
                @unlink($export_path);
                exit();
            } else {
                throw new Exception("Không thể tạo file export");
            }
            
        } elseif ($action === 'export_file') {
            $file_path = $_POST['file_path'] ?? $_GET['file'] ?? '';
            $full_path = $project_root . '/' . $file_path;
            
            if (!file_exists($full_path) || !is_file($full_path)) {
                throw new Exception("File không tồn tại");
            }
            
            $real_path = realpath($full_path);
            $real_root = realpath($project_root);
            if (strpos($real_path, $real_root) !== 0) {
                throw new Exception("Truy cập không hợp lệ");
            }
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($full_path));
            readfile($full_path);
            exit();
            
        } elseif ($action === 'export_database') {
            $db_path = $project_root . '/db/edservices.db';
            $export_name = 'edservices_db_' . date('Y-m-d_H-i-s') . '.db';
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $export_name . '"');
            header('Content-Length: ' . filesize($db_path));
            readfile($db_path);
            exit();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Helper function - Add folder to ZIP
function addFolderToZip($zip, $folder, $zipPath, &$manifest, $include_uploads) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        $relativePath = $zipPath . '/' . substr($filePath, strlen($folder) + 1);
        
        if (!$include_uploads && strpos($relativePath, 'uploads/') !== false) {
            continue;
        }
        
        $skipPatterns = ['.git', 'node_modules', '.env', '.DS_Store', 'Thumbs.db'];
        $skip = false;
        foreach ($skipPatterns as $pattern) {
            if (strpos($relativePath, $pattern) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;
        
        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($filePath, $relativePath);
            $manifest['files'][] = [
                'path' => $relativePath,
                'size' => $file->getSize()
            ];
        }
    }
}

// Helper functions
function getFolderSize($dir) {
    $size = 0;
    try {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
    } catch (Exception $e) {}
    return $size;
}

function formatSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function getFileIcon($ext) {
    $icons = [
        'php' => 'fa-php', 'js' => 'fa-js', 'css' => 'fa-css3-alt', 'html' => 'fa-html5',
        'json' => 'fa-code', 'db' => 'fa-database', 'sql' => 'fa-database',
        'png' => 'fa-image', 'jpg' => 'fa-image', 'jpeg' => 'fa-image', 'gif' => 'fa-image', 'svg' => 'fa-image',
        'pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel', 'zip' => 'fa-file-archive',
        'txt' => 'fa-file-alt', 'md' => 'fa-file-alt'
    ];
    return $icons[strtolower($ext)] ?? 'fa-file';
}

// Thêm function mới để lấy cấu trúc thư mục đệ quy
function getFolderStructure($path, $relativePath = '', $maxDepth = 10, $currentDepth = 0) {
    $result = [];
    if ($currentDepth >= $maxDepth) return $result;
    
    $skipDirs = ['.', '..', '.git', 'node_modules', '.idea', '.DS_Store'];
    $items = @scandir($path);
    
    if (!$items) return $result;
    
    foreach ($items as $item) {
        if (in_array($item, $skipDirs)) continue;
        
        $fullPath = $path . '/' . $item;
        $itemRelativePath = $relativePath ? $relativePath . '/' . $item : $item;
        
        if (is_dir($fullPath)) {
            $subItems = getFolderStructure($fullPath, $itemRelativePath, $maxDepth, $currentDepth + 1);
            $fileCount = 0;
            try {
                $fileCount = iterator_count(new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                ));
            } catch (Exception $e) {}
            
            $result[] = [
                'type' => 'folder',
                'name' => $item,
                'path' => $itemRelativePath,
                'size' => getFolderSize($fullPath),
                'children' => $subItems,
                'file_count' => $fileCount
            ];
        } else {
            $result[] = [
                'type' => 'file',
                'name' => $item,
                'path' => $itemRelativePath,
                'size' => @filesize($fullPath) ?: 0,
                'ext' => pathinfo($item, PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Sort: folders first, then files
    usort($result, function($a, $b) {
        if ($a['type'] === $b['type']) return strcasecmp($a['name'], $b['name']);
        return $a['type'] === 'folder' ? -1 : 1;
    });
    
    return $result;
}

// Lấy cấu trúc thư mục với thư mục con - SỬA để lấy toàn bộ (maxDepth = 10)
$folder_structure = [];
$root_items = @scandir($project_root);

// Lấy cả file ở root
$root_files = [];

if ($root_items) {
    foreach ($root_items as $item) {
        if (in_array($item, ['.', '..', '.git', 'node_modules', '.idea', '.DS_Store'])) continue;
        $path = $project_root . '/' . $item;
        if (is_dir($path)) {
            $fileCount = 0;
            try {
                $fileCount = iterator_count(new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                ));
            } catch (Exception $e) {}
            
            $folder_structure[] = [
                'type' => 'folder',
                'name' => $item,
                'path' => $item,
                'size' => getFolderSize($path),
                'children' => getFolderStructure($path, $item, 10, 1),
                'file_count' => $fileCount
            ];
        } else {
            $root_files[] = [
                'type' => 'file',
                'name' => $item,
                'path' => $item,
                'size' => @filesize($path) ?: 0,
                'ext' => pathinfo($item, PATHINFO_EXTENSION)
            ];
        }
    }
}

// Sort folder_structure
usort($folder_structure, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

// Sort root_files
usort($root_files, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

// Khởi tạo $main_folders từ $folder_structure (để tương thích với code cũ)
$main_folders = $folder_structure;

// Thông tin database
$db_path = $project_root . '/db/edservices.db';
$db_size = file_exists($db_path) ? filesize($db_path) : 0;

try {
    $db_stats = [
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0,
        'classes' => $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn() ?? 0,
    ];
} catch (Exception $e) {
    $db_stats = ['users' => 0, 'classes' => 0];
}

// Tính tổng kích thước dự án
$total_size = 0;
foreach ($main_folders as $folder) {
    $total_size += $folder['size'];
}
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
        .export-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
        }
        
        .export-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .export-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .export-card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .export-card-header h3 i { color: #4CAF50; }
        
        .export-card-body { padding: 24px; }
        
        /* Project Tree */
        .project-tree {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            max-height: 450px;
            overflow-y: auto;
        }
        
        .tree-header {
            padding: 16px 20px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .tree-header label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .tree-header input {
            width: 18px;
            height: 18px;
            accent-color: white;
        }
        
        .tree-body { padding: 12px 0; }
        
        .tree-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .tree-item:hover {
            background: #f8fafc;
            border-left-color: #4CAF50;
        }
        
        .tree-item.selected {
            background: #e8f5e9;
            border-left-color: #4CAF50;
        }
        
        .tree-item input {
            width: 18px;
            height: 18px;
            accent-color: #4CAF50;
            flex-shrink: 0;
        }
        
        .tree-item-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .tree-item-icon .fa-folder { color: #f59e0b; font-size: 18px; }
        
        .tree-item-info { flex: 1; min-width: 0; }
        
        .tree-item-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .tree-item-meta {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .tree-item-size {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .tree-search {
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 52px;
            z-index: 9;
        }
        
        .tree-search-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
            background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='%2394a3b8' viewBox='0 0 24 24'%3E%3Cpath d='M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z'/%3E%3C/svg%3E") no-repeat 12px center;
            background-size: 20px;
        }
        
        .tree-search-input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .tree-search-input::placeholder {
            color: #94a3b8;
        }
        
        .search-results-info {
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
            display: none;
        }
        
        .search-results-info.active {
            display: block;
        }
        
        .search-results-info .highlight-count {
            color: #4CAF50;
            font-weight: 600;
        }
        
        .tree-item.search-match,
        .tree-item-file.search-match {
            background: #fef3c7 !important;
            border-left-color: #f59e0b !important;
        }
        
        .tree-item.search-hidden,
        .tree-item-file.search-hidden,
        .tree-folder-wrapper.search-hidden {
            display: none !important;
        }
        
        .search-highlight {
            background: #fde047;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        .clear-search {
            position: absolute;
            right: 28px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            display: none;
        }
        
        .clear-search:hover {
            color: #64748b;
        }
        
        .tree-search-wrapper {
            position: relative;
        }
        
        .tree-search-wrapper.has-value .clear-search {
            display: block;
        }

        /* Expand/Collapse All buttons */
        .tree-actions {
            display: flex;
            gap: 8px;
            padding: 8px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .tree-action-btn {
            padding: 6px 12px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .tree-action-btn:hover {
            background: #f1f5f9;
            border-color: #4CAF50;
            color: #4CAF50;
        }
        
        .tree-action-btn i {
            font-size: 10px;
        }
        
        /* Export Options */
        .export-options-card {
            margin-top: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .export-options-card h4 {
            margin: 0 0 16px;
            font-size: 14px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-options-card h4 i { color: #4CAF50; }
        
        .option-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .option-item:hover { border-color: #4CAF50; }
        .option-item.checked { border-color: #4CAF50; background: #f0fdf4; }
        
        .option-item input {
            width: 20px;
            height: 20px;
            accent-color: #4CAF50;
        }
        
        .option-info { flex: 1; }
        .option-info strong { display: block; font-size: 14px; color: #1e293b; }
        .option-info span { font-size: 12px; color: #64748b; }
        
        .option-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #e8f5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            font-size: 18px;
        }
        
        /* Quick Export Panel */
        .quick-export {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 24px;
        }
        
        .quick-export-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            border-radius: 12px 12px 0 0;
            color: white;
        }
        
        .quick-export-header h4 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-export-body { padding: 20px; }
        
        .quick-export-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .quick-export-item:hover {
            background: #f0fdf4;
            border-color: #4CAF50;
            transform: translateX(4px);
        }
        
        .quick-export-item:last-child { margin-bottom: 0; }
        
        .quick-export-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .quick-export-icon.db { color: #336791; }
        .quick-export-icon.zip { color: #4CAF50; }
        .quick-export-icon.code { color: #f59e0b; }
        
        .quick-export-info { flex: 1; }
        .quick-export-info strong { display: block; font-size: 14px; color: #1e293b; margin-bottom: 4px; }
        .quick-export-info span { font-size: 12px; color: #64748b; }
        
        .quick-export-btn {
            padding: 10px 18px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .quick-export-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        /* DB Info */
        .db-info-card {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 12px;
            border: 1px solid #90caf9;
        }
        
        .db-info-card h5 {
            margin: 0 0 16px;
            font-size: 14px;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .db-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .db-stat {
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .db-stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .db-stat-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }
        
.tree-toggle {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #64748b;
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        
        .tree-toggle.expanded {
            transform: rotate(90deg);
        }
        
        .tree-toggle.empty {
            visibility: hidden;
        }
        
        .tree-children {
            display: none;
            padding-left: 20px;
            border-left: 2px solid #e2e8f0;
            margin-left: 30px;
        }
        
        .tree-children.expanded {
            display: block;
        }
        
        .tree-item-file {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 20px 8px 40px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .tree-item-file:hover {
            background: #f8fafc;
            border-left-color: #4CAF50;
        }
        
        .tree-item-file.selected {
            background: #e8f5e9;
            border-left-color: #4CAF50;
        }
        
        .tree-item-file input {
            width: 16px;
            height: 16px;
            accent-color: #4CAF50;
            flex-shrink: 0;
        }
        
        .file-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
        }
        
        .file-icon.php { color: #777BB4; }
        .file-icon.js { color: #F7DF1E; }
        .file-icon.css { color: #264de4; }
        .file-icon.html { color: #e34c26; }
        .file-icon.json { color: #000; }
        .file-icon.db { color: #336791; }
        .file-icon.image { color: #4CAF50; }
        .file-icon.default { color: #64748b; }
        
        .tree-item-sub {
            padding-left: 20px;
        }
        
        .tree-item-sub .tree-item-icon {
            width: 30px;
            height: 30px;
        }
        
        .tree-item-sub .tree-item-icon .fa-folder {
            font-size: 14px;
        }
        
        .load-more-btn {
            padding: 8px 16px;
            margin: 8px 20px 8px 50px;
            background: #f1f5f9;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            color: #64748b;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .load-more-btn:hover {
            background: #e2e8f0;
            border-color: #4CAF50;
            color: #4CAF50;
        }

        /* Buttons */
        .btn-export {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4CAF50, #388E3C);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.35);
        }
        
        .btn-export:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .selected-summary {
            margin-top: 20px;
            padding: 16px;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-radius: 10px;
            border: 1px solid #a5d6a7;
        }
        
        .selected-summary-title {
            font-size: 13px;
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .selected-summary-stats {
            display: flex;
            gap: 20px;
        }
        
        .summary-stat {
            text-align: center;
        }
        
        .summary-stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #1b5e20;
        }
        
        .summary-stat-label {
            font-size: 11px;
            color: #388e3c;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        /* Project Info */
        .project-info {
            padding: 16px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .project-info-item {
            text-align: center;
        }
        
        .project-info-number {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }
        
        .project-info-label {
            font-size: 11px;
            color: #64748b;
        }
        
        @media (max-width: 1200px) {
            .export-layout {
                grid-template-columns: 1fr;
            }
            .quick-export {
                position: static;
            }
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
                    <h1><i class="fas fa-file-export"></i> Export Dữ liệu</h1>
                    <p>Xuất dự án hoặc các thành phần cụ thể</p>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="export-layout">
                <!-- Main Export Form -->
                <div class="export-card">
                    <div class="export-card-header">
                        <h3><i class="fas fa-folder-tree"></i> Chọn thành phần xuất</h3>
                        <span class="badge badge-primary"><?php echo count($main_folders); ?> thư mục</span>
                    </div>
                    <div class="export-card-body">
                        <form method="POST" id="export-form">
                            <input type="hidden" name="action" value="export_project">

                            <!-- Project Tree -->
                            <div class="project-tree">
                                <div class="tree-header">
                                    <label>
                                        <input type="checkbox" id="select-all-folders" onchange="toggleAllFolders()">
                                        <i class="fas fa-folder"></i>
                                        <span>EDUSERVICE/</span>
                                    </label>
                                    <span style="font-size: 12px; opacity: 0.9;"><?php echo formatSize($total_size); ?></span>
                                </div>
                                
                                <!-- Search Box -->
                                <div class="tree-search">
                                    <div class="tree-search-wrapper" id="search-wrapper">
                                        <input type="text" class="tree-search-input" id="tree-search" 
                                               placeholder="Tìm kiếm file, thư mục..." 
                                               oninput="handleSearch(this.value)">
                                        <button type="button" class="clear-search" onclick="clearSearch()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="search-results-info" id="search-results-info">
                                        Tìm thấy <span class="highlight-count" id="search-count">0</span> kết quả
                                    </div>
                                </div>
                                
                                <!-- Tree Actions -->
                                <div class="tree-actions">
                                    <button type="button" class="tree-action-btn" onclick="expandAllFolders()">
                                        <i class="fas fa-expand-alt"></i> Mở rộng tất cả
                                    </button>
                                    <button type="button" class="tree-action-btn" onclick="collapseAllFolders()">
                                        <i class="fas fa-compress-alt"></i> Thu gọn tất cả
                                    </button>
                                </div>
                                
                                <div class="tree-body" id="tree-body">
                                    <?php 
                                    function renderTreeItem($item, $depth = 0) {
                                        $hasChildren = !empty($item['children']);
                                        $paddingLeft = 20 + ($depth * 20);
                                        
                                        if ($item['type'] === 'folder'):
                                    ?>
                                        <div class="tree-folder-wrapper" data-path="<?php echo htmlspecialchars($item['path']); ?>" data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>">
                                            <div class="tree-item" style="padding-left: <?php echo $paddingLeft; ?>px;" onclick="toggleFolder(this, event)" data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>">
                                                <span class="tree-toggle <?php echo $hasChildren ? '' : 'empty'; ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                                <input type="checkbox" name="folders[]" value="<?php echo htmlspecialchars($item['path']); ?>" 
                                                       class="folder-checkbox" data-size="<?php echo $item['size']; ?>" 
                                                       data-files="<?php echo $item['file_count']; ?>"
                                                       onclick="event.stopPropagation(); handleFolderCheck(this);">
                                                <div class="tree-item-icon">
                                                    <i class="fas fa-folder"></i>
                                                </div>
                                                <div class="tree-item-info">
                                                    <div class="tree-item-name"><?php echo htmlspecialchars($item['name']); ?>/</div>
                                                    <div class="tree-item-meta"><?php echo $item['file_count']; ?> files</div>
                                                </div>
                                                <span class="tree-item-size"><?php echo formatSize($item['size']); ?></span>
                                            </div>
                                            <?php if ($hasChildren): ?>
                                            <div class="tree-children" data-path="<?php echo htmlspecialchars($item['path']); ?>">
                                                <?php foreach ($item['children'] as $child): ?>
                                                    <?php renderTreeItem($child, $depth + 1); ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php 
                                        else: // File
                                            $ext = strtolower($item['ext']);
                                            $iconClass = 'default';
                                            if (in_array($ext, ['php'])) $iconClass = 'php';
                                            elseif (in_array($ext, ['js'])) $iconClass = 'js';
                                            elseif (in_array($ext, ['css'])) $iconClass = 'css';
                                            elseif (in_array($ext, ['html', 'htm'])) $iconClass = 'html';
                                            elseif (in_array($ext, ['json'])) $iconClass = 'json';
                                            elseif (in_array($ext, ['db', 'sqlite'])) $iconClass = 'db';
                                            elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'])) $iconClass = 'image';
                                    ?>
                                        <label class="tree-item-file" style="padding-left: <?php echo $paddingLeft + 24; ?>px;" data-name="<?php echo htmlspecialchars(strtolower($item['name'])); ?>" data-path="<?php echo htmlspecialchars($item['path']); ?>">
                                            <input type="checkbox" name="files[]" value="<?php echo htmlspecialchars($item['path']); ?>" 
                                                   class="file-checkbox" data-size="<?php echo $item['size']; ?>"
                                                   onchange="updateSummary(); updateParentCheckbox(this);">
                                            <div class="file-icon <?php echo $iconClass; ?>">
                                                <i class="fas <?php echo getFileIcon($ext); ?>"></i>
                                            </div>
                                            <div class="tree-item-info">
                                                <div class="tree-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                            </div>
                                            <span class="tree-item-size"><?php echo formatSize($item['size']); ?></span>
                                        </label>
                                    <?php 
                                        endif;
                                    }
                                    
                                    // Render folders first
                                    foreach ($folder_structure as $folder):
                                        renderTreeItem($folder);
                                    endforeach;
                                    
                                    // Then render root files
                                    foreach ($root_files as $file):
                                        renderTreeItem($file, 0);
                                    endforeach;
                                    ?>
                                </div>
                                <div class="project-info">
                                    <div class="project-info-item">
                                        <div class="project-info-number"><?php echo count($main_folders); ?></div>
                                        <div class="project-info-label">Thư mục</div>
                                    </div>
                                    <div class="project-info-item">
                                        <div class="project-info-number"><?php echo formatSize($total_size); ?></div>
                                        <div class="project-info-label">Tổng dung lượng</div>
                                    </div>
                                    <div class="project-info-item">
                                        <div class="project-info-number"><?php echo formatSize($db_size); ?></div>
                                        <div class="project-info-label">Database</div>
                                    </div>
                                </div>
                            </div>
 
                            <!-- Export Options -->
                            <div class="export-options-card">
                                <h4><i class="fas fa-cog"></i> Tùy chọn Export</h4>
                                <div class="option-group">
                                    <label class="option-item" onclick="this.classList.toggle('checked')">
                                        <input type="checkbox" name="include_db" checked onchange="updateSummary()">
                                        <div class="option-icon"><i class="fas fa-database"></i></div>
                                        <div class="option-info">
                                            <strong>Bao gồm Database</strong>
                                            <span>File SQLite (<?php echo formatSize($db_size); ?>)</span>
                                        </div>
                                    </label>
                                    <label class="option-item" onclick="this.classList.toggle('checked')">
                                        <input type="checkbox" name="include_uploads" onchange="updateSummary()">
                                        <div class="option-icon"><i class="fas fa-images"></i></div>
                                        <div class="option-info">
                                            <strong>Bao gồm Uploads</strong>
                                            <span>Ảnh, tài liệu người dùng tải lên</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Selected Summary -->
                            <div class="selected-summary" id="selected-summary" style="display: none;">
                                <div class="selected-summary-title">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Đã chọn để export</span>
                                </div>
                                <div class="selected-summary-stats">
                                    <div class="summary-stat">
                                        <div class="summary-stat-number" id="summary-folders">0</div>
                                        <div class="summary-stat-label">Thư mục</div>
                                    </div>
                                    <div class="summary-stat">
                                        <div class="summary-stat-number" id="summary-files">0</div>
                                        <div class="summary-stat-label">Files</div>
                                    </div>
                                    <div class="summary-stat">
                                        <div class="summary-stat-number" id="summary-size">0 KB</div>
                                        <div class="summary-stat-label">Dung lượng</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-export" id="btn-export">
                                <i class="fas fa-download"></i>
                                <span>Tạo file Export (.zip)</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Export Panel -->
                <div class="quick-export">
                    <div class="quick-export-header">
                        <h4><i class="fas fa-bolt"></i> Export nhanh</h4>
                    </div>
                    <div class="quick-export-body">
                        <!-- Export Database Only -->
                        <form method="POST" class="quick-export-item" onsubmit="return true;">
                            <input type="hidden" name="action" value="export_database">
                            <div class="quick-export-icon db">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="quick-export-info">
                                <strong>Chỉ Database</strong>
                                <span><?php echo formatSize($db_size); ?> • SQLite</span>
                            </div>
                            <button type="submit" class="quick-export-btn">
                                <i class="fas fa-download"></i>
                            </button>
                        </form>
                        
                        <!-- Export Full Project -->
                        <div class="quick-export-item" onclick="selectAllAndExport()">
                            <div class="quick-export-icon zip">
                                <i class="fas fa-file-archive"></i>
                            </div>
                            <div class="quick-export-info">
                                <strong>Toàn bộ dự án</strong>
                                <span><?php echo formatSize($total_size + $db_size); ?> • ZIP</span>
                            </div>
                            <button type="button" class="quick-export-btn">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                        
                        <!-- Export Code Only -->
                        <div class="quick-export-item" onclick="selectCodeFolders()">
                            <div class="quick-export-icon code">
                                <i class="fas fa-code"></i>
                            </div>
                            <div class="quick-export-info">
                                <strong>Chỉ mã nguồn</strong>
                                <span>PHP, CSS, JS (không uploads)</span>
                            </div>
                            <button type="button" class="quick-export-btn">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                        
                        <!-- Database Info -->
                        <div class="db-info-card">
                            <h5><i class="fas fa-chart-pie"></i> Thông tin Database</h5>
                            <div class="db-stats">
                                <div class="db-stat">
                                    <div class="db-stat-number"><?php echo $db_stats['users']; ?></div>
                                    <div class="db-stat-label">Người dùng</div>
                                </div>
                                <div class="db-stat">
                                    <div class="db-stat-number"><?php echo $db_stats['classes']; ?></div>
                                    <div class="db-stat-label">Lớp học</div>
                                </div>
                                <div class="db-stat">
                                    <div class="db-stat-number"><?php echo formatSize($db_size); ?></div>
                                    <div class="db-stat-label">Dung lượng</div>
                                </div>
                                <div class="db-stat">
                                    <div class="db-stat-number">SQLite</div>
                                    <div class="db-stat-label">Loại DB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

         let searchTimeout = null;
        
        function handleSearch(query) {
            const wrapper = document.getElementById('search-wrapper');
            
            if (query.length > 0) {
                wrapper.classList.add('has-value');
            } else {
                wrapper.classList.remove('has-value');
            }
            
            // Debounce search
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 200);
        }
        
        function performSearch(query) {
            const treeBody = document.getElementById('tree-body');
            const resultsInfo = document.getElementById('search-results-info');
            const countSpan = document.getElementById('search-count');
            
            // Reset all items
            treeBody.querySelectorAll('.tree-item, .tree-item-file, .tree-folder-wrapper').forEach(el => {
                el.classList.remove('search-match', 'search-hidden');
            });
            
            // Reset highlighted text
            treeBody.querySelectorAll('.tree-item-name').forEach(el => {
                el.innerHTML = el.textContent;
            });
            
            if (!query || query.length < 1) {
                resultsInfo.classList.remove('active');
                // Collapse all when search is cleared
                collapseAllFolders();
                return;
            }
            
            query = query.toLowerCase();
            let matchCount = 0;
            
            // Search folders
            treeBody.querySelectorAll('.tree-folder-wrapper').forEach(wrapper => {
                const name = wrapper.dataset.name || '';
                const path = wrapper.dataset.path || '';
                const treeItem = wrapper.querySelector(':scope > .tree-item');
                
                if (name.includes(query) || path.toLowerCase().includes(query)) {
                    wrapper.classList.add('search-match');
                    treeItem.classList.add('search-match');
                    matchCount++;
                    
                    // Highlight matching text
                    const nameEl = treeItem.querySelector('.tree-item-name');
                    if (nameEl) {
                        nameEl.innerHTML = highlightText(nameEl.textContent, query);
                    }
                    
                    // Expand parent folders
                    expandParentFolders(wrapper);
                }
            });
            
            // Search files
            treeBody.querySelectorAll('.tree-item-file').forEach(fileEl => {
                const name = fileEl.dataset.name || '';
                const path = fileEl.dataset.path || '';
                
                if (name.includes(query) || path.toLowerCase().includes(query)) {
                    fileEl.classList.add('search-match');
                    matchCount++;
                    
                    // Highlight matching text
                    const nameEl = fileEl.querySelector('.tree-item-name');
                    if (nameEl) {
                        nameEl.innerHTML = highlightText(nameEl.textContent, query);
                    }
                    
                    // Expand parent folders
                    expandParentFolders(fileEl);
                }
            });
            
            // Show results info
            resultsInfo.classList.add('active');
            countSpan.textContent = matchCount;
        }
        
        function highlightText(text, query) {
            const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
            return text.replace(regex, '<span class="search-highlight">$1</span>');
        }
        
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        function expandParentFolders(element) {
            let parent = element.parentElement;
            while (parent) {
                if (parent.classList.contains('tree-children')) {
                    parent.classList.add('expanded');
                    const wrapper = parent.closest('.tree-folder-wrapper');
                    if (wrapper) {
                        const toggle = wrapper.querySelector(':scope > .tree-item .tree-toggle');
                        if (toggle) {
                            toggle.classList.add('expanded');
                        }
                    }
                }
                parent = parent.parentElement;
            }
        }
        
        function clearSearch() {
            const searchInput = document.getElementById('tree-search');
            searchInput.value = '';
            handleSearch('');
            searchInput.focus();
        }
        
        // Expand all folders
        function expandAllFolders() {
            document.querySelectorAll('.tree-children').forEach(el => {
                el.classList.add('expanded');
            });
            document.querySelectorAll('.tree-toggle:not(.empty)').forEach(el => {
                el.classList.add('expanded');
            });
        }
        
        // Collapse all folders
        function collapseAllFolders() {
            document.querySelectorAll('.tree-children').forEach(el => {
                el.classList.remove('expanded');
            });
            document.querySelectorAll('.tree-toggle').forEach(el => {
                el.classList.remove('expanded');
            });
        }
        
        // Handle folder checkbox with proper cascade
        function handleFolderCheck(checkbox) {
            const wrapper = checkbox.closest('.tree-folder-wrapper');
            const children = wrapper.querySelector('.tree-children');
            
            if (children) {
                const childCheckboxes = children.querySelectorAll('.folder-checkbox, .file-checkbox');
                childCheckboxes.forEach(cb => {
                    cb.checked = checkbox.checked;
                    cb.indeterminate = false;
                });
            }
            
            updateParentCheckbox(checkbox);
            updateSummary();
        }
        // Toggle all folders
        function toggleAllFolders() {
            const selectAll = document.getElementById('select-all-folders');
            const checkboxes = document.querySelectorAll('.folder-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.closest('.tree-item').classList.toggle('selected', selectAll.checked);
            });
            
            updateSummary();
        }
        
        // Update summary
        function updateSummary() {
            const checkboxes = document.querySelectorAll('.folder-checkbox:checked');
            const includeDb = document.querySelector('input[name="include_db"]').checked;
            
            let totalFolders = checkboxes.length;
            let totalFiles = 0;
            let totalSize = 0;
            
            checkboxes.forEach(cb => {
                totalFiles += parseInt(cb.dataset.files) || 0;
                totalSize += parseInt(cb.dataset.size) || 0;
                cb.closest('.tree-item').classList.add('selected');
            });
            
            document.querySelectorAll('.folder-checkbox:not(:checked)').forEach(cb => {
                cb.closest('.tree-item').classList.remove('selected');
            });
            
            if (includeDb) {
                totalSize += <?php echo $db_size; ?>;
            }
            
            const summary = document.getElementById('selected-summary');
            if (totalFolders > 0 || includeDb) {
                summary.style.display = 'block';
                document.getElementById('summary-folders').textContent = totalFolders;
                document.getElementById('summary-files').textContent = totalFiles;
                document.getElementById('summary-size').textContent = formatSize(totalSize);
            } else {
                summary.style.display = 'none';
            }
            
            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.folder-checkbox');
            const selectAll = document.getElementById('select-all-folders');
            selectAll.checked = checkboxes.length === allCheckboxes.length && allCheckboxes.length > 0;
            selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
        }
        
        // Format size
        function formatSize(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' B';
        }
        
        // Select all and export
        function selectAllAndExport() {
            document.getElementById('select-all-folders').checked = true;
            toggleAllFolders();
            document.querySelector('input[name="include_db"]').checked = true;
            document.querySelector('input[name="include_uploads"]').checked = true;
            
            Swal.fire({
                title: 'Export toàn bộ dự án?',
                text: 'Điều này sẽ tạo file ZIP chứa tất cả thư mục và database',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Export ngay',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#4CAF50'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('export-form').submit();
                }
            });
        }

        // ...existing code... (thêm vào trong thẻ script, sau các function hiện có)

        // Toggle folder expand/collapse
        function toggleFolder(element, event) {
            if (event.target.type === 'checkbox') return;
            
            const wrapper = element.closest('.tree-folder-wrapper');
            const toggle = element.querySelector('.tree-toggle');
            const children = wrapper.querySelector('.tree-children');
            
            if (!children || toggle.classList.contains('empty')) return;
            
            toggle.classList.toggle('expanded');
            children.classList.toggle('expanded');
        }
        
        // Update parent checkbox based on children
        function updateParentCheckbox(checkbox) {
            const wrapper = checkbox.closest('.tree-folder-wrapper');
            if (!wrapper) return;
            
            const parentWrapper = wrapper.parentElement.closest('.tree-folder-wrapper');
            if (!parentWrapper) return;
            
            const parentCheckbox = parentWrapper.querySelector(':scope > .tree-item .folder-checkbox');
            const siblingCheckboxes = parentWrapper.querySelectorAll(':scope > .tree-children > .tree-folder-wrapper > .tree-item .folder-checkbox, :scope > .tree-children > .tree-item-file .file-checkbox');
            
            let checkedCount = 0;
            siblingCheckboxes.forEach(cb => {
                if (cb.checked) checkedCount++;
            });
            
            if (checkedCount === 0) {
                parentCheckbox.checked = false;
                parentCheckbox.indeterminate = false;
            } else if (checkedCount === siblingCheckboxes.length) {
                parentCheckbox.checked = true;
                parentCheckbox.indeterminate = false;
            } else {
                parentCheckbox.checked = false;
                parentCheckbox.indeterminate = true;
            }
            
            updateParentCheckbox(parentCheckbox);
        }
        
        // Select/deselect all children when parent is clicked
        document.querySelectorAll('.folder-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const wrapper = this.closest('.tree-folder-wrapper');
                const children = wrapper.querySelector('.tree-children');
                
                if (children) {
                    const childCheckboxes = children.querySelectorAll('.folder-checkbox, .file-checkbox');
                    childCheckboxes.forEach(cb => {
                        cb.checked = this.checked;
                        cb.indeterminate = false;
                    });
                }
                
                updateSummary();
            });
        });

        // Update summary to include files
        function updateSummary() {
            const folderCheckboxes = document.querySelectorAll('.folder-checkbox:checked');
            const fileCheckboxes = document.querySelectorAll('.file-checkbox:checked');
            const includeDb = document.querySelector('input[name="include_db"]').checked;
            
            let totalFolders = folderCheckboxes.length;
            let totalFiles = fileCheckboxes.length;
            let totalSize = 0;
            
            folderCheckboxes.forEach(cb => {
                totalFiles += parseInt(cb.dataset.files) || 0;
                totalSize += parseInt(cb.dataset.size) || 0;
                const item = cb.closest('.tree-item');
                if (item) item.classList.add('selected');
            });
            
            fileCheckboxes.forEach(cb => {
                totalSize += parseInt(cb.dataset.size) || 0;
                const item = cb.closest('.tree-item-file');
                if (item) item.classList.add('selected');
            });
            
            document.querySelectorAll('.folder-checkbox:not(:checked)').forEach(cb => {
                const item = cb.closest('.tree-item');
                if (item) item.classList.remove('selected');
            });
            
            document.querySelectorAll('.file-checkbox:not(:checked)').forEach(cb => {
                const item = cb.closest('.tree-item-file');
                if (item) item.classList.remove('selected');
            });
            
            if (includeDb) {
                totalSize += <?php echo $db_size; ?>;
            }
            
            const summary = document.getElementById('selected-summary');
            if (totalFolders > 0 || totalFiles > 0 || includeDb) {
                summary.style.display = 'block';
                document.getElementById('summary-folders').textContent = totalFolders;
                document.getElementById('summary-files').textContent = totalFiles;
                document.getElementById('summary-size').textContent = formatSize(totalSize);
            } else {
                summary.style.display = 'none';
            }
            
            // Update select all checkbox
            const allFolderCheckboxes = document.querySelectorAll('.folder-checkbox');
            const selectAll = document.getElementById('select-all-folders');
            const topLevelChecked = document.querySelectorAll('.tree-body > .tree-folder-wrapper > .tree-item .folder-checkbox:checked').length;
            const topLevelTotal = document.querySelectorAll('.tree-body > .tree-folder-wrapper > .tree-item .folder-checkbox').length;
            
            selectAll.checked = topLevelChecked === topLevelTotal && topLevelTotal > 0;
            selectAll.indeterminate = topLevelChecked > 0 && topLevelChecked < topLevelTotal;
        }

        // Toggle all folders - updated
        function toggleAllFolders() {
            const selectAll = document.getElementById('select-all-folders');
            const checkboxes = document.querySelectorAll('.folder-checkbox, .file-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                cb.indeterminate = false;
            });
            
            updateSummary();
        }
        
        // Select code folders only
        function selectCodeFolders() {
            const codeFolders = ['admin', 'components', 'dangnhap', 'db', 'giaovien', 'gioithieu', 'hdtn', 'huongdan', 'saudn', 'trangchu'];
            
            document.querySelectorAll('.folder-checkbox').forEach(cb => {
                const folderName = cb.value;
                cb.checked = codeFolders.includes(folderName) && folderName !== 'uploads';
            });
            
            document.querySelector('input[name="include_db"]').checked = true;
            document.querySelector('input[name="include_uploads"]').checked = false;
            
            updateSummary();
            
            Swal.fire({
                title: 'Export mã nguồn?',
                text: 'Điều này sẽ export các thư mục code và database (không bao gồm uploads)',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Export ngay',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#4CAF50'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('export-form').submit();
                }
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSummary();
            
            // Add click handler for option items
            document.querySelectorAll('.option-item input').forEach(input => {
                input.addEventListener('change', function() {
                    this.closest('.option-item').classList.toggle('checked', this.checked);
                    updateSummary();
                });
                
                // Set initial state
                if (input.checked) {
                    input.closest('.option-item').classList.add('checked');
                }
            });
        });
    </script>
</body>
</html>