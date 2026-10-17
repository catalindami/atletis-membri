<?php
declare(strict_types=1);

namespace App;

/** Protecție împotriva trimiterii de formulare din alte site-uri. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(): void
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        if ($sent === '' || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('Sesiunea formularului a expirat. Întoarce-te, reîncarcă pagina și încearcă din nou.');
        }
    }
}
