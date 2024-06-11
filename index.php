<?php declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

require file_exists('./vendor/autoload.php') ? './vendor/autoload.php' : './vendor.phar';
//require !file_exists('./vendor.phar') ? './vendor/autoload.php' : './vendor.phar';

ini_set('max_execution_time', 3);

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && !in_array('no-cache', $_SERVER)) { 
    http_response_code(304);
    exit; 
}

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])){
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])){
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit; 
}

$startTime = microtime(true);


$args = [];
$args['method'] = $_SERVER['REQUEST_METHOD'];
$args['body'] = file_get_contents('php://input');

$args['url'] = $_GET['_url'] ?? ('https:/'.$_SERVER['REQUEST_URI']);

if(!parse_url($args['url'], PHP_URL_HOST))$args['url'] = 'http://httpbin.org/anything';

$args['headers'] = array_filter([
    'Host' => $_SERVER['HTTP_HOST'],
    'Authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
    'Accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
    'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'Cookie' => $_SERVER['HTTP_COOKIE'] ?? '',
    'Content-Type' => $_SERVER['HTTP_CONTENT_TYPE'] ?? '',
    'Referer' => str_replace($_SERVER['HTTP_HOST'], parse_url($args['url'], PHP_URL_HOST), $_SERVER['HTTP_REFERER'] ?? ''),
]);




// dd($args, $_SERVER);

$client = new \GuzzleHttp\Client();

$stack = \GuzzleHttp\HandlerStack::create();
$stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
    new \Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy(
        new \Kevinrob\GuzzleCache\Storage\FlysystemStorage(
            new \League\Flysystem\Adapter\Local('/tmp')
        )
    )
), 'cache');


$response = $client->request($args['method'], $args['url'], [
    'headers' => $args['headers'],
    'body' => $args['body'],
    // 'handler' => $stack,
    'http_errors' => false
]);

$content = $response->getBody()->getContents();
if(is_string($content)) $content = str_replace(parse_url($args['url'], PHP_URL_HOST), '', $content);

http_response_code($response->getStatusCode());
header('Server-Timing: request;dur='. round((microtime(true) - $startTime) * 1000, 2));

$only = ['Content-Type', 'Cache-Control', 'Etag', 'Last-Modified', 'Set-Cookie', 'X-Kevinrob-Cache'];
foreach ($response->getHeaders() as $key => $value) {
    if(!in_array($key, $only)) continue;
    header($key.': '.implode(' ', $value), true);
}

echo $content;

