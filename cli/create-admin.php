<?php
/**
 * Creează sau resetează un cont de admin din Terminal:
 *   php cli/create-admin.php adresa@exemplu.ro
 * Parola se cere interactiv (nu apare pe ecran și nu rămâne în istoricul comenzilor).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Auth;
use App\Db;

$email = mb_strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Folosire: php cli/create-admin.php adresa@exemplu.ro\n");
    exit(1);
}

$password = getenv('ADMIN_PASSWORD') ?: '';
if ($password === '') {
    echo 'Parola nouă: ';
    system('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo PHP_EOL;
}
if ($problem = Auth::passwordProblem($password)) {
    fwrite(STDERR, $problem . PHP_EOL);
    exit(1);
}

$existing = Db::run('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
if ($existing) {
    Db::run('UPDATE users SET password_hash = ?, role = "admin", is_active = 1 WHERE id = ?', [
        password_hash($password, PASSWORD_DEFAULT), $existing,
    ]);
    echo "Parola pentru $email a fost resetată.\n";
} else {
    Auth::createUser($email, $password, 'admin');
    echo "Contul de admin $email a fost creat.\n";
}
