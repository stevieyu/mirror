<?php

function requestURL($url, $headers, $callbackHeader, $callbackData){
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode('\r\n', $headers),
            'follow_location' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]); 
    $stream = fopen($url, 'r', false, $context);
         
    $meta = stream_get_meta_data($stream);
    $callbackHeader($meta['wrapper_data']);
       
    while (!feof($stream)) {       
      $data = fread($stream,8192);      
      $callbackData($data);        
      flush();       
    }
    fclose($stream);
  }

// $url = 'https://api.microlink.io/?url=https%3A%2F%2Fmicrolink.io%2Fdocs%2Fapi%2Fparameters%2Fscreenshot&screenshot=true&embed=screenshot.url';
$url = 'https://httpbin.org/get';

// function getallheaders(){
//     $headers = [];
//     foreach ($_SERVER as $name => $value) {
//         if (substr($name, 0, 5) == 'HTTP_') {
//             $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
//             $headers[$name] = $value;
//         }
//     }
//     return $headers;
//  }

$headers = [];
foreach(getallheaders() as $k => $v){
    $headerKeys = implode('|', [
        '-.+?-',
        'Host',
        'Referer',
        'Dnt'
    ]);
    print_r(preg_match("/($headerKeys)/i", $k));
    if(preg_match("/($headerKeys)/i", $k)) continue;
    $headers[] = $k.': '.$v;
}
print_r($headers);

$cache = [
    'headers' => [],
    'data' => '',
];
requestURL(
    $url,
    $headers,
    function($headers) use (&$cache){
        // print_r($headers);
        $headerKeys = implode('|', [
            '^Content-Type',
            '^ETag',
            '^Last-Modified',
            '^Cache-Control'
        ]);
        foreach($headers as $header){
            if(!preg_match("/($headerKeys)/i", $header)) continue;
            header($header);
            $cache['headers'][] = $header;
        }
    }, 
    function($data) use (&$cache){
        $cache['data'] .= $data;
        // echo $data;
    }
);

// print_r($cache);