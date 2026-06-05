<?php

// .env 파일이 config.php와 같은 디렉토리 (www 하위)
$envPath = __DIR__ . '/.env';

if (is_file($envPath)) {
  $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
      $line = trim($line);

      // 주석(#)이나 빈 줄 건너뛰기
      if ($line === '' || str_starts_with($line, '#')) {
        continue;
      }

      // '=' 가 없으면 무시
      if (strpos($line, '=') === false) {
        continue;
      }

      // KEY=VALUE 형태로 분리
      list($name, $value) = explode('=', $line, 2);
      $name = trim($name);
      $value = trim($value);

      // 시스템 환경변수에 등록
      putenv("$name=$value");
      $_ENV[$name] = $value;
      $_SERVER[$name] = $value;
  }
}

// Database 관련 상수
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'scpay02');
define('DB_USER', getenv('DB_USER') ?: 'scpay02');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Keyin API 관련 상수
define('KEYIN_API_BASE', getenv('KEYIN_API_BASE') ?: 'https://wspay.net/api/v1/keyin');
define('KEYIN_API_KEY', getenv('KEYIN_API_KEY') ?: '');
define('KEYIN_TID', getenv('KEYIN_TID') ?: '');
define('KEYIN_MERCHANT_ID', getenv('KEYIN_MERCHANT_ID') ?: '');
define('KEYIN_MERCHANT_NAME', getenv('KEYIN_MERCHANT_NAME') ?: '테스트가맹점');

// 타임존 설정
define('TIMEZONE', getenv('TIMEZONE') ?: 'Asia/Seoul');
date_default_timezone_set(TIMEZONE);

