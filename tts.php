<?php declare(strict_types = 1);

if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && !in_array('no-cache', $_SERVER)) {
    http_response_code(304);
    exit;
}

$text = $_GET['w']??'本 text to speech 由百度翻译提供！';
$text = urlencode($text);

//curl 'https://fanyi.qq.com/api/tts?platform=PC_Website&lang=zh&text=%E7%BF%BB%E8%AF%91%E5%90%9B%20%E5%85%A8%E6%96%B0%E4%BA%BA%E5%B7%A5%E6%99%BA%E8%83%BD%E7%BF%BB%E8%AF%91%EF%BC%8C%20%E5%8F%A5%E5%AD%90%E3%80%81%E6%96%87%E7%AB%A0%E3%80%81%E8%AE%BA%E6%96%87%E3%80%81%E8%B5%84%E6%96%99%E7%BF%BB%E8%AF%91%E9%A6%96%E9%80%89&guid=5cf6771f-97b2-4240-b26b-6c45ef901d9e'   -H 'Referer: https://fanyi.qq.com/'
//https://tts.youdao.com/fanyivoice?word=%E6%B5%8B%E8%AF%95%E6%B5%8B%E8%AF%95&le=zh&keyfrom=speaker-target
//https://fanyi.sogou.com/reventondc/synthesis?text=%E4%BD%A0%E5%A5%BD%E4%B8%96%E7%95%8C&speed=1&lang=zh-CHS&speaker=1
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
    $content = file_get_contents($url);
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
    file_put_contents($filepath, $content);
    $headers = array_values(array_filter($http_response_header, function($i){
        return preg_match('/Content-Disposition|Content-Type/i', $i);
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

header('Cache-Control: ' . 'public, max-age=31536000, s-maxage=31536000, immutable');

echo $content;


