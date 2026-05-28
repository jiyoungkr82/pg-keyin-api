<?php

class KeyinApi
{
    private $cfg;

    public function __construct()
    {
        $this->cfg = keyin_config('keyin', []);
    }

    public function isConfigured()
    {
        $key = trim((string)($this->cfg['api_key'] ?? ''));
        $tid = trim((string)($this->cfg['tid'] ?? ''));
        if ($key === '' || $tid === '') {
            return false;
        }
        if ($key === 'ssp-your-api-key-here' || $tid === 'your-tid-here') {
            return false;
        }
        return true;
    }

    public function pay(array $payload)
    {
        return $this->request('POST', 'pay.php', $payload);
    }

    public function list(array $params = [])
    {
        return $this->request('GET', 'list.php', $params);
    }

    public static function mapPaymentStatus($apiStatus)
    {
        $map = [
            'approved' => 'SUCCESS',
            'failed' => 'FAILED',
            'pending' => 'PENDING',
            'cancelled' => 'CANCELLED',
        ];
        $apiStatus = strtolower((string)$apiStatus);
        return $map[$apiStatus] ?? 'FAILED';
    }

    public static function maskCardNo($cardNo)
    {
        $digits = preg_replace('/\D/', '', (string)$cardNo);
        $len = strlen($digits);
        if ($len < 8) {
            return str_repeat('*', $len);
        }
        return substr($digits, 0, 4).str_repeat('*', $len - 8).substr($digits, -4);
    }

    private function request($method, $endpoint, array $payload = [])
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'API Key 또는 TID가 설정되지 않았습니다.',
                'error_code' => 'CONFIG',
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'message' => 'PHP cURL 확장이 필요합니다.',
                'error_code' => 'CURL',
            ];
        }

        $apiBase = rtrim((string)($this->cfg['api_base'] ?? 'https://wspay.net/api/v1/keyin'), '/');
        $url = $apiBase.'/'.ltrim($endpoint, '/');

        if (strtoupper($method) === 'GET' && $payload) {
            $url .= (strpos($url, '?') === false ? '?' : '&').http_build_query($payload);
        }

        $headers = [
            'Content-Type: application/json',
            'X-API-Key: '.$this->cfg['api_key'],
            'X-TID: '.$this->cfg['tid'],
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $opts);
        $this->applySsl($ch);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return [
                'success' => false,
                'message' => '결제 API 통신 오류: '.$curlErr,
                'error_code' => 'NETWORK',
                'http_code' => $httpCode,
            ];
        }

        $result = json_decode($raw, true);
        if (!is_array($result)) {
            return [
                'success' => false,
                'message' => 'API 응답 형식이 올바르지 않습니다.',
                'error_code' => 'INVALID_JSON',
                'http_code' => $httpCode,
                'raw' => $raw,
            ];
        }

        $result['http_code'] = $httpCode;
        return $result;
    }

    private function applySsl($ch)
    {
        $sslVerify = !empty($this->cfg['ssl_verify']);
        $caBundle = trim((string)($this->cfg['ca_bundle'] ?? ''));

        if ($sslVerify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if ($caBundle !== '' && is_file($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }
}
