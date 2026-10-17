<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                config('db.host'),
                config('db.name'),
                config('db.charset', 'utf8mb4')
            );
            self::$pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Folosim ora României și în MySQL, ca datele să fie coerente cu PHP.
            $offset = (new \DateTimeImmutable())->format('P');
            self::$pdo->exec("SET time_zone = '" . $offset . "'");
        }
        return self::$pdo;
    }

    /** Rulează o interogare parametrizată și întoarce statement-ul. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
