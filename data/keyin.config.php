<?php
/**
 * 키인 결제 API 설정
 * define() 대신 배열로 반환합니다. (상수 중복 정의 문제 방지)
 */
if (!defined('_GNUBOARD_')) exit;

return array(
    'api_base' => 'https://wspay.net/api/v1/keyin',
    'api_key' => 'ssp-7c8eeaafd723229dd030da8426acfbb39f08692000eb3f4fa1754823944c',
    'tid' => 'wspm00302m',
    'merchant_name' => '테스트가맹점',
    // 로컬(WAMP) SSL self-signed 오류 시 false. 운영 서버에서는 반드시 true
    'ssl_verify' => false,
    // ssl_verify=true 일 때 CA 번들 경로 (예: C:/wamp64/bin/php/php8.x.x/extras/ssl/cacert.pem)
    'ca_bundle' => '',
);
