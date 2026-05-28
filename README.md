# Key-in Payment (순수 PHP)

wspay 키인(수기) 결제 API 연동 — 그누보드 없이 동작합니다.

## 요구 사항

- PHP 7.4+ (8.x 권장)
- PHP 확장: `pdo_mysql`, `curl`, `json`, `session`
- MySQL / MariaDB — 테이블 `g5_shop_payment` (기존 스키마 사용)

## 설치

1. `config/config.example.php` 를 `config/config.local.php` 로 복사 후 DB·API 값 입력
2. DB에 `sql/schema.sql` 적용 (테이블이 이미 있으면 생략)
3. WAMP Apache에서 아래 URL로 접속

```
http://localhost/study/public/pay.php
```

DocumentRoot를 `public/` 으로 두는 것을 권장합니다.

## 로컬 SSL

WAMP에서 `SSL certificate problem` 발생 시 `config.local.php`:

```php
'ssl_verify' => false,  // 로컬만
```

운영 서버에서는 반드시 `'ssl_verify' => true`.

## 파일 구조

```
public/          웹 접근 (pay.php, pay_process.php, pay_result.php)
src/             PHP 클래스·부트스트랩
config/          설정 (local은 git 제외)
sql/             DB 스키마
storage/sessions/ 세션 파일
```

## 보안

- 로그인 없음 — 내부망·방화벽으로 접근 제한 권장
- 카드번호·`cert_pw`·`cert_no` 는 DB에 저장하지 않음
- `config/config.local.php` 는 커밋하지 마세요

## 브랜치

- `main`: 이 순수 PHP 프로젝트
- `gnuboard_keyin`: 그누보드 통합 버전 (참고용)
