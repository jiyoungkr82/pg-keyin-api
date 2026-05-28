<?php
/**
 * WAMP 기본 경로(http://localhost/study/) 접속 시 결제 페이지로 이동
 */
header('Location: public/pay.php', true, 302);
exit;
