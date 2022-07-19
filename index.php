<?php
require './vendor/autoload.php';

ini_set('max_execution_time', 3);

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && !in_array('no-cache', $_SERVER)) { 
    http_response_code(304);
    exit; 
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');
header("Access-Control-Request-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Headers: content-type');

if($_SERVER['REQUEST_METHOD'] == 'OPTIONS'){
    exit;
}

$startTime = microtime(true);

$client = new \GuzzleHttp\Client();

$origin = 'https://dev.to';

$args = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'],
    'headers' => [
        'Accept' => $_SERVER['HTTP_ACCEPT'],
        'User-Agent' => $_SERVER['HTTP_USER_AGENT'],
        'Cookie' => $_SERVER['HTTP_COOKIE'],
    ],
];

$stack = \GuzzleHttp\HandlerStack::create();
$stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(), 'cache');

$response = $client->request($args['method'], $origin.$args['uri'], [
    'headers' => $args['headers'],
    'handler' => $stack
]);

$content = $response->getBody()->getContents();
if(is_string($content)) $content = str_replace($origin, '', $content);

http_response_code($response->getStatusCode());
header('Server-Timing: app;dur='. round((microtime(true) - $startTime) * 1000, 2));

$only = ['Content-Type', 'Cache-Control', 'Etag', 'Last-Modified', 'Set-Cookie'];
foreach ($response->getHeaders() as $key => $value) {
    if(!in_array($key, $only)) continue;
    header($key.': '.implode(' ', $value), true);
}

echo $content;






// Send an asynchronous request.
// $request = new \GuzzleHttp\Psr7\Request('GET', 'http://httpbin.org/get');

// $promise = $client->sendAsync($request)->then(function ($response) {
//     echo 'I completed! ' . $response->getBody();
// });
// echo "promise";
// $promise->wait();
