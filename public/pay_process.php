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

// LOG 1. 결제 시도 로그 기록 (INFO)
Logger::info("결제 요청 시작 - 구매자: {$validated['buyer_name']}, 금액: {$validated['amount']}");

$result = $api->pay($payload);

$isSuccess = !empty($result['success']);
$data = isset($result['data']) && is_array($result['data']) ? $result['data'] : [];

// LOG 2. 결제 금액 위변조 검증 (가장 중요)
if ($isSuccess) {
    $requestedAmount = (int)$validated['amount'];
    $approvedAmount = (int)($data['amount'] ?? 0);

    if ($requestedAmount !== $approvedAmount) {
        $isSuccess = false;
        $apiStatus = 'failed';
        $result['message'] = "결제 금액 위변조 의심 (요청: {$requestedAmount}원 / 승인: {$approvedAmount}원)";
        
        // **중요** 금액 불일치는 치명적인 해킹 시도일 수 있으므로 ERROR 로그 기록
        Logger::error("금액 검증 실패 오류 발생!", [
            'order_no' => $data['order_no'] ?? 'unknown',
            'requested' => $requestedAmount,
            'approved' => $approvedAmount
        ]);
        
        // 필요 시 여기서 PG사 자동 취소(Cancel) API를 호출하는 로직이 추가되어야 안전합니다.
    }
}

$apiStatus = $isSuccess ? 'approved' : (isset($data['status']) ? (string)$data['status'] : 'failed');

// LOG 3. 결제 결과에 따른 차등 로그 기록
if ($isSuccess) {
    Logger::info("결제 성공 승인 - 주문번호: {$data['order_no']}, 승인번호: {$data['approval_number']}");
} else {
    Logger::warn("결제 승인 실패 - 코드: {$result['error_code']}, 사유: {$result['message']}");
}

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
    // LOG 4. DB 저장 실패는 시스템 다운 수준의 에러이므로 CRITICAL/ERROR 로그 기록
    Logger::error("결제 완료 후 DB 저장 실패 (돈은 나갔는데 DB 기록 안됨!): " . $e->getMessage(), [
        'order_no' => $data['order_no'] ?? '',
        'approval_number' => $data['approval_number'] ?? ''
    ]);
    
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
