<?php
class Database
{
  private static $pdo;

  public static function pdo()
  {
    if (self::$pdo !== null) {
      return self::$pdo;
    }
    $dsn = sprintf(
      'mysql:host=%s;dbname=%s;port=%s;charset=utf8mb4',
      DB_HOST, DB_NAME, DB_PORT
    );
    self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return self::$pdo;
  }

}