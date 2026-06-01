<?php

define('KEYIN_ROOT', dirname(__DIR__));
define('KEYIN_SRC', KEYIN_ROOT.'/src');
define('KEYIN_CONFIG_DIR', KEYIN_ROOT.'/config');
// .env 파일을 읽어서 서버 환경변수(getenv) 메모리에 강제로 등록하는 엔진
$envPath = KEYIN_ROOT . '/.env';
if (is_file($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 주석(#)으로 시작하는 줄은 무시합니다.
        if (strpos(trim($line), '#') === 0) continue;
        
        // KEY=VALUE 형태로 분리하여 서버 환경변수에 등록합니다.
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // 시스템 메모리에 등록 (getenv()로 읽을 수 있게 됨)
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

$configFile = KEYIN_CONFIG_DIR.'/config.local.php';
if (!is_file($configFile)) {
    $configFile = KEYIN_CONFIG_DIR.'/config.example.php';
}
if (!is_file($configFile)) {
    http_response_code(500);
    exit('설정 파일이 없습니다. config/config.local.php 를 생성하세요.');
}

$GLOBALS['keyin_config'] = require $configFile;

$tz = $GLOBALS['keyin_config']['timezone'] ?? 'Asia/Seoul';
date_default_timezone_set($tz);

$sessionPath = KEYIN_ROOT.'/storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once KEYIN_SRC.'/helpers.php';
require_once KEYIN_SRC.'/Database.php';
require_once KEYIN_SRC.'/KeyinApi.php';
require_once KEYIN_SRC.'/PaymentValidator.php';
require_once KEYIN_SRC.'/PaymentRepository.php';
require_once KEYIN_SRC.'/Logger.php';

function keyin_config($key = null, $default = null)
{
    $cfg = $GLOBALS['keyin_config'] ?? [];
    if ($key === null) {
        return $cfg;
    }
    $parts = explode('.', $key);
    $val = $cfg;
    foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) {
            return $default;
        }
        $val = $val[$p];
    }
    return $val;
}
