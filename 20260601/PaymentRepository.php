<?php
class PaymentRepository
{
  public static function insert(array $row)
  {
    $pdo = Database::pdo();
    $sql = 'INSERT INTO keyin_payment (
          tid, order_no, approval_number, approved_at, 
          amount, goods_name, buyer_name, card_masked,
          receipt_url, result_message, status, created_at
        ) VALUES (
            :tid, :order_no, :approval_number, :approved_at, 
            :amount, :goods_name, :buyer_name, :card_masked,
            :receipt_url, :result_message, :status, :created_at
        )';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':tid' => $row['tid'] ?? '',
      ':order_no' => $row['order_no'] ?? '',
      ':approval_number' => $row['approval_number'] ?? '',
      ':approved_at' => $row['approved_at'] ?? null,
      ':amount' => (int)($row['amount'] ?? 0),
      ':goods_name' => $row['goods_name'] ?? '',
      ':buyer_name' => $row['buyer_name'] ?? '',
      ':card_masked' => $row['card_masked'] ?? '',
      ':receipt_url' => $row['receipt_url'] ?? '',
      ':result_message' => $row['result_message'] ?? '',
      ':status' => $row['status'] ?? 'FAIL',
      ':created_at' => date('Y-m-d H:i:s'),
    ]);
    return (int)$pdo->lastInsertId();
  }

}