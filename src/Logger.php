<?php

class Logger {
    /**
     * INFO 등급 로그 기록 (정상 흐름용)
     */
    public static function info($message, $context = []) {
        self::write('INFO', $message, $context);
    }

    /**
     * ERROR 등급 로그 기록 (치명적 오류용)
     */
    public static function error($message, $context = []) {
        self::write('ERROR', $message, $context);
    }

    /**
     * WARN 등급 로그 기록 (경고용)
     */
    public static function warn($message, $context = []) {
        self::write('WARN', $message, $context);
    }

    /**
     * 실제 로그 파일에 쓰는 내부 메서드
     */
    private static function write($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        
        // 로그 포맷 예시: [2026-06-01 10:45:00] [INFO] 결제 성공 {"amount": 50000}
        $logMessage = "[$timestamp] [$level] $message";
        if (!empty($context)) {
            $logMessage .= " " . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $logMessage .= PHP_EOL;

        // 로그를 저장할 logs 폴더 경로 지정
        $logDir = dirname(__DIR__) . '/logs';
        
        // [실무 팁] logs 폴더가 없으면 자동으로 생성해 주는 안전장치
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // 하루 단위로 로그 파일 분할 생성 (예: app_2026-06-01.log)
        $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';

        // 파일 끝에 내용 추가 (Append)
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
