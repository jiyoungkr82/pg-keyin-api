<?php
// 1. 그누보드 공통 파일 불러오기 (이걸 해야 DB 연결 및 로그인 체크 가능)
include_once('./common.php');
include_once(G5_PATH.'/head.sub.php'); // 그누보드 상단 기본 레이아웃

// 안전장치: 로그인 안 한 사용자(가맹점주/직원)는 로그인 창으로 튕겨내기
if (!$is_member) {
    alert('로그인이 필요한 페이지입니다.', G5_BBS_URL.'/login.php');
}

// -----------------------------
// 화면/권한 설정
// -----------------------------
// 비회원/권한 부족 사용자는 로그인 화면 및 상세 내역 제한 처리
$viewer_name = isset($member['mb_nick']) && $member['mb_nick'] ? $member['mb_nick'] : ($member['mb_name'] ?? $member['mb_id']);

$status_filter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'ALL';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// -----------------------------
// "오늘" 기준 날짜 범위(서버 시간: G5_TIME_YMD)
// -----------------------------
$today_ymd = G5_TIME_YMD; // YYYY-MM-DD
$start_dt = $today_ymd . ' 00:00:00';
$end_dt   = $today_ymd . ' 23:59:59';

// -----------------------------
// status 필터: 오늘 날짜 범위 내 실제 payment_status 값만 허용
// -----------------------------
$allowed_statuses = ['ALL'];
$status_list_sql = "
    SELECT DISTINCT payment_status AS ps
    FROM g5_shop_payment
    WHERE created_at >= '" . sql_escape_string($start_dt) . "'
      AND created_at <= '" . sql_escape_string($end_dt) . "'
";
$status_list_res = sql_query($status_list_sql);
while ($s = sql_fetch_array($status_list_res)) {
    if (!empty($s['ps'])) $allowed_statuses[] = (string)$s['ps'];
}
unset($s, $status_list_res);

if (!in_array($status_filter, $allowed_statuses, true)) $status_filter = 'ALL';

// WHERE 조건 구성
$base_where = " created_at >= '" . sql_escape_string($start_dt) . "' AND created_at <= '" . sql_escape_string($end_dt) . "'";

// 리스트 WHERE 조건(상태 필터 반영)
$where = "1=1"; #$base_where;
if ($status_filter !== 'ALL') {
    $where .= " AND payment_status = '" . sql_escape_string($status_filter) . "'";
}

// 1) 오늘 성공 총 결제금액 및 성공 건수(요약은 SUCCESS 고정)
$sum_sql = "
    SELECT
        COALESCE(SUM(amount), 0) AS total_amt,
        COUNT(*) AS cnt
    FROM g5_shop_payment
    WHERE {$base_where} AND payment_status = 'SUCCESS'
";
$row = sql_fetch($sum_sql);
$total_amt = isset($row['total_amt']) ? (float)$row['total_amt'] : 0;
$cnt = isset($row['cnt']) ? (int)$row['cnt'] : 0;

echo "<div style='padding:20px; font-family: sans-serif; max-width:900px; margin:0 auto;'>";
echo "<h2>🚀 가맹점 매출 요약 대시보드 (신입 사원 훈련용)</h2>";
echo "<p>접속 직원: <b>" . get_text($viewer_name) . "</b>님 환영합니다.</p>";
echo "<p style='margin:8px 0 0;'><a href='./keyin_pay.php' style='display:inline-block;padding:8px 14px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;'>키인 결제하기</a></p>";
echo "<hr>";

echo "<div style='background:#eef; padding:15px; border-radius:5px; margin-bottom:20px;'>";
echo "<h3>📊 오늘 총 매출: <span style='color:blue;'>" . number_format($total_amt) . "원</span> (성공 {$cnt}건)</h3>";
echo "</div>";

// -----------------------------
// 상세 내역(권한: 관리자만)
// -----------------------------
if ($is_admin) {
    // 2) 오늘 결제 상세 내역 리스트(필요 컬럼만, 상태/기간 필터 + 페이징)
    $count_sql = "
        SELECT COUNT(*) AS total_cnt
        FROM g5_shop_payment
        WHERE {$where}
    ";
    $count_row = sql_fetch($count_sql);
    $total_cnt = isset($count_row['total_cnt']) ? (int)$count_row['total_cnt'] : 0;
    $total_page = max(1, (int)ceil($total_cnt / $per_page));
    if ($page > $total_page) $page = $total_page;

    $list_sql = "
        SELECT id, merchant_name, amount, payment_status, created_at
        FROM g5_shop_payment
        WHERE {$where}
        ORDER BY id ASC
        LIMIT {$offset}, {$per_page}
    ";
    $result = sql_query($list_sql);

    echo "<h3>📝 결제 승인/취소 내역</h3>";

    // 상태 필터 + 페이징 UI(간단)
    $qs_status = "status=" . urlencode($status_filter);
    echo "<div style='margin-bottom:10px; font-size:14px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;'>";
    echo "<form method='get' style='margin:0;'>";
    echo "<input type='hidden' name='page' value='1' />";
    echo "<label style='margin-right:6px;'>상태:</label>";
    echo "<select name='status' onchange='this.form.submit()' style='padding:6px 8px;'>";
    foreach ($allowed_statuses as $st) {
        $st_label = ($st === 'ALL') ? '전체' : $st;
        $sel = ($st === $status_filter) ? "selected" : "";
        echo "<option value='" . get_text($st, 0) . "' {$sel}>" . get_text($st_label, 0) . "</option>";
    }
    echo "</select>";
    echo "</form>";
    echo "<span style='color:#555;'>페이지 {$page} / {$total_page} (총 {$total_cnt}건)</span>";
    echo "<div style='display:inline;'>";
    if ($page > 1) {
        echo "<a href='?{$qs_status}&page=" . ($page - 1) . "' style='margin-right:10px;'>이전</a>";
    }
    echo "<a href='?{$qs_status}&page=1' style='margin-right:10px;'>첫페이지</a>";
    if ($page < $total_page) {
        echo "<a href='?{$qs_status}&page=" . ($page + 1) . "'>다음</a>";
    }
    echo "</div>";
    echo "</div>";

    echo "<table border='1' style='width:100%; border-collapse:collapse; text-align:center;'>
            <tr style='background:#f4f4f4;'>
                <th style='padding:10px;'>ID</th>
                <th>가맹점명</th>
                <th>결제금액</th>
                <th>상태</th>
                <th>결제일시</th>
            </tr>";

    while ($data = sql_fetch_array($result)) {
        $status = $data['payment_status'] ?? '';
        $status_color = ($status === 'SUCCESS') ? 'green' : 'red';

        echo "<tr>
                <td style='padding:10px;'>" . (int)($data['id'] ?? 0) . "</td>
                <td>" . get_text($data['merchant_name'] ?? '') . "</td>
                <td>" . number_format((float)($data['amount'] ?? 0)) . "원</td>
                <td style='color:{$status_color}; font-weight:bold;'>" . get_text($status) . "</td>
                <td>" . get_text($data['created_at'] ?? '') . "</td>
              </tr>";
    }
    echo "</table>";
    // 메인 컨테이너 닫기
    echo "</div>";
} else {
    // 관리자 외 사용자: 상세 리스트 노출 금지(민감한 결제 원장 정보 보호)
    echo "<div style='background:#fff3cd; border:1px solid #ffeeba; padding:12px; border-radius:5px; margin-top:15px;'>";
    echo "<b>권한 안내:</b> 결제 상세 내역은 관리자만 조회할 수 있습니다.";
    echo "</div>";
    echo "</div>"; // 메인 컨테이너 닫기
}

include_once(G5_PATH.'/tail.sub.php'); // 그누보드 하단 레이아웃
?>
