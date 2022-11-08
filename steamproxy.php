<?php declare(strict_types = 1);

$url = "https://fanyi.baidu.com/gettts?lan=zh&text=%E4%BD%A0%E5%A5%BD%20word&spd=5&source=web";

$ch = curl_init($url);
curl_setopt_array($ch,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]
);

header("Content-Type: application/octet-stream");
header("Content-Disposition: inline; filename=tts.mp3");

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36 Edg/107.0.1418.35',
]);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header){
    header($header);
    return strlen($header);
});
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $body){
    echo $body;
    return strlen($body);
});

$response = curl_exec($ch);
curl_close($ch);

