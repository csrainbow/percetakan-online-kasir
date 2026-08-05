<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/data/#', $uri)) {
    http_response_code(404);
    exit;
}
return false;
