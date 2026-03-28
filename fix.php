<?php
$dir = 'd:/Eduservice/saudn/api';
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        $search = "\$_SESSION['role'] !== 'student'";
        $replace = "!in_array(\$_SESSION['role'], ['student', 'admin', 'teacher'])";
        $newContent = str_replace($search, $replace, $content);
        if ($newContent !== $content) {
            file_put_contents($file, $newContent);
            echo "Updated $file\n";
        }
    }
}
echo "Done.";
