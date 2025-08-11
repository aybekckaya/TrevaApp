<?php
/**
 * /api/v1/media.php
 *
 * POST   (multipart/form-data): add media to a trip
 *   fields: trip_id (req)
 *   files : media[] (one or more)
 *
 * GET    : ?trip_id=123  -> list media for that trip (ownership enforced)
 *
 * DELETE : ?id=MEDIA_ID  |  JSON { "id": MEDIA_ID }
 *   -> removes DB row and deletes the file from /uploads/...
 *
 * All routes require a valid Bearer token via AuthMiddleware::check()
 */

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

header('Content-Type: application/json');

/* -------- Auth -------- */
$user = AuthMiddleware::check();
$userId = (int)$user['uid'];

/* -------- Helpers -------- */
function isMultipart(): bool {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    return stripos($ct, 'multipart/form-data') !== false;
}
function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) ErrorManager::throw('INVALID_JSON', 400);
    return $data;
}

const MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024; // 50MB
const ALLOWED_IMAGE_MIME = ['image/jpeg','image/png','image/webp','image/gif'];
const ALLOWED_VIDEO_MIME = ['video/mp4','video/quicktime','video/webm','video/x-matroska'];

function projectRoot(): string { return dirname(__DIR__, 2); }
function ensureDir(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) ErrorManager::throw('UPLOAD_DIR_NOT_WRITABLE', 500);
}
function normalizeFiles(?array $files): array {
    if (!$files || !isset($files['name'])) return [];
    if (is_array($files['name'])) {
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $out;
    }
    return [$files];
}
function guessExtensionByMime(string $mime): ?string {
    static $map = [
        'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif',
        'video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','video/x-matroska'=>'mkv',
    ];
    return $map[$mime] ?? null;
}
function detectMediaType(string $mime): ?string {
    if (in_array($mime, ALLOWED_IMAGE_MIME, true)) return 'image';
    if (in_array($mime, ALLOWED_VIDEO_MIME, true)) return 'video';
    return null;
}
function saveMediaFiles(int $tripId, array $files): array {
    $root = projectRoot();
    $uploadsDir = $root . '/uploads';
    $imagesDir  = $uploadsDir . '/images';
    $videosDir  = $uploadsDir . '/videos';

    ensureDir($uploadsDir); ensureDir($imagesDir); ensureDir($videosDir);

    $saved = [];
    foreach ($files as $f) {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($f['error'] !== UPLOAD_ERR_OK) ErrorManager::throw('UPLOAD_FAILED', 400);
        if (($f['size'] ?? 0) <= 0 || $f['size'] > MAX_FILE_SIZE_BYTES) ErrorManager::throw('FILE_SIZE_INVALID', 413);

        $mime = $f['type'] ?? '';
        $mediaType = detectMediaType($mime);
        if (!$mediaType) ErrorManager::throw('UNSUPPORTED_MEDIA_TYPE', 415);

        $ext = guessExtensionByMime($mime) ?: pathinfo($f['name'] ?? '', PATHINFO_EXTENSION);
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $ext));
        $basename  = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $subPath   = ($mediaType === 'image' ? 'images/' : 'videos/') . $basename;
        $targetAbs = $uploadsDir . '/' . $subPath;

        if (!is_uploaded_file($f['tmp_name'])) ErrorManager::throw('INVALID_UPLOAD_STREAM', 400);
        if (!move_uploaded_file($f['tmp_name'], $targetAbs)) ErrorManager::throw('MOVE_UPLOAD_FAILED', 500);

        $ok = DB::execute(SQL::insertMedia(), [$tripId, $mediaType, $subPath]);
        if (!$ok) { @unlink($targetAbs); ErrorManager::throw('MEDIA_DB_INSERT_FAILED', 500); }

        $saved[] = ['media_type'=>$mediaType,'full_name'=>$subPath];
    }
    return $saved;
}

/* -------- Router -------- */
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    /* ------- CREATE: add media to a trip ------- */
    case 'POST': {
        if (!isMultipart()) ErrorManager::throw('CONTENT_TYPE_MUST_BE_MULTIPART', 415);

        $tripId = (int)($_POST['trip_id'] ?? 0);
        if ($tripId <= 0) ErrorManager::throw('INVALID_INPUT', 400);

        // ownership
        $trip = DB::execute(SQL::getTripByIdForUser(), [$tripId, $userId]);
        if (!$trip || count($trip) === 0) ErrorManager::throw('NOT_FOUND', 404);

        $files = normalizeFiles($_FILES['media'] ?? null);
        if (empty($files)) ErrorManager::throw('NO_FILES_PROVIDED', 400);

        $saved = saveMediaFiles($tripId, $files);

        $mediaList = DB::execute(SQL::listMediaByTrip(), [$tripId]) ?: [];
        Response::success(['message'=>'Media uploaded','saved'=>$saved,'items'=>$mediaList]);
        break;
    }

    /* ------- READ: list media for a trip ------- */
    case 'GET': {
        $tripId = (int)($_GET['trip_id'] ?? 0);
        if ($tripId <= 0) ErrorManager::throw('INVALID_INPUT', 400);

        // ownership
        $trip = DB::execute(SQL::getTripByIdForUser(), [$tripId, $userId]);
        if (!$trip || count($trip) === 0) ErrorManager::throw('NOT_FOUND', 404);

        $mediaList = DB::execute(SQL::listMediaByTrip(), [$tripId]) ?: [];
        Response::success(['items'=>$mediaList]);
        break;
    }

    /* ------- DELETE: remove a single media (DB + file) ------- */
    case 'DELETE': {
        // id can come from query or JSON
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $body = readJsonBody();
            $id = (int)($body['id'] ?? 0);
        }
        if ($id <= 0) ErrorManager::throw('INVALID_INPUT', 400);

        // Get media row (trip_id + path)
        // Make sure SQL::getMediaById() exists:
        //   SELECT id, trip_id, media_type, full_name FROM media WHERE id = ?
        $mediaRow = DB::execute(SQL::getMediaById(), [$id]);
        if (!$mediaRow || count($mediaRow) === 0) ErrorManager::throw('MEDIA_NOT_FOUND', 404);
        $media = $mediaRow[0];

        // Ownership via related trip
        $tripId = (int)$media['trip_id'];
        $trip = DB::execute(SQL::getTripByIdForUser(), [$tripId, $userId]);
        if (!$trip || count($trip) === 0) ErrorManager::throw('MEDIA_OWNERSHIP_VIOLATION', 403);

        // Delete DB row
        $ok = DB::execute(SQL::deleteMediaById(), [$id]);
        if (!$ok) ErrorManager::throw('DELETE_FAILED', 500);

        // Delete file from disk
        $absPath = projectRoot() . '/uploads/' . ltrim((string)$media['full_name'], '/');
        if (is_file($absPath)) { @unlink($absPath); }

        // Return remaining list
        $mediaList = DB::execute(SQL::listMediaByTrip(), [$tripId]) ?: [];
        Response::success(['message'=>'Media deleted','id'=>$id,'items'=>$mediaList]);
        break;
    }

    default:
        ErrorManager::throw('METHOD_NOT_ALLOWED', 405);
}
