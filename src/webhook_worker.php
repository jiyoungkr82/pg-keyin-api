<?php
// pg_gateway/src/webhook_worker.php
// ⚠️ 이 파일은 대기열 DB를 감시하다가 webhook_test로 전화를 거는 일꾼입니다.

require_once __DIR__ . '/bootstrap.php';

Logger::info("웹훅 일꾼(Worker) 작동 시작 - 대기열 감시 중...");
$db = Database::pdo();

// 1. 대기(pending) 상태인 일감을 가져옵니다.
$stmt = $db->query("SELECT * FROM webhook_queue WHERE status = 'pending' LIMIT 5");
$jobs = $stmt->fetchAll();

foreach ($jobs as $job) {
    // 동시성 충돌 방지를 위해 상태를 처리중으로 변경
    $db->prepare("UPDATE webhook_queue SET status = 'processing' WHERE id = ?")->execute([$job['id']]);

    Logger::info("일감 처리 시작 - ID: {$job['id']}");

    // 2. 진짜 cURL로 가맹점 주소(webhook_test)에 웹훅 발사
    $ch = curl_init($job['webhook_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $job['payload']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    // [중요] 아까 발생했던 로컬 SSL 인증서 에러 방지 가드 추가
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 3. 결과 처리
    if ($httpCode === 200) {
        $db->prepare("DELETE FROM webhook_queue WHERE id = ?")->execute([$job['id']]);
        Logger::info("일감 처리 완수 및 대기열 삭제 - ID: {$job['id']}");
    } else {
        $db->prepare("UPDATE webhook_queue SET status = 'pending', attempts = attempts + 1 WHERE id = ?")->execute([$job['id']]);
        Logger::warn("가맹점 응답 실패 - ID: {$job['id']}, HTTP_CODE: {$httpCode}");
    }
}
