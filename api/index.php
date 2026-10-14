<?php
/**
 * Oroma TV mobile API — front controller.
 *
 *   GET  /api/ping
 *   GET  /api/v1/home
 *   GET  /api/v1/live
 *   GET  /api/v1/categories
 *   GET  /api/v1/articles            ?page &per_page &category &q &tag
 *   GET  /api/v1/articles/{slug}
 *   POST /api/v1/articles/{slug}/comments
 *   GET  /api/v1/shows               ?page &per_page
 *   GET  /api/v1/shows/{id}
 *   POST /api/v1/contact
 *
 * Works with the .htaccess rewrite (clean URLs) and also as /api/index.php/v1/home if rewrites are off.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/routes.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { // CORS preflight from the Expo web preview
    api_headers();
    http_response_code(204);
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[oroma-api] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    global $is_local;
    json_error(!empty($is_local) ? 'Server error: ' . $e->getMessage() : 'The server had a problem. Please try again shortly.', 500);
});

$route  = trim((string) ($_GET['route'] ?? ($_SERVER['PATH_INFO'] ?? '')), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    ['GET',  '#^ping$#',                             static fn() => route_ping()],
    ['GET',  '#^v1/home$#',                          static fn() => route_home()],
    ['GET',  '#^v1/live$#',                          static fn() => route_live()],
    ['GET',  '#^v1/categories$#',                    static fn() => route_categories()],
    ['GET',  '#^v1/articles$#',                      static fn() => route_articles()],
    ['GET',  '#^v1/articles/([A-Za-z0-9-]+)$#',      static fn($m) => route_article($m[1])],
    ['POST', '#^v1/articles/([A-Za-z0-9-]+)/comments$#', static fn($m) => route_comment_create($m[1])],
    ['POST', '#^v1/live/ping$#',                  static fn() => route_live_ping()],
    ['GET',  '#^v1/radio$#',                      static fn() => route_radio()],
    ['GET',  '#^v1/schedule$#',                   static fn() => route_schedule()],
    ['GET',  '#^v1/programmes/(\d+)$#',           static fn($m) => route_programme((int) $m[1])],
    ['GET',  '#^v1/shows$#',                         static fn() => route_shows()],
    ['GET',  '#^v1/shows/(\d+)$#',                   static fn($m) => route_show((int) $m[1])],
    ['POST', '#^v1/contact$#',                       static fn() => route_contact()],
];

$pathMatched = false;
foreach ($routes as [$verb, $pattern, $handler]) {
    if (preg_match($pattern, $route, $m)) {
        $pathMatched = true;
        if ($verb === $method) {
            $handler($m);
            exit;
        }
    }
}

if ($pathMatched) {
    json_error('Method not allowed.', 405);
}
json_error('Not found.', 404);
