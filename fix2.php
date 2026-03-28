<?php
$dir = 'd:/Eduservice/saudn/api';
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        
        // Remove bad patterns from powershell replace attempt
        $bad1 = "if (!isset(\$_SESSION['user_id']) || !in_array(if (!isset(\$_SESSION['user_id']) || !in_array(\$_SESSION['role'], ['student', 'admin', 'teacher'])) {SESSION['role'], ['student', 'admin', 'teacher'])) {";
        $good = "if (!isset(\$_SESSION['user_id']) || !in_array(\$_SESSION['role'], ['student', 'admin', 'teacher'])) {";
        
        $newContent = str_replace($bad1, $good, $content);
        
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "Fixed $file\n";
        }
    }
}
echo "Done.";
