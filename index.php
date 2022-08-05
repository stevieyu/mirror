<?php
declare(strict_types=1);

require file_exists('./vendor/autoload.php') ? './vendor/autoload.php' : './vendor.phar';

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


$origin = $_COOKIE['origin'] ?? 'https://httpbin.org'; //anything

$args = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'url' => preg_replace('/\/$/', '', trim($origin.($_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO']))),
    'headers' => [
        'Accept' => $_SERVER['HTTP_ACCEPT'],
        'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
        'Cookie' => $_SERVER['HTTP_COOKIE'],
    ],
];

$startTime = microtime(true);

// if($args['method'] === 'GET'){
//     $cacheKey = hash('md5', json_encode($args));
// }

$client = new \GuzzleHttp\Client();

$stack = \GuzzleHttp\HandlerStack::create();
$stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
    new \Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy(
        new \Kevinrob\GuzzleCache\Storage\FlysystemStorage(
            new \League\Flysystem\Adapter\Local('./cache')
        )
    )
), 'cache');

$response = $client->request($args['method'], $args['url'], [
    'headers' => $args['headers'],
    'handler' => $stack
]);

$content = $response->getBody()->getContents();
if(is_string($content)) $content = str_replace($origin, '', $content);

http_response_code($response->getStatusCode());
header('Server-Timing: request;dur='. round((microtime(true) - $startTime) * 1000, 2));

$only = ['Content-Type', 'Cache-Control', 'Etag', 'Last-Modified', 'Set-Cookie'];
foreach ($response->getHeaders() as $key => $value) {
    if(!in_array($key, $only)) continue;
    header($key.': '.implode(' ', $value), true);
}

echo $content;

