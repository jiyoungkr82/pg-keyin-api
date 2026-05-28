<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * 키인 결제 API 공통 라이브러리
 */
function keyin_load_config()
{
    static $cfg = null;
    static $loaded_mtime = 0;

    $file = G5_DATA_PATH.'/keyin.config.php';
    if (!is_file($file)) {
        return null;
    }

    $mtime = (int)filemtime($file);
    if ($cfg !== null && $loaded_mtime === $mtime) {
        return $cfg;
    }

    $loaded = include $file;

    // 구버전 define() 방식 파일 호환
    if (!is_array($loaded)) {
        $loaded = array(
            'api_base' => defined('G5_KEYIN_API_BASE') ? G5_KEYIN_API_BASE : 'https://wspay.net/api/v1/keyin',
            'api_key' => defined('G5_KEYIN_API_KEY') ? G5_KEYIN_API_KEY : '',
            'tid' => defined('G5_KEYIN_API_TID') ? G5_KEYIN_API_TID : '',
            'merchant_name' => defined('G5_KEYIN_MERCHANT_NAME') ? G5_KEYIN_MERCHANT_NAME : '',
        );
    }

    $cfg = array(
        'api_base' => trim((string)($loaded['api_base'] ?? 'https://wspay.net/api/v1/keyin')),
        'api_key' => trim((string)($loaded['api_key'] ?? '')),
        'tid' => trim((string)($loaded['tid'] ?? '')),
        'merchant_name' => trim((string)($loaded['merchant_name'] ?? '')),
        'ssl_verify' => isset($loaded['ssl_verify']) ? (bool)$loaded['ssl_verify'] : true,
        'ca_bundle' => trim((string)($loaded['ca_bundle'] ?? '')),
    );

    $loaded_mtime = $mtime;

    return $cfg;
}

function keyin_is_configured()
{
    $cfg = keyin_load_config();
    if (!$cfg) {
        return false;
    }

    if ($cfg['api_key'] === '' || $cfg['tid'] === '') {
        return false;
    }

    if ($cfg['api_key'] === 'ssp-your-api-key-here' || $cfg['tid'] === 'your-tid-here') {
        return false;
    }

    return true;
}

/**
 * 키인 결제 페이지 절대 URL 생성
 */
function keyin_url($filename, $query = '')
{
    $filename = ltrim((string)$filename, '/');

    if (defined('G5_URL') && G5_URL !== '') {
        $base = rtrim(G5_URL, '/');
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
        $base = $scheme.'://'.$host.$dir;
    }

    $url = $base.'/'.$filename;

    if ($query !== '') {
        $url .= (strpos($url, '?') === false ? '?' : '&').ltrim((string)$query, '?&');
    }

    return $url;
}

/**
 * POST 처리 후 결과 페이지로 이동 (303 See Other)
 */
function keyin_redirect($filename, $query = '')
{
    $url = keyin_url($filename, $query);

    if (function_exists('safe_filter_url_host')) {
        $url = safe_filter_url_host($url);
    }

    $url = str_replace('&amp;', '&', $url);

    if (!headers_sent()) {
        header('HTTP/1.1 303 See Other');
        header('Location: '.$url);
    } else {
        echo '<script>location.replace('.json_encode($url).');</script>';
    }
    exit;
}

/**
 * cURL SSL 옵션 적용 (WAMP 등 로컬 환경 self-signed 인증서 대응)
 */
function keyin_apply_curl_ssl_options($ch, $cfg)
{
    $ssl_verify = !empty($cfg['ssl_verify']);
    $ca_bundle = $cfg['ca_bundle'] ?? '';

    if ($ssl_verify) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($ca_bundle !== '' && is_file($ca_bundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca_bundle);
        }
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
}

/**
 * @return array{success:bool,message?:string,error_code?:string,data?:array,http_code?:int,raw?:string}
 */
function keyin_api_request($method, $endpoint, $payload = array())
{
    $cfg = keyin_load_config();
    if (!$cfg) {
        return array('success' => false, 'message' => 'API 설정 파일이 없습니다. (data/keyin.config.php)', 'error_code' => 'CONFIG');
    }

    if (!keyin_is_configured()) {
        return array('success' => false, 'message' => 'API Key 또는 TID가 설정되지 않았습니다.', 'error_code' => 'CONFIG');
    }

    if (!function_exists('curl_init')) {
        return array('success' => false, 'message' => 'PHP cURL 확장이 필요합니다.', 'error_code' => 'CURL');
    }

    $url = rtrim($cfg['api_base'], '/').'/'.ltrim($endpoint, '/');
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($payload);
    }

    $headers = array(
        'Content-Type: application/json',
        'X-API-Key: '.$cfg['api_key'],
        'X-TID: '.$cfg['tid'],
    );

    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    );

    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $opts);
    keyin_apply_curl_ssl_options($ch, $cfg);

    $raw = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return array(
            'success' => false,
            'message' => '결제 API 통신 오류: '.$curl_err,
            'error_code' => 'NETWORK',
            'http_code' => $http_code,
        );
    }

    $result = json_decode($raw, true);
    if (!is_array($result)) {
        return array(
            'success' => false,
            'message' => 'API 응답 형식이 올바르지 않습니다.',
            'error_code' => 'INVALID_JSON',
            'http_code' => $http_code,
            'raw' => $raw,
        );
    }

    $result['http_code'] = $http_code;
    return $result;
}

/**
 * 키인 결제 요청
 */
function keyin_pay($data)
{
    return keyin_api_request('POST', 'pay.php', $data);
}

/**
 * 키인 결제 목록 조회
 */
function keyin_list($params = array())
{
    return keyin_api_request('GET', 'list.php', $params);
}

/**
 * API status → DB payment_status
 */
function keyin_map_payment_status($api_status)
{
    $map = array(
        'approved' => 'SUCCESS',
        'failed' => 'FAILED',
        'pending' => 'PENDING',
        'cancelled' => 'CANCELLED',
    );

    $api_status = strtolower((string)$api_status);
    return isset($map[$api_status]) ? $map[$api_status] : 'FAILED';
}

/**
 * 결제 결과 DB 저장 (테이블/컬럼은 install/keyin_payment_schema.sql 참고)
 */
function keyin_save_payment($row)
{
    $cfg = keyin_load_config();
    $merchant_name = isset($row['merchant_name']) && $row['merchant_name'] !== ''
        ? $row['merchant_name']
        : ($cfg['merchant_name'] ?? '');

    $sql = " INSERT INTO g5_shop_payment
                SET merchant_name = '".sql_escape_string($merchant_name)."',
                    goods_name = '".sql_escape_string($row['goods_name'] ?? '')."',
                    buyer_name = '".sql_escape_string($row['buyer_name'] ?? '')."',
                    amount = '".(int)($row['amount'] ?? 0)."',
                    payment_status = '".sql_escape_string($row['payment_status'] ?? 'FAILED')."',
                    api_status = '".sql_escape_string($row['api_status'] ?? '')."',
                    order_no = '".sql_escape_string($row['order_no'] ?? '')."',
                    approval_number = '".sql_escape_string($row['approval_number'] ?? '')."',
                    error_code = '".sql_escape_string($row['error_code'] ?? '')."',
                    error_message = '".sql_escape_string($row['error_message'] ?? '')."',
                    mb_id = '".sql_escape_string($row['mb_id'] ?? '')."',
                    created_at = '".G5_TIME_YMDHIS."' ";

    sql_query($sql, false);

    return function_exists('sql_insert_id') ? (int)sql_insert_id() : 0;
}

/**
 * DB 저장 건을 결과 화면용 배열로 변환
 */
function keyin_result_from_row($row)
{
    if (!is_array($row) || empty($row['id'])) {
        return null;
    }

    $payment_status = (string)($row['payment_status'] ?? '');

    return array(
        'success' => ($payment_status === 'SUCCESS'),
        'message' => (string)($row['error_message'] ?? ($payment_status === 'SUCCESS' ? '결제가 완료되었습니다.' : '결제에 실패했습니다.')),
        'error_code' => (string)($row['error_code'] ?? ''),
        'amount' => (int)($row['amount'] ?? 0),
        'goods_name' => (string)($row['goods_name'] ?? ''),
        'buyer_name' => (string)($row['buyer_name'] ?? ''),
        'order_no' => (string)($row['order_no'] ?? ''),
        'approval_number' => (string)($row['approval_number'] ?? ''),
        'card_mask' => '',
        'installment' => '',
        'http_code' => 0,
    );
}

function keyin_mask_card_no($card_no)
{
    $digits = preg_replace('/\D/', '', (string)$card_no);
    $len = strlen($digits);
    if ($len < 8) {
        return str_repeat('*', $len);
    }

    return substr($digits, 0, 4).str_repeat('*', $len - 8).substr($digits, -4);
}

function keyin_validate_pay_input($input)
{
    $errors = array();

    $amount = isset($input['amount']) ? (int)preg_replace('/\D/', '', (string)$input['amount']) : 0;
    if ($amount < 100) {
        $errors[] = '결제금액은 100원 이상이어야 합니다.';
    }

    $goods_name = trim((string)($input['goods_name'] ?? ''));
    if ($goods_name === '') {
        $errors[] = '상품명을 입력하세요.';
    }

    $buyer_name = trim((string)($input['buyer_name'] ?? ''));
    if ($buyer_name === '') {
        $errors[] = '구매자명을 입력하세요.';
    }

    $card_no = preg_replace('/\D/', '', (string)($input['card_no'] ?? ''));
    if (!preg_match('/^\d{15,16}$/', $card_no)) {
        $errors[] = '카드번호는 15~16자리 숫자여야 합니다.';
    }

    $expire_yymm = preg_replace('/\D/', '', (string)($input['expire_yymm'] ?? ''));
    if (!preg_match('/^\d{4}$/', $expire_yymm)) {
        $errors[] = '유효기간은 YYMM 형식(4자리)이어야 합니다.';
    }

    $installment = trim((string)($input['installment'] ?? ''));
    if (!preg_match('/^(00|0[2-9]|1[0-2])$/', $installment)) {
        $errors[] = '할부개월은 00(일시불) 또는 02~12만 가능합니다.';
    }

    $buyer_phone = preg_replace('/\D/', '', (string)($input['buyer_phone'] ?? ''));
    $buyer_email = trim((string)($input['buyer_email'] ?? ''));
    if ($buyer_email !== '' && !filter_var($buyer_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '구매자 이메일 형식이 올바르지 않습니다.';
    }

    // 구인증(비인증) 필수: cert_pw, cert_no
    $cert_pw = preg_replace('/\D/', '', (string)($input['cert_pw'] ?? ''));
    if (!preg_match('/^\d{2}$/', $cert_pw)) {
        $errors[] = '카드 비밀번호 앞 2자리를 입력하세요.';
    }

    $cert_no = preg_replace('/\D/', '', (string)($input['cert_no'] ?? ''));
    if (!preg_match('/^(\d{6}|\d{10})$/', $cert_no)) {
        $errors[] = '주민번호 앞 6자리 또는 사업자번호 10자리를 입력하세요.';
    }

    return array(
        'errors' => $errors,
        'amount' => $amount,
        'goods_name' => $goods_name,
        'buyer_name' => $buyer_name,
        'buyer_phone' => $buyer_phone,
        'buyer_email' => $buyer_email,
        'card_no' => $card_no,
        'expire_yymm' => $expire_yymm,
        'installment' => $installment,
        'cert_pw' => $cert_pw,
        'cert_no' => $cert_no,
    );
}
