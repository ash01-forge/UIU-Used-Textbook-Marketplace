<?php
/**
 * BookBridge - UIU Used Textbook Marketplace
 * Seller Image Upload Handler (api/seller/upload-image.php)
 *
 * Endpoint: POST /api/seller/upload-image.php
 * Format  : multipart/form-data
 * Field   : 'image' or 'file'
 * Access  : Authenticated Seller only
 * CSRF    : Required (header X-CSRF-Token or POST csrf_token)
 */

require_once __DIR__ . '/helpers.php';

sellerRequireMethod(['POST']);

$user = requireRole('seller');
requireCsrfToken();

// Find uploaded file in $_FILES
$fileKey = null;
if (isset($_FILES['image'])) {
    $fileKey = 'image';
} elseif (isset($_FILES['file'])) {
    $fileKey = 'file';
}

if ($fileKey === null) {
    sendErrorResponse('No image file uploaded. Please upload a file with field name "image".', 422, [
        'image' => 'File is required.',
    ]);
}

$file = $_FILES[$fileKey];

// Check upload errors
if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
    sendErrorResponse('Invalid file upload parameters.', 422);
}

switch ($file['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        sendErrorResponse('File exceeds the maximum upload size limit (2 MB).', 422, [
            'image' => 'File size is too large.',
        ]);
        break;
    case UPLOAD_ERR_NO_FILE:
        sendErrorResponse('No file was sent.', 422, [
            'image' => 'File is required.',
        ]);
        break;
    default:
        sendErrorResponse('File upload failed due to a server error.', 500);
}

// 1. File size check (Max 2MB)
$maxBytes = 2 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    sendErrorResponse('File size exceeds the 2 MB limit.', 422, [
        'image' => 'Maximum allowed size is 2 MB.',
    ]);
}

if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    sendErrorResponse('Invalid uploaded image.', 422);
}
$actualSize = filesize($file['tmp_name']);
if ($actualSize === false || $actualSize < 1 || $actualSize > $maxBytes) {
    sendErrorResponse('Image must be nonempty and no larger than 2 MB.', 422);
}
$file['size'] = $actualSize;

// 2. MIME type inspection using finfo (do not trust client extension or Content-Type header)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeMap = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

if (!array_key_exists($mimeType, $allowedMimeMap)) {
    sendErrorResponse('Invalid image file format. Only JPG, PNG, and WebP images are allowed.', 422, [
        'image' => 'Allowed MIME types: image/jpeg, image/png, image/webp.',
    ]);
}

if (@getimagesize($file['tmp_name']) === false) {
    sendErrorResponse('The uploaded file is not a readable image.', 422);
}
$extension = $allowedMimeMap[$mimeType];

// 3. Prepare target upload directory
$projectRoot = dirname(__DIR__, 2);
$uploadsDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';
$targetDir = $uploadsDir . DIRECTORY_SEPARATOR . 'listings';

if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        sendErrorResponse('Server failed to initialize storage directory.', 500);
    }
}

// Secure upload directory: create .htaccess to disable script execution
$htaccessPath = $uploadsDir . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = <<<EOT
# Disable PHP and script execution in upload directories
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|php8|phps|phar|cgi|pl|exe|sh)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
Options -ExecCGI -Indexes
php_flag engine off
EOT;
    @file_put_contents($htaccessPath, $htaccessContent);
}

// 4. Generate safe unique filename
$randomName = 'img_' . bin2hex(random_bytes(16)) . '.' . $extension;
$destination = $targetDir . DIRECTORY_SEPARATOR . $randomName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    sendErrorResponse('Failed to store the uploaded image.', 500);
}

// Relative web URL
$relativeUrl = 'uploads/listings/' . $randomName;
$_SESSION['seller_uploads'][$relativeUrl] = (int) $user['id'];

sendSuccessResponse('Image uploaded successfully.', [
    'image_url' => $relativeUrl,
    'mime_type' => $mimeType,
    'size'      => $file['size'],
], 201);
