<?php declare(strict_types = 1);

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && !in_array('no-cache', $_SERVER)) {
    http_response_code(304);
    exit;
}

$text = $_GET['w']??'本 text to speech 由百度翻译提供！';
$text = urlencode($text);
$url = "https://fanyi.baidu.com/gettts?lan=zh&text=$text&spd=5&source=web";

$dir = ($_SERVER['DOCUMENT_ROOT'] ?? getcwd()).'cache';
if(!file_exists($dir)) mkdir($dir);

$cacheKey = md5($url);

$filepath = $dir.'/'.$cacheKey;

$content = '';
$metapath = $filepath.'.json';
$meta = [];

if(file_exists($metapath)){
    $content = file_get_contents($filepath);
    $meta = json_decode(file_get_contents($metapath), true);
}
if(!$content){
    $content = file_get_contents($url);
    file_put_contents($filepath, $content);
    $headers = array_values(array_filter($http_response_header, function($i){
        return preg_match('/Content-Disposition|Content-Type/i', $i);
    }));
    $meta = compact('headers');
    file_put_contents($metapath, json_encode($meta));
}

foreach($meta['headers'] ?? [] as $i){
    header($i);
}

header('Cache-Control: ' . 'public, max-age=31536000, s-maxage=31536000, immutable');

echo $content;


