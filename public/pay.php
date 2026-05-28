<?php
require_once dirname(__DIR__).'/src/bootstrap.php';

$api = new KeyinApi();
$configOk = $api->isConfigured();
$merchantName = keyin_config('keyin.merchant_name', '');
$csrf = csrf_token();
$flashError = flash_get('error');

layout_header('키인 결제');
?>
<h1>키인 결제</h1>
<p class="sub">카드 정보를 입력하고 결제를 진행합니다.</p>

<?php if ($flashError) { ?>
<div class="alert alert-error"><?php echo h($flashError); ?></div>
<?php } ?>

<?php if (!$configOk) { ?>
<div class="alert alert-warn">
    <strong>API 설정 필요</strong><br>
    <code>config/config.local.php</code> 에 API Key, TID를 입력해 주세요.
</div>
<?php } ?>

<form method="post" action="<?php echo h(keyin_url('pay_process.php')); ?>" id="payForm" autocomplete="off" novalidate>
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">

    <fieldset>
        <legend>주문 정보</legend>
        <label>
            <span class="lbl">결제금액 (원) <span class="req">*</span></span>
            <input type="number" name="amount" min="100" step="1" required placeholder="100 이상">
        </label>
        <label>
            <span class="lbl">상품명 <span class="req">*</span></span>
            <input type="text" name="goods_name" maxlength="100" required placeholder="예: 테스트상품">
        </label>
        <label>
            <span class="lbl">구매자명 <span class="req">*</span></span>
            <input type="text" name="buyer_name" maxlength="50" required>
        </label>
        <label>
            <span class="lbl">구매자 연락처</span>
            <input type="text" name="buyer_phone" maxlength="20" placeholder="01012345678">
        </label>
        <label>
            <span class="lbl">구매자 이메일</span>
            <input type="email" name="buyer_email" maxlength="100" placeholder="example@email.com">
        </label>
    </fieldset>

    <fieldset>
        <legend>카드 정보</legend>
        <label>
            <span class="lbl">카드번호 <span class="req">*</span></span>
            <input type="text" name="card_no" inputmode="numeric" maxlength="19" required placeholder="15~16자리">
        </label>
        <div class="row">
            <label>
                <span class="lbl">유효기간 (YYMM) <span class="req">*</span></span>
                <input type="text" name="expire_yymm" inputmode="numeric" maxlength="4" required placeholder="2612">
            </label>
            <label>
                <span class="lbl">할부 <span class="req">*</span></span>
                <select name="installment" required>
                    <option value="00">일시불 (00)</option>
                    <?php for ($i = 2; $i <= 12; $i++) {
                        $v = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                        echo '<option value="'.h($v).'">'.$i.'개월</option>';
                    } ?>
                </select>
            </label>
        </div>
        <p class="hint">구인증 결제 시에만 아래 항목을 입력하세요. (비인증은 비워도 됩니다)</p>
        <div class="row">
            <label>
                <span class="lbl">카드 비밀번호 앞 2자리</span>
                <input type="password" name="cert_pw" inputmode="numeric" maxlength="2" autocomplete="off" placeholder="구인증 시">
            </label>
            <label class="wide">
                <span class="lbl">생년월일 6자리 / 사업자번호 10자리</span>
                <input type="password" name="cert_no" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="구인증 시">
            </label>
        </div>
    </fieldset>

    <?php if ($merchantName !== '') { ?>
    <p class="hint">가맹점: <?php echo h($merchantName); ?></p>
    <?php } ?>

    <button type="submit" class="btn btn-primary" id="btnPay"<?php echo $configOk ? '' : ' disabled'; ?>>결제하기</button>
</form>
<script>
document.getElementById('payForm').addEventListener('submit', function () {
    var btn = document.getElementById('btnPay');
    btn.disabled = true;
    btn.textContent = '결제 처리 중...';
});
</script>
<?php
layout_footer();
