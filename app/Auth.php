<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    private const MAX_ATTEMPTS   = 5;     // încercări greșite permise...
    private const WINDOW_MINUTES = 15;    // ...în acest interval
    private const IDLE_TIMEOUT   = 7200;  // deconectare după 2 ore de inactivitate

    public static function startSession(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name('atletis_sess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        if (isset($_SESSION['user_id'])) {
            $last = (int) ($_SESSION['last_activity'] ?? 0);
            if ($last && time() - $last > self::IDLE_TIMEOUT) {
                self::logout();
                session_start();
                flash('info', 'Ai fost deconectat după o perioadă de inactivitate.');
            } else {
                $_SESSION['last_activity'] = time();
            }
        }
    }

    public static function user(): ?array
    {
        static $user = false;
        if ($user === false) {
            $user = null;
            if (!empty($_SESSION['user_id'])) {
                $row = Db::run(
                    'SELECT id, email, role, member_id FROM users WHERE id = ? AND is_active = 1',
                    [$_SESSION['user_id']]
                )->fetch();
                $user = $row ?: null;
            }
        }
        return $user;
    }

    public static function requireAdmin(): void
    {
        $user = self::user();
        if (!$user) {
            redirect('login');
        }
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            exit('Nu ai acces la această pagină.');
        }
    }

    public static function isLockedOut(string $email): bool
    {
        $row = Db::run(
            'SELECT
                SUM(ip = ?)    AS by_ip,
                SUM(email = ?) AS by_email
             FROM login_attempts
             WHERE attempted_at > (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)',
            [client_ip(), $email]
        )->fetch();
        return (int) $row['by_ip'] >= self::MAX_ATTEMPTS * 2
            || (int) $row['by_email'] >= self::MAX_ATTEMPTS;
    }

    /** Întoarce true dacă autentificarea a reușit. */
    public static function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        $user = Db::run(
            'SELECT id, password_hash, role FROM users WHERE email = ? AND is_active = 1',
            [$email]
        )->fetch();

        // Verificăm parola și când utilizatorul nu există, ca timpul de răspuns
        // să nu dezvăluie ce adrese sunt înregistrate.
        $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $user !== false;

        if (!$ok) {
            Db::run('INSERT INTO login_attempts (ip, email) VALUES (?, ?)', [client_ip(), $email]);
            return false;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT), $user['id'],
            ]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        Db::run('DELETE FROM login_attempts WHERE email = ?', [$email]);
        // curățăm din când în când încercările vechi
        Db::run('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Reguli minime pentru parole. Întoarce mesaj de eroare sau null. */
    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < 12) {
            return 'Parola trebuie să aibă cel puțin 12 caractere.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Parola trebuie să conțină litere și cifre.';
        }
        return null;
    }

    public static function createUser(string $email, string $password, string $role = 'admin', ?int $memberId = null): int
    {
        Db::run(
            'INSERT INTO users (email, password_hash, role, member_id) VALUES (?, ?, ?, ?)',
            [mb_strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role, $memberId]
        );
        return (int) Db::pdo()->lastInsertId();
    }

    public static function userCount(): int
    {
        return (int) Db::run('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
