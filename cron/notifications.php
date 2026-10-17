<?php
/**
 * Rulează zilnic din cPanel → Cron Jobs, de exemplu la 08:00:
 *
 *   /usr/local/bin/php /home/UTILIZATOR/membri-app/cron/notifications.php >> /home/UTILIZATOR/membri-app/storage/logs/cron.log 2>&1
 *
 * Opțiuni pentru test manual (din Terminal):
 *   --force-summary   trimite rezumatul acum, indiferent de zi
 *   --summary-only    nu trimite mementouri către membri
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Notifier;

$args = array_slice($argv, 1);
$notifier = new Notifier();

try {
    if (!in_array('--summary-only', $args, true)) {
        $count = $notifier->sendMemberReminders();
        echo '[' . date('Y-m-d H:i:s') . "] Mementouri trimise: $count", PHP_EOL;
    }
    $notifier->sendWeeklySummary(in_array('--force-summary', $args, true));
} catch (\Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] EROARE FATALĂ: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
