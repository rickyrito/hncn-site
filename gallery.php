<?php
require_once __DIR__ . '/fb-config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Flush forçado: apaga cache e reconstrói
if (isset($_GET['flush'])) {
    @unlink(CACHE_FILE);
}

// Servir cache se ainda válida
if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
    readfile(CACHE_FILE);
    exit;
}

// ── Auto-renovação do token ──────────────────────────────────────────────────
function fb_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ? json_decode($res, true) : null;
}

function get_valid_token() {
    $token_file = TOKEN_FILE;

    // Carregar token guardado
    if (file_exists($token_file)) {
        $data = json_decode(file_get_contents($token_file), true);
        $token      = isset($data['token'])      ? $data['token']      : null;
        $expires_at = isset($data['expires_at']) ? $data['expires_at'] : 0;
    } else {
        // Primeira execução: usar token do config
        $token      = FB_USER_TOKEN;
        $expires_at = time() + 5184000; // ~60 dias
    }

    // Renovar se faltar menos de 7 dias para expirar
    if ($token && ($expires_at - time()) < 7 * 24 * 3600) {
        $url = "https://graph.facebook.com/oauth/access_token"
             . "?grant_type=fb_exchange_token"
             . "&client_id=" . FB_APP_ID
             . "&client_secret=" . FB_APP_SECRET
             . "&fb_exchange_token=" . urlencode($token);

        $result = fb_get($url);
        if ($result && isset($result['access_token'])) {
            $token      = $result['access_token'];
            $expires_in = isset($result['expires_in']) ? (int)$result['expires_in'] : 5184000;
            $expires_at = time() + $expires_in;
        }
    }

    // Guardar token actualizado
    file_put_contents($token_file, json_encode([
        'token'      => $token,
        'expires_at' => $expires_at,
    ]));

    return $token;
}

$token = get_valid_token();
// ────────────────────────────────────────────────────────────────────────────

$albums_url = "https://graph.facebook.com/v21.0/" . FB_PAGE_ID
    . "/albums?access_token={$token}&fields=id,name,count&limit=20";

$albums_data = fb_get($albums_url);

if (!$albums_data) {
    echo json_encode(['error' => 'Não foi possível contactar a API do Facebook.']);
    exit;
}
if (isset($albums_data['error'])) {
    echo json_encode(['error' => $albums_data['error']]);
    exit;
}
// Álbuns a ignorar
$ignore = ['timeline photos', 'cover photos', 'profile pictures', 'videos', 'untitled album'];

$gallery = [];

foreach ($albums_data['data'] as $album) {
    if (in_array(strtolower($album['name']), $ignore)) continue;
    if ((isset($album['count']) ? $album['count'] : 0) === 0) continue;

    $photos_url = "https://graph.facebook.com/v21.0/" . $album['id']
        . "/photos?access_token={$token}&fields=images,name&limit=50";

    $photos_data = fb_get($photos_url);
    if (!$photos_data || empty($photos_data['data'])) continue;

    $photos = [];
    foreach ($photos_data['data'] as $photo) {
        $images = isset($photo['images']) ? $photo['images'] : array();
        if (empty($images)) continue;

        usort($images, function($a, $b) { return $b['width'] - $a['width']; });

        $large = $images[0];
        $thumb = $large;
        foreach ($images as $img) {
            if ($img['width'] <= 720) { $thumb = $img; break; }
        }

        $photos[] = [
            'src'     => $large['source'],
            'thumb'   => $thumb['source'],
            'caption' => isset($photo['name']) ? $photo['name'] : '',
        ];
    }

    if (empty($photos)) continue;

    $gallery[] = [
        'id'     => $album['id'],
        'name'   => $album['name'],
        'count'  => count($photos),
        'photos' => $photos,
    ];
}

$json = json_encode($gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(CACHE_FILE, $json);
echo $json;
