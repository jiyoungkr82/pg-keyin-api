<?php

function h($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function keyin_base_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/pay.php');
    $dir = rtrim(dirname($script), '/');
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    }
    return $scheme.'://'.$host.$dir;
}

function keyin_url($path, $query = '')
{
    $path = ltrim((string)$path, '/');
    $url = keyin_base_url().'/'.$path;
    if ($query !== '') {
        $url .= (strpos($url, '?') === false ? '?' : '&').ltrim((string)$query, '?&');
    }
    return $url;
}

function keyin_redirect($path, $query = '')
{
    $url = keyin_url($path, $query);
    if (!headers_sent()) {
        header('HTTP/1.1 303 See Other');
        header('Location: '.$url);
    } else {
        echo '<script>location.replace('.json_encode($url).');</script>';
    }
    exit;
}

function csrf_token()
{
    if (empty($_SESSION['keyin_csrf'])) {
        $_SESSION['keyin_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['keyin_csrf'];
}

function csrf_verify()
{
    $token = $_POST['csrf'] ?? '';
    $session = $_SESSION['keyin_csrf'] ?? '';
    if ($token === '' || $session === '' || !hash_equals($session, $token)) {
        return false;
    }
    return true;
}

function flash_set($key, $value)
{
    $_SESSION['keyin_flash'][$key] = $value;
}

function flash_get($key, $default = null)
{
    if (!isset($_SESSION['keyin_flash'][$key])) {
        return $default;
    }
    $val = $_SESSION['keyin_flash'][$key];
    unset($_SESSION['keyin_flash'][$key]);
    return $val;
}

function layout_header($title = '키인 결제')
{
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.h($title).'</title>';
    echo '<link rel="stylesheet" href="'.h(keyin_url('assets/style.css')).'">';
    echo '</head><body><div class="wrap">';
}

function layout_footer()
{
    echo '</div></body></html>';
}
