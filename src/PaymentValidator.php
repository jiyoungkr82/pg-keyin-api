<?php

class PaymentValidator
{
    public static function validate(array $input)
    {
        $errors = [];

        $amount = isset($input['amount']) ? (int)preg_replace('/\D/', '', (string)$input['amount']) : 0;
        if ($amount < 100) {
            $errors[] = '결제금액은 100원 이상이어야 합니다.';
        }

        $goodsName = trim((string)($input['goods_name'] ?? ''));
        if ($goodsName === '') {
            $errors[] = '상품명을 입력하세요.';
        }

        $buyerName = trim((string)($input['buyer_name'] ?? ''));
        if ($buyerName === '') {
            $errors[] = '구매자명을 입력하세요.';
        }

        $cardNo = preg_replace('/\D/', '', (string)($input['card_no'] ?? ''));
        if (!preg_match('/^\d{15,16}$/', $cardNo)) {
            $errors[] = '카드번호는 15~16자리 숫자여야 합니다.';
        }

        $expireYymm = preg_replace('/\D/', '', (string)($input['expire_yymm'] ?? ''));
        if (!preg_match('/^\d{4}$/', $expireYymm)) {
            $errors[] = '유효기간은 YYMM 형식(4자리)이어야 합니다.';
        }

        $installment = trim((string)($input['installment'] ?? ''));
        if (!preg_match('/^(00|0[2-9]|1[0-2])$/', $installment)) {
            $errors[] = '할부개월은 00(일시불) 또는 02~12만 가능합니다.';
        }

        $buyerPhone = preg_replace('/\D/', '', (string)($input['buyer_phone'] ?? ''));
        $buyerEmail = trim((string)($input['buyer_email'] ?? ''));
        if ($buyerEmail !== '' && !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '구매자 이메일 형식이 올바르지 않습니다.';
        }

        // 구인증 시에만 필요 — 비인증은 비워도 됨
        $certPw = preg_replace('/\D/', '', (string)($input['cert_pw'] ?? ''));
        if ($certPw !== '' && !preg_match('/^\d{2}$/', $certPw)) {
            $errors[] = '카드 비밀번호 앞 2자리는 숫자 2자리여야 합니다.';
        }

        $certNo = preg_replace('/\D/', '', (string)($input['cert_no'] ?? ''));
        if ($certNo !== '' && !preg_match('/^(\d{6}|\d{10})$/', $certNo)) {
            $errors[] = '생년월일 6자리 또는 사업자번호 10자리 형식이 올바르지 않습니다.';
        }

        return [
            'errors' => $errors,
            'amount' => $amount,
            'goods_name' => $goodsName,
            'buyer_name' => $buyerName,
            'buyer_phone' => $buyerPhone,
            'buyer_email' => $buyerEmail,
            'card_no' => $cardNo,
            'expire_yymm' => $expireYymm,
            'installment' => $installment,
            'cert_pw' => $certPw,
            'cert_no' => $certNo,
        ];
    }
}
