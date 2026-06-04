<?php
// 1. 초기 보안 확인 및 데이터 수집
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "잘못된 접근입니다.";
    exit('Method Not Allowed');
}

// index.html Form 에서 전송된 데이터 수집
$amount = $_POST['amount'] ?? '';
$goodsName = $_POST['goods_name'] ?? '';
$buyerName = $_POST['buyer_name'] ?? '';
$buyerPhone = $_POST['buyer_phone'] ?? '';
$buyerEmail = $_POST['buyer_email'] ?? '';
$cardNo = $_POST['cn_val'] ?? '';
$expireYymm = $_POST['expire_yymm'] ?? '';
$installment = $_POST['installment'] ?? '';

// 2. 서버 사이드 필수 데이터 검증
if (empty($amount) || (int)$amount < 100 || empty($goodsName) || empty($buyerName || empty($cardNo)) || strlen($expireYymm) !== 4) {
    echo "<script>alert('입력된 정보가 유효하지 않습니다.'); history.back();</script>";
    exit('Bad Request');
}

// 3. PG사 수기결제 API와 cURL 통신 수행
$url = 'https://wspay.net/api/v1/keyin/pay.php';
$apiKey = 'ssp-b42ed917dda54de9042ee488381806923a9d4f19f80e24962b6e1a1b5e49';
$tid = 'wspm00301m';
$headers = [
    'Content-Type: application/json',
    'X-API-Key: ' .$apiKey,
    'X-TID: ' .$tid,
];
$data = [
    'amount' => (int)$amount,
    'goods_name' => $goodsName,
    'buyer_name' => $buyerName,
    'buyer_phone' => $buyerPhone,
    'card_no' => $cardNo,
    'expire_yymm' => $expireYymm,
    'installment' => $installment,
    // 구인증 방식일 경우 필요한 파라미터 추가 (비, 생)
];

$ch = curl_init(); // 핸들러를 먼저 안전하게 초기화

// JSON 형식으로 변환
$json_payload = json_encode($data);

// PHP 내장 cURL 라이브러리로 통신 제어
// $ch = curl_init($url);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 로컬 아파치가 인증서 유효성을 강제로 검사하지 않고 무조건 통과시키도록 강제 명령합니다.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true); 

// 모든 세팅이 완료된 후 통신 실행
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 응답받은 JSON 문자열을 PHP 배열로 변환
$result = json_decode($response, true);

// 4. DB 연결 설정 (mysqli)
// 카페24 호스팅 정보 대입
$db_host = 'localhost';
$db_user = 'scpay02';
$db_pass = 'wspay1157';
$db_name = 'scpay02';
$db_port = '3306';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
if (!$conn) {
    echo "데이터베이스 연결 실패: " . mysqli_connect_error();
    exit('Database Connection Failed');
}

// SQL 인젝션 방지를 위해 문자열 이스케이프 처리 (mysqli 방식 필수 보안 절차)
// 이스케이프란? 사용자 입력값에 포함된 특수문자 앞에 문자나 기호를 추가하여, 
// 해당 문자가 SQL 명령어 코드가 아닌 단순한 문자열 데이터로 인식되도록 무효화하는 보안 기법 => 내장 함수 사용
$safe_buyer_name = mysqli_real_escape_string($conn, $buyerName);
$safe_goods_name = mysqli_real_escape_string($conn, $goodsName);
$safe_amount = (int)$amount;

// 5. 결제 정보 데이터베이스 저장 후 브라우저 화면 출력
if (isset($result['success']) && $result['success'] === true) {

    // 응답 스펙 내부의 data 오브젝트 데이터 추출
    $pay_data       = $result['data'];
    $order_no       = mysqli_real_escape_string($conn, $pay_data['order_no'] ?? '');
    $app_number     = mysqli_real_escape_string($conn, $pay_data['approval_number'] ?? '');
    $approved_at    = mysqli_real_escape_string($conn, $pay_data['approved_at'] ?? '');
    $res_amount     = (int)($pay_data['amount'] ?? 0);
    $res_goods      = mysqli_real_escape_string($conn, $pay_data['goods_name'] ?? '');
    $res_buyer      = mysqli_real_escape_string($conn, $pay_data['buyer_name'] ?? '');
    $card_masked    = mysqli_real_escape_string($conn, $pay_data['card_no_masked'] ?? '');
    $receipt_url    = mysqli_real_escape_string($conn, $pay_data['receipt_url'] ?? '');
    
    // prepared statement 방식
    // ① SQL 뼈대(틀) 준비 (데이터가 들어갈 자리를 물음표 '?'로 비워둠)
    // 변수를 직접 문자열에 섞지 않으므로 따옴표 처리가 필요 없어 가독성이 좋아집니다.
    $sql = "INSERT INTO keyin_payment (tid, order_no, approval_number, approved_at, amount, goods_name, buyer_name, result_message, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'SUCCESS', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        // ② 물음표 '?' 자리에 매칭될 데이터 타입과 실제 변수를 안전하게 바인딩
        // "sssissss" 뜻: 문자열(s) 3개, 정수(i) 1개, 문자열(s) 4개를 순서대로 매핑함
        mysqli_stmt_bind_param($stmt, "ssssisss", $tid, $order_no, $app_number, $approved_at, $res_amount, $res_goods, $res_buyer, $card_masked, $receipt_url);
        
        // ③ 데이터가 완벽히 분리된 상태로 실행
        mysqli_stmt_execute($stmt);
        
        // ④ 준비된 서브밋 객체 자원을 명시적으로 해제
        mysqli_stmt_close($stmt);
    }
    // 커넥션 종료
    mysqli_close($conn);

    // 성공 화면 출력 및 3초 후 index.html 자동 이동
    echo "<!DOCTYPE html>
    <html lang='ko'>
    <head>
        <meta charset='UTF-8'>
        <meta http-equiv='refresh' content='3;url=index.html'>
        <title>결제 성공</title>
    </head>
    <body>
        <div style='text-align:center; margin-top:50px;'>
            <h1 style='color:green;'>🎉 결제가 정상 완료되었습니다.</h1>
            <hr style='width:400px;'>
            <p><strong>주문 번호:</strong> " . htmlspecialchars($order_no) . "</p>
            <p><strong>승인 번호:</strong> " . htmlspecialchars($app_number) . "</p>
            <p><strong>주문 상품:</strong> " . htmlspecialchars($res_goods) . "</p>
            <p><strong>결제 금액:</strong> " . number_format($res_amount) . "원</p>
            <p><a href='" . htmlspecialchars($receipt_url) . "' target='_blank' style='color:blue; text-decoration:underline;'>[영수증(매출전표) 확인하기]</a></p>
            <br>
            <p style='color:#666;'>3초 후에 결제창으로 자동으로 돌아갑니다...</p>
        </div>
    </body>
    </html>";
    exit;

} else {
    // 결제 실패 시 처리
    $error_msg  = $result['message'] ?? 'PG사 통신 에러가 발생했습니다.';
    $error_code = $result['error_code'] ?? 'UNKNOWN_CODE';
    
    // 실패용 데이터 빌드 (가상 주문번호 및 현시점 14자리 시간 자동 생성)
    $fail_order_no = "FAIL_" . time() . "_" . rand(1000, 9999); 
    $fail_time     = date("YmdHis");

    // SQL 물음표 '?' 총 7개 구성 (approval_number 자리는 명시적으로 NULL 처리하여 적재 최소화)
    $sql = "INSERT INTO keyin_payment (tid, order_no, approval_number, approved_at, amount, goods_name, buyer_name, result_message, status, created_at) 
            VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 'FAIL', NOW())";
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        // "sssisss" -> 문자열 3개, 정수 1개, 문자열 3개
        mysqli_stmt_bind_param($stmt, "sssisss", $tid, $fail_order_no, $fail_time, $safe_amount, $safe_goods_name, $safe_buyer_name, $error_msg);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);

    echo "<!DOCTYPE html>
    <html lang='ko'>
    <head><meta charset='UTF-8'><title>결제 실패</title></head>
    <body>
        <div style='text-align:center; margin-top:50px;'>
            <h1 style='color:red;'>❌ 결제 승인에 실패했습니다.</h1>
            <hr style='width:300px;'>
            <p style='color:#555;'><strong>실패 사유:</strong> " . htmlspecialchars($error_msg) . "</p>
            <p style='color:#555;'><strong>에러 코드:</strong> " . htmlspecialchars($error_code) . "</p>
            <br>
            <button onclick='history.back()' style='padding:10px 20px;'>다시 시도하기</button>
        </div>
    </body>
    </html>";
    exit;
}
?>