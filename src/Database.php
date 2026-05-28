<?php

class Database
{
    /** @var PDO|null */
    private static $pdo = null;

    public static function pdo()
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = keyin_config('db.host', 'localhost');
        $name = keyin_config('db.name', '');
        $user = keyin_config('db.user', 'root');
        $pass = keyin_config('db.pass', '');
        $charset = keyin_config('db.charset', 'utf8mb4');

        $dsn = 'mysql:host='.$host.';dbname='.$name.';charset='.$charset;
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }
}
