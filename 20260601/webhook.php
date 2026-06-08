<?php
// 1. 인프라 및 설정 로드 (수기결제 폴더 내부)
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require __DIR__ . '/Logger.php';

// 💡 [임시 추가] 에러가 나면 숨기지 말고 화면에 영어로 다 출력하라는 명령
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Deprecated 경고 무시하기

// log4php 활성화
$logger = get_logger('pay_webhook');

// 1. JSON 데이터 원시 수신
$payload = file_get_contents('php://input');

// 데이터 조작 전 원본 수신 내역 로깅
$logger->info("================ WEBHOOK NOTI RECEIVED ================");
$logger->info("Raw Payload:" . $payload);

$data = json_decode($payload, true);

// 2. 데이터 유효성 검증
if (!$data || !isset($data['transaction']) || !isset($data['event'])) {
    $logger->error("유효하지 않은 페이로드 구조 수신");
    http_response_code(400);
    exit('Invalid payload');
}

$transaction = $data['transaction'];
$order_no = $transaction['order_number'] ?? null;
$amount = $transaction['amount'] ?? null;
$event = $data['event'];

if (!$order_no || !$amount) {
    $logger->error("필수 파라미터(주문번호 또는 금액) 누락");
    http_response_code(400);
    exit("Missing required fields");
}

try {
    $pdo = Database::pdo();
    $pdo->beginTransaction(); // 트랜잭션 시작

    // 멱등성 체크 및 Row Lock (동시성 제어)
    // 기존 테이블 구조의 주문 상태와 금액을 함께 조회
    $stmt = $pdo->prepare("SELECT status, amount FROM keyin_payment WHERE order_no = :order_no FOR UPDATE");
    $stmt->execute([':order_no' => $order_no]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("존재하지 않는 주문번호 요청: $order_no");
    }

    // 금액 위변조 교차 검증 (금액 스니핑 방지)
    // DB의 원본 주문 금액과 외부에서 노티로 날아온 금애기 다르면 숭인을 저러
    // if((int)$order['amount'] !== (int)$amount) {
    //   throw new Exception("금액 위변조 의심 거래 차단! DB금액: {$order['amount']} / 노티금액: $amount");
    // }

    // 이벤트 분기 처리 (DB 상태 업데이트)
    if ($event === 'payment.approved') {
        
        // 이미 처리된 건이라면 중복 실행 방지 (멱등성 보장)
        if ($order['status'] === 'paid') {
            $pdo->rollBack();
            $logger->info("[SKIP] 이미 결제 완료(paid) 처리된 주문건 건너뜀: $order_no");
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // 승인 처리 진행
        $updateStmt = $pdo->prepare("UPDATE payment_logs SET status = 'paid', paid_at = NOW() WHERE order_no = :order_no");
        $updateStmt->execute([':order_no' => $order_no]);

        $logger->info("[SUCCESS] 주문 결제 승인 완료 처리: $order_no");

        // 💡 실무 확장 : 필요 시 여기서 알림톡 발송, SMS 발송 큐(Queue) 등록 등
    } elseif ($event === 'payment.cancelled') {

        if ($order['status'] === 'cancelled') {
            $pdo->rollBack();
            $logger->info("[SKIP] 이미 취소(cancelled) 처리된 주문건 건너뜀: $order_no");
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // 취소 처리 진행
        $updateStmt = $pdo->prepare("UPDATE payment_logs SET status = 'cancelled', cancelled_at = NOW() WHERE order_no = :order_no");
        $updateStmt->execute([':order_no' => $order_no]);

        $logger->info("[CANCEL] 주문 결제 취소 완료 처리: $order_no");
    } else {
        // 💡 wspay에서 결제 실패(failed) 관련 노티가 왔을 때의 실무적 확장
        $updateStmt = $pdo->prepare("UPDATE payment_logs SET status = 'cancelled', cancelled_at = NOW() WHERE order_no = :order_no");
        $updateStmt->execute([':order_no' => $order_no]);
        $logger->info("결제 실패 노티 기록 완료: 주문번호 = $order_no");
    } 

    $pdo->commit(); //정상 처리 시 확정 적용
    
    // 자사 표준 규격에 맞춘 성공 응답 반환
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);

} catch(Exception $e) {
    if(isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
       $pdo->rollBack();
    }

    // 에러 원인을 추적할 수 있도록 파일에 로깅
    $logger->error("Webhook 처리 실패 [주문번호: $order_no]: " . $e->getMessage());

    // PG사 서버가 재전송(Retry)을 보낼 수 있도록 500 에러를 명시적으로 반환
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal processing failed']);

}