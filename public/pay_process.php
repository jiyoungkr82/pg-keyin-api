<?php
require_once dirname(__DIR__).'/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    keyin_redirect('pay.php');
}

if (!csrf_verify()) {
    flash_set('error', '잘못된 요청입니다. 다시 시도해 주세요.');
    keyin_redirect('pay.php');
}

$validated = PaymentValidator::validate($_POST);
if (!empty($validated['errors'])) {
    flash_set('error', implode("\n", $validated['errors']));
    keyin_redirect('pay.php');
}

$api = new KeyinApi();
if (!$api->isConfigured()) {
    flash_set('error', 'API 설정이 완료되지 않았습니다. config/config.local.php 를 확인하세요.');
    keyin_redirect('pay.php');
}

$payload = [
    'amount' => $validated['amount'],
    'goods_name' => $validated['goods_name'],
    'buyer_name' => $validated['buyer_name'],
    'card_no' => $validated['card_no'],
    'expire_yymm' => $validated['expire_yymm'],
    'installment' => $validated['installment'],
];

if ($validated['buyer_phone'] !== '') {
    $payload['buyer_phone'] = $validated['buyer_phone'];
}
if ($validated['buyer_email'] !== '') {
    $payload['buyer_email'] = $validated['buyer_email'];
}
// 구인증 시에만 전송 (비인증 요청 예시와 동일하게 빈 값은 제외)
if ($validated['cert_pw'] !== '') {
    $payload['cert_pw'] = $validated['cert_pw'];
}
if ($validated['cert_no'] !== '') {
    $payload['cert_no'] = $validated['cert_no'];
}

$result = $api->pay($payload);

$isSuccess = !empty($result['success']);
$data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];

$apiStatus = $isSuccess ? 'approved' : (isset($data['status']) ? (string)$data['status'] : 'failed');

try {
    $paymentId = PaymentRepository::insert([
        'merchant_name' => keyin_config('keyin.merchant_name', ''),
        'goods_name' => $validated['goods_name'],
        'buyer_name' => $validated['buyer_name'],
        'amount' => $validated['amount'],
        'payment_status' => KeyinApi::mapPaymentStatus($apiStatus),
        'api_status' => $apiStatus,
        'order_no' => (string)($data['order_no'] ?? ''),
        'approval_number' => (string)($data['approval_number'] ?? ''),
        'error_code' => (string)($result['error_code'] ?? ''),
        'error_message' => (string)($result['message'] ?? ''),
        'mb_id' => '',
    ]);
} catch (Throwable $e) {
    flash_set('error', 'DB 저장 오류: '.$e->getMessage());
    keyin_redirect('pay.php');
}

flash_set('result', [
    'success' => $isSuccess,
    'message' => (string)($result['message'] ?? ($isSuccess ? '결제가 완료되었습니다.' : '결제에 실패했습니다.')),
    'error_code' => (string)($result['error_code'] ?? ''),
    'amount' => $validated['amount'],
    'goods_name' => $validated['goods_name'],
    'buyer_name' => $validated['buyer_name'],
    'order_no' => (string)($data['order_no'] ?? ''),
    'approval_number' => (string)($data['approval_number'] ?? ''),
    'card_mask' => KeyinApi::maskCardNo($validated['card_no']),
    'installment' => $validated['installment'],
    'http_code' => (int)($result['http_code'] ?? 0),
]);

$query = $paymentId > 0 ? 'id='.$paymentId : '';
keyin_redirect('pay_result.php', $query);
