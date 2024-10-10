<?php
function getCurrentUrl() {
    // 检查请求是否使用 HTTPS
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    
    // 获取主机名
    $host = $_SERVER['HTTP_HOST'];
    
    // 获取请求的 URI
    $uri = $_SERVER['REQUEST_URI'];
    
    // 返回完整的 URL
    return $protocol . $host . $uri;
}
function performanceTest() {
    $startTime = microtime(true);
    $endTime = $startTime + ini_get('max_execution_time') - 10;
    // $endTime = $startTime + 5;
    $count = 0;

    while (microtime(true) < $endTime) {
        // 大字符串操作
        $longString = str_repeat('abcdefghijklmnopqrstuvwxyz', 1000); // 生成一个长字符串
        $length = strlen($longString); // 获取字符串长度
        $substring = substr($longString, 0, 100); // 截取子字符串
        $reversed = strrev($substring); // 反转子字符串

        // 复杂正则表达式
        $pattern = '/[a-z]{5,10}/'; // 匹配5到10个字母的子字符串
        preg_match_all($pattern, $longString, $matches); // 匹配所有符合条件的子字符串
        $matchCount = count($matches[0]); // 统计匹配的数量
        $firstMatch = reset($matches[0]); // 获取第一个匹配项

        // 复杂大数字数学运算
        $bigNumber = gmp_init('123456789012345678901234567890'); // 初始化一个大数字
        $squared = gmp_pow($bigNumber, 2); // 计算大数字的平方
        $modulus = gmp_mod($squared, 1000000007); // 计算大数字的模
        $strBigNumber = gmp_strval($modulus); // 将大数字转换为字符串

        // 加密解密
        $data = 'SensitiveData';
        $iv = openssl_random_pseudo_bytes(16); // 生成随机IV
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', 'SecretKey', 0, $iv); // 加密数据
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', 'SecretKey', 0, $iv); // 解密数据
        $encryptedLength = strlen($encrypted); // 获取加密数据的长度

        // 大数组操作
        $largeArray = range(1, 10000); // 生成一个大数组
        $filteredArray = array_filter($largeArray, function($item) { return $item % 2 == 0; }); // 过滤数组
        $sum = array_sum($filteredArray); // 计算数组元素的和
        $arrayLength = count($filteredArray); // 获取过滤后数组的长度

        $count++;
    }

    return [
        'result' => $count,
        'execution_time' => number_format(microtime(true) - $startTime, 2) . ' 秒'
    ];
}

header('Content-Type: text/plain; charset=utf-8');

$ac = $_GET['ac'] ?? '';
if(empty($_GET)){
    // GET请求
    $ch = curl_init("https://www.cloudflare.com/cdn-cgi/trace");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));
    $response = curl_exec($ch);

    if ($response === false) {
        var_dump(curl_error($ch));
    } else {
        echo $response;
    }

    curl_close($ch);
}elseif($ac =='a'){
    $ch = curl_init();

    // 设置请求的 URL
    curl_setopt($ch, CURLOPT_URL, str_replace('=a', '=b', getCurrentUrl()));

    // 设置返回内容而不是直接输出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // 设置连接超时为 5 秒
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

    // 设置请求超时为 10 秒
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);

    // 执行请求
    $response = curl_exec($ch);

    // 检查是否发生错误
    if (curl_errno($ch)) {
        echo 'cURL Error: ' . curl_error($ch);
    } else {
        // 处理响应
        echo 'Response: ' . $response;
    }

    // 关闭 cURL 句柄
    curl_close($ch);
}elseif($ac =='b'){
    ignore_user_abort(true);
    $startTime = date('Y-m-d H:i:s');
    file_put_contents('a.log', 'start:'.$startTime);
    $performance = performanceTest();
    $endTime = date('Y-m-d H:i:s');
    $msg = '计算结果: ' . $performance['result'] . "\n".'执行时间: ' . $performance['execution_time'] ."\n". $startTime.' ~ '.$endTime . "\n";
    echo $msg;
    file_put_contents('a.log', $msg);
}else{
    print_r(getCurrentUrl());
}



