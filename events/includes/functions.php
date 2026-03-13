<?php
declare(strict_types=1);

function upload_file(array $file, array $allowedExtensions, string $targetDir, string $prefix): ?string
{
    if (empty($file['name']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Invalid file type.');
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $filename = $prefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = rtrim($targetDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not move uploaded file.');
    }

    return $filename;
}