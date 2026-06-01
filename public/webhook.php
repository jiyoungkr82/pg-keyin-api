<?php
// webhook_test/webhook.php

// 1. 타임존 설정 (한국 시간)
date_default_timezone_set('Asia/Seoul');

// 2. 오직 POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// 3. PG사(pg_gateway) 일꾼이 보낸 순수 JSON 데이터 추출
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// 4. 테스트 검증용 로그 파일 생성 및 기록 (wamp64/www/webhook_test/received_webhook.log)
$logMessage = "[" . date('Y-m-d H:i:s') . "] [수신성공] " . $payload . PHP_EOL;
file_put_contents(__DIR__ . '/received_webhook.log', $logMessage, FILE_APPEND);

// 5. PG사 서버(일꾼)에게 정상 처리되었다고 성공 응답 반환
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => '가맹점이 안전하게 데이터를 수신했습니다.']);
