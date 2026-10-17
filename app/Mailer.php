<?php
declare(strict_types=1);

namespace App;

use PHPMailer\PHPMailer\PHPMailer;

/** Trimite emailuri prin SMTP (contul din cPanel) sau le scrie într-un log, pentru test. */
final class Mailer
{
    /** @throws \RuntimeException dacă trimiterea eșuează */
    public static function send(string $to, string $subject, string $text, ?string $html = null): void
    {
        $transport = config('mail.transport', 'smtp');

        if ($transport === 'log') {
            $entry = sprintf(
                "===== %s =====\nCătre: %s\nSubiect: %s\n\n%s\n\n",
                date('Y-m-d H:i:s'), $to, $subject, $text
            );
            file_put_contents(APP_ROOT . '/storage/logs/mail.log', $entry, FILE_APPEND | LOCK_EX);
            return;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = (string) config('mail.host');
            $mail->Port       = (int) config('mail.port', 465);
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) config('mail.username');
            $mail->Password   = (string) config('mail.password');
            $mail->SMTPSecure = config('mail.encryption', 'ssl') === 'tls'
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;
            $mail->Timeout    = 20;

            $mail->setFrom((string) config('mail.from_email'), (string) config('mail.from_name'));
            if ($replyTo = config('mail.reply_to')) {
                $mail->addReplyTo((string) $replyTo);
            }
            $mail->addAddress($to);
            $mail->Subject = $subject;

            if ($html !== null) {
                $mail->isHTML(true);
                $mail->Body    = $html;
                $mail->AltBody = $text;
            } else {
                $mail->Body = $text;
            }
            $mail->send();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Emailul către ' . $to . ' nu a putut fi trimis: ' . $mail->ErrorInfo, 0, $e);
        }
    }
}
