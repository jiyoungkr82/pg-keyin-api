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
        
        // 중요 금액 불일치는 치명적인 해킹 시도일 수 있으므로 ERROR 로그 기록
        Logger::error("금액 검증 실패 오류 발생!", [
            'order_no' => $data['order_no'] ?? 'unknown',
            'requested' => $requestedAmount,
            'approved' => $approvedAmount
        ]);
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

//// send noti to queque start *************************************************************************
// 🛠️ [교정 완료] 누락되었던 가맹점 발송용 웹훅 데이터 페이로드(Payload)를 완벽하게 조립합니다.
$webhookPayload = [
    'event' => $isSuccess ? 'payment.approved' : 'payment.failed',
    'transaction' => [
        'order_number' => (string)($data['order_no'] ?? 'ORDER_' . time()),
        'amount' => (int)$validated['amount'],
        'goods_name' => $validated['goods_name'],
        'buyer_name' => $validated['buyer_name'],
    ]
];

// 결제 실패 건일 경우 실패 사유 데이터를 추가 적재합니다.
if (!$isSuccess) {
    $webhookPayload['transaction']['failure_code'] = (string)($result['error_code'] ?? 'PG_DECLINED');
    $webhookPayload['transaction']['failure_message'] = (string)($result['message'] ?? '카드 정보 유효성 검사 실패');
}

// 1. 현재 결제를 시도 중인 가맹점의 고유 ID를 가져옵니다. (유지)
$currentMerchantId = keyin_config('keyin.merchant_id', 'jiyoung-test01');

$webhookUrl = '';
try {
    // DB에서 해당 가맹점이 등록해 둔 실시간 웹훅 URL을 조회합니다. (유지)
    $merchantStmt = Database::pdo()->prepare("SELECT webhook_url FROM merchants WHERE merchant_id = ? LIMIT 1");
    $merchantStmt->execute([$currentMerchantId]);
    $merchant = $merchantStmt->fetch();
    
    if ($merchant && !empty($merchant['webhook_url'])) {
        $webhookUrl = $merchant['webhook_url']; 
    } else {
        // 혹시 DB 조회 실패 시 작동할 안전장치 주소 교정
        $webhookUrl = 'http://localhost/webhook_test/webhook.php'; 
        Logger::warn("가맹점 웹훅 주소 미등록 - 기본 주소로 대체합니다: {$currentMerchantId}");
    }
} catch (Throwable $e) {
    Logger::error("가맹점 정보 조회 중 DB 에러 발생: " . $e->getMessage());
    $webhookUrl = 'http://localhost/webhook_test/webhook.php'; 
}

// -------------------------------------------------------------
// 📦 2. [기존 로직 연결] 조회한 동적 URL을 메시지 큐에 삽입 (유지)
// -------------------------------------------------------------
try {
    $stmt = Database::pdo()->prepare("
        INSERT INTO webhook_queue (webhook_url, payload, status) 
        VALUES (:url, :payload, 'pending')
    ");
    $stmt->execute([
        ':url' => $webhookUrl, 
        ':payload' => json_encode($webhookPayload, JSON_UNESCAPED_UNICODE) // ➡️ 정상 조립된 데이터가 완벽히 들어갑니다!
    ]);
    
    Logger::info("메시지 큐 동적 등록 성공 - 타겟 URL: {$webhookUrl}");
} catch (Throwable $e) {
    Logger::error("메시지 큐 등록 실패: " . $e->getMessage());
}
//// send noti to queue end *************************************************************************

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
