<?php
/**
 * Router for the PHP built-in dev server, so local behaviour matches a real
 * host. The built-in server ignores .htaccess entirely — without this,
 * http://localhost:8000/data/portfolio.db downloads your whole database in
 * development while being correctly blocked in production. Better to have both
 * behave the same.
 *
 *   php -S localhost:8000 -t . tools/router.php
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Resolve against the served document root, not this script's location, so the
// router behaves correctly when pointed at a built dist/ as well as the source.
$docroot = $_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__);

// Mirrors data/.htaccess, plus the folders deploy.sh never uploads.
if (preg_match('#^/(data|migrations|tools)(/|$)#i', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden\n";
    return true;
}

// Mirrors the FilesMatch rules in pictures/ and certificates/.
if (preg_match('#^/(pictures|certificates)/.*\.(php|phar|phtml|php[0-9]?)$#i', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "403 Forbidden\n";
    return true;
}

// A request for a .php file that does not exist must 404, the way Apache would.
// The built-in server otherwise falls back to index.php and answers 200, which
// makes a deleted admin.php look like it is still responding in development.
if (preg_match('#\.php$#i', $path) && !is_file($docroot . $path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "404 Not Found\n";
    return true;
}

return false; // everything else: let the built-in server handle it
