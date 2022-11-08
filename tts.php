<?php declare(strict_types = 1);

$text = $_GET['w']??'本 text to speech 由百度翻译提供！';
$text = urlencode($text);
$url = "https://fanyi.baidu.com/gettts?lan=zh&text=$text&spd=5&source=web";

$dir = ($_SERVER['DOCUMENT_ROOT'] ?? getcwd()).'cache';
if(!file_exists($dir)) mkdir($dir);

$cacheKey = md5($url);

$content = '';
$filepath = $dir.'/'.$cacheKey;
$meta = [];

if(file_exists($filepath.'.json')){
    $content = file_get_contents($filepath);
    $meta = json_decode(file_get_contents($filepath.'.json'), true);
}
if(!$content){
    $content = file_get_contents($url);
    file_put_contents($filepath, $content);
    $headers = array_values(array_filter($http_response_header, function($i){
        return preg_match('/Content-Disposition|Content-Type/i', $i);
    }));
    $meta = compact('headers');
    file_put_contents($filepath.'.json', json_encode($meta));
}

foreach($meta['headers'] as $i){
    header($i);
}

echo $content;


