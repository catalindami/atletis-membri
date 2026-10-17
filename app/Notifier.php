<?php
declare(strict_types=1);

namespace App;

/**
 * Logica notificărilor, rulată zilnic de cron/notifications.php:
 *  1. email către fiecare membru cu N zile înainte de expirarea contribuției;
 *  2. rezumat săptămânal pentru admin.
 */
final class Notifier
{
    /** @var callable(string):void */
    private $out;

    public function __construct(?callable $out = null)
    {
        $this->out = $out ?? static function (string $line): void {
            echo $line, PHP_EOL;
        };
    }

    private function log(string $line): void
    {
        ($this->out)('[' . date('Y-m-d H:i:s') . '] ' . $line);
    }

    /** Întoarce numărul de emailuri trimise. */
    public function sendMemberReminders(): int
    {
        $days = (int) config('notifications.days_before', 7);
        // De la azi până la azi+N: dacă cron-ul n-a rulat într-o zi, membrul primește totuși emailul.
        $members = Members::expiringBetween(0, $days);
        $sent = 0;

        foreach ($members as $m) {
            if (empty($m['email'])) {
                $this->log("Sărit (fără email): {$m['last_name']} {$m['first_name']}");
                continue;
            }
            if ($this->alreadySent('subscription_reminder', (int) $m['id'], $m['subscription_expires_on'])) {
                continue;
            }

            [$subject, $text, $html] = $this->memberReminderEmail($m);
            try {
                Mailer::send($m['email'], $subject, $text, $html);
                $this->record('subscription_reminder', (int) $m['id'], $m['subscription_expires_on'], $m['email'], 'sent');
                $this->log("Trimis memento către {$m['email']} (expiră {$m['subscription_expires_on']})");
                $sent++;
            } catch (\RuntimeException $e) {
                $this->record('subscription_reminder', (int) $m['id'], $m['subscription_expires_on'], $m['email'], 'failed', $e->getMessage());
                $this->log('EROARE: ' . $e->getMessage());
            }
        }
        return $sent;
    }

    /** Trimite rezumatul dacă azi e ziua stabilită (sau dacă $force). */
    public function sendWeeklySummary(bool $force = false): bool
    {
        $day = (int) config('notifications.weekly_summary_day', 1);
        if (!$force && (int) date('N') !== $day) {
            return false;
        }
        $to = (string) config('notifications.admin_email', '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->log('EROARE: notifications.admin_email nu este setat corect în config.php');
            return false;
        }
        $todayStr = today()->format('Y-m-d');
        if (!$force && $this->alreadySent('weekly_summary', null, $todayStr)) {
            return false;
        }

        $horizon  = (int) config('notifications.summary_horizon_days', 14);
        $medHoriz = (int) config('notifications.medical_horizon_days', 30);
        $expiring = Members::expiringBetween(0, $horizon);
        $expired  = Members::expired();
        $medical  = Members::medicalExpiringWithin($medHoriz);

        [$subject, $text, $html] = $this->summaryEmail($expiring, $expired, $medical, $horizon, $medHoriz);
        try {
            Mailer::send($to, $subject, $text, $html);
            $this->record('weekly_summary', null, $todayStr, $to, 'sent');
            $this->log("Trimis rezumatul săptămânal către $to");
            return true;
        } catch (\RuntimeException $e) {
            $this->record('weekly_summary', null, $todayStr, $to, 'failed', $e->getMessage());
            $this->log('EROARE: ' . $e->getMessage());
            return false;
        }
    }

    private function alreadySent(string $type, ?int $memberId, string $refDate): bool
    {
        $sql = 'SELECT 1 FROM notification_log WHERE type = ? AND ref_date = ? AND status = "sent" AND '
            . ($memberId === null ? 'member_id IS NULL' : 'member_id = ?') . ' LIMIT 1';
        $params = [$type, $refDate];
        if ($memberId !== null) {
            $params[] = $memberId;
        }
        return (bool) Db::run($sql, $params)->fetchColumn();
    }

    private function record(string $type, ?int $memberId, string $refDate, string $to, string $status, ?string $error = null): void
    {
        Db::run(
            'INSERT INTO notification_log (member_id, type, ref_date, recipient, status, error) VALUES (?, ?, ?, ?, ?, ?)',
            [$memberId, $type, $refDate, $to, $status, $error ? mb_substr($error, 0, 500) : null]
        );
    }

    private function memberReminderEmail(array $m): array
    {
        $club    = (string) config('mail.from_name', 'ACS Atletis');
        $date    = fmt_date($m['subscription_expires_on']);
        $days    = days_until($m['subscription_expires_on']);
        $when    = $days === 0 ? 'astăzi' : ($days === 1 ? 'mâine' : 'în ' . zile((int) $days));
        $replyTo = (string) config('mail.reply_to', config('mail.from_email'));

        $subject = "Contribuția ta la $club expiră $when ($date)";
        $text = "Bună, {$m['first_name']},\n\n"
            . "Îți reamintim că valabilitatea contribuției anuale la $club expiră $when, pe $date.\n\n"
            . "Pentru reînnoire, te rugăm să achiți contribuția pentru anul următor. "
            . "Dacă ai plătit deja, ignoră acest mesaj.\n\n"
            . "Pentru întrebări, răspunde la acest email sau scrie-ne la $replyTo.\n\n"
            . "Sportiv,\nEchipa $club\n";

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1f2937;max-width:560px">'
            . '<p>Bună, ' . e($m['first_name']) . ',</p>'
            . '<p>Îți reamintim că valabilitatea contribuției anuale la <strong>' . e($club) . '</strong> expiră '
            . e($when) . ', pe <strong>' . e($date) . '</strong>.</p>'
            . '<p>Pentru reînnoire, te rugăm să achiți contribuția pentru anul următor. Dacă ai plătit deja, ignoră acest mesaj.</p>'
            . '<p>Pentru întrebări, răspunde la acest email sau scrie-ne la <a href="mailto:' . e($replyTo) . '">' . e($replyTo) . '</a>.</p>'
            . '<p>Sportiv,<br>Echipa ' . e($club) . '</p></div>';

        return [$subject, $text, $html];
    }

    private function summaryEmail(array $expiring, array $expired, array $medical, int $horizon, int $medHoriz): array
    {
        $appUrl  = rtrim((string) config('app.url', ''), '/');
        $subject = sprintf(
            'Rezumat membri: %d expiră în %s, %d expirate',
            count($expiring), zile($horizon), count($expired)
        );

        $sections = [
            ['Contribuții care expiră în următoarele ' . zile($horizon), $expiring, 'subscription_expires_on', $horizon],
            ['Contribuții expirate (membri activi, nereînnoite)', $expired, 'subscription_expires_on', $horizon],
            ['Vize medicale expirate sau care expiră în ' . zile($medHoriz), $medical, 'medical_expires_on', $medHoriz],
        ];

        $text = 'Rezumat săptămânal – ' . today()->format('d.m.Y') . "\n\n";
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1f2937;max-width:640px">'
            . '<h2 style="font-size:18px;margin:0 0 12px">Rezumat săptămânal – ' . e(today()->format('d.m.Y')) . '</h2>';

        foreach ($sections as [$title, $rows, $field, $soonDays]) {
            $text .= $title . ' (' . count($rows) . "):\n";
            $html .= '<h3 style="font-size:15px;margin:20px 0 6px">' . e($title) . ' (' . count($rows) . ')</h3>';
            if (!$rows) {
                $text .= "  – niciunul\n\n";
                $html .= '<p style="color:#6b7280;margin:0">Niciunul.</p>';
                continue;
            }
            $html .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px">';
            foreach ($rows as $r) {
                $name = $r['last_name'] . ' ' . $r['first_name'];
                $contact = $r['email'] ?: ($r['phone'] ?: 'fără contact');
                $text .= sprintf("  – %s, %s (%s) – %s\n", $name, fmt_date($r[$field]), expiry_label($r[$field], $soonDays), $contact);
                $html .= '<tr style="border-bottom:1px solid #e5e7eb">'
                    . '<td>' . e($name) . '</td>'
                    . '<td style="white-space:nowrap">' . e(fmt_date($r[$field])) . '</td>'
                    . '<td style="color:#6b7280">' . e(expiry_label($r[$field], $soonDays)) . '</td>'
                    . '<td>' . e($contact) . '</td></tr>';
            }
            $html .= '</table>';
            $text .= "\n";
        }

        if ($appUrl) {
            $text .= "Aplicația: $appUrl\n";
            $html .= '<p style="margin-top:24px"><a href="' . e($appUrl) . '">Deschide aplicația</a></p>';
        }
        $html .= '</div>';

        return [$subject, $text, $html];
    }
}
