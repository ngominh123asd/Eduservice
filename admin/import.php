<?php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db/db_config.php';

$page_title = "Import Dữ liệu";
$current_page = "import";

$success_message = '';
$error_message = '';
$import_results = [];

$project_root = dirname(__DIR__);
$upload_dir = $project_root . '/uploads/imports';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// AJAX: Lấy cấu trúc thư mục hiện tại
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_structure') {
    header('Content-Type: application/json');
    $path = $_GET['path'] ?? '';
    $full_path = $project_root . '/' . $path;
    
    $real_path = realpath($full_path);
    $real_root = realpath($project_root);
    if ($real_path === false || strpos($real_path, $real_root) !== 0) {
        echo json_encode(['error' => 'Invalid path']);
        exit;
    }
    
    $items = getDirectoryContents($full_path, $path);
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

// AJAX: Xem cấu trúc file ZIP
if (isset($_GET['ajax']) && $_GET['ajax'] === 'preview_zip') {
    header('Content-Type: application/json');
    
    $upload_id = $_POST['upload_id'] ?? '';
    $temp_file = $upload_dir . '/temp_' . $upload_id . '.zip';
    
    if (!file_exists($temp_file)) {
        echo json_encode(['success' => false, 'error' => 'File không tồn tại']);
        exit;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($temp_file) !== TRUE) {
        echo json_encode(['success' => false, 'error' => 'Không thể mở file ZIP']);
        exit;
    }
    
    $structure = [];
    $manifest = null;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = $stat['name'];
        $size = $stat['size'];
        $isDir = substr($name, -1) === '/';
        
        if ($name === 'manifest.json') {
            $manifest = json_decode($zip->getFromIndex($i), true);
            continue;
        }
        
        if ($name && !$isDir) {
            $structure[] = [
                'path' => $name,
                'name' => basename($name),
                'type' => 'file',
                'size' => $size,
                'ext' => pathinfo($name, PATHINFO_EXTENSION)
            ];
        }
    }
    
    $zip->close();
    
    $tree = buildTreeFromPaths($structure);
    
    echo json_encode([
        'success' => true,
        'total_files' => count($structure),
        'total_size' => array_sum(array_column($structure, 'size')),
        'manifest' => $manifest,
        'structure' => $tree
    ]);
    exit;
}

// AJAX: Upload ZIP file
if (isset($_GET['ajax']) && $_GET['ajax'] === 'upload_zip') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Không có file được tải lên']);
        exit;
    }
    
    $file = $_FILES['zip_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'zip') {
        echo json_encode(['success' => false, 'error' => 'Chỉ chấp nhận file ZIP']);
        exit;
    }
    
    if ($file['size'] > 100 * 1024 * 1024) { // 100MB
        echo json_encode(['success' => false, 'error' => 'File quá lớn (tối đa 100MB)']);
        exit;
    }
    
    $upload_id = uniqid();
    $temp_file = $upload_dir . '/temp_' . $upload_id . '.zip';
    
    if (move_uploaded_file($file['tmp_name'], $temp_file)) {
        echo json_encode([
            'success' => true,
            'upload_id' => $upload_id,
            'filename' => $file['name'],
            'size' => $file['size']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Không thể lưu file']);
    }
    exit;
}

// AJAX: Cleanup temp files
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cleanup') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (isset($data['upload_id'])) {
        $temp_file = $upload_dir . '/temp_' . $data['upload_id'] . '.zip';
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Xử lý import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['ajax'])) {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'import_zip') {
            $upload_id = $_POST['upload_id'] ?? '';
            $temp_file = $upload_dir . '/temp_' . $upload_id . '.zip';
            
            if (!file_exists($temp_file)) {
                throw new Exception("File không tồn tại");
            }
            
            $selected_items = json_decode($_POST['selected_items'] ?? '[]', true);
            $import_options = $_POST['import_options'] ?? [];
            
            $temp_dir = $upload_dir . '/extract_' . time();
            mkdir($temp_dir, 0755, true);
            
            $zip = new ZipArchive();
            if ($zip->open($temp_file) !== TRUE) {
                throw new Exception("Không thể mở file ZIP");
            }
            
            $imported_count = 0;
            
            // Extract selected items
            if (!empty($selected_items)) {
                foreach ($selected_items as $item) {
                    $zip->extractTo($temp_dir, $item);
                }
            } else {
                $zip->extractTo($temp_dir);
            }
            
            $zip->close();
            
            // Import database
            if (in_array('database', $import_options)) {
                $manifest_file = $temp_dir . '/manifest.json';
                if (file_exists($manifest_file)) {
                    $manifest = json_decode(file_get_contents($manifest_file), true);
                    if (isset($manifest['database'])) {
                        $db_file = $temp_dir . '/' . $manifest['database'];
                        if (file_exists($db_file)) {
                            $target_db = $project_root . '/db/edservices.db';
                            copy($target_db, $target_db . '.backup_' . date('YmdHis'));
                            copy($db_file, $target_db);
                            $import_results[] = ['type' => 'success', 'message' => 'Đã import database'];
                        }
                    }
                }
            }
            
            // Import files
            if (in_array('files', $import_options)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($files as $file_item) {
                    $relative_path = substr($file_item->getPathname(), strlen($temp_dir) + 1);
                    
                    if ($relative_path === 'manifest.json') continue;
                    
                    $target = $project_root . '/' . $relative_path;
                    
                    if ($file_item->isDir()) {
                        if (!is_dir($target)) {
                            mkdir($target, 0755, true);
                        }
                    } else {
                        $target_dir = dirname($target);
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0755, true);
                        }
                        
                        if (in_array('overwrite', $import_options) || !file_exists($target)) {
                            copy($file_item->getPathname(), $target);
                            $imported_count++;
                        }
                    }
                }
                $import_results[] = ['type' => 'success', 'message' => "Đã import $imported_count files"];
            }
            
            deleteDirectory($temp_dir);
            @unlink($temp_file);
            
            $success_message = "Import hoàn tất!";
            
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'import_project', ?, ?, datetime('now'))");
            $logStmt->execute([$_SESSION['user_id'], "Imported $imported_count items", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            
        } elseif ($action === 'import_database') {
            if (isset($_FILES['db_file']) && $_FILES['db_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['db_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if ($ext !== 'db') {
                    throw new Exception("Chỉ chấp nhận file .db (SQLite)");
                }
                
                $target_db = $project_root . '/db/edservices.db';
                copy($target_db, $target_db . '.backup_' . date('YmdHis'));
                
                if (move_uploaded_file($file['tmp_name'], $target_db)) {
                    $success_message = "Đã import database thành công";
                    $import_results[] = ['type' => 'success', 'message' => 'Database đã được thay thế'];
                    
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'import_database', ?, ?, datetime('now'))");
                    $logStmt->execute([$_SESSION['user_id'], "Imported database", $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                }
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Helper functions
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

function getDirectoryContents($dir, $prefix = '') {
    $items = [];
    $skipPatterns = ['.', '..', '.git', 'node_modules', '.idea', 'vendor', '.DS_Store', 'Thumbs.db'];
    
    $contents = @scandir($dir);
    if (!$contents) return $items;
    
    $dirs = [];
    $files = [];
    
    foreach ($contents as $item) {
        if (in_array($item, $skipPatterns)) continue;
        
        $path = $dir . '/' . $item;
        $relativePath = $prefix ? $prefix . '/' . $item : $item;
        
        if (is_dir($path)) {
            $dirs[] = [
                'name' => $item,
                'path' => $relativePath,
                'type' => 'dir',
                'size' => 0,
                'hasChildren' => hasChildren($path)
            ];
        } else {
            $files[] = [
                'name' => $item,
                'path' => $relativePath,
                'type' => 'file',
                'size' => filesize($path),
                'ext' => pathinfo($item, PATHINFO_EXTENSION)
            ];
        }
    }
    
    usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    
    return array_merge($dirs, $files);
}

function hasChildren($dir) {
    $contents = @scandir($dir);
    if (!$contents) return false;
    $skipPatterns = ['.', '..', '.git', 'node_modules', '.idea', 'vendor'];
    foreach ($contents as $item) {
        if (!in_array($item, $skipPatterns)) return true;
    }
    return false;
}

function buildTreeFromPaths($items) {
    $tree = [];
    $paths = [];
    
    foreach ($items as $item) {
        $parts = explode('/', $item['path']);
        $current = &$tree;
        $currentPath = '';
        
        foreach ($parts as $i => $part) {
            $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
            $isLast = $i === count($parts) - 1;
            
            $key = null;
            foreach ($current as $k => $existing) {
                if ($existing['name'] === $part) {
                    $key = $k;
                    break;
                }
            }
            
            if ($key === null) {
                $newItem = [
                    'name' => $part,
                    'path' => $currentPath,
                    'type' => $isLast ? $item['type'] : 'dir',
                    'size' => $isLast ? $item['size'] : 0,
                    'ext' => $isLast ? ($item['ext'] ?? '') : '',
                    'children' => []
                ];
                $current[] = $newItem;
                $key = count($current) - 1;
            }
            
            if (!$isLast) {
                $current = &$current[$key]['children'];
            }
        }
    }
    
    return $tree;
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
        'pdf' => 'fa-file-pdf', 'zip' => 'fa-file-archive', 'txt' => 'fa-file-alt', 'md' => 'fa-file-alt'
    ];
    return 'fa-' . ($icons[strtolower($ext)] ?? 'file');
}

function getFileIconClass($ext) {
    $classes = [
        'php' => 'text-purple', 'js' => 'text-yellow', 'css' => 'text-blue', 'html' => 'text-orange',
        'json' => 'text-gray', 'db' => 'text-blue', 'png' => 'text-green', 'jpg' => 'text-green',
        'jpeg' => 'text-green', 'gif' => 'text-green', 'svg' => 'text-green', 'pdf' => 'text-red',
        'zip' => 'text-yellow'
    ];
    return $classes[strtolower($ext)] ?? 'text-gray';
}

function renderTreeNode($item, $level) {
    $isDir = $item['type'] === 'dir';
    $icon = $isDir ? 'fa-folder' : getFileIcon($item['ext'] ?? '');
    $iconClass = $isDir ? '' : getFileIconClass($item['ext'] ?? '');
    ?>
    <div class="tree-node" data-path="<?php echo htmlspecialchars($item['path']); ?>" data-type="<?php echo $item['type']; ?>" data-size="<?php echo $item['size'] ?? 0; ?>">
        <div class="tree-node-content" onclick="handleCurrentNodeClick(event, '<?php echo htmlspecialchars($item['path']); ?>')">
            <span class="tree-toggle <?php echo ($isDir && $item['hasChildren']) ? '' : 'empty'; ?>" onclick="event.stopPropagation(); <?php echo ($isDir && $item['hasChildren']) ? "toggleCurrentFolder('" . htmlspecialchars($item['path']) . "', this)" : ''; ?>">
                <i class="fas fa-chevron-right"></i>
            </span>
            <span class="tree-icon <?php echo $iconClass; ?>">
                <i class="fas <?php echo $icon; ?>"></i>
            </span>
            <span class="tree-name"><?php echo htmlspecialchars($item['name']); ?><?php echo $isDir ? '/' : ''; ?></span>
            <?php if (isset($item['size']) && $item['size'] > 0): ?>
                <span class="tree-size"><?php echo formatSize($item['size']); ?></span>
            <?php endif; ?>
        </div>
        <?php if ($isDir): ?>
            <div class="tree-children"></div>
        <?php endif; ?>
    </div>
    <?php
}
// Get current project structure
$root_items = getDirectoryContents($project_root);
$db_path = $project_root . '/db/edservices.db';
$db_size = file_exists($db_path) ? filesize($db_path) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
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
        .import-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
        }
        
        .import-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .import-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .import-card-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .import-card-header h3 i { color: #4CAF50; }
        .import-card-body { padding: 24px; }
        
        /* Upload Zone */
        .upload-zone {
            border: 3px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .upload-zone:hover {
            border-color: #4CAF50;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }
        
        .upload-zone.dragover {
            border-color: #4CAF50;
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        }
        
        .upload-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #4CAF50;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
        }
        
        .upload-text h4 {
            margin: 0 0 8px;
            font-size: 18px;
            color: #1e293b;
        }
        
        .upload-text p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .upload-text .btn-choose {
            margin-top: 16px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .upload-text .btn-choose:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        /* File Preview */
        .file-preview {
            margin-top: 24px;
            padding: 20px;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-radius: 12px;
            border: 2px solid #a5d6a7;
            display: none;
        }
        
        .file-preview.active { display: block; }
        
        .file-preview-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .file-preview-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #2e7d32;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .file-preview-info { flex: 1; }
        .file-preview-info h5 {
            margin: 0 0 4px;
            font-size: 16px;
            color: #1e293b;
        }
        
        .file-preview-info p {
            margin: 0;
            font-size: 13px;
            color: #4a5568;
        }
        
        .file-preview-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-preview, .btn-remove {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-preview {
            background: white;
            color: #2e7d32;
        }
        
        .btn-remove {
            background: #dc2626;
            color: white;
        }
        
        /* File Tree */
        .file-tree {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            max-height: 400px;
            overflow-y: auto;
            background: #fafafa;
            margin-top: 16px;
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
        
        .tree-content {
            padding: 8px 0;
        }
        
        .tree-node {
            user-select: none;
        }
        
        .tree-node-content {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.15s;
            border-left: 3px solid transparent;
        }
        
        .tree-node-content:hover {
            background: #e8f5e9;
            border-left-color: #4CAF50;
        }
        
        .tree-node-content.selected {
            background: #c8e6c9;
            border-left-color: #2e7d32;
        }
        
        .tree-toggle {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 10px;
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        
        .tree-toggle.expanded { transform: rotate(90deg); }
        .tree-toggle.empty { visibility: hidden; }
        
        .tree-checkbox {
            width: 16px;
            height: 16px;
            accent-color: #4CAF50;
            flex-shrink: 0;
        }
        
        .tree-icon {
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .tree-icon .fa-folder { color: #f59e0b; }
        .tree-icon .fa-folder-open { color: #d97706; }
        .tree-icon.text-purple { color: #7c3aed; }
        .tree-icon.text-yellow { color: #eab308; }
        .tree-icon.text-blue { color: #3b82f6; }
        .tree-icon.text-orange { color: #f97316; }
        .tree-icon.text-green { color: #22c55e; }
        .tree-icon.text-red { color: #ef4444; }
        .tree-icon.text-gray { color: #64748b; }
        
        .tree-name {
            flex: 1;
            font-size: 13px;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .tree-size {
            font-size: 11px;
            color: #94a3b8;
            padding: 2px 8px;
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .tree-children {
            display: none;
            padding-left: 20px;
        }
        
        .tree-children.expanded { display: block; }
        
        /* Import Options */
        .import-options-card {
            margin-top: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .import-options-card h4 {
            margin: 0 0 16px;
            font-size: 14px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
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
            margin-bottom: 12px;
        }
        
        .option-item:last-child { margin-bottom: 0; }
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
        
        /* Quick Import Panel - Green Theme */
        .quick-import {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 24px;
        }
        
        .quick-import-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            border-radius: 12px 12px 0 0;
            color: white;
        }
        
        .quick-import-header h4 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-import-body { padding: 20px; }
        
        .quick-import-item {
            padding: 16px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 10px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #bbf7d0;
        }
        
        .quick-import-item:hover {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border-color: #4CAF50;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
        }
        
        .quick-import-item h5 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-import-item p {
            margin: 0;
            font-size: 12px;
            color: #4a5568;
        }
        
        /* Current Structure */
        .current-structure-card {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border-radius: 12px;
            border: 1px solid #a5d6a7;
        }
        
        .current-structure-card h5 {
            margin: 0 0 16px;
            font-size: 14px;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .structure-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .structure-stat {
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .structure-stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #2e7d32;
        }
        
        .structure-stat-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

                .tree-loading {
            padding: 12px 20px;
            color: #64748b;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tree-loading i.fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Manifest info badge */
        .manifest-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border: 1px solid #a5d6a7;
            border-radius: 6px;
            font-size: 12px;
            color: #2e7d32;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .manifest-badge i {
            color: #4CAF50;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 16px;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 14px;
        }
        
        /* Buttons */
        .btn-import {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
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
        
        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.35);
        }
        
        .btn-import:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 12px;
            display: none;
        }
        
        .progress-bar.active { display: block; }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #2E7D32);
            width: 0%;
            transition: width 0.3s;
        }
        
        @media (max-width: 1200px) {
            .import-layout { grid-template-columns: 1fr; }
            .quick-import { position: static; }
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
                    <h1><i class="fas fa-file-import"></i> Import Dữ liệu</h1>
                    <p>Nhập dự án hoặc các thành phần từ file ZIP</p>
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
            
            <?php if (!empty($import_results)): ?>
                <?php foreach ($import_results as $result): ?>
                    <div class="alert alert-<?php echo $result['type']; ?>">
                        <i class="fas fa-<?php echo $result['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <span><?php echo htmlspecialchars($result['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="import-layout">
                <div class="import-card">
                    <div class="import-card-header">
                        <h3><i class="fas fa-cloud-upload-alt"></i> Tải lên file Import</h3>
                    </div>
                    <div class="import-card-body">
                        <form method="POST" id="import-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="import_zip">
                            <input type="hidden" name="upload_id" id="upload-id">
                            <input type="hidden" name="selected_items" id="selected-items">
                            
                            <!-- Upload Zone -->
                            <div class="upload-zone" id="upload-zone" onclick="document.getElementById('file-input').click()">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="upload-text">
                                    <h4>Kéo thả file ZIP vào đây</h4>
                                    <p>hoặc click để chọn file (tối đa 100MB)</p>
                                    <button type="button" class="btn-choose">
                                        <i class="fas fa-folder-open"></i> Chọn file
                                    </button>
                                </div>
                                <input type="file" id="file-input" accept=".zip" style="display: none;" onchange="handleFileSelect(event)">
                            </div>
                            
                            <div class="progress-bar" id="progress-bar">
                                <div class="progress-fill" id="progress-fill"></div>
                            </div>
                            
                            <!-- File Preview -->
                            <div class="file-preview" id="file-preview">
                                <div class="file-preview-header">
                                    <div class="file-preview-icon">
                                        <i class="fas fa-file-archive"></i>
                                    </div>
                                    <div class="file-preview-info">
                                        <h5 id="preview-filename">file.zip</h5>
                                        <p id="preview-filesize">0 MB</p>
                                    </div>
                                    <div class="file-preview-actions">
                                        <button type="button" class="btn-preview" onclick="previewZipStructure()">
                                            <i class="fas fa-eye"></i> Xem cấu trúc
                                        </button>
                                        <button type="button" class="btn-remove" onclick="removeFile()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- ZIP Structure Tree -->
                                <div class="file-tree" id="zip-tree" style="display: none;">
                                    <div class="tree-header">
                                        <label>
                                            <input type="checkbox" id="select-all-zip" onchange="toggleSelectAllZip()">
                                            <i class="fas fa-file-archive"></i>
                                            <span id="zip-filename">archive.zip</span>
                                        </label>
                                        <span style="font-size: 12px; opacity: 0.9;" id="zip-info">0 files</span>
                                    </div>
                                    <div class="tree-content" id="zip-tree-content">
                                        <!-- Tree will be loaded dynamically -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Import Options -->
                            <div class="import-options-card" id="import-options" style="display: none;">
                                <h4><i class="fas fa-cog"></i> Tùy chọn Import</h4>
                                <label class="option-item checked">
                                    <input type="checkbox" name="import_options[]" value="files" checked onchange="this.closest('.option-item').classList.toggle('checked', this.checked)">
                                    <div class="option-icon"><i class="fas fa-folder"></i></div>
                                    <div class="option-info">
                                        <strong>Import Files & Folders</strong>
                                        <span>Các thư mục và file trong ZIP</span>
                                    </div>
                                </label>
                                <label class="option-item checked">
                                    <input type="checkbox" name="import_options[]" value="database" checked onchange="this.closest('.option-item').classList.toggle('checked', this.checked)">
                                    <div class="option-icon"><i class="fas fa-database"></i></div>
                                    <div class="option-info">
                                        <strong>Import Database</strong>
                                        <span>Thay thế database hiện tại (có backup tự động)</span>
                                    </div>
                                </label>
                                <label class="option-item">
                                    <input type="checkbox" name="import_options[]" value="overwrite" onchange="this.closest('.option-item').classList.toggle('checked', this.checked)">
                                    <div class="option-icon"><i class="fas fa-sync-alt"></i></div>
                                    <div class="option-info">
                                        <strong>Ghi đè files trùng</strong>
                                        <span>Thay thế các file đã tồn tại</span>
                                    </div>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-import" id="btn-import" disabled>
                                <i class="fas fa-file-import"></i>
                                <span>Bắt đầu Import</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Import Panel -->
                <div class="quick-import">
                    <div class="quick-import-header">
                        <h4><i class="fas fa-bolt"></i> Import nhanh</h4>
                    </div>
                    <div class="quick-import-body">
                        <form method="POST" enctype="multipart/form-data" class="quick-import-item" onsubmit="return validateDbImport(this)">
                            <input type="hidden" name="action" value="import_database">
                            <input type="file" name="db_file" accept=".db" style="display: none;" id="db-file-input" onchange="this.closest('form').submit()">
                            <div onclick="document.getElementById('db-file-input').click()">
                                <h5>
                                    <i class="fas fa-database"></i>
                                    Chỉ import Database
                                </h5>
                                <p>Chọn file .db để thay thế database hiện tại</p>
                            </div>
                        </form>
                        
                        <div class="quick-import-item" onclick="showImportGuide()">
                            <h5>
                                <i class="fas fa-question-circle"></i>
                                Hướng dẫn Import
                            </h5>
                            <p>Xem các bước và lưu ý khi import dữ liệu</p>
                        </div>
                        
                        <div class="current-structure-card">
                            <h5><i class="fas fa-folder-tree"></i> Cấu trúc hiện tại</h5>
                            <div class="structure-stats">
                                <div class="structure-stat">
                                    <div class="structure-stat-number"><?php echo count($root_items); ?></div>
                                    <div class="structure-stat-label">Thư mục gốc</div>
                                </div>
                                <div class="structure-stat">
                                    <div class="structure-stat-number"><?php echo formatSize($db_size); ?></div>
                                    <div class="structure-stat-label">Database</div>
                                </div>
                            </div>
                            <button type="button" class="btn-import" onclick="viewCurrentStructure()" style="margin-top: 16px; padding: 12px;">
                                <i class="fas fa-eye"></i>
                                <span>Xem chi tiết</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let uploadedFile = null;
        let uploadId = null;
        let selectedZipItems = new Set();
        
        // Drag & Drop
        const uploadZone = document.getElementById('upload-zone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'), false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'), false);
        });
        
        uploadZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });
        
        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) handleFile(file);
        }
        
        async function handleFile(file) {
            if (!file.name.toLowerCase().endsWith('.zip')) {
                Swal.fire('Lỗi', 'Chỉ chấp nhận file ZIP', 'error');
                return;
            }
            
            if (file.size > 100 * 1024 * 1024) {
                Swal.fire('Lỗi', 'File quá lớn (tối đa 100MB)', 'error');
                return;
            }
            
            uploadedFile = file;
            
            // Show preview
            document.getElementById('preview-filename').textContent = file.name;
            document.getElementById('preview-filesize').textContent = formatSize(file.size);
            document.getElementById('file-preview').classList.add('active');
            
            // Upload file
            const formData = new FormData();
            formData.append('zip_file', file);
            
            const progressBar = document.getElementById('progress-bar');
            const progressFill = document.getElementById('progress-fill');
            progressBar.classList.add('active');
            
            try {
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 100;
                        progressFill.style.width = percent + '%';
                    }
                });
                
                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            uploadId = response.upload_id;
                            document.getElementById('upload-id').value = uploadId;
                            document.getElementById('btn-import').disabled = false;
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Tải lên thành công',
                                text: 'Click "Xem cấu trúc" để xem chi tiết file ZIP',
                                timer: 2000
                            });
                        }
                    }
                    progressBar.classList.remove('active');
                });
                
                xhr.open('POST', '?ajax=upload_zip');
                xhr.send(formData);
                
            } catch (e) {
                progressBar.classList.remove('active');
                Swal.fire('Lỗi', 'Không thể tải file lên', 'error');
            }
        }
        
        async function previewZipStructure() {
            if (!uploadId) {
                Swal.fire('Lỗi', 'Chưa có file được tải lên', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Đang tải cấu trúc...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            try {
                const formData = new FormData();
                formData.append('upload_id', uploadId);
                
                const response = await fetch('?ajax=preview_zip', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.close();
                    
                    document.getElementById('zip-filename').textContent = uploadedFile.name;
                    document.getElementById('zip-info').textContent = `${data.total_files} files • ${formatSize(data.total_size)}`;
                    
                    const treeContent = document.getElementById('zip-tree-content');
                    treeContent.innerHTML = data.structure.map(item => createZipTreeNodeHTML(item)).join('');
                    
                    document.getElementById('zip-tree').style.display = 'block';
                    document.getElementById('import-options').style.display = 'block';
                    
                    if (data.manifest) {
                        Swal.fire({
                            icon: 'info',
                            title: 'File Export được phát hiện',
                            html: `
                                <div style="text-align: left;">
                                    <p><strong>Tên:</strong> ${data.manifest.name || 'N/A'}</p>
                                    <p><strong>Ngày tạo:</strong> ${data.manifest.date || 'N/A'}</p>
                                    <p><strong>Số files:</strong> ${data.manifest.files ? data.manifest.files.length : 0}</p>
                                </div>
                            `,
                            confirmButtonColor: '#4CAF50'
                        });
                    }
                } else {
                    Swal.fire('Lỗi', data.error || 'Không thể xem cấu trúc file', 'error');
                }
            } catch (e) {
                Swal.fire('Lỗi', 'Không thể kết nối server', 'error');
            }
        }
        
        function createZipTreeNodeHTML(item, level = 0) {
            const isDir = item.type === 'dir';
            const hasChildren = item.children && item.children.length > 0;
            const icon = isDir ? 'fa-folder' : getFileIcon(item.ext || '');
            const iconClass = isDir ? '' : getFileIconClass(item.ext || '');
            const paddingLeft = level * 20;
            
            let html = `
                <div class="tree-node" data-path="${item.path}" data-type="${item.type}" data-size="${item.size || 0}" style="padding-left: ${paddingLeft}px">
                    <div class="tree-node-content" onclick="handleZipNodeClick(event, '${item.path}')">
                        <span class="tree-toggle ${hasChildren ? '' : 'empty'}" onclick="event.stopPropagation(); ${hasChildren ? `toggleZipFolder(this)` : ''}">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        <input type="checkbox" class="tree-checkbox" onchange="toggleZipItem('${item.path}', this.checked)" onclick="event.stopPropagation()">
                        <span class="tree-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </span>
                        <span class="tree-name">${item.name}${isDir ? '/' : ''}</span>
                        ${item.size ? `<span class="tree-size">${formatSize(item.size)}</span>` : ''}
                    </div>
                    ${hasChildren ? `<div class="tree-children">${item.children.map(child => createZipTreeNodeHTML(child, level + 1)).join('')}</div>` : ''}
                </div>
            `;
            
            return html;
        }
        
        function toggleZipFolder(element) {
            const nodeContent = element.closest('.tree-node-content');
            const node = nodeContent.closest('.tree-node');
            const childrenContainer = node.querySelector('.tree-children');
            const toggleIcon = nodeContent.querySelector('.tree-toggle');
            const folderIcon = nodeContent.querySelector('.tree-icon i');
            
            if (childrenContainer.classList.contains('expanded')) {
                childrenContainer.classList.remove('expanded');
                toggleIcon.classList.remove('expanded');
                folderIcon.classList.replace('fa-folder-open', 'fa-folder');
            } else {
                childrenContainer.classList.add('expanded');
                toggleIcon.classList.add('expanded');
                folderIcon.classList.replace('fa-folder', 'fa-folder-open');
            }
        }
        
        function handleZipNodeClick(event, path) {
            const nodeContent = event.currentTarget;
            const checkbox = nodeContent.querySelector('.tree-checkbox');
            const toggle = nodeContent.querySelector('.tree-toggle');
            
            if (!toggle.classList.contains('empty')) {
                toggleZipFolder(toggle);
            }
        }
        
        function toggleZipItem(path, checked) {
            if (checked) {
                selectedZipItems.add(path);
            } else {
                selectedZipItems.delete(path);
            }
            
            updateSelectedItems();
        }
        
        function toggleSelectAllZip() {
            const selectAll = document.getElementById('select-all-zip');
            const checkboxes = document.querySelectorAll('#zip-tree-content .tree-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                const node = cb.closest('.tree-node');
                const path = node.dataset.path;
                
                if (selectAll.checked) {
                    selectedZipItems.add(path);
                } else {
                    selectedZipItems.delete(path);
                }
            });
            
            updateSelectedItems();
        }
        
        function updateSelectedItems() {
            document.getElementById('selected-items').value = JSON.stringify([...selectedZipItems]);
        }
        
        function removeFile() {
            uploadedFile = null;
            uploadId = null;
            selectedZipItems.clear();
            
            document.getElementById('file-input').value = '';
            document.getElementById('upload-id').value = '';
            document.getElementById('file-preview').classList.remove('active');
            document.getElementById('zip-tree').style.display = 'none';
            document.getElementById('import-options').style.display = 'none';
            document.getElementById('btn-import').disabled = true;
        }
        
        function formatSize(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' B';
        }
        
        function getFileIcon(ext) {
            const icons = {
                'php': 'fa-php', 'js': 'fa-js', 'css': 'fa-css3-alt', 'html': 'fa-html5',
                'json': 'fa-code', 'db': 'fa-database', 'png': 'fa-image', 'jpg': 'fa-image',
                'pdf': 'fa-file-pdf', 'zip': 'fa-file-archive'
            };
            return icons[ext.toLowerCase()] || 'fa-file';
        }
        
        function getFileIconClass(ext) {
            const classes = {
                'php': 'text-purple', 'js': 'text-yellow', 'css': 'text-blue', 'html': 'text-orange',
                'json': 'text-gray', 'db': 'text-blue', 'png': 'text-green', 'jpg': 'text-green',
                'pdf': 'text-red', 'zip': 'text-yellow'
            };
            return classes[ext.toLowerCase()] || 'text-gray';
        }
        
        function validateDbImport(form) {
            return confirm('Bạn có chắc muốn thay thế database hiện tại?\n\nDatabase cũ sẽ được backup tự động.');
        }
        
        function showImportGuide() {
            Swal.fire({
                title: 'Hướng dẫn Import',
                html: `
                    <div style="text-align: left;">
                        <h4 style="color: #4CAF50; margin-top: 0;">📦 Chuẩn bị file ZIP</h4>
                        <ul>
                            <li>File ZIP phải chứa cấu trúc thư mục hợp lệ</li>
                            <li>Nếu muốn import database, file ZIP phải có <code>manifest.json</code></li>
                            <li>Kích thước tối đa: 100MB</li>
                        </ul>
                        
                        <h4 style="color: #4CAF50;">⚙️ Các bước Import</h4>
                        <ol>
                            <li>Kéo thả hoặc chọn file ZIP</li>
                            <li>Click "Xem cấu trúc" để preview nội dung</li>
                            <li>Chọn các mục muốn import (hoặc chọn tất cả)</li>
                            <li>Cấu hình tùy chọn import</li>
                            <li>Click "Bắt đầu Import"</li>
                        </ol>
                        
                        <h4 style="color: #4CAF50;">⚠️ Lưu ý</h4>
                        <ul>
                            <li>Database sẽ được backup tự động trước khi import</li>
                            <li>Các file trùng sẽ không bị ghi đè trừ khi chọn tùy chọn</li>
                            <li>Quá trình import có thể mất vài phút tùy dung lượng</li>
                        </ul>
                    </div>
                `,
                width: 600,
                confirmButtonColor: '#4CAF50'
            });
        }
        
        async function viewCurrentStructure() {
            Swal.fire({
                title: 'Cấu trúc dự án hiện tại',
                html: '<div id="swal-tree-content" style="text-align: left; max-height: 400px; overflow-y: auto;"></div>',
                width: 600,
                confirmButtonColor: '#4CAF50',
                didOpen: async () => {
                    const response = await fetch('?ajax=get_structure&path=');
                    const data = await response.json();
                    if (data.success) {
                        document.getElementById('swal-tree-content').innerHTML = 
                            data.items.map(item => `
                                <div style="padding: 8px; border-bottom: 1px solid #eee;">
                                    <i class="fas ${item.type === 'dir' ? 'fa-folder' : 'fa-file'}" style="color: ${item.type === 'dir' ? '#f59e0b' : '#64748b'};"></i>
                                    ${item.name}${item.type === 'dir' ? '/' : ''}
                                    ${item.size ? `<span style="color: #94a3b8; float: right;">${formatSize(item.size)}</span>` : ''}
                                </div>
                            `).join('');
                    }
                }
            });
        }
        
        // Form submit confirmation
        document.getElementById('import-form').addEventListener('submit', (e) => {
            e.preventDefault();
            
            Swal.fire({
                title: 'Xác nhận Import?',
                text: 'Dữ liệu hiện tại có thể bị thay đổi. Bạn có chắc chắn muốn tiếp tục?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Tiếp tục Import',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#4CAF50',
                cancelButtonColor: '#64748b'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Đang Import...',
                        html: `
                            <div style="text-align: center;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #4CAF50; margin: 20px 0;"></i>
                                <p>Vui lòng đợi, quá trình có thể mất vài phút...</p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            e.target.submit();
                        }
                    });
                }
            });
        });

        async function toggleCurrentFolder(path, element) {
            const nodeContent = element.closest('.tree-node-content');
            const node = nodeContent.closest('.tree-node');
            const childrenContainer = node.querySelector('.tree-children');
            const toggleIcon = nodeContent.querySelector('.tree-toggle');
            const folderIcon = nodeContent.querySelector('.tree-icon i');
            
            if (childrenContainer.classList.contains('expanded')) {
                childrenContainer.classList.remove('expanded');
                toggleIcon.classList.remove('expanded');
                folderIcon.classList.replace('fa-folder-open', 'fa-folder');
            } else {
                if (!childrenContainer.hasChildNodes()) {
                    childrenContainer.innerHTML = '<div class="tree-loading"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';
                    childrenContainer.classList.add('expanded');
                    
                    try {
                        const response = await fetch(`?ajax=get_structure&path=${encodeURIComponent(path)}`);
                        const data = await response.json();
                        
                        if (data.success && data.items.length > 0) {
                            childrenContainer.innerHTML = data.items.map(item => createCurrentTreeNodeHTML(item)).join('');
                        } else {
                            childrenContainer.innerHTML = '<div class="tree-loading" style="color: #94a3b8;"><i class="fas fa-folder-open"></i> Thư mục trống</div>';
                        }
                    } catch (e) {
                        childrenContainer.innerHTML = '<div class="tree-loading" style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> Lỗi tải dữ liệu</div>';
                    }
                } else {
                    childrenContainer.classList.add('expanded');
                }
                
                toggleIcon.classList.add('expanded');
                folderIcon.classList.replace('fa-folder', 'fa-folder-open');
            }
        }
        
        function createCurrentTreeNodeHTML(item) {
            const isDir = item.type === 'dir';
            const icon = isDir ? 'fa-folder' : getFileIcon(item.ext || '');
            const iconClass = isDir ? '' : getFileIconClass(item.ext || '');
            const hasChildren = item.hasChildren || false;
            
            return `
                <div class="tree-node" data-path="${item.path}" data-type="${item.type}" data-size="${item.size || 0}">
                    <div class="tree-node-content" onclick="handleCurrentNodeClick(event, '${item.path}')">
                        <span class="tree-toggle ${isDir && hasChildren ? '' : 'empty'}" onclick="event.stopPropagation(); ${isDir && hasChildren ? `toggleCurrentFolder('${item.path}', this)` : ''}">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        <span class="tree-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </span>
                        <span class="tree-name">${item.name}${isDir ? '/' : ''}</span>
                        ${item.size ? `<span class="tree-size">${formatSize(item.size)}</span>` : ''}
                    </div>
                    ${isDir ? '<div class="tree-children"></div>' : ''}
                </div>
            `;
        }
        
        function handleCurrentNodeClick(event, path) {
            const nodeContent = event.currentTarget;
            const toggle = nodeContent.querySelector('.tree-toggle');
            
            if (!toggle.classList.contains('empty')) {
                toggleCurrentFolder(path, toggle);
            }
        }
        
        // Clean up temp files on page unload
        window.addEventListener('beforeunload', () => {
            if (uploadId) {
                // Send beacon to cleanup temp file
                navigator.sendBeacon('?ajax=cleanup', JSON.stringify({ upload_id: uploadId }));
            }
        });
        
        // Auto-check import options on page load
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.option-item input[type="checkbox"]').forEach(input => {
                if (input.checked) {
                    input.closest('.option-item').classList.add('checked');
                }
            });
        });
    </script>
</body>
</html>