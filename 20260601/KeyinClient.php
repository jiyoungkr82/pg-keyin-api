<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class KeyinClient
{
  private Client $client;

  public function __construct()
  {
    $this->client = new Client([
      'base_uri' => rtrim(KEYIN_API_BASE, '/') . '/',
      'timeout' => 30,
      'http_errors' => false,
    ]);
  }

  public function pay(array $payload): array
  {
    try {
      $response = $this->client->post('pay.php', [
        'headers' => [
          'Content-Type' => 'application/json',
          'X-API-Key' => KEYIN_API_KEY,
          'X-TID' => KEYIN_TID,
        ],
        'json' => $payload,
      ]);
    } catch (GuzzleException $e) {
      return [
        'success' => false,
        'message' => 'PG API 통신 실패: ' . $e->getMessage(),
        'error_code' => 'HTTP_CLIENT_ERROR',
      ];
    }

    $body = (string)$response->getBody();
    $httpCode = $response->getStatusCode();

    $result = json_decode($body, true);
    if (!is_array($result)) {
      return [
        'success' => false,
        'message' => 'PG 응답 JSON 파싱 오류',
        'error_code' => 'INVALID_JSON',
        'http_code' => $httpCode,
        'raw' => $body,
      ];
    }

    $result['http_code'] = $httpCode;
    return $result;
  }
}