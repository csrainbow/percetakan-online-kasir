<?php
// Router untuk php -S (server built-in) — melindungi file sensitif
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

$protected = [
    '/database.sqlite',
    '/kasir/data',
];

foreach ($protected as $p) {
    if ($uri === $p || str_starts_with($uri, $p . '/')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo '403 Forbidden';
        return true;
    }
}

return false;
