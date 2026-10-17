<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Lipsește config/config.php. Copiază config/config.example.php ca config.php și completează-l.');
}
$GLOBALS['config'] = require $configFile;

date_default_timezone_set(config('app.timezone', 'Europe/Bucharest'));
mb_internal_encoding('UTF-8');

$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

spl_autoload_register(static function (string $class): void {
    $map = [
        'PHPMailer\\PHPMailer\\' => APP_ROOT . '/lib/PHPMailer/',
        'App\\'                  => APP_ROOT . '/app/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

require APP_ROOT . '/app/helpers.php';

/** Citește o valoare din config cu notație „secțiune.cheie”. */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
