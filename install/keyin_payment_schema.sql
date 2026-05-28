-- g5_shop_payment 테이블 (수동 적용용)
-- 이미 테이블이 있으면 필요한 컬럼만 ALTER 로 추가하세요.

CREATE TABLE IF NOT EXISTS `g5_shop_payment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_name` varchar(100) NOT NULL DEFAULT '',
  `goods_name` varchar(255) NOT NULL DEFAULT '',
  `buyer_name` varchar(100) NOT NULL DEFAULT '',
  `amount` int(11) NOT NULL DEFAULT 0,
  `payment_status` varchar(20) NOT NULL DEFAULT 'PENDING',
  `api_status` varchar(20) NOT NULL DEFAULT '',
  `order_no` varchar(64) NOT NULL DEFAULT '',
  `approval_number` varchar(64) NOT NULL DEFAULT '',
  `error_code` varchar(32) NOT NULL DEFAULT '',
  `error_message` varchar(255) NOT NULL DEFAULT '',
  `mb_id` varchar(20) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
