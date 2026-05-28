<?php
/**
 * 설정 샘플 — config/config.local.php 로 복사 후 값을 입력하세요.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'gnuboard_db',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
    ],
    'keyin' => [
        'api_base' => 'https://wspay.net/api/v1/keyin',
        'api_key' => 'ssp-your-api-key-here',
        'tid' => 'your-tid-here',
        'merchant_name' => '테스트가맹점',
        'ssl_verify' => true,
        'ca_bundle' => '',
    ],
    'timezone' => 'Asia/Seoul',
];
