<?php
/**
 * /api/v1/user.php
 * User CRUD + Follow System (DB, SQL, Response, Logger, ErrorManager kullanır)
 */

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

require_once __DIR__ . '/../../core/DB.php';
require_once __DIR__ . '/../../core/SQL.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Logger.php';
require_once __DIR__ . '/../../core/ErrorManager.php';

header('Content-Type: application/json');

/* -------- Auth -------- */
try {
    $auth = AuthMiddleware::check(); // invalid ise zaten halt ediyor
    $authUserId = (int)$auth['uid'];
} catch (Throwable $e) {
    Logger::error("Auth error: " . $e->getMessage());
    ErrorManager::throw('UNAUTHORIZED', 401);
}

/* -------- Helpers -------- */
function body(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') return [];
    $json = json_decode($raw, true);
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
        ErrorManager::throw('INVALID_JSON', 400);
    }
    return is_array($json) ? $json : [];
}

function intGET(string $k, int $def = 0): int {
    return isset($_GET[$k]) ? (int)$_GET[$k] : $def;
}

function strGET(string $k, string $def = ''): string {
    return isset($_GET[$k]) ? (string)$_GET[$k] : $def;
}

function profileWithCounters(PDO $pdo, int $viewerId, int $targetId): array {
    // user
    $st = $pdo->prepare(SQL::getUserById());
    $st->execute([$targetId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) return [];

    // counts
    $c1 = $pdo->prepare(SQL::followersCount());
    $c1->execute([$targetId]);
    $followers = (int)$c1->fetchColumn();

    $c2 = $pdo->prepare(SQL::followingCount());
    $c2->execute([$targetId]);
    $following = (int)$c2->fetchColumn();

    // is_following
    $isFollowing = 0;
    if ($viewerId > 0 && $viewerId !== $targetId) {
        $q = $pdo->prepare(SQL::isFollowing());
        $q->execute([$viewerId, $targetId]);
        $isFollowing = $q->fetchColumn() ? 1 : 0;
    }

    unset($u['password']); // asla göndermeyelim
    $u['followers_count'] = $followers;
    $u['following_count'] = $following;
    $u['is_following']    = $isFollowing;
    $u['is_me']           = ($viewerId === $targetId) ? 1 : 0;

    return $u;
}

/* -------- Router -------- */
$pdo    = DB::connect();
$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$sub    = substr($path, strlen($base . '/user')); // "", "/search", "/follow", ...

//var_dump($method, $path, $base, $sub); // Debugging: remove in production


// Alternate pretty route support (e.g., /TrevaApp/user/search)
$acceptAltSearchPath = ($method === 'GET' && preg_match('#/user/search/?$#', $path));
$acceptAltFollowersPath = ($method === 'GET' && preg_match('#/user/followers/?$#', $path));
$acceptAltFollowingPath = ($method === 'GET' && preg_match('#/user/following/?$#', $path));


try {

    /* GET /search  (hem /user.php/search hem /user/search) */
    if (($method === 'GET' && preg_match('#/search$#', $sub)) || $acceptAltSearchPath) {
        $q = trim(strGET('q', ''));
        $page  = max(1, intGET('page', 1));
        $limit = min(50, max(1, intGET('limit', 20)));
        $offset = ($page - 1) * $limit;

        if ($q === '') {
            Logger::info("user.search q=<empty> page=$page limit=$limit");
            return Response::success(['items' => [], 'page' => $page, 'limit' => $limit]);
        }

        $like = '%' . $q . '%';
        // LIMIT/OFFSET %d'leri sprintf ile göm
        $sql = sprintf(SQL::searchUsers(), (int)$limit, (int)$offset);
        $st = $pdo->prepare($sql);
        $st->bindValue(1, $like);
        $st->bindValue(2, $like);
        $st->bindValue(3, $like);
        $st->bindValue(4, $like);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) unset($r['password']);
        Logger::info("user.search q=$q page=$page limit=$limit");
        return Response::success(['items' => $rows, 'page' => $page, 'limit' => $limit]);
    }

    /* GET ?me=1 */
    if ($method === 'GET' && isset($_GET['me'])) {
        $me = profileWithCounters($pdo, $authUserId, $authUserId);
        if (!$me) ErrorManager::throw('USER_NOT_FOUND', 404);
        Logger::info("user.me id=$authUserId");
        return Response::success($me);
    }

    /* GET ?id=... */
    if ($method === 'GET' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $profile = profileWithCounters($pdo, $authUserId, $id);
        if (!$profile) ErrorManager::throw('USER_NOT_FOUND', 404);
        Logger::info("user.get id=$id viewer=$authUserId");
        return Response::success($profile);
    }

    /* PATCH (update my profile) */
    if ($method === 'PATCH' && $sub === '') {
        $b = body();
        $allowed = ['name','surname','phone','username','bio','avatar_url','is_private'];

        // dynamic set
        $fields = [];
        $values = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $b)) {
                $v = $b[$k];
                if ($k === 'username' && $v !== null && $v !== '') {
                    $v = trim(mb_strtolower($v));
                    // unique kontrol
                    $c = $pdo->prepare(SQL::getUserByUsername());
                    $c->execute([$v]);
                    $exists = $c->fetch(PDO::FETCH_ASSOC);
                    if ($exists && (int)$exists['id'] !== $authUserId) {
                        ErrorManager::throw('USERNAME_TAKEN', 409);
                    }
                }
                $fields[] = $k;
                $values[] = $v;
            }
        }

        if (!$fields) ErrorManager::throw('NO_FIELDS_TO_UPDATE', 400);

        // SET cümlesini üret ve sorguya göm
        $set = implode(', ', array_map(fn($k) => "$k = ?", $fields));
        $sql = sprintf(SQL::updateUserDynamic(), $set);
        $values[] = $authUserId;

        $st = $pdo->prepare($sql);
        $st->execute($values);

        $profile = profileWithCounters($pdo, $authUserId, $authUserId);
        Logger::info("user.patch id=$authUserId fields=" . implode(',', $fields));
        return Response::success($profile);
    }

    /* DELETE (soft delete) */
    if ($method === 'DELETE' && $sub === '') {
        $st = $pdo->prepare(SQL::softDeleteUser());
        $st->execute([$authUserId]);

        // Takip ilişkilerini temizle (FK CASCADE hard delete'de yeterli olurdu)
        $pdo->prepare("DELETE FROM user_follow WHERE follower_id = ? OR following_id = ?")
            ->execute([$authUserId, $authUserId]);

        Logger::info("user.delete id=$authUserId");
        return Response::success(['message' => 'Account deleted']);
    }

    /* POST /follow */
    if ($method === 'POST' && preg_match('#/follow$#', $sub)) {
        $b = body();
        $targetId = (int)($b['user_id'] ?? 0);

        if ($targetId <= 0 || $targetId === $authUserId) {
            ErrorManager::throw('INVALID_USER_ID', 400);
        }

        // hedef var mı?
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
        $chk->execute([$targetId]);
        if (!$chk->fetch()) ErrorManager::throw('TARGET_NOT_FOUND', 404);

        // follow
        $st = $pdo->prepare(SQL::followInsert());
        $st->execute([$authUserId, $targetId]);

        Logger::info("user.follow $authUserId -> $targetId");
        return Response::success(['message' => 'Followed']);
    }

    /* DELETE /follow (unfollow) */
    if ($method === 'DELETE' && preg_match('#/follow$#', $sub)) {
        $b = body();
        $targetId = (int)($b['user_id'] ?? 0);
        if ($targetId <= 0) ErrorManager::throw('INVALID_USER_ID', 400);

        $st = $pdo->prepare(SQL::followDelete());
        $st->execute([$authUserId, $targetId]);

        Logger::info("user.unfollow $authUserId -/-> $targetId");
        return Response::success(['message' => 'Unfollowed']);
    }

    /* GET /followers */
   if (($method === 'GET' && preg_match('#/followers$#', $sub)) || $acceptAltFollowersPath) {
    $targetId = intGET('user_id', 0);
    if ($targetId <= 0) ErrorManager::throw('INVALID_USER_ID', 400);

    $page  = max(1, intGET('page', 1));
    $limit = min(50, max(1, intGET('limit', 20)));
    $offset = ($page - 1) * $limit;

    $sql = sprintf(SQL::followersList(), (int)$limit, (int)$offset);
    $st = $pdo->prepare($sql);
    $st->bindValue(1, $targetId, PDO::PARAM_INT);
    $st->execute();
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    Logger::info("user.followers id=$targetId page=$page limit=$limit");
    return Response::success(['items' => $items, 'page' => $page, 'limit' => $limit]);
}

    /* GET /following */
if (($method === 'GET' && preg_match('#/following$#', $sub)) || $acceptAltFollowingPath) {
    $targetId = intGET('user_id', 0);
    if ($targetId <= 0) ErrorManager::throw('INVALID_USER_ID', 400);

    $page  = max(1, intGET('page', 1));
    $limit = min(50, max(1, intGET('limit', 20)));
    $offset = ($page - 1) * $limit;

    $sql = sprintf(SQL::followingList(), (int)$limit, (int)$offset);
    $st = $pdo->prepare($sql);
    $st->bindValue(1, $targetId, PDO::PARAM_INT);
    $st->execute();
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    Logger::info("user.following id=$targetId page=$page limit=$limit");
    return Response::success(['items' => $items, 'page' => $page, 'limit' => $limit]);
}

    // Not found
    ErrorManager::throw('ENDPOINT_NOT_FOUND', 404);

} catch (Throwable $e) {
    Logger::error("user api error: " . $e->getMessage());
    var_dump($e->getMessage());
    ErrorManager::throw('SERVER_ERROR', 500);
}
