<?php
require_once dirname(__DIR__).'/src/bootstrap.php';

$result = flash_get('result');

if (!is_array($result) || !isset($result['success'])) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        try {
            $row = PaymentRepository::findById($id);
            if ($row) {
                $result = PaymentRepository::toResultArray($row);
            }
        } catch (Throwable $e) {
            flash_set('error', '결제 내역을 불러올 수 없습니다.');
            keyin_redirect('pay.php');
        }
    }
}

if (!is_array($result) || !isset($result['success'])) {
    flash_set('error', '결제 결과 정보가 없습니다. 다시 결제해 주세요.');
    keyin_redirect('pay.php');
}

$ok = !empty($result['success']);
$title = $ok ? '결제 완료' : '결제 실패';

layout_header($title);
?>
<div class="result-box <?php echo $ok ? 'ok' : 'fail'; ?>">
    <div class="result-icon"><?php echo $ok ? '✓' : '✕'; ?></div>
    <h2><?php echo $ok ? '결제가 완료되었습니다' : '결제에 실패했습니다'; ?></h2>
    <p><?php echo h($result['message'] ?? ''); ?></p>
    <?php if (!$ok && !empty($result['error_code'])) { ?>
    <p class="hint">에러코드: <?php echo h($result['error_code']); ?></p>
    <?php } ?>
</div>

<table class="detail">
    <tr>
        <th>결제금액</th>
        <td><strong><?php echo number_format((int)($result['amount'] ?? 0)); ?>원</strong></td>
    </tr>
    <tr>
        <th>상품명</th>
        <td><?php echo h($result['goods_name'] ?? ''); ?></td>
    </tr>
    <tr>
        <th>구매자</th>
        <td><?php echo h($result['buyer_name'] ?? ''); ?></td>
    </tr>
    <?php if (!empty($result['card_mask'])) { ?>
    <tr>
        <th>카드번호</th>
        <td><?php echo h($result['card_mask']); ?></td>
    </tr>
    <?php } ?>
    <?php if (!empty($result['installment'])) { ?>
    <tr>
        <th>할부</th>
        <td><?php echo ($result['installment'] === '00') ? '일시불' : h($result['installment']).'개월'; ?></td>
    </tr>
    <?php } ?>
    <?php if ($ok && !empty($result['approval_number'])) { ?>
    <tr>
        <th>승인번호</th>
        <td style="color:#2563eb;font-weight:bold;"><?php echo h($result['approval_number']); ?></td>
    </tr>
    <?php } ?>
    <?php if (!empty($result['order_no'])) { ?>
    <tr>
        <th>주문번호</th>
        <td><?php echo h($result['order_no']); ?></td>
    </tr>
    <?php } ?>
</table>

<div class="actions">
    <a href="<?php echo h(keyin_url('pay.php')); ?>" class="btn btn-primary">다시 결제하기</a>
</div>
<?php
layout_footer();
