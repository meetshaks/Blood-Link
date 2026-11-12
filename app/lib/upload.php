<?php
declare(strict_types=1);

function store_avatar(array $file, ?string $oldRelativePath = null, bool $deleteOld = true): string
{
    $config = bloodlink_config();
    $maxSize = (int) ($config['upload_max'] ?? 2 * 1024 * 1024);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Image upload failed.');
    }
    if (!isset($file['tmp_name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Invalid uploaded file.');
    }
    if ((int) $file['size'] > $maxSize) {
        throw new InvalidArgumentException('Image exceeds the maximum size.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true) || @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Only valid JPEG, PNG, or WebP images are allowed.');
    }
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('Image processing is unavailable.');
    }

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png' => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
    };
    if ($source === false) {
        throw new InvalidArgumentException('Image could not be decoded.');
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $scale = min(1, 400 / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $target = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $directory = dirname(__DIR__, 2) . '/public/uploads/avatars';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Avatar directory could not be created.');
    }
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!imagejpeg($target, $absolutePath, 85)) {
        throw new RuntimeException('Avatar could not be saved.');
    }
    imagedestroy($source);
    imagedestroy($target);

    if ($deleteOld && $oldRelativePath !== null) {
        $oldPath = dirname(__DIR__, 2) . '/public/' . ltrim($oldRelativePath, '/');
        if (is_file($oldPath) && realpath(dirname($oldPath)) === realpath($directory)) {
            unlink($oldPath);
        }
    }
    return '/uploads/avatars/' . $filename;
}
