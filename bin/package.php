<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
$group = $argv[1] ?? 'JHOP';
if (!preg_match('/^[A-Za-z0-9_-]{1,24}$/', $group)) { fwrite(STDERR, "Grupo inválido.\n"); exit(1); }
$output = $root . '/entrega';
if (!is_dir($output)) mkdir($output, 0755, true);
$filename = $output . '/PW2026_' . $group . '_BookGoLite.zip';
$zip = new ZipArchive();
if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "No se pudo crear ZIP.\n"); exit(1); }
$files = ['.htaccess','.env.example','.gitignore','README.md','app','bin','database','docs','public','security','tests','views'];
foreach ($files as $part) {
    $path = $root . '/' . $part;
    if (is_file($path)) { $zip->addFile($path, $part); continue; }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        if (!$item->isFile()) continue;
        $relative = substr($item->getPathname(), strlen($root) + 1);
        if (str_contains($relative, '__pycache__')) continue;
        $zip->addFile($item->getPathname(), $relative);
    }
}
$zip->close();
echo "Archivo creado: $filename\n";
