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

function URL($raw){
    $url = parse_url($raw);
    if(!$url || empty($url['host']) || !strstr($url['host'], '.') || !count(dns_get_record($url['host'], DNS_A))) return false;

    return array_merge(
        $url,
        [
            'origin' => preg_replace('/(\w+)\/.*/', '$1', $raw),
            'raw' => $raw
        ]
    );
}

$logStore = new \SleekDB\Store("log", 'tmp', [
    "auto_cache" => true,
    "cache_lifetime" => 60 * 60 * 24 * 7,
]);
// $logStore->insert($article);
 // $logStore->findAll();


if(preg_match('/^\/(\?.*)?$/', $_SERVER['REQUEST_URI'])){
    header('Content-Type: application/json');
    echo json_encode($logStore->findAll());
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

$args['url'] = URL($_GET['_url'] ?? ('https:/'.$_SERVER['REQUEST_URI']));
if(!$args['url'] && strstr($_SERVER['REQUEST_URI'], '/http')) {
    $args['url'] = URL(preg_replace('/\/(https?:\/)\/?(.*)/', '$1/$2', $_SERVER['REQUEST_URI']));
}
if(!$args['url']) {
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
    

if(preg_match('/\.(ts|jpg|png)$/', $args['url']['raw'])){
    http_response_code(308);
    header('Location: https:/'.$args['url']['raw']);
    exit;
}


// dd($args, getallheaders());

$stack = \GuzzleHttp\HandlerStack::create();
$stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
    new \Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy(
        new \Kevinrob\GuzzleCache\Storage\FlysystemStorage(
            new \League\Flysystem\Local\LocalFilesystemAdapter('/tmp')
        ),
        60,
        new \Kevinrob\GuzzleCache\KeyValueHttpHeader(['Authorization'])
    )
), 'cache');


$client = new \GuzzleHttp\Client([
    // 'proxy' => 'http://47.96.252.3:80'
]);

$response = $client->request($args['method'], $args['url']['raw'], [
    'headers' => $args['headers'],
    'body' => $args['body'],
    'handler' => $stack,
    'http_errors' => false
]);

$content = $response->getBody()->getContents();

$log['response'] = [
    'headers' => array_map(fn($i) => implode(' ', $i), $response->getHeaders()),
    'body' => $content,
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
foreach ($response->getHeaders() as $key => $value) {
    if(!in_array($key, $only)) continue;
    header($key.': '.implode(' ', $value), true);
}

echo $content;

