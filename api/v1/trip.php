<?php
/**
 * /api/v1/trip.php
 * CRUD for trips (Bearer token required via AuthMiddleware::check)
 */

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

header('Content-Type: application/json');

/* ---------------- Request ID & timing ---------------- */
$t0 = microtime(true);
try {
    $RID = bin2hex(random_bytes(4));
} catch (Throwable $e) {
    $RID = uniqid('rid_', true);
}
function rid() { global $RID; return $RID; }

/* ---------------- Auth ---------------- */
$user  = AuthMiddleware::check();
$userId = (int)$user['uid'];

/* ---------------- Helpers ---------------- */
function isMultipart(): bool {
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    return stripos($ct, 'multipart/form-data') !== false;
}
function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Logger::error("trip rid=" . rid() . " invalid_json");
        ErrorManager::throw('INVALID_JSON', 400);
    }
    return $data;
}
function validateLat($lat): bool { return is_numeric($lat) && $lat >= -90 && $lat <= 90; }
function validateLng($lng): bool { return is_numeric($lng) && $lng >= -180 && $lng <= 180; }

const MAX_FILE_SIZE_BYTES = 50 * 1024 * 1024; // 50 MB
const ALLOWED_IMAGE_MIME = ['image/jpeg','image/png','image/webp','image/gif'];
const ALLOWED_VIDEO_MIME = ['video/mp4','video/quicktime','video/webm','video/x-matroska'];

function projectRoot(): string { return dirname(__DIR__, 2); }
function ensureDir(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        Logger::error("trip rid=" . rid() . " upload_dir_not_writable dir={$dir}");
        ErrorManager::throw('UPLOAD_DIR_NOT_WRITABLE', 500);
    }
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
function summarizeFiles(?array $files): array {
    $count = 0; $bytes = 0; $types = [];
    $flat = normalizeFiles($files);
    foreach ($flat as $f) {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $count++;
        $bytes += (int)($f['size'] ?? 0);
        $types[] = $f['type'] ?? 'unknown';
    }
    return ['count'=>$count, 'bytes'=>$bytes, 'types'=>$types];
}
/** Save uploaded media and insert DB rows. */
function saveMediaFilesForTrip(int $tripId, array $files): array {
    $root = projectRoot();
    $uploadsDir = $root . '/uploads';
    $imagesDir  = $uploadsDir . '/images';
    $videosDir  = $uploadsDir . '/videos';
    ensureDir($uploadsDir); ensureDir($imagesDir); ensureDir($videosDir);

    $saved = [];
    foreach ($files as $idx => $f) {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($f['error'] !== UPLOAD_ERR_OK) {
            Logger::error("trip rid=" . rid() . " upload_failed idx={$idx} err=" . ($f['error'] ?? 'n/a'));
            ErrorManager::throw('UPLOAD_FAILED', 400);
        }
        if (($f['size'] ?? 0) <= 0 || $f['size'] > MAX_FILE_SIZE_BYTES) {
            Logger::error("trip rid=" . rid() . " file_size_invalid idx={$idx} size=" . ($f['size'] ?? 0));
            ErrorManager::throw('FILE_SIZE_INVALID', 413);
        }

        $mime = $f['type'] ?? '';
        $mediaType = detectMediaType($mime);
        if (!$mediaType) {
            Logger::error("trip rid=" . rid() . " unsupported_media_type idx={$idx} mime={$mime}");
            ErrorManager::throw('UNSUPPORTED_MEDIA_TYPE', 415);
        }

        $ext = guessExtensionByMime($mime) ?: pathinfo($f['name'] ?? '', PATHINFO_EXTENSION);
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $ext));
        $basename  = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $subPath   = ($mediaType === 'image' ? 'images/' : 'videos/') . $basename;
        $targetAbs = $uploadsDir . '/' . $subPath;

        if (!is_uploaded_file($f['tmp_name'])) {
            Logger::error("trip rid=" . rid() . " invalid_upload_stream idx={$idx}");
            ErrorManager::throw('INVALID_UPLOAD_STREAM', 400);
        }
        if (!move_uploaded_file($f['tmp_name'], $targetAbs)) {
            Logger::error("trip rid=" . rid() . " move_upload_failed idx={$idx} path={$targetAbs}");
            ErrorManager::throw('MOVE_UPLOAD_FAILED', 500);
        }

        $ok = DB::execute(SQL::insertMedia(), [$tripId, $mediaType, $subPath]);
        if (!$ok) {
            @unlink($targetAbs);
            Logger::error("trip rid=" . rid() . " media_db_insert_failed idx={$idx} path={$subPath}");
            ErrorManager::throw('MEDIA_DB_INSERT_FAILED', 500);
        }

        $saved[] = ['trip_id'=>$tripId,'media_type'=>$mediaType,'full_name'=>$subPath];
        Logger::info("trip rid=" . rid() . " media_saved idx={$idx} type={$mediaType} bytes=" . filesize($targetAbs));
    }
    return $saved;
}

/* initial request log */
$method = $_SERVER['REQUEST_METHOD'];
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
Logger::info("trip rid=" . rid() . " start method={$method} ct=\"{$ct}\" uid={$userId}");

/* ---------------- Router ---------------- */
switch ($method) {
    /* ---------- READ ---------- */
    case 'GET': {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            Logger::info("trip rid=" . rid() . " get_one id={$id}");
            $rows = DB::execute(SQL::getTripByIdForUser(), [$id, $userId]);
            if (!$rows || count($rows) === 0) {
                Logger::error("trip rid=" . rid() . " not_found id={$id}");
                ErrorManager::throw('NOT_FOUND', 404);
            }

            $trip  = $rows[0];
            $media = DB::execute(SQL::listMediaByTrip(), [$id]) ?: [];
            $trip['media'] = $media;

            Logger::info("trip rid=" . rid() . " get_one_ok id={$id} media_count=" . count($media));
            Response::success(['trip' => $trip]);
        } else {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $per  = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
            $offset = ($page - 1) * $per;

            Logger::info("trip rid=" . rid() . " list page={$page} per={$per}");

            $totalRow = DB::execute(SQL::countTripsByUser(), [$userId]);
            $total = (int)($totalRow[0]['cnt'] ?? 0);

            $sql = sprintf(SQL::listTripsByUserPaginated(), $per, $offset);
            $items = DB::execute($sql, [$userId]) ?: [];

            if (!empty($items)) {
                $tripIds = array_column($items, 'id');
                $placeholders = implode(',', array_fill(0, count($tripIds), '?'));
                $mediaSql = "SELECT trip_id, id, media_type, full_name 
                             FROM media 
                             WHERE trip_id IN ($placeholders)
                             ORDER BY id ASC";
                $allMedia = DB::execute($mediaSql, $tripIds) ?: [];

                $mediaMap = [];
                foreach ($allMedia as $m) {
                    $tid = (int)$m['trip_id'];
                    if (!isset($mediaMap[$tid])) $mediaMap[$tid] = [];
                    $mediaMap[$tid][] = [
                        'id'         => (int)$m['id'],
                        'media_type' => $m['media_type'],
                        'full_name'  => $m['full_name'],
                    ];
                }

                foreach ($items as &$r) {
                    $tid = (int)$r['id'];
                    $r['media'] = $mediaMap[$tid] ?? [];
                    $r['media_count'] = count($r['media']);
                }
                unset($r);
            }

            Logger::info("trip rid=" . rid() . " list_ok page={$page} per={$per} total={$total} items=" . count($items));
            Response::success([
                'page' => $page,
                'per_page' => $per,
                'total' => $total,
                'items' => $items
            ]);
        }
        break;
    }

    /* ---------- CREATE ---------- */
    case 'POST': {
        if (isMultipart()) {
            $sum = summarizeFiles($_FILES['media'] ?? null);
            Logger::info("trip rid=" . rid() . " create multipart files=" . ($sum['count'] ?? 0) . " bytes=" . ($sum['bytes'] ?? 0));

            $title = trim($_POST['title'] ?? '');
            $lat   = $_POST['latitude'] ?? null;
            $lng   = $_POST['longitude'] ?? null;
            $desc  = array_key_exists('description', $_POST) ? (string)$_POST['description'] : null;

            if ($title === '' || !validateLat($lat) || !validateLng($lng)) {
                Logger::error("trip rid=" . rid() . " invalid_input title_len=" . strlen($title) . " lat=" . ($lat ?? 'null') . " lng=" . ($lng ?? 'null'));
                ErrorManager::throw('INVALID_INPUT', 400);
            }
            if (mb_strlen($title) > 255) {
                Logger::error("trip rid=" . rid() . " title_too_long len=" . mb_strlen($title));
                ErrorManager::throw('TITLE_TOO_LONG', 422);
            }

            $ok = DB::execute(SQL::insertTrip(), [$title, $desc, (float)$lat, (float)$lng, $userId]);
            if (!$ok) {
                Logger::error("trip rid=" . rid() . " create_failed before_media");
                ErrorManager::throw('CREATE_FAILED', 500);
            }

            $row  = DB::execute(SQL::getLastTripByUser(), [$userId]);
            $trip = $row[0] ?? null;
            if (!$trip) {
                Logger::error("trip rid=" . rid() . " create_failed last_trip_missing");
                ErrorManager::throw('CREATE_FAILED', 500);
            }

            $files = normalizeFiles($_FILES['media'] ?? null);
            $savedMedia = !empty($files) ? saveMediaFilesForTrip((int)$trip['id'], $files) : [];

            $trip['media'] = $savedMedia;
            Logger::info("trip rid=" . rid() . " create_ok id=" . $trip['id'] . " media_saved=" . count($savedMedia));
            Response::success(['message'=>'Trip created','trip'=>$trip]);
        } else {
            Logger::info("trip rid=" . rid() . " create json");
            $input = readJsonBody();
            $title = trim($input['title'] ?? '');
            $lat   = $input['latitude'] ?? null;
            $lng   = $input['longitude'] ?? null;
            $desc  = isset($input['description']) ? (string)$input['description'] : null;

            if ($title === '' || !validateLat($lat) || !validateLng($lng)) {
                Logger::error("trip rid=" . rid() . " invalid_input_json title_len=" . strlen($title) . " lat=" . ($lat ?? 'null') . " lng=" . ($lng ?? 'null'));
                ErrorManager::throw('INVALID_INPUT', 400);
            }
            if (mb_strlen($title) > 255) {
                Logger::error("trip rid=" . rid() . " title_too_long len=" . mb_strlen($title));
                ErrorManager::throw('TITLE_TOO_LONG', 422);
            }

            $ok = DB::execute(SQL::insertTrip(), [$title, $desc, (float)$lat, (float)$lng, $userId]);
            if (!$ok) {
                Logger::error("trip rid=" . rid() . " create_failed_json");
                ErrorManager::throw('CREATE_FAILED', 500);
            }

            $row = DB::execute(SQL::getLastTripByUser(), [$userId]);
            Logger::info("trip rid=" . rid() . " create_ok_json id=" . ($row[0]['id'] ?? 'null'));
            Response::success(['message'=>'Trip created','trip'=>$row[0] ?? null]);
        }
        break;
    }

    /* ---------- UPDATE ---------- */
    case 'PUT': {
        $input = readJsonBody();
        $id = (int)($input['id'] ?? 0);
        Logger::info("trip rid=" . rid() . " update id={$id}");
        if ($id <= 0) {
            Logger::error("trip rid=" . rid() . " invalid_input_update id_missing");
            ErrorManager::throw('INVALID_INPUT', 400);
        }

        $exists = DB::execute(SQL::getTripByIdForUser(), [$id, $userId]);
        if (!$exists || count($exists) === 0) {
            Logger::error("trip rid=" . rid() . " not_found_update id={$id}");
            ErrorManager::throw('NOT_FOUND', 404);
        }

        $fields = [];
        $params = [];

        if (isset($input['title'])) {
            $title = trim((string)$input['title']);
            if ($title === '' || mb_strlen($title) > 255) {
                Logger::error("trip rid=" . rid() . " invalid_title_update len=" . mb_strlen($title));
                ErrorManager::throw('INVALID_TITLE', 422);
            }
            $fields[] = "title = ?";
            $params[] = $title;
        }
        if (array_key_exists('description', $input)) {
            $desc = $input['description'];
            if (!is_null($desc) && !is_string($desc)) {
                Logger::error("trip rid=" . rid() . " invalid_description_update");
                ErrorManager::throw('INVALID_DESCRIPTION', 422);
            }
            $fields[] = "description = ?";
            $params[] = $desc;
        }
        if (isset($input['latitude'])) {
            if (!validateLat($input['latitude'])) {
                Logger::error("trip rid=" . rid() . " invalid_lat_update val=" . $input['latitude']);
                ErrorManager::throw('INVALID_LATITUDE', 422);
            }
            $fields[] = "latitude = ?";
            $params[] = (float)$input['latitude'];
        }
        if (isset($input['longitude'])) {
            if (!validateLng($input['longitude'])) {
                Logger::error("trip rid=" . rid() . " invalid_lng_update val=" . $input['longitude']);
                ErrorManager::throw('INVALID_LONGITUDE', 422);
            }
            $fields[] = "longitude = ?";
            $params[] = (float)$input['longitude'];
        }

        if (empty($fields)) {
            Logger::error("trip rid=" . rid() . " nothing_to_update id={$id}");
            ErrorManager::throw('NOTHING_TO_UPDATE', 400);
        }

        $sql = sprintf(SQL::updateTripDynamic(), implode(', ', $fields));
        $params[] = $id;
        $params[] = $userId;

        $ok = DB::execute($sql, $params);
        if (!$ok) {
            Logger::error("trip rid=" . rid() . " update_failed id={$id}");
            ErrorManager::throw('UPDATE_FAILED', 500);
        }

        $row   = DB::execute(SQL::getTripByIdForUser(), [$id, $userId]);
        $media = DB::execute(SQL::listMediaByTrip(), [$id]) ?: [];
        $trip  = $row[0] ?? null;
        if ($trip) $trip['media'] = $media;

        Logger::info("trip rid=" . rid() . " update_ok id={$id} media_count=" . count($media));
        Response::success(['message'=>'Trip updated','trip'=>$trip]);
        break;
    }

    /* ---------- DELETE ---------- */
    case 'DELETE': {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            $body = readJsonBody();
            $id = (int)($body['id'] ?? 0);
        }
        Logger::info("trip rid=" . rid() . " delete id={$id}");
        if ($id <= 0) {
            Logger::error("trip rid=" . rid() . " invalid_input_delete id_missing");
            ErrorManager::throw('INVALID_INPUT', 400);
        }

        $exists = DB::execute(SQL::getTripByIdForUser(), [$id, $userId]);
        if (!$exists || count($exists) === 0) {
            Logger::error("trip rid=" . rid() . " not_found_delete id={$id}");
            ErrorManager::throw('NOT_FOUND', 404);
        }

        $ok = DB::execute(SQL::deleteTripForUser(), [$id, $userId]);
        if (!$ok) {
            Logger::error("trip rid=" . rid() . " delete_failed id={$id}");
            ErrorManager::throw('DELETE_FAILED', 500);
        }

        Logger::info("trip rid=" . rid() . " delete_ok id={$id}");
        Response::success(['message'=>'Trip deleted','id'=>$id]);
        break;
    }

    default:
        Logger::error("trip rid=" . rid() . " method_not_allowed method={$method}");
        ErrorManager::throw('METHOD_NOT_ALLOWED', 405);
}

/* final timing log (yalnızca normal akışlarda buraya gelinir; Response::success/error çıkış yapıyorsa görülmeyebilir) */
$elapsed = (int)round((microtime(true) - $t0) * 1000);
Logger::info("trip rid=" . rid() . " end elapsed_ms={$elapsed}");
