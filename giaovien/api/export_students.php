<?php
/**
 * Export Students to Excel
 * Exports student list with optional scores and progress details
 */

// Error handling configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean all output buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Start session
session_start();

// Performance settings
ini_set('memory_limit', '256M');
set_time_limit(120);

// Load dependencies
try {
    $vendorPath = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        throw new Exception('Vendor autoload not found. Run: composer install');
    }
    require_once $vendorPath;
    
    $dbPath = __DIR__ . '/../../db/db_config.php';
    if (!file_exists($dbPath)) {
        throw new Exception('Database config not found');
    }
    require_once $dbPath;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    exit(json_encode(['error' => $e->getMessage()]));
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Authentication & Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Content-Type: application/json');
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized access']));
}

try {
    // ===== VALIDATE INPUT =====
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    $type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
    
    // Map 'list' to 'basic' for backward compatibility
    if ($type === 'list') {
        $type = 'basic';
    }
    
    if (!$classId) {
        throw new Exception('Invalid class_id parameter');
    }
    
    if (!in_array($type, ['basic', 'scores', 'full'])) {
        throw new Exception('Invalid type parameter. Must be: basic, scores, or full');
    }
    
    // Verify database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }
    
    // ===== VERIFY CLASS OWNERSHIP =====
    $stmt = $pdo->prepare("SELECT c.id, c.class_name FROM classes c WHERE c.id = ? AND c.teacher_id = ?");
    $stmt->execute([$classId, $_SESSION['user_id']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class) {
        throw new Exception('Class not found or you do not have permission');
    }
    
    // ===== GET ALL LESSONS AND ASSIGNMENTS =====
    // Get all lessons in order
    $lessonsStmt = $pdo->prepare("
        SELECT l.id, l.title, l.max_score, l.order_index, ch.title as chapter_name
        FROM lessons l
        JOIN chapters ch ON l.chapter_id = ch.id
        WHERE ch.class_id = ?
        ORDER BY ch.order_index, l.order_index
    ");
    $lessonsStmt->execute([$classId]);
    $allLessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all assignments in order
    $assignmentsStmt = $pdo->prepare("
        SELECT id, title, max_score, created_at
        FROM assignments
        WHERE class_id = ?
        ORDER BY created_at
    ");
    $assignmentsStmt->execute([$classId]);
    $allAssignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalLessons = count($allLessons);
    $totalAssignments = count($allAssignments);
    
    // ===== CONFIGURE HEADERS =====
    $headers = ['STT', 'Mã SV', 'Họ và tên', 'Email', 'Ngày tham gia'];
    
    if ($type === 'scores' || $type === 'full') {
        // Add summary scores
        $headers = array_merge($headers, [
            'Điểm TB Tổng',
            'Điểm TB Lý thuyết',
            'Điểm TB Bài tập',
            'Bài hoàn thành',
            'Tổng bài học'
        ]);
    }
    
    // Add individual columns for 'full' type
    if ($type === 'full') {
        // Add individual lesson columns
        foreach ($allLessons as $lesson) {
            $headers[] = 'LT: ' . $lesson['title'];
        }
        
        // Add individual assignment columns
        foreach ($allAssignments as $assignment) {
            $headers[] = 'BT: ' . $assignment['title'];
        }
    }
    
    // ===== CREATE SPREADSHEET =====
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set sheet title
    $className = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $className->execute([$classId]);
    $classNameResult = $className->fetchColumn();
    $sheet->setTitle('Danh sách học sinh');
    
    // ===== WRITE HEADERS =====
    $headerRow = 1;
    $lastColumnIndex = count($headers) - 1;
    
    // Helper function to get column letter
    $getColumn = function($index) {
        if ($index <= 25) {
            return chr(65 + $index);
        } else {
            $firstChar = chr(65 + floor($index / 26) - 1);
            $secondChar = chr(65 + ($index % 26));
            return $firstChar . $secondChar;
        }
    };
    
    $lastColumn = $getColumn($lastColumnIndex);
    
    foreach ($headers as $index => $header) {
        $col = $getColumn($index);
        $sheet->setCellValue($col . $headerRow, $header);
    }
    
    // ===== STYLE HEADER ROW =====
    $headerRange = 'A' . $headerRow . ':' . $lastColumn . $headerRow;
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    
    // ===== BUILD QUERY =====
    $query = "
        SELECT 
            u.id,
            u.fullname as full_name,
            u.email,
            ce.enrolled_at
    ";
    
    if ($type === 'scores' || $type === 'full') {
        $query .= ",
            -- Overall average score
            COALESCE(
                (
                    SELECT AVG(score) FROM (
                        -- Lesson scores: Use max_score if completed, regardless of score column
                        SELECT 
                            CASE 
                                WHEN lp.status = 'completed' THEN 
                                    COALESCE(lp.score, l.max_score)
                                ELSE 0
                            END as score
                        FROM lesson_progress lp
                        JOIN lessons l ON lp.lesson_id = l.id
                        JOIN chapters ch ON l.chapter_id = ch.id
                        WHERE lp.user_id = u.id 
                          AND ch.class_id = ?
                        
                        UNION ALL
                        
                        -- Assignment scores
                        SELECT s.score
                        FROM submissions s
                        JOIN assignments a ON s.assignment_id = a.id
                        WHERE s.user_id = u.id 
                          AND a.class_id = ?
                          AND s.score IS NOT NULL
                    ) as all_scores
                ), 0
            ) as avg_score,
            
            -- Lesson average score: Use max_score if completed
            COALESCE(
                (
                    SELECT AVG(
                        CASE 
                            WHEN lp.status = 'completed' THEN 
                                COALESCE(lp.score, l.max_score)
                            ELSE 0
                        END
                    )
                    FROM lesson_progress lp
                    JOIN lessons l ON lp.lesson_id = l.id
                    JOIN chapters ch ON l.chapter_id = ch.id
                    WHERE lp.user_id = u.id 
                      AND ch.class_id = ?
                ), 0
            ) as lesson_avg_score,
            
            -- Assignment average score
            COALESCE(
                (
                    SELECT AVG(s.score)
                    FROM submissions s
                    JOIN assignments a ON s.assignment_id = a.id
                    WHERE s.user_id = u.id 
                      AND a.class_id = ?
                      AND s.score IS NOT NULL
                ), 0
            ) as assignment_avg_score,
            
            -- Completed lessons count
            COALESCE(
                (SELECT COUNT(DISTINCT lp3.lesson_id)
                 FROM lesson_progress lp3
                 JOIN lessons l3 ON lp3.lesson_id = l3.id
                 JOIN chapters ch3 ON l3.chapter_id = ch3.id
                 WHERE lp3.user_id = u.id
                   AND ch3.class_id = ?
                   AND lp3.status = 'completed'
                ), 0
            ) as completed_lessons
        ";
    }
    
    $query .= "
        FROM users u
        JOIN class_enrollments ce ON u.id = ce.user_id
        WHERE ce.class_id = ?
        ORDER BY u.fullname ASC
    ";
    
    // ===== EXECUTE QUERY =====
    $params = [$classId];
    if ($type === 'scores' || $type === 'full') {
        array_unshift($params, $classId, $classId, $classId, $classId, $classId);
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== GET DETAILED SCORES FOR EACH STUDENT (FOR FULL EXPORT) =====
    $studentLessonScores = [];
    $studentAssignmentScores = [];
    
    if ($type === 'full') {
        foreach ($students as $student) {
            $userId = $student['id'];
            
            // Initialize with null for all lessons
            $studentLessonScores[$userId] = [];
            
            // Get actual lesson scores
            $lessonScoresStmt = $pdo->prepare("
                SELECT 
                    l.id as lesson_id,
                    l.max_score,
                    lp.score,
                    lp.status,
                    lp.complete_time,
                    CASE 
                        WHEN lp.status = 'completed' THEN 
                            COALESCE(lp.score, l.max_score)
                        ELSE NULL
                    END as final_score
                FROM lessons l
                JOIN chapters ch ON l.chapter_id = ch.id
                LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
                WHERE ch.class_id = ?
                ORDER BY ch.order_index, l.order_index
            ");
            $lessonScoresStmt->execute([$userId, $classId]);
            
            foreach ($lessonScoresStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $studentLessonScores[$userId][$row['lesson_id']] = [
                    'score' => $row['final_score'], // Use calculated final_score
                    'max_score' => $row['max_score'],
                    'status' => $row['status'],
                    'completed' => $row['status'] === 'completed'
                ];
            }
            
            // Initialize with null for all assignments
            $studentAssignmentScores[$userId] = [];
            
            // Get actual assignment scores
            $assignmentScoresStmt = $pdo->prepare("
                SELECT 
                    a.id as assignment_id,
                    a.max_score,
                    s.score,
                    s.status,
                    s.submitted_at
                FROM assignments a
                LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
                WHERE a.class_id = ?
                ORDER BY a.created_at
            ");
            $assignmentScoresStmt->execute([$userId, $classId]);
            
            foreach ($assignmentScoresStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $studentAssignmentScores[$userId][$row['assignment_id']] = [
                    'score' => $row['score'],
                    'max_score' => $row['max_score'],
                    'status' => $row['status'],
                    'submitted' => $row['submitted_at'] !== null
                ];
            }
        }
    }
    
    // ===== WRITE STUDENT DATA =====
    $row = 2;
    $stt = 1;
    
    foreach ($students as $student) {
        $colIndex = 0;
        
        // STT
        $sheet->setCellValue($getColumn($colIndex++) . $row, $stt++);
        
        // Student info
        $sheet->setCellValue($getColumn($colIndex++) . $row, $student['id']);
        $sheet->setCellValue($getColumn($colIndex++) . $row, $student['full_name']);
        $sheet->setCellValue($getColumn($colIndex++) . $row, $student['email']);
        $sheet->setCellValue($getColumn($colIndex++) . $row, date('d/m/Y', strtotime($student['enrolled_at'])));
        
        // Summary Scores
        if ($type === 'scores' || $type === 'full') {
            $avgScore = round($student['avg_score'], 1);
            $lessonAvgScore = round($student['lesson_avg_score'], 1);
            $assignmentAvgScore = round($student['assignment_avg_score'], 1);
            $completedLessons = (int)$student['completed_lessons'];
            
            // Overall average
            $sheet->setCellValue($getColumn($colIndex++) . $row, $avgScore);
            
            // Lesson average
            $sheet->setCellValue($getColumn($colIndex++) . $row, $lessonAvgScore);
            
            // Assignment average
            $sheet->setCellValue($getColumn($colIndex++) . $row, $assignmentAvgScore);
            
            // Progress
            $sheet->setCellValue($getColumn($colIndex++) . $row, $completedLessons);
            $sheet->setCellValue($getColumn($colIndex++) . $row, $totalLessons);
            
            // Color coding for summary columns
            $completionRate = $totalLessons > 0 ? ($completedLessons / $totalLessons) : 0;
            
            if ($completionRate >= 0.8) {
                $scoreColor = 'C6EFCE'; // Green
            } elseif ($completionRate >= 0.5) {
                $scoreColor = 'FFEB9C'; // Yellow
            } else {
                $scoreColor = 'FFC7CE'; // Red
            }
            
            // Apply color to summary score columns (5,6,7)
            for ($i = 5; $i <= 7; $i++) {
                $sheet->getStyle($getColumn($i) . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $scoreColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER
                    ]
                ]);
            }
        }
        
        // Individual lesson scores (FULL EXPORT ONLY)
        if ($type === 'full') {
            $userId = $student['id'];
            
            // Lesson scores
            foreach ($allLessons as $lesson) {
                $lessonId = $lesson['id'];
                
                if (isset($studentLessonScores[$userId][$lessonId])) {
                    $data = $studentLessonScores[$userId][$lessonId];
                    
                    // Check if lesson is completed
                    if ($data['completed'] && $data['score'] !== null) {
                        $score = $data['score'];
                        $maxScore = $data['max_score'];
                        $displayValue = $score . '/' . $maxScore;
                        
                        // Color code based on score percentage
                        $percentage = ($score / $maxScore) * 100;
                        if ($percentage >= 80) {
                            $cellColor = 'C6EFCE'; // Green - excellent
                        } elseif ($percentage >= 50) {
                            $cellColor = 'FFEB9C'; // Yellow - average
                        } else {
                            $cellColor = 'FFC7CE'; // Red - poor
                        }
                    } else if ($data['status'] === 'in_progress') {
                        $displayValue = 'Đang học';
                        $cellColor = 'FFF2CC'; // Light yellow
                    } else {
                        $displayValue = '-';
                        $cellColor = 'FFFFFF'; // White - not started
                    }
                } else {
                    $displayValue = '-';
                    $cellColor = 'FFFFFF';
                }
                
                $currentCol = $getColumn($colIndex);
                $sheet->setCellValue($currentCol . $row, $displayValue);
                $sheet->getStyle($currentCol . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $cellColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER
                    ]
                ]);
                
                $colIndex++;
            }
            
            // Assignment scores
            foreach ($allAssignments as $assignment) {
                $assignmentId = $assignment['id'];
                
                if (isset($studentAssignmentScores[$userId][$assignmentId])) {
                    $data = $studentAssignmentScores[$userId][$assignmentId];
                    
                    if ($data['score'] === null) {
                        $displayValue = $data['submitted'] ? 'Chờ chấm' : '-';
                        $cellColor = $data['submitted'] ? 'FFF2CC' : 'FFFFFF'; // Light yellow if submitted
                    } else {
                        $score = $data['score'];
                        $maxScore = $data['max_score'];
                        $displayValue = $score . '/' . $maxScore;
                        
                        // Color code based on score percentage
                        $percentage = ($score / $maxScore) * 100;
                        if ($percentage >= 80) {
                            $cellColor = 'C6EFCE'; // Green - excellent
                        } elseif ($percentage >= 50) {
                            $cellColor = 'FFEB9C'; // Yellow - average
                        } else {
                            $cellColor = 'FFC7CE'; // Red - poor
                        }
                    }
                } else {
                    $displayValue = '-';
                    $cellColor = 'FFFFFF';
                }
                
                $currentCol = $getColumn($colIndex);
                $sheet->setCellValue($currentCol . $row, $displayValue);
                $sheet->getStyle($currentCol . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $cellColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER
                    ]
                ]);
                
                $colIndex++;
            }
        }
        
        $row++;
    }
    
    // ===== ADD AVERAGE ROW FOR FULL EXPORT =====
    if ($type === 'full' && count($students) > 0) {
        $avgRow = $row;
        $colIndex = 0;
        
        // Labels
        $sheet->setCellValue($getColumn($colIndex++) . $avgRow, '');
        $sheet->setCellValue($getColumn($colIndex++) . $avgRow, '');
        $sheet->setCellValue($getColumn($colIndex++) . $avgRow, 'ĐIỂM TRUNG BÌNH LỚP');
        $sheet->mergeCells($getColumn(2) . $avgRow . ':' . $getColumn(4) . $avgRow);
        
        // Skip summary columns (5-9)
        $colIndex = 10; // Start from first lesson column
        
        // Calculate average for each lesson
        foreach ($allLessons as $lesson) {
            $lessonId = $lesson['id'];
            $maxScore = $lesson['max_score'];
            $total = 0;
            $count = 0;
            
            foreach ($students as $student) {
                $userId = $student['id'];
                if (isset($studentLessonScores[$userId][$lessonId]) &&
                    $studentLessonScores[$userId][$lessonId]['completed'] &&
                    $studentLessonScores[$userId][$lessonId]['score'] !== null) {
                    $total += $studentLessonScores[$userId][$lessonId]['score'];
                    $count++;
                }
            }
            
            if ($count > 0) {
                $avg = round($total / $count, 1);
                $displayValue = $avg . '/' . $maxScore;
            } else {
                $displayValue = '-';
            }
            
            $currentCol = $getColumn($colIndex++);
            $sheet->setCellValue($currentCol . $avgRow, $displayValue);
            $sheet->getStyle($currentCol . $avgRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9D9D9']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
        }
        
        // Calculate average for each assignment
        foreach ($allAssignments as $assignment) {
            $assignmentId = $assignment['id'];
            $maxScore = $assignment['max_score'];
            $total = 0;
            $count = 0;
            
            foreach ($students as $student) {
                $userId = $student['id'];
                if (isset($studentAssignmentScores[$userId][$assignmentId]) && 
                    $studentAssignmentScores[$userId][$assignmentId]['score'] !== null) {
                    $total += $studentAssignmentScores[$userId][$assignmentId]['score'];
                    $count++;
                }
            }
            
            $avg = $count > 0 ? round($total / $count, 1) : 0;
            $currentCol = $getColumn($colIndex++);
            $sheet->setCellValue($currentCol . $avgRow, $avg . '/' . $maxScore);
            $sheet->getStyle($currentCol . $avgRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9D9D9']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
        }
    }
    
    // ===== APPLY BORDERS =====
    $lastDataRow = $type === 'full' && count($students) > 0 ? $row : $row - 1;
    $dataRange = 'A1:' . $lastColumn . $lastDataRow;
    $sheet->getStyle($dataRange)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    // ===== AUTO-SIZE COLUMNS =====
    for ($i = 0; $i <= $lastColumnIndex; $i++) {
        $col = $getColumn($i);
        if ($type === 'full' && $i >= 10) {
            // Fixed width for score columns
            $sheet->getColumnDimension($col)->setWidth(18);
        } else {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    // ===== SET ROW HEIGHTS =====
    $sheet->getRowDimension($headerRow)->setRowHeight(25);
    
    // ===== ADD METADATA =====
    $spreadsheet->getProperties()
        ->setCreator("VolunteerHub")
        ->setTitle("Danh sách học sinh - " . $classNameResult)
        ->setSubject("Báo cáo học sinh")
        ->setDescription("Danh sách học sinh và tiến độ học tập");
    
    // ===== GENERATE FILE =====
    $filename = 'students_' . $classId . '_' . $type . '_' . date('YmdHis') . '.xlsx';
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Write file to output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    exit();

} catch (Exception $e) {
    // Clear any output
    if (ob_get_length()) ob_clean();
    
    // Log error
    error_log("Export error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return JSON error
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
    exit();
}
?>