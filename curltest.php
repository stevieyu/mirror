<?php

$ch = curl_init("https://www.cloudflare.com/cdn-cgi/trace");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
$response = curl_exec($ch);

if ($response === false) {
    var_dump(curl_error($ch));
} else {
    var_dump($response);
}

curl_close($ch);
