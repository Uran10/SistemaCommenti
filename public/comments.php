<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $config = require dirname(__DIR__) . '/config.php';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== $config['blog_origin']) {
        reply(403, ['error' => 'Origine non consentita.']);
    }
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $config['blog_origin']);
        header('Vary: Origin');
    }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204);
        exit;
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST, OPTIONS');
        reply(405, ['error' => 'Metodo non consentito.']);
    }
    $input = [];
    if ($method === 'POST') {
        // Richiedere JSON impedisce l'invio tramite form cross-site (anche senza cookie).
        if ($origin !== $config['blog_origin']) {
            reply(403, ['error' => 'Origine richiesta.']);
        }
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            reply(415, ['error' => 'È richiesto application/json.']);
        }
        $raw = file_get_contents('php://input', false, null, 0, 16385);
        if ($raw === false || strlen($raw) > 16384) {
            reply(413, ['error' => 'Richiesta troppo grande.']);
        }
        try {
            $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            reply(400, ['error' => 'JSON non valido.']);
        }
        if (!is_array($input)) reply(400, ['error' => 'Richiesta non valida.']);
    }
    $path = $method === 'GET' ? ($_GET['post'] ?? '') : ($input['post'] ?? '');
    $listCounts = $method === 'GET' && ($_GET['view'] ?? '') === 'counts';
    // Il client usa pathname: ID ASCII stabile, senza query string o frammenti.
    if (!$listCounts && (!is_string($path) || !preg_match('~^/[A-Za-z0-9/_.%\-]{0,511}$~D', $path))) {
        reply(422, ['error' => 'Percorso articolo non valido.']);
    }
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($listCounts) {
        $query = $db->query("SELECT post_path, COUNT(*) FROM comments WHERE status = 'approved' GROUP BY post_path");
        reply(200, ['counts' => (object) $query->fetchAll(PDO::FETCH_KEY_PAIR)]);
    }
    if ($method === 'GET') {
        $query = $db->prepare("SELECT id, author, body, created_at FROM comments WHERE post_path = ? AND status = 'approved' ORDER BY id DESC LIMIT 100");
        $query->execute([$path]);
        reply(200, ['comments' => $query->fetchAll()]);
    }
    foreach (['author', 'body', 'website'] as $field) {
        if (isset($input[$field]) && !is_string($input[$field])) reply(422, ['error' => 'Campi non validi.']);
    }
    if (!empty($input['website'])) reply(422, ['error' => 'Invio non valido.']);
    $author = trim($input['author'] ?? '');
    $body = trim($input['body'] ?? '');
    if (!mb_check_encoding($author . $body, 'UTF-8') || mb_strlen($author) < 1 || mb_strlen($author) > 80 || mb_strlen($body) < 3 || mb_strlen($body) > 5000) {
        reply(422, ['error' => 'Inserisci un nome (1–80 caratteri) e un commento (3–5000 caratteri).']);
    }
    $hash = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $config['password']);
    $now = time();
    $db->beginTransaction();
    $query = $db->prepare('INSERT IGNORE INTO comment_rate_limits (client_hash, last_submission) VALUES (?, 0)');
    $query->execute([$hash]);
    $query = $db->prepare('SELECT last_submission FROM comment_rate_limits WHERE client_hash = ? FOR UPDATE');
    $query->execute([$hash]);
    if ($now - (int) $query->fetchColumn() < 60) {
        $db->rollBack();
        header('Retry-After: 60');
        reply(429, ['error' => 'Attendi un minuto prima di inviare un altro commento.']);
    }
    $query = $db->prepare('UPDATE comment_rate_limits SET last_submission = ? WHERE client_hash = ?');
    $query->execute([$now, $hash]);
    $status = $config['moderation'] ? 'pending' : 'approved';
    $query = $db->prepare('INSERT INTO comments (post_path, author, body, status) VALUES (?, ?, ?, ?)');
    $query->execute([$path, $author, $body, $status]);
    $db->commit();
    reply(201, ['message' => $status === 'pending' ? 'Commento ricevuto: sarà pubblicato dopo la moderazione.' : 'Commento pubblicato.']);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('Comments API: ' . $e->getMessage());
    reply(500, ['error' => 'Servizio temporaneamente non disponibile.']);
}
