<?php
/**
 * 비인증 키인 결제 API 연동 테스트 (CLI)
 * 사용: php scripts/test_keyin_pay.php
 */
require_once dirname(__DIR__).'/src/bootstrap.php';

$api = new KeyinApi();
if (!$api->isConfigured()) {
    fwrite(STDERR, "FAIL: API 설정이 없습니다.\n");
    exit(1);
}

// 비인증 예시 — cert_pw, cert_no 없음
$payload = [
    'amount' => 50000,
    'goods_name' => '테스트상품',
    'buyer_name' => '홍길동',
    'buyer_phone' => '01012345678',
    'card_no' => '1234567890123456',
    'expire_yymm' => '2612',
    'installment' => '00',
];

echo "=== Key-in API test (비인증 payload, cert_pw/cert_no 제외) ===\n";
echo "Payload keys: ".implode(', ', array_keys($payload))."\n\n";

$result = $api->pay($payload);

echo "HTTP: ".($result['http_code'] ?? 'n/a')."\n";
echo "success: ".(!empty($result['success']) ? 'true' : 'false')."\n";
echo "message: ".($result['message'] ?? '')."\n";
echo "error_code: ".($result['error_code'] ?? '')."\n";

if (!empty($result['data']) && is_array($result['data'])) {
    echo "data: ".json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
}

// 연동 성공 기준: API가 JSON 응답을 반환 (네트워크/설정 오류가 아님)
$ok = isset($result['http_code']) && $result['http_code'] > 0
    && !in_array($result['error_code'] ?? '', ['CONFIG', 'CURL', 'NETWORK', 'INVALID_JSON'], true);

echo "\n".($ok ? "RESULT: API 연동 OK (PG 승인 여부는 success/message 참고)\n" : "RESULT: API 연동 FAIL\n");
exit($ok ? 0 : 1);
