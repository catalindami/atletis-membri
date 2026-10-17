<?php
declare(strict_types=1);

/** Escape pentru HTML. Folosit la orice afișare de date. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construiește un link intern: url('members', ['q' => 'ion']) */
function url(string $route = 'dashboard', array $params = []): string
{
    $query = http_build_query(['r' => $route] + $params);
    return 'index.php?' . $query;
}

function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Afișează un template din app/views, în layout-ul comun. */
function render(string $view, array $data = [], bool $withLayout = true): void
{
    $data['flashes'] = take_flashes();
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . '/app/views/' . $view . '.php';
    $content = ob_get_clean();
    if ($withLayout) {
        require APP_ROOT . '/app/views/layout.php';
    } else {
        echo $content;
    }
}

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

/** Transformă „YYYY-MM-DD” în obiect dată sau null dacă e gol/invalid. */
function parse_date(?string $value): ?DateTimeImmutable
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
        return null;
    }
    return $date;
}

/** Afișează data în format românesc: 17.10.2026 */
function fmt_date(?string $value): string
{
    $date = parse_date($value);
    return $date ? $date->format('d.m.Y') : '—';
}

function age(?string $birthDate): ?int
{
    $date = parse_date($birthDate);
    return $date ? $date->diff(today())->y : null;
}

/** Zile rămase până la o dată (negativ = a trecut). */
function days_until(?string $value): ?int
{
    $date = parse_date($value);
    if (!$date) {
        return null;
    }
    $diff = today()->diff($date);
    return $diff->invert ? -$diff->days : $diff->days;
}

/**
 * Starea unei date de expirare, pentru culori și filtre:
 * ok / soon / expired / none
 */
function expiry_status(?string $value, int $soonDays = 7): string
{
    $days = days_until($value);
    if ($days === null) {
        return 'none';
    }
    if ($days < 0) {
        return 'expired';
    }
    return $days <= $soonDays ? 'soon' : 'ok';
}

function expiry_label(?string $value, int $soonDays = 7): string
{
    $days = days_until($value);
    if ($days === null) {
        return 'Nesetat';
    }
    if ($days < 0) {
        return 'Expirat de ' . zile(-$days);
    }
    if ($days === 0) {
        return 'Expiră azi';
    }
    if ($days <= $soonDays) {
        return 'Expiră în ' . zile($days);
    }
    return 'Activ';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** „1 zi”, „5 zile”, „20 de zile” – acordul corect în română. */
function zile(int $n): string
{
    if ($n === 1) {
        return '1 zi';
    }
    $r = $n % 100;
    return $n . (($n >= 20 && ($r === 0 || $r >= 20)) ? ' de zile' : ' zile');
}
