<?php

class PaymentRepository
{
    public static function insert(array $row)
    {
        $merchantName = trim((string)($row['merchant_name'] ?? ''));
        if ($merchantName === '') {
            $merchantName = trim((string)keyin_config('keyin.merchant_name', ''));
        }

        $sql = 'INSERT INTO g5_shop_payment (
            merchant_name, goods_name, buyer_name, amount,
            payment_status, api_status, order_no, approval_number,
            error_code, error_message, mb_id, created_at
        ) VALUES (
            :merchant_name, :goods_name, :buyer_name, :amount,
            :payment_status, :api_status, :order_no, :approval_number,
            :error_code, :error_message, :mb_id, :created_at
        )';

        $pdo = Database::pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':merchant_name' => $merchantName,
            ':goods_name' => (string)($row['goods_name'] ?? ''),
            ':buyer_name' => (string)($row['buyer_name'] ?? ''),
            ':amount' => (int)($row['amount'] ?? 0),
            ':payment_status' => (string)($row['payment_status'] ?? 'FAILED'),
            ':api_status' => (string)($row['api_status'] ?? ''),
            ':order_no' => (string)($row['order_no'] ?? ''),
            ':approval_number' => (string)($row['approval_number'] ?? ''),
            ':error_code' => (string)($row['error_code'] ?? ''),
            ':error_message' => (string)($row['error_message'] ?? ''),
            ':mb_id' => (string)($row['mb_id'] ?? ''),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function findById($id)
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM g5_shop_payment WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function toResultArray(array $row, array $extra = [])
    {
        $paymentStatus = (string)($row['payment_status'] ?? '');
        $message = (string)($row['error_message'] ?? '');
        if ($message === '') {
            $message = $paymentStatus === 'SUCCESS' ? '결제가 완료되었습니다.' : '결제에 실패했습니다.';
        }

        return array_merge([
            'success' => ($paymentStatus === 'SUCCESS'),
            'message' => $message,
            'error_code' => (string)($row['error_code'] ?? ''),
            'amount' => (int)($row['amount'] ?? 0),
            'goods_name' => (string)($row['goods_name'] ?? ''),
            'buyer_name' => (string)($row['buyer_name'] ?? ''),
            'order_no' => (string)($row['order_no'] ?? ''),
            'approval_number' => (string)($row['approval_number'] ?? ''),
            'card_mask' => '',
            'installment' => '',
            'http_code' => 0,
        ], $extra);
    }
}
