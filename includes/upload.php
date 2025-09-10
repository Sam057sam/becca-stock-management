<?php
function handle_file_upload(array $file, string $upload_dir, array $allowed_mime_types): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        // No file was uploaded or there was an error
        return null;
    }

    // Check the MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    if (!in_array($mime_type, $allowed_mime_types, true)) {
        // Invalid file type
        return null;
    }

    // Create the upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!@mkdir($upload_dir, 0777, true)) {
            // Failed to create the directory
            return null;
        }
    }

    // Generate a unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s.%s', $basename, $extension);

    $destination = $upload_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Return the public path to the file
        return 'uploads/' . basename($upload_dir) . '/' . $filename;
    }

    return null;
}