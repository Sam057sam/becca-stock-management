<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload.php';


function save_digital_signature(int $quote_id, ?string $base64_data = null, ?array $file = null): bool {
    error_log("DEBUG: save_digital_signature function called for quote ID $quote_id");

    $pdo = get_db();

    // Check if the quote exists
    $stmt_check = $pdo->prepare("SELECT id FROM quotes WHERE id = ? LIMIT 1");
    $stmt_check->execute([$quote_id]);
    if (!$stmt_check->fetch()) {
        error_log("SIG_SAVE_ERROR: Attempted to save signature for non-existent quote ID: $quote_id");
        return false;
    }
    error_log("DEBUG: Quote exists, proceeding to directory check.");

    $upload_dir = __DIR__ . '/../public/uploads/signatures';
    $public_path = null;

    if ($file) {
        error_log("DEBUG: Handling file upload.");
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $public_path = handle_file_upload($file, $upload_dir, $allowed_mime_types);
        if (!$public_path) {
            error_log("SIG_SAVE_ERROR: File upload failed. Check permissions for: " . $upload_dir);
            return false;
        }
        error_log("DEBUG: File moved successfully.");
    } elseif ($base64_data) {
        error_log("DEBUG: Handling base64 data.");
        if (strpos($base64_data, 'data:image/png;base64,') !== 0) {
            error_log("SIG_SAVE_ERROR: Invalid base64 signature data format.");
            return false;
        }
        $base64_string = substr($base64_data, strpos($base64_data, ',') + 1);
        $image_data = base64_decode($base64_string);
        if ($image_data === false) {
            error_log("SIG_SAVE_ERROR: Base64 decoding failed.");
            return false;
        }

        $filename = 'signature_' . $quote_id . '_' . time() . '.png';
        $filepath = $upload_dir . '/' . $filename;

        if (file_put_contents($filepath, $image_data) === false) {
            error_log("SIG_SAVE_ERROR: Failed to write signature image to file: $filepath. Check folder permissions.");
            return false;
        }
        $public_path = 'uploads/signatures/' . $filename;
        error_log("DEBUG: Base64 data saved successfully.");
    } else {
        error_log("SIG_SAVE_ERROR: No signature data provided.");
        return false;
    }

    // Update the database record
    try {
        error_log("DEBUG: Updating database record.");
        $stmt_update = $pdo->prepare("UPDATE quotes SET signature_path = ?, signed_at = NOW() WHERE id = ?");
        return $stmt_update->execute([$public_path, $quote_id]);
    } catch (Throwable $e) {
        error_log("SIG_SAVE_ERROR: Database update failed: " . $e->getMessage());
        return false;
    }
}

function delete_digital_signature(int $quote_id): bool {
    $pdo = get_db();
    
    // Fetch the current signature path
    $stmt_fetch = $pdo->prepare("SELECT signature_path FROM quotes WHERE id = ? LIMIT 1");
    $stmt_fetch->execute([$quote_id]);
    $path = $stmt_fetch->fetchColumn();
    
    // If a path exists, try to delete the file
    if (!empty($path) && file_exists(__DIR__ . '/../public/' . $path)) {
        @unlink(__DIR__ . '/../public/' . $path);
    }
    
    // Clear the database fields
    try {
        $stmt_update = $pdo->prepare("UPDATE quotes SET signature_path = NULL, signed_at = NULL WHERE id = ?");
        return $stmt_update->execute([$quote_id]);
    } catch (Throwable $e) {
        error_log("SIG_DELETE_ERROR: Database update failed: " . $e->getMessage());
        return false;
    }
}