<?php
$excludeDirs = [
    'libs/phpqrcode',
    'libs/fpdf',
    'includes/PHPMailer',
];
function shouldExclude($filePath) {
    global $excludeDirs;
    foreach ($excludeDirs as $dir) {
        if (strpos($filePath, $dir) !== false) {
            return true;
        }
    }
    return false;
}
function removeAllComments($code) {
    $lines = explode("\n", $code);
    $result = [];
    $inBlockComment = false;
    $inCSSBlock = false;
    foreach ($lines as $lineNum => $line) {
        $origLine = $line;
        if ($inBlockComment) {
            if (preg_match('/\*\
                $inBlockComment = false;
                $line = preg_replace('/.*?\*\
            } else {
                continue;
            }
        }
        while (preg_match('/\/\*/', $line)) {
            $matches = [];
            if (preg_match('/^(.*?)\/\*(.*?)\*\/(.*?)$/', $line, $matches)) {
                $line = $matches[1] . $matches[3];
            } elseif (preg_match('/^(.*?)\/\*/', $line)) {
                $inBlockComment = true;
                $line = preg_match('/^(.*?)\/\*/', $line, $m) ? $m[1] : '';
                break;
            } else {
                break;
            }
        }
        $line = preg_replace('/\/\/.*$/', '', $line);
        $trimmed = trim($line);
        if ($trimmed !== '' || $origLine === '') {
            $result[] = $line;
        }
    }
    $output = implode("\n", $result);
    $output = preg_replace('/\n\s*\n\s*\n/', "\n\n", $output);
    return $output;
}
function processFiles($dir, &$stats) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $dir . '/' . $file;
        $relativePath = str_replace(dirname(__DIR__) . '/Smartbookers-1/', '', $filePath);
        if (shouldExclude($relativePath)) {
            continue;
        }
        if (is_dir($filePath)) {
            processFiles($filePath, $stats);
        } elseif (preg_match('/\.(php|js)$/', $filePath)) {
            $code = file_get_contents($filePath);
            $originalSize = strlen($code);
            $cleaned = removeAllComments($code);
            if (strlen($cleaned) < $originalSize) {
                file_put_contents($filePath, $cleaned);
                $stats['processed']++;
                $stats['bytesRemoved'] += $originalSize - strlen($cleaned);
                echo "✓ Processed: $relativePath (~" . ($originalSize - strlen($cleaned)) . " bytes)\n";
            } else {
                $stats['nothingRemoved']++;
            }
        }
    }
}
$baseDir = __DIR__;
$stats = ['processed' => 0, 'nothingRemoved' => 0, 'bytesRemoved' => 0];
echo "🔄 Second pass: Removing remaining comments...\n\n";
processFiles($baseDir, $stats);
echo "\n✅ Second pass complete!\n";
echo "Files modified: {$stats['processed']}\n";
echo "Files already clean: {$stats['nothingRemoved']}\n";
echo "Total bytes removed: {$stats['bytesRemoved']}\n";
