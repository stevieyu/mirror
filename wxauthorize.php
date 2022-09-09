<?php declare(strict_types=1);

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// ini_set('error_reporting', E_ERROR);

function builderRedirectUri(string $uri, array $query): string
{
    $parse_url_arr = parse_url($uri);
    $redirect_query_str = $parse_url_arr['query'] ?? '';

    if ($redirect_query_str) {
        $uri = str_replace('?' . $redirect_query_str, '', $uri);
    }

    if (!empty($parse_url_arr['fragment'])) {
        $uri .= strstr($parse_url_arr['fragment'], '?') ? '&' : '?';
    } else {
        $uri .= strstr($uri, '?') ? '&' : '?';
    }

    parse_str($redirect_query_str, $redirect_query);
    $redirect_query_str = http_build_query(array_merge($redirect_query, $query));
    $uri .= $redirect_query_str;

    return $uri;
}

function builderAuthorize(array $query): string
{
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (stristr($user_agent, 'MicroMessenger')) {
        $uri = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    } else {
        $uri = 'https://open.weixin.qq.com/connect/qrconnect';
    }
    $uri .= '?' . http_build_query($query);
    $uri .= '#wechat_redirect';

    return $uri;
}

if (!empty($_GET['appid']) && !empty($_GET['response_type']) && !empty($_GET['scope']) && isset($_GET['state'])) {
    $query = $_GET;

    if (!empty($query['redirect_uri'])) {
        setcookie('redirect_uri', $query['redirect_uri']);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'http') . '://' . $_SERVER['HTTP_HOST'];
    $query['redirect_uri'] = $origin . '/' . $_SERVER['SCRIPT_FILENAME'];

    header('Location: ' . builderAuthorize($query), true, 301);
    exit;
} elseif ((!empty($_GET['code']) || isset($_GET['state'])) && !empty($_COOKIE['redirect_uri'])) {
    setcookie('redirect_uri', '', time() - 1);

    http_response_code(301);
    header('Location: ' . builderRedirectUri($_COOKIE['redirect_uri'], $_GET),true, 301);
    exit;
}
