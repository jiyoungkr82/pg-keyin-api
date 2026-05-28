<?php
include_once('./common.php');
include_once(G5_LIB_PATH.'/keyin.lib.php');

if (!$is_member) {
    alert('로그인이 필요한 페이지입니다.', G5_BBS_URL.'/login.php');
}

$g5['title'] = '키인 결제';
include_once(G5_PATH.'/head.sub.php');

$token = get_token();
$cfg = keyin_load_config();
$merchant_name = $cfg['merchant_name'] ?? '';
$default_buyer = $member['mb_name'] ?: ($member['mb_nick'] ?: $member['mb_id']);
$default_phone = preg_replace('/\D/', '', (string)($member['mb_hp'] ?? ''));
$config_ok = keyin_is_configured();
?>

<div class="keyin-wrap" style="max-width:560px;margin:0 auto;padding:24px 20px;font-family:sans-serif;">
    <h2 style="margin:0 0 8px;">키인 결제</h2>
    <p style="color:#666;margin:0 0 20px;">카드 정보를 입력하고 결제를 진행합니다.</p>

    <?php if (!$config_ok) { ?>
    <div style="background:#fff3cd;border:1px solid #ffeeba;padding:12px;border-radius:6px;margin-bottom:16px;">
        <strong>API 설정 필요</strong><br>
        <code>data/keyin.config.php</code> 에 API Key, TID를 입력해 주세요.
    </div>
    <?php } ?>

    <form method="post" action="./keyin_pay_update.php" id="keyinPayForm" autocomplete="off" novalidate>
        <input type="hidden" name="token" value="<?php echo $token; ?>">

        <fieldset style="border:1px solid #ddd;border-radius:8px;padding:16px 18px;margin:0 0 16px;">
            <legend style="padding:0 8px;font-weight:bold;">주문 정보</legend>

            <label style="display:block;margin-bottom:12px;">
                <span style="display:block;margin-bottom:4px;font-size:14px;">결제금액 (원) <span style="color:#c00;">*</span></span>
                <input type="number" name="amount" min="100" step="1" required
                       style="width:100%;padding:10px;box-sizing:border-box;" placeholder="100 이상">
            </label>

            <label style="display:block;margin-bottom:12px;">
                <span style="display:block;margin-bottom:4px;font-size:14px;">상품명 <span style="color:#c00;">*</span></span>
                <input type="text" name="goods_name" maxlength="100" required
                       style="width:100%;padding:10px;box-sizing:border-box;" placeholder="예: 테스트상품">
            </label>

            <label style="display:block;margin-bottom:12px;">
                <span style="display:block;margin-bottom:4px;font-size:14px;">구매자명 <span style="color:#c00;">*</span></span>
                <input type="text" name="buyer_name" maxlength="50" required
                       value="<?php echo get_text($default_buyer); ?>"
                       style="width:100%;padding:10px;box-sizing:border-box;">
            </label>

            <label style="display:block;margin-bottom:12px;">
                <span style="display:block;margin-bottom:4px;font-size:14px;">구매자 연락처</span>
                <input type="text" name="buyer_phone" maxlength="20"
                       value="<?php echo get_text($default_phone); ?>"
                       style="width:100%;padding:10px;box-sizing:border-box;" placeholder="01012345678">
            </label>

            <label style="display:block;">
                <span style="display:block;margin-bottom:4px;font-size:14px;">구매자 이메일</span>
                <input type="email" name="buyer_email" maxlength="100"
                       value="<?php echo get_text($member['mb_email'] ?? ''); ?>"
                       style="width:100%;padding:10px;box-sizing:border-box;" placeholder="example@email.com">
            </label>
        </fieldset>

        <fieldset style="border:1px solid #ddd;border-radius:8px;padding:16px 18px;margin:0 0 20px;">
            <legend style="padding:0 8px;font-weight:bold;">카드 정보</legend>

            <label style="display:block;margin-bottom:12px;">
                <span style="display:block;margin-bottom:4px;font-size:14px;">카드번호 <span style="color:#c00;">*</span></span>
                <input type="text" name="card_no" inputmode="numeric" maxlength="19" required
                       style="width:100%;padding:10px;box-sizing:border-box;" placeholder="숫자만 15~16자리">
            </label>

            <div style="display:flex;gap:12px;">
                <label style="flex:1;">
                    <span style="display:block;margin-bottom:4px;font-size:14px;">유효기간 (YYMM) <span style="color:#c00;">*</span></span>
                    <input type="text" name="expire_yymm" inputmode="numeric" maxlength="4" required
                           style="width:100%;padding:10px;box-sizing:border-box;" placeholder="2612">
                </label>
                <label style="flex:1;">
                    <span style="display:block;margin-bottom:4px;font-size:14px;">할부 <span style="color:#c00;">*</span></span>
                    <select name="installment" required style="width:100%;padding:10px;box-sizing:border-box;">
                        <option value="00">일시불 (00)</option>
                        <?php for ($i = 2; $i <= 12; $i++) {
                            $v = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                            echo '<option value="'.$v.'">'.$i.'개월</option>';
                        } ?>
                    </select>
                </label>
            </div>

            <p style="font-size:13px;color:#666;margin:16px 0 12px;">구인증(비인증) 결제 시 아래 항목이 필수입니다.</p>

            <div style="display:flex;gap:12px;">
                <label style="flex:1;">
                    <span style="display:block;margin-bottom:4px;font-size:14px;">카드 비밀번호 앞 2자리 <span style="color:#c00;">*</span></span>
                    <input type="password" name="cert_pw" inputmode="numeric" maxlength="2" required
                           autocomplete="off"
                           style="width:100%;padding:10px;box-sizing:border-box;" placeholder="●●">
                </label>
                <label style="flex:2;">
                    <span style="display:block;margin-bottom:4px;font-size:14px;">생년월일 6자리 / 사업자번호 10자리 <span style="color:#c00;">*</span></span>
                    <input type="password" name="cert_no" inputmode="numeric" maxlength="10" required
                           autocomplete="off"
                           style="width:100%;padding:10px;box-sizing:border-box;" placeholder="예: 900101 또는 1234567890">
                </label>
            </div>
        </fieldset>

        <?php if ($merchant_name) { ?>
        <p style="font-size:13px;color:#888;margin:0 0 16px;">가맹점: <?php echo get_text($merchant_name); ?></p>
        <?php } ?>

        <button type="submit" id="btnPay"
                style="width:100%;padding:14px;font-size:16px;font-weight:bold;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;">
            결제하기
        </button>
    </form>

    <p style="margin-top:16px;font-size:13px;">
        <a href="./my_dashboard.php">← 대시보드로 돌아가기</a>
    </p>
</div>

<script>
document.getElementById('keyinPayForm').addEventListener('submit', function () {
    var btn = document.getElementById('btnPay');
    btn.disabled = true;
    btn.textContent = '결제 처리 중...';
});
</script>

<?php
include_once(G5_PATH.'/tail.sub.php');
