<?php declare(strict_types = 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$url = $_GET['url'] ?? $_SERVER['QUERY_STRING'] ?? $_SERVER['PATH_INFO'] ?? 'http://httpbin.org/anything';
if(!preg_match('/^http(s)?:\\/\\/.+/', $url)) $url = 'http://'.$url;

$cache_key = hash('md5', $url);
if (!empty($_SERVER['HTTP_USER_AGENT']) && preg_match('/polyfill/', $url)) {
    $cache_key.= hash('md5', $_SERVER['HTTP_USER_AGENT']);
}
$file = '/tmp/static-' . $cache_key;

function output_headers(string $file) {
  $info = json_decode(file_get_contents($file . '.json'), true);

  http_response_code($info['http_code']);
  header('Access-Control-Allow-Origin: *');
  header('Content-Type: ' . $info['content_type']);
  header('Content-Length: ' . filesize($file));
  header('Cache-Control: ' . 'public, max-age=31536000, s-maxage=31536000, immutable');
  header('ETag: ' . 'W/"static"');
  //header('Content-Length: ' . $info['download_content_length']);
}

if (file_exists($file) && file_exists($file . '.json') && empty($_COOKIE['debug'])) {
  header('X-Cache-Key: '.$cache_key);
  output_headers($file);
  readfile($file);
  exit();
}

$headers = getallheaders();

$output = fopen($file, 'w');

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_VERBOSE, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_ENCODING, '');
curl_setopt($curl, CURLOPT_HTTPHEADER, array_map(function($value, $key) {
  if (!in_array($key, array('Origin', 'Referer', 'Connection', 'Host'))) {
    return $key . ':' . $value;
  }
}, $headers, array_keys($headers)));
curl_setopt($curl, CURLOPT_FILE, $output);
curl_exec($curl);
file_put_contents($file . '.json', json_encode(curl_getinfo($curl), JSON_PRETTY_PRINT));
curl_close($curl);
fclose($output);

output_headers($file);
readfile($file);
exit();