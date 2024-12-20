<?php declare(strict_types=1);

date_default_timezone_set('PRC');

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
error_reporting(E_ERROR | E_PARSE);


require file_exists('./vendor/autoload.php') ? './vendor/autoload.php' : './vendor.phar';
// require file_exists('./vendor.phar') ? './vendor.phar' : './vendor/autoload.php';


ini_set('max_execution_time', 3);


header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit;
}
if (preg_match('/ico$/', $_SERVER['REQUEST_URI'] ?? '')) {
    exit;
}
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && !in_array('no-cache', $_SERVER)) {
    http_response_code(304);
    exit;
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

class CustomCacheStrategy extends \Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy
{
    /**
     * @param \Psr\Http\Message\RequestInterface  $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function cache(\Psr\Http\Message\RequestInterface $request, \Psr\Http\Message\ResponseInterface $response)
    {

        if ($response->getStatusCode() >= 300) {
            return false;
        }
        return parent::cache($request, $response);
    }
}

function fetch($url, $options)
{

    $ttl = 60 * 60;

    // $cache_dir = sys_get_temp_dir();
    $cache_dir = __DIR__ . '/guzzle-cache';

    $stack = \GuzzleHttp\HandlerStack::create();
    $stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
        new CustomCacheStrategy(
            new \Kevinrob\GuzzleCache\Storage\FlysystemStorage(
                new \League\Flysystem\Local\LocalFilesystemAdapter($cache_dir)
            ),
            $ttl,
            new \Kevinrob\GuzzleCache\KeyValueHttpHeader(['Authorization'])
        )
    ), 'cache');


    $client = new \GuzzleHttp\Client();

    return $client->request($options['method'], $url, [
        'headers' => $options['headers'],
        'body' => $options['body'],
        'form_params' => $options['form_params'],
        'handler' => $stack,
        'http_errors' => false,
        // 'stream' => true,
        'verify' => false,
        //http://demo.spiderpy.cn/get/
        // 'proxy' => 'http://177.12.118.160:80'
    ]);
}
function setCookieFromHeader(string $cookieHeader): void
{
    $parts = explode(';', $cookieHeader);
    $cookieParams = array_shift($parts); // 获取name=value对
    $cookieArray = match (true) {
        str_contains($cookieParams, '=') => explode('=', $cookieParams, 2),
        default => ['', $cookieParams],
    };

    $cookieArray += [
        'name' => $cookieArray[0],
        'value' => $cookieArray[1],
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => false,
        'samesite' => null,
    ];
    unset($cookieArray[0]);
    unset($cookieArray[1]);

    foreach ($parts as $part) {
        $part = trim($part);
        if (str_starts_with($part, 'expires=')) {
            $cookieArray['expires'] = strtotime(substr($part, 8));
        } elseif (str_starts_with($part, 'path=')) {
            $cookieArray['path'] = substr($part, 5);
        } elseif (str_starts_with($part, 'domain=')) {
            $cookieArray['domain'] = substr($part, 7);
        } elseif ($part === 'secure') {
            $cookieArray['secure'] = true;
        } elseif ($part === 'httponly') {
            $cookieArray['httponly'] = true;
        } elseif (str_starts_with($part, 'samesite=')) {
            $cookieArray['samesite'] = substr($part, 9);
        }
    }

    setcookie(
        $cookieArray['name'],
        $cookieArray['value'],
        $cookieArray['expires'],
        $cookieArray['path'],
        $cookieArray['domain'],
        $cookieArray['secure'],
        $cookieArray['httponly']
    );

    if ($cookieArray['samesite'] !== null) {
        setcookie(
            $cookieArray['name'],
            $cookieArray['value'],
            [
                'expires' => $cookieArray['expires'],
                'path' => $cookieArray['path'],
                'domain' => $cookieArray['domain'],
                'secure' => $cookieArray['secure'],
                'httponly' => $cookieArray['httponly'],
                'samesite' => $cookieArray['samesite'],
            ]
        );
    }
}
function URL($raw)
{

    $url = parse_url($raw);
    if (!$url || empty($url['host']) || !preg_match('/\w+\.\w+$/', $url['host']) || !checkdnsrr($url['host'], 'A')){
        return false;
    }

    return array_merge(
        $url,
        [
            'origin' => preg_replace('/(\w+)\/.*/', '$1', $raw),
            'raw' => $raw,
            'path' => $url['path'] ?? '/',
            'query' => $url['query'] ?? '',
            'ext' => pathinfo($url['path'] ?? '/', PATHINFO_EXTENSION),
            'dir' => pathinfo($url['path'] ?? '/', PATHINFO_DIRNAME),
            'filename' => pathinfo($url['path'] ?? '/', PATHINFO_BASENAME),
        ]
    );
}
function filterM3U8NotSort($content)
{
    $prev = 0;
    $isSort = true;
    $matches = array_values(preg_grep('/\w+.ts/', explode("\n", $content)));

    $dirs_count = [];

    $ads = array_values(array_filter($matches, function ($i, $idx) use (&$prev, &$isSort, &$dirs_count) {
        $current = intval(preg_replace('/.*?(\d+).ts/', '$1', $i));
        $isNotSort = ($current - $prev) != 1;

        $dir = (string)pathinfo($i, PATHINFO_DIRNAME);
        if ($dir && $dir != '.') {
            if (empty($dirs_count[$dir])) {
                $dirs_count[$dir] = 0;
            }
            $dirs_count[$dir] += 1;
        }

        if ($idx == 0 || !$isNotSort) {
            $prev = $current;
        }

        if ($idx == 1 && $isNotSort) {
            $isSort = false;
        }

        if (!$isSort) {
            return false;
        }

        return $idx > 0 && $isNotSort;
    }, ARRAY_FILTER_USE_BOTH));



    if (count($ads)) {
        $regex = '/.*?\s' . generateRegexpFromStrings($ads) . '\s/';
        $content = preg_replace($regex, '', $content);
    } elseif (count($dirs_count) >= 2) {
        asort($dirs_count);
        $remove_dir = array_key_first($dirs_count);
        $remove_dir = preg_replace(['/\//', '/\./'], ['\/', '\.'], $remove_dir);
        $content = preg_replace('/#EXTINF.*?\s' . $remove_dir . '.*?\s/', '', $content);
    }
    $content = preg_replace('/(#EXT-X-DIS.*?\s){2,}/', '$1', $content);

    return $content;
}
function generateRegexpFromStrings($array)
{
    $baseString = $array[0];
    $commonString = $baseString;

    foreach ($array as $str) {
        for ($i = 0; $i < strlen($baseString) && $i < strlen($str); $i++) {
            if ($baseString[$i] != $str[$i]) {
                $commonString[$i] = '*';
            }
        }
    }

    $regexp_str = preg_replace_callback('/\*+/', function ($match) {
        $len = strlen($match[0]);
        return '\w' . ($len > 1 ? '{' . $len . '}' : '');
    }, $commonString);

    $regexp_str = preg_replace(['/\//', '/\./'], ['\/', '\.'], $regexp_str);

    return $regexp_str;
}

function m3u8Handler($content, $url, $host)
{
    if (preg_match('/http[^#$]+\.m3u8/', $content)) {
        $content = preg_replace('{https:\\\/([^#$]+\.m3u8)}', 'https:\/\/' . $host . '$1', $content);
        return $content;
    }

    if ($url['ext'] == 'm3u8') {
        //统一内容路径, 绝对转相对
        $content = preg_replace('/(\s)' . preg_replace('/([.\/])/', '\\\$1', $url['dir']) . '\//', '$1', $content);

        if (preg_match('/\.ts/', $content)) {

            $content = preg_replace([
                //移除绝对路径
                '/.*?\s\/\S+\s/',
                //带key的无分片块
                '/(#EXT-X-DIS.*?\s(#EXT-X-KEY.*?\s)+){2,}/',
            ], '', $content);

            $content = filterM3U8NotSort($content);

            //相对转绝对
            $content = preg_replace('/(\s)(\w+\.ts)/', '$1' . $url['origin'] . $url['dir'] . '/$2', $content);
            $content = preg_replace('/(\w+\.key)/', $url['origin'] . $url['dir'] . '/$1', $content);
        }
        return $content;
    }

    return $content;
}

function getIp()
{
    $ip_sources = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ip_sources as $source) {
        if (!empty($_SERVER[$source]) && strcasecmp($_SERVER[$source], "unknown") !== 0) {
            return $_SERVER[$source];
        }
    }

    return "unknown";
}



// $cache_dir = sys_get_temp_dir();
$cache_dir = __DIR__.'/sleekdb-db';
$logStore = new \SleekDB\Store("log", $cache_dir, [
    // "auto_cache" => true,
    // "cache_lifetime" => 60 * 60 * 24 * 7,
]);
// $logStore->insert($article);
// $logStore->findAll();
$logStoreFind = $logStore->findAll(['_id' => 'asc'], 6);


if (preg_match('/^\/(\?.*)?$/', $_SERVER['REQUEST_URI'] ?? '') && (empty($_COOKIE['_to']) || !str_contains($_SERVER['HTTP_REFERER'] ?? '', $_COOKIE['_to'] ?? ''))) {
    header('Content-Type: application/json');
    echo json_encode($logStore->findAll(['_id' => 'desc'], 5));
    exit;
} else if (count($logStoreFind) >= 5) {
    $del_id = $logStoreFind[0]['_id'] ?? '';
    if ($del_id)
        $logStore->deleteById($del_id);
}


$log = [
    'datetime' => date('Y-m-d H:i:s'),
    'ip' => getIp(),
    'request' => [],
    'response' => []
];
$startTime = microtime(true);


$args = [];
$args['method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$args['body'] = file_get_contents('php://input');
$args['form_params'] = $_POST;

$args['url'] = preg_replace('/^\//', '', $_SERVER['REQUEST_URI'] ?? '');
$args['url'] = preg_match('/^https?:\/\//', $args['url']) ? $args['url'] : 'https://' . $args['url'];
$args['url'] = URL($args['url']);
if (!$args['url'] && !empty($_SERVER['HTTP_REFERER'])) {
    $refererUrl = URL($_SERVER['HTTP_REFERER'] ?? '');

    if ($refererUrl && $refererUrl['path'] && $refererUrl['path'] != '/') {
        $refererUrl = 'https://' . preg_replace('/\/([^\/]+)(?:.*)?/', '$1', $refererUrl['path']) . $_SERVER['REQUEST_URI'];
        $args['url'] = URL($refererUrl);
    }
}
$cookie_to = $_COOKIE['_to'] ?? '';
if (!$args['url'] && $cookie_to) {
    $args['url'] = URL('https://' . $cookie_to . $_SERVER['REQUEST_URI']);
}
if ($args['url'] && (!$cookie_to || $cookie_to != $args['url']['host'])) {
    setcookie('_to', $args['url']['host'], 0, '/');
}

if (!$args['url']) {
    $args['url'] = URL('https://httpbin.org/anything');
}




$args['headers'] = array_filter(
    array_merge(
        getallheaders(),
        [
            // 'Origin' => $args['url']['origin'],
            // 'Host' => $args['url']['host'],
            'Cookie' => preg_replace('/_to=[^&]+&?/', '', $_SERVER['HTTP_COOKIE'] ?? ''),
            'Referer' => str_replace($_SERVER['HTTP_HOST'] ?? '', $args['url']['host'], $_SERVER['HTTP_REFERER'] ?? $args['url']['origin']),
            'Accept-Encoding' => 'gzip, deflate',
        ]
    ),
    fn($v, $k) => $v,
    // fn($v, $k) => $v && str_contains('Authorization,Accept,User-Agent,Cookie,Content-Type,Host,Referer,Accept-Encoding,Cache-Control,Accept-Language', $k),
    ARRAY_FILTER_USE_BOTH
);
unset($args['headers']['Host']);
unset($args['headers']['Origin']);
if (!empty($args['form_params'])) {
    unset($args['headers']['Content-Type']);
} else {
    $args['form_params'] = null;
}


$log['request'] = $args;
$log['request']['url'] = $args['url']['raw'];




$jumpExts = 'ts|zip|gz|bz2|rar|7z|tar|xz|mp4|mp3';
if ($args['url']['ext'] && str_contains($jumpExts, $args['url']['ext'])) {
    http_response_code(301);
    header('Location: ' . $args['url']['raw']);
    exit;
}

$txtExts = 'css|js|mjs|json|php|html|m3u8|m3u';
$noTxtExts = 'ttf|woff|woff2';

$isContentTxt = $args['url']['ext'] && str_contains($txtExts, $args['url']['ext']);


if ($args['method'] == 'GET' && $args['url']['ext'] && !$isContentTxt && !str_contains($noTxtExts, $args['url']['ext'])) {
    $response = fetch($args['url']['raw'], array_merge($args, [
        'method' => 'HEAD',
    ]));
    $isContentTxt = preg_match('/text\/|\/json|\/javascript/', implode('|', $response->getHeader('Content-Type')));
    if(!$isContentTxt) {
        http_response_code(301);
        header('Location: ' . $args['url']['raw']);
        exit;
    }
}


// dd($args, getallheaders());

$response = fetch($args['url']['raw'], $args);

$isContentTxt = preg_match('/text\/|\/json|\/javascript/', implode('|', $response->getHeader('Content-Type')));
if(!$isContentTxt) {
    http_response_code(301);
    header('Location: ' . $args['url']['raw']);
    exit;
}

$content = $response->getBody()->getContents();
// $headers = array_map(fn($i) => implode(' ', $i), $response->getHeaders());

$log['response'] = [
    'status' => $response->getStatusCode(),
    'headers' => $response->getHeaders(),
    'body' => $isContentTxt ? $content : '[object]',
];
$log['ms'] = round((microtime(true) - $startTime), 2);
$logStore->insert($log);



if (!$args['url']['ext'] || $isContentTxt) {
    if (str_contains($content, 'href=') || str_contains($content, 'src=')) {
        $content = preg_replace('/((?:href|src)=[\'"])(?:https?:)?\/\/' . str_replace('.', '\.', $args['url']['host']) . '/', '$1', $content);
    }
    $content = preg_replace(
        '/\/' . $args['url']['host'] . '/',
        '/' . (getallheaders()['Host'] ?? ''),
        $content
    );
    if (preg_match('/export\s/', $content)) {
        $content = preg_replace(
            '/"(\/.*?m?js)/',
            '"/' . $args['url']['host'] . '$1',
            $content
        );
    }

    $content = m3u8Handler($content, $args['url'], 'proxy-mdjhpniduu.cn-shenzhen.fcapp.run');
}

http_response_code($response->getStatusCode());
header('Server-Timing: request;dur=' . round((microtime(true) - $startTime) * 1000, 2));


foreach ($response->getHeader('Set-Cookie') as $key => $value) {
    setCookieFromHeader($value);
}


$only = ['Content-Type', 'Cache-Control', 'Etag', 'Last-Modified', 'X-Kevinrob-Cache'];
foreach ($only as $key) {
    foreach ($response->getHeader($key) as $v) {
        header($key . ': ' . $v);
    }
}

echo $content;