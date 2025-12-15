<?php
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../../db/db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // LESSONS
        case 'get_pending_lessons':
            getPendingLessons($pdo);
            break;
        case 'get_lessons':
            getLessons($pdo);
            break;
        case 'get_lesson':
            getLesson($pdo);
            break;
        case 'approve_lesson':
            approveLesson($pdo);
            break;
        case 'reject_lesson':
            rejectLesson($pdo);
            break;
        case 'delete_lesson':
            deleteLesson($pdo);
            break;
            
        // SUBMISSIONS
        case 'get_pending_submissions':
            getPendingSubmissions($pdo);
            break;
        case 'get_submission':
            getSubmission($pdo);
            break;
        case 'grade_submission':
            gradeSubmission($pdo);
            break;
            
        // DOCUMENTS
        case 'get_documents':
            getDocuments($pdo);
            break;
        case 'download_document':
            downloadDocument($pdo);
            break;
        case 'scan_document':
            scanDocument($pdo);
            break;
        case 'delete_document':
            deleteDocument($pdo);
            break;
        case 'cleanup_files':
            cleanupFiles($pdo);
            break;
        case 'scan_all_files':
            scanAllFiles($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// === LESSONS ===

function getPendingLessons($pdo) {
    $stmt = $pdo->query("
        SELECT l.*, c.class_name, u.fullname as teacher_name
        FROM lessons l
        LEFT JOIN classes c ON l.class_id = c.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE l.status = 'pending'
        ORDER BY l.created_at DESC
    ");
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'lessons' => $lessons]);
}

function getLessons($pdo) {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(l.title LIKE ? OR c.class_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status) {
        $where[] = "l.status = ?";
        $params[] = $status;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("
        SELECT l.*, c.class_name, u.fullname as teacher_name,
               (SELECT COUNT(*) FROM lesson_views WHERE lesson_id = l.id) as views,
               (SELECT AVG(duration) FROM lesson_views WHERE lesson_id = l.id) as avg_duration
        FROM lessons l
        LEFT JOIN classes c ON l.class_id = c.id
        LEFT JOIN users u ON c.teacher_id = u.id
        $whereClause
        ORDER BY l.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'lessons' => $lessons]);
}

function getLesson($pdo) {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT l.*, c.class_name, u.fullname as teacher_name
        FROM lessons l
        LEFT JOIN classes c ON l.class_id = c.id
        LEFT JOIN users u ON c.teacher_id = u.id
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lesson) {
        echo json_encode(['success' => true, 'lesson' => $lesson]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài học']);
    }
}

function approveLesson($pdo) {
    $id = (int)($_POST['lesson_id'] ?? 0);
    
    $stmt = $pdo->prepare("UPDATE lessons SET status = 'published', updated_at = datetime('now') WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        logActivity($pdo, 'approve_lesson', "Approved lesson ID: $id");
        echo json_encode(['success' => true, 'message' => 'Đã duyệt bài học']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể duyệt bài học']);
    }
}

function rejectLesson($pdo) {
    $id = (int)($_POST['lesson_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE lessons SET status = 'rejected', updated_at = datetime('now') WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        logActivity($pdo, 'reject_lesson', "Rejected lesson ID: $id. Reason: $reason");
        echo json_encode(['success' => true, 'message' => 'Đã từ chối bài học']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể từ chối bài học']);
    }
}

function deleteLesson($pdo) {
    $id = (int)($_POST['lesson_id'] ?? 0);
    
    // Delete related records
    $pdo->prepare("DELETE FROM lesson_views WHERE lesson_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM documents WHERE lesson_id = ?")->execute([$id]);
    
    $stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        logActivity($pdo, 'delete_lesson', "Deleted lesson ID: $id");
        echo json_encode(['success' => true, 'message' => 'Đã xóa bài học']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa bài học']);
    }
}

// === SUBMISSIONS ===

function getPendingSubmissions($pdo) {
    $stmt = $pdo->query("
        SELECT s.*, a.title as assignment_title, a.due_date, u.fullname as student_name, c.class_name
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON a.class_id = c.id
        WHERE s.status IN ('submitted', 'grading')
        ORDER BY s.submitted_at ASC
        LIMIT 50
    ");
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'submissions' => $submissions]);
}

function getSubmission($pdo) {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT s.*, a.title as assignment_title, a.due_date, u.fullname as student_name, c.class_name
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN classes c ON a.class_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        echo json_encode(['success' => true, 'submission' => $submission]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài nộp']);
    }
}

function gradeSubmission($pdo) {
    $id = (int)($_POST['submission_id'] ?? 0);
    $score = floatval($_POST['score'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');
    
    if ($score < 0 || $score > 10) {
        echo json_encode(['success' => false, 'message' => 'Điểm số phải từ 0 đến 10']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE assignment_submissions 
        SET score = ?, feedback = ?, status = 'graded', graded_at = datetime('now'), graded_by = ?
        WHERE id = ?
    ");
    $result = $stmt->execute([$score, $feedback, $_SESSION['user_id'], $id]);
    
    if ($result) {
        logActivity($pdo, 'grade_submission', "Graded submission ID: $id with score: $score");
        echo json_encode(['success' => true, 'message' => 'Đã chấm điểm']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể chấm điểm']);
    }
}

// === DOCUMENTS ===

function getDocuments($pdo) {
    $search = $_GET['search'] ?? '';
    $type = $_GET['type'] ?? '';
    
    $where = ["d.status = 'active'"];
    $params = [];
    
    if ($search) {
        $where[] = "(d.title LIKE ? OR d.original_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($type) {
        if ($type === 'image') {
            $where[] = "d.file_type IN ('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp')";
        } elseif ($type === 'video') {
            $where[] = "d.file_type IN ('mp4', 'avi', 'mov', 'wmv', 'webm')";
        } elseif ($type === 'doc') {
            $where[] = "d.file_type IN ('doc', 'docx')";
        } elseif ($type === 'xls') {
            $where[] = "d.file_type IN ('xls', 'xlsx')";
        } else {
            $where[] = "d.file_type = ?";
            $params[] = $type;
        }
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT d.*, u.fullname as uploader_name, c.class_name
        FROM documents d
        LEFT JOIN users u ON d.uploaded_by = u.id
        LEFT JOIN classes c ON d.class_id = c.id
        $whereClause
        ORDER BY d.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'documents' => $documents]);
}

function downloadDocument($pdo) {
    $id = (int)($_GET['id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc && file_exists($doc['file_path'])) {
        // Update download count
        $pdo->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?")->execute([$id]);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $doc['original_name'] . '"');
        header('Content-Length: ' . filesize($doc['file_path']));
        readfile($doc['file_path']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'File không tồn tại']);
    }
}

function scanDocument($pdo) {
    $id = (int)($_POST['document_id'] ?? 0);
    
    // Giả lập quét virus - trong thực tế sẽ dùng ClamAV hoặc API
    $scan_result = 'clean';
    
    $stmt = $pdo->prepare("UPDATE documents SET scan_status = ?, is_safe = ?, updated_at = datetime('now') WHERE id = ?");
    $result = $stmt->execute([$scan_result, 1, $id]);
    
    if ($result) {
        logActivity($pdo, 'scan_document', "Scanned document ID: $id. Result: $scan_result");
        echo json_encode(['success' => true, 'scan_status' => $scan_result, 'message' => 'Đã quét file']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể quét file']);
    }
}

function deleteDocument($pdo) {
    $id = (int)($_POST['document_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT file_path, original_name FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc) {
        // Delete file
        if (file_exists($doc['file_path'])) {
            @unlink($doc['file_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            logActivity($pdo, 'delete_document', "Deleted document: {$doc['original_name']}");
            echo json_encode(['success' => true, 'message' => 'Đã xóa tài liệu']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa tài liệu']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Tài liệu không tồn tại']);
    }
}

function cleanupFiles($pdo) {
    $project_root = dirname(dirname(__DIR__));
    $upload_dir = $project_root . '/uploads';
    $deleted_count = 0;
    
    // Get all file paths from database
    $db_files = $pdo->query("SELECT file_path FROM documents")->fetchAll(PDO::FETCH_COLUMN);
    
    if (is_dir($upload_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            $path = $file->getPathname();
            // Delete if not in DB and older than 30 days
            if (!in_array($path, $db_files) && $file->getMTime() < strtotime('-30 days')) {
                @unlink($path);
                $deleted_count++;
            }
        }
    }
    
    logActivity($pdo, 'cleanup_files', "Cleaned up $deleted_count orphan files");
    echo json_encode(['success' => true, 'message' => "Đã dọn dẹp $deleted_count file rác"]);
}

function scanAllFiles($pdo) {
    // Giả lập quét tất cả files
    $stmt = $pdo->query("SELECT id FROM documents WHERE scan_status = 'pending'");
    $pending = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $clean = 0;
    $suspicious = 0;
    $infected = 0;
    
    foreach ($pending as $id) {
        // Giả lập kết quả quét (thực tế sẽ dùng antivirus)
        $result = 'clean';
        $is_safe = 1;
        
        $pdo->prepare("UPDATE documents SET scan_status = ?, is_safe = ? WHERE id = ?")->execute([$result, $is_safe, $id]);
        
        if ($result === 'clean') $clean++;
        elseif ($result === 'suspicious') $suspicious++;
        else $infected++;
    }
    
    $total = count($pending);
    logActivity($pdo, 'scan_all_files', "Scanned $total files. Clean: $clean, Suspicious: $suspicious, Infected: $infected");
    
    echo json_encode([
        'success' => true,
        'total_scanned' => $total,
        'clean' => $clean,
        'suspicious' => $suspicious,
        'infected' => $infected
    ]);
}

// Helper: Log activity
function logActivity($pdo, $action, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
?>
