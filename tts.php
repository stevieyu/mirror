<?php declare(strict_types = 1);

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && !in_array('no-cache', $_SERVER)) {
    http_response_code(304);
    exit;
}

$text = $_GET['w']??'本 text to speech 由百度翻译提供！';
$text = urlencode($text);


/*
const tfetspktok = async () => {
    if(cache.get('tfetspktok')) return cache.get('tfetspktok');

    const html = await (await fetch('https://cn.bing.com/translator?text&from=en&to=zh-Hans')).text()
    const [,ig] = html.match(/IG:"(\w+)"/) || []
    const [,key, token] = html.match(/params_RichTranslateHelper = \[(\d+),"([\w-]+)"/) || []

    if(!ig || !key || !token) throw new Error('fetch id,key,token fail')

    const json = await (await fetch(`https://cn.bing.com/tfetspktok?isVertical=1&&IG=${ig}&IID=translator.5023.1`, {
        headers: {
            "content-type": "application/x-www-form-urlencoded",
        },
        body: `&token=${token}&key=${key}`,
        method: "POST",
    })).json()

    const ms = json.expiryDurationInMS
    delete json.expiryDurationInMS
    cache.set('tfetspktok', json, ms)

    return json
}

export default async (text = '本 text to speech 由 Bing 翻译提供') => {
    const {region, token} = await tfetspktok()

    const blob = await (await fetch(`https://${region}.tts.speech.microsoft.com/cognitiveservices/v1?`, {
        headers: {
            "authorization": `Bearer ${token}`,
            "content-type": "application/ssml+xml",
            "x-microsoft-outputformat": "audio-16khz-32kbitrate-mono-mp3"
        },
        body: `<speak version='1.0' xml:lang='zh-CN'><voice xml:lang='zh-CN' xml:gender='Female' name='zh-CN-XiaoxiaoNeural'><prosody rate='-20.00%'>${text}</prosody></voice></speak>`,
        method: "POST",
    })).blob();

    return URL.createObjectURL(blob)
}
*/

// $dir = ($_SERVER['DOCUMENT_ROOT'] ?? '' ?: getcwd()).'/cache';
$dir = '/tmp/tts';
if(!file_exists($dir)) mkdir($dir);

$cacheKey = md5($text);

$filepath = $dir.'/'.$cacheKey;

$content = '';
$metapath = $filepath.'.json';
$meta = [];

// if(file_exists($metapath)){
//     $content = file_get_contents($filepath);
//     $meta = json_decode(file_get_contents($metapath), true);
// }
if(!$content){
    $source = 'baidu';
    $url = "https://fanyi.baidu.com/gettts?lan=zh&text=$text&spd=5&source=web";
    $content = file_get_contents($url, false, stream_context_create([
        'http' => [
            'header' => implode("\r\n", [
                'Referer: https://fanyi.baidu.com/',
                'Cookie: BAIDUID=94C8EA5CF28448327B544714078070BA:FG=1; BAIDUID_BFESS=94C8EA5CF28448327B544714078070BA:FG=1',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36 Edg/107.0.1418.62',
            ])
        ]
    ]));
    if(!$content) {
        $source = 'youdao';
        $url = "https://tts.youdao.com/fanyivoice?word=$text&le=zh&keyfrom=speaker-target";
        $content = file_get_contents($url);
    }
    if(!$content) {
        $source = 'sogou';
        $url = "https://fanyi.sogou.com/reventondc/synthesis?text=$text&speed=1&lang=zh-CHS&speaker=1";
        $content = file_get_contents($url);
    }
    if(!$content) {
        $source = 'qq';
        $url = "https://fanyi.qq.com/api/tts?platform=PC_Website&lang=zh&text=$text";
        $token = file_get_contents('https://fanyi.qq.com/api/reauth12f', false, stream_context_create([
            'http' => [
                'method'=> 'POST',
            ]
        ]));
        if($token) {
            $token = json_decode($token);
            $content = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'header' => implode("\r\n", [
                        'Referer: https://fanyi.qq.com/',
                        "Cookie: qtv=$token->qtv;qtk=$token->qtk"
                    ])
                ]
            ]));
        }
        if(strlen($content) <= 6400) $content = '';
    }
    file_put_contents($filepath, $content);
    $headers = array_values(array_filter($http_response_header, function($i){
        return preg_match('/Content-Type/i', $i);
    }));
    $meta = compact('headers');
    $meta['headers'][] = "X-Source: $source";
    file_put_contents($metapath, json_encode($meta));
}else{
    header('X-Cache: ' . $cacheKey);
}


foreach($meta['headers'] ?? [] as $i){
    header($i);
}

header('Cache-Control: public, max-age=31536000, s-maxage=31536000, immutable');

echo $content;


