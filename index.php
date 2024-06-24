<?php declare(strict_types=1);

date_default_timezone_set('PRC');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);


require file_exists('./vendor/autoload.php') ? './vendor/autoload.php' : './vendor.phar';
// require !file_exists('./vendor.phar') ? './vendor/autoload.php' : './vendor.phar';

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
if(preg_match('/ico$/', $_SERVER['REQUEST_URI'])){
    exit;
}


if (!function_exists('getallheaders')){
    function getallheaders(){
       $headers = [];
       foreach ($_SERVER as $name => $value) {
           if (substr($name, 0, 5) == 'HTTP_') {
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
           }
       }
       return $headers;
    }
}

function fetch($url, $options){
    $stack = \GuzzleHttp\HandlerStack::create();
    $stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
        new \Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy(
            new \Kevinrob\GuzzleCache\Storage\FlysystemStorage(
                new \League\Flysystem\Local\LocalFilesystemAdapter(sys_get_temp_dir())
            ),
            60,
            new \Kevinrob\GuzzleCache\KeyValueHttpHeader(['Authorization'])
        )
    ), 'cache');


    $client = new \GuzzleHttp\Client([
        // 'proxy' => 'http://47.96.252.3:80'
    ]);

    $response = $client->request($options['method'], $url, [
        'headers' => $options['headers'],
        'body' => $options['body'],
        'handler' => $stack,
        'http_errors' => false,
        // 'stream' => true,
        'verify' => false,
    ]);

    return $response;
}

function URL($raw){
    $url = parse_url($raw);
    if(!$url || empty($url['host']) || !strstr($url['host'], '.') || !count(dns_get_record($url['host'], DNS_A))) return false;

    return array_merge(
        $url,
        [
            'origin' => preg_replace('/(\w+)\/.*/', '$1', $raw),
            'raw' => $raw,
            'path' => $url['path'] ?? '/',
            'query' => $url['query'] ?? '',
        ]
    );
}

$logStore = new \SleekDB\Store("log", sys_get_temp_dir(), [
    // "auto_cache" => true,
    // "cache_lifetime" => 60 * 60 * 24 * 7,
]);
// $logStore->insert($article);
 // $logStore->findAll();


if(preg_match('/^\/(\?.*)?$/', $_SERVER['REQUEST_URI'])){
    header('Content-Type: application/json');
    echo json_encode($logStore->findAll([], 10));
    exit;
}


$log = [
    'request' => [],
    'response' => []
];

$startTime = microtime(true);


$args = [];
$args['method'] = $_SERVER['REQUEST_METHOD'];
$args['body'] = file_get_contents('php://input');

$args['url'] = preg_replace('/^\//', '', $_SERVER['REQUEST_URI'] ?? '');
$args['url'] = URL(preg_match('/^https?:\/\//', $args['url']) ? $args['url'] : 'https://'.$args['url']);
if(!$args['url']) {
    $refererUrl = URL($_SERVER['HTTP_REFERER'] ?? '');
    if($refererUrl && $refererUrl['path'] && $refererUrl['path'] != '/') {
        $args['url'] = URL('https://'.preg_replace('/\/([^\/]+).*?/', '$1', $refererUrl['path']).$_SERVER['REQUEST_URI']);
    }
}
if(!$args['url']){
    $args['url'] = URL($_COOKIE['_to'].$_SERVER['REQUEST_URI']);
}
if(!$args['url']) {
    $args['url'] = URL('https://httpbin.org/anything');
}

if($_COOKIE['_to'] != $args['url']['origin']){
    setcookie('_to', $args['url']['origin'], 0, '/');
}



$args['headers'] = array_filter(
    array_merge(
        getallheaders(), 
        [
            'Origin' => $args['url']['origin'],
            'Host' => $args['url']['host'],
            'Cookie' => preg_replace('/_to=[^&]+&?/', '', $_SERVER['HTTP_COOKIE'] ?? ''),
            'Referer' => str_replace($_SERVER['HTTP_HOST'], $args['url']['host'], $_SERVER['HTTP_REFERER'] ?? $args['url']['origin']),
            'Accept-Encoding' => 'gzip, deflate',
        ]
    ), 
    fn($v, $k) => $v,
    // fn($v, $k) => $v && strstr('Authorization,Accept,User-Agent,Cookie,Content-Type,Host,Referer,Accept-Encoding,Cache-Control,Accept-Language', $k),
    ARRAY_FILTER_USE_BOTH
);


$log['request'] = $args;


$ext = pathinfo($args['url']['path'], PATHINFO_EXTENSION);
$textExt = 'css|js|json|php|html|m3u8|m3u';
$otherExt = 'ttf|woff|woff2';
if($ext && !str_contains($textExt.'|'.$otherExt, $ext)){
    http_response_code(308);
    header('Location: '.$args['url']['raw']);
    exit;
}


// dd($args, getallheaders());

$response = fetch($args['url']['raw'], $args);

$content = $response->getBody()->getContents();
$headers = array_map(fn($i) => implode(' ', $i), $response->getHeaders());

$log['response'] = [
    'headers' => $headers,
    'body' => $ext && str_contains($textExt, $ext) ? $content : '[object]',
];
$logStore->insert($log);



if(is_string($content)) {
    $content = str_replace(
        $args['url']['host'], 
        getallheaders()['Host'], 
        $content
    );
}

http_response_code($response->getStatusCode());
header('Server-Timing: request;dur='. round((microtime(true) - $startTime) * 1000, 2));

$only = ['Content-Type', 'Cache-Control', 'Etag', 'Last-Modified', 'Set-Cookie', 'X-Kevinrob-Cache'];
foreach ($headers as $key => $value) {
    if(!in_array($key, $only)) continue;
    header($key.': '.$value, true);
}

echo $content;

