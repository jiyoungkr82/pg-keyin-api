<?php
include_once('./common.php');
include_once(G5_LIB_PATH.'/keyin.lib.php');

if (!$is_member) {
    alert('로그인이 필요한 페이지입니다.', G5_BBS_URL.'/login.php');
}

$result = get_session('ss_keyin_pay_result');
set_session('ss_keyin_pay_result', '');

if (!is_array($result) || !isset($result['success'])) {
    $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
    if ($pid > 0 && !empty($member['mb_id'])) {
        $row = sql_fetch("
            SELECT *
            FROM g5_shop_payment
            WHERE id = '{$pid}'
              AND mb_id = '".sql_escape_string($member['mb_id'])."'
            LIMIT 1
        ");
        $result = keyin_result_from_row($row);
    }
}

if (!is_array($result) || !isset($result['success'])) {
    alert('결제 결과 정보가 없습니다. 다시 결제해 주세요.', keyin_url('keyin_pay.php'));
}

$g5['title'] = $result['success'] ? '결제 완료' : '결제 실패';
include_once(G5_PATH.'/head.sub.php');

$is_success = !empty($result['success']);
$box_bg = $is_success ? '#ecfdf5' : '#fef2f2';
$box_border = $is_success ? '#a7f3d0' : '#fecaca';
$title_color = $is_success ? '#047857' : '#b91c1c';
$title_text = $is_success ? '결제가 완료되었습니다' : '결제에 실패했습니다';
$icon = $is_success ? '✓' : '✕';
?>

<div class="keyin-result" style="max-width:560px;margin:0 auto;padding:24px 20px;font-family:sans-serif;">
    <div style="background:<?php echo $box_bg; ?>;border:1px solid <?php echo $box_border; ?>;border-radius:12px;padding:28px 24px;text-align:center;margin-bottom:24px;">
        <div style="font-size:48px;line-height:1;color:<?php echo $title_color; ?>;"><?php echo $icon; ?></div>
        <h2 style="margin:12px 0 8px;color:<?php echo $title_color; ?>;"><?php echo $title_text; ?></h2>
        <p style="margin:0;color:#444;"><?php echo get_text($result['message'] ?? ''); ?></p>
        <?php if (!$is_success && !empty($result['error_code'])) { ?>
        <p style="margin:8px 0 0;font-size:13px;color:#666;">에러코드: <?php echo get_text($result['error_code']); ?></p>
        <?php } ?>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:15px;">
        <tbody>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;width:38%;color:#666;">결제금액</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;font-weight:bold;">
                <?php echo number_format((int)($result['amount'] ?? 0)); ?>원
            </td>
        </tr>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;color:#666;">상품명</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;"><?php echo get_text($result['goods_name'] ?? ''); ?></td>
        </tr>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;color:#666;">구매자</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;"><?php echo get_text($result['buyer_name'] ?? ''); ?></td>
        </tr>
        <?php if (!empty($result['card_mask'])) { ?>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;color:#666;">카드번호</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;"><?php echo get_text($result['card_mask']); ?></td>
        </tr>
        <?php } ?>
        <?php if (!empty($result['installment'])) { ?>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;color:#666;">할부</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;">
                <?php echo ($result['installment'] === '00') ? '일시불' : get_text($result['installment']).'개월'; ?>
            </td>
        </tr>
        <?php } ?>
        <?php if ($is_success && !empty($result['approval_number'])) { ?>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;color:#666;">승인번호</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;font-weight:bold;color:#2563eb;">
                <?php echo get_text($result['approval_number']); ?>
            </td>
        </tr>
        <?php } ?>
        <?php if (!empty($result['order_no'])) { ?>
        <tr>
            <th style="text-align:left;padding:10px 8px;border-bottom:1px solid #eee;color:#666;">주문번호</th>
            <td style="padding:10px 8px;border-bottom:1px solid #eee;"><?php echo get_text($result['order_no']); ?></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>

    <div style="margin-top:28px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?php echo keyin_url('keyin_pay.php'); ?>"
           style="flex:1;min-width:140px;text-align:center;padding:12px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;">
            다시 결제하기
        </a>
        <a href="<?php echo keyin_url('my_dashboard.php'); ?>"
           style="flex:1;min-width:140px;text-align:center;padding:12px;background:#f3f4f6;color:#111;border-radius:8px;text-decoration:none;">
            대시보드 보기
        </a>
    </div>
</div>

<?php
include_once(G5_PATH.'/tail.sub.php');
