<?php
include_once('./common.php');
include_once(G5_LIB_PATH.'/keyin.lib.php');

if (!$is_member) {
    alert('로그인이 필요한 페이지입니다.', G5_BBS_URL.'/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.', keyin_url('keyin_pay.php'));
}

check_token();

$validated = keyin_validate_pay_input($_POST);
if (!empty($validated['errors'])) {
    alert(implode("\n", $validated['errors']), keyin_url('keyin_pay.php'));
}

if (!keyin_is_configured()) {
    alert('API 설정이 완료되지 않았습니다.\ndata/keyin.config.php 의 api_key, tid 값을 확인하세요.', keyin_url('keyin_pay.php'));
}

$api_payload = array(
    'amount' => $validated['amount'],
    'goods_name' => $validated['goods_name'],
    'buyer_name' => $validated['buyer_name'],
    'card_no' => $validated['card_no'],
    'expire_yymm' => $validated['expire_yymm'],
    'installment' => $validated['installment'],
    'cert_pw' => $validated['cert_pw'],
    'cert_no' => $validated['cert_no'],
);

if ($validated['buyer_phone'] !== '') {
    $api_payload['buyer_phone'] = $validated['buyer_phone'];
}

if ($validated['buyer_email'] !== '') {
    $api_payload['buyer_email'] = $validated['buyer_email'];
}

$result = keyin_pay($api_payload);

$cfg = keyin_load_config();
$is_success = !empty($result['success']);
$data = isset($result['data']) && is_array($result['data']) ? $result['data'] : array();

$api_status = '';
if ($is_success) {
    $api_status = 'approved';
} elseif (isset($data['status'])) {
    $api_status = (string)$data['status'];
} else {
    $api_status = 'failed';
}

$db_row = array(
    'merchant_name' => $cfg['merchant_name'] ?? '',
    'goods_name' => $validated['goods_name'],
    'buyer_name' => $validated['buyer_name'],
    'amount' => $validated['amount'],
    'payment_status' => keyin_map_payment_status($api_status),
    'api_status' => $api_status,
    'order_no' => (string)($data['order_no'] ?? ''),
    'approval_number' => (string)($data['approval_number'] ?? ''),
    'error_code' => (string)($result['error_code'] ?? ''),
    'error_message' => (string)($result['message'] ?? ''),
    'mb_id' => $member['mb_id'] ?? '',
);

$payment_id = keyin_save_payment($db_row);

$session_result = array(
    'success' => $is_success,
    'message' => (string)($result['message'] ?? ($is_success ? '결제가 완료되었습니다.' : '결제에 실패했습니다.')),
    'error_code' => (string)($result['error_code'] ?? ''),
    'amount' => $validated['amount'],
    'goods_name' => $validated['goods_name'],
    'buyer_name' => $validated['buyer_name'],
    'order_no' => (string)($data['order_no'] ?? ''),
    'approval_number' => (string)($data['approval_number'] ?? ''),
    'card_mask' => keyin_mask_card_no($validated['card_no']),
    'installment' => $validated['installment'],
    'http_code' => (int)($result['http_code'] ?? 0),
);

set_session('ss_keyin_pay_result', $session_result);

$query = $payment_id > 0 ? 'pid='.$payment_id : '';
keyin_redirect('keyin_pay_result.php', $query);
