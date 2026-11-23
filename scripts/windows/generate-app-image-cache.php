<?php
declare(strict_types=1);

// Derived display copies only; source uploads remain untouched.
$root = dirname(__DIR__, 2);
$source = $root.'/storage/app/public/product_images';
$target = $root.'/storage/app/mobile-image-cache/product_images';
if (!function_exists('imagewebp')) throw new RuntimeException('GD WebP support is required.');
if (!is_dir($target)) mkdir($target, 0755, true);
$count = $originalBytes = $displayBytes = 0;
foreach (new DirectoryIterator($source) as $file) {
    if (!$file->isFile() || !preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) continue;
    $destination = $target.'/'.$file->getFilename();
    if (is_file($destination) && filemtime($destination) >= $file->getMTime()) continue;
    $size = @getimagesize($file->getPathname());
    if (!$size || $size[0] * $size[1] > 20000000) continue;
    $input = @imagecreatefromstring(file_get_contents($file->getPathname()));
    if (!$input) continue;
    $scale = min(1, 1024 / max($size[0], $size[1]));
    $width = max(1, (int) round($size[0] * $scale));
    $height = max(1, (int) round($size[1] * $scale));
    $output = imagecreatetruecolor($width, $height);
    imagealphablending($output, false);
    imagesavealpha($output, true);
    imagecopyresampled($output, $input, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
    $temporary = $destination.'.tmp';
    if (imagewebp($output, $temporary, 82)) {
        if (filesize($temporary) < $file->getSize()) {
            rename($temporary, $destination);
            $count++;
            $originalBytes += $file->getSize();
            $displayBytes += filesize($destination);
        } else { unlink($temporary); }
    }
    unset($input, $output);
}
echo json_encode(compact('count', 'originalBytes', 'displayBytes')).PHP_EOL;
