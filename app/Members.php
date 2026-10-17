<?php
declare(strict_types=1);

namespace App;

/** Toate operațiile pe tabela members. */
final class Members
{
    public const FIELDS = [
        'first_name', 'last_name', 'birth_date', 'gender', 'email', 'phone', 'address',
        'category', 'coach', 'joined_on', 'strava_url', 'medical_expires_on',
        'subscription_expires_on', 'notes', 'is_active',
    ];

    private const DATE_FIELDS = ['birth_date', 'joined_on', 'medical_expires_on', 'subscription_expires_on'];

    public const STATUS_FILTERS = [
        ''         => 'Toți membrii activi',
        'active'   => 'Contribuție la zi',
        'expiring' => 'Expiră în curând',
        'expired'  => 'Contribuție expirată',
        'none'     => 'Fără dată de contribuție',
        'inactive' => 'Membri retrași',
        'all'      => 'Toți (inclusiv retrași)',
    ];

    public static function find(int $id): ?array
    {
        $row = Db::run('SELECT * FROM members WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    /**
     * Listă cu căutare și filtre.
     * $status: vezi STATUS_FILTERS. $sort: name | expiry | joined
     */
    public static function search(string $q = '', string $status = '', string $sort = 'name', int $soonDays = 7): array
    {
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(CONCAT(first_name, " ", last_name) LIKE :q1 OR CONCAT(last_name, " ", first_name) LIKE :q2
                         OR email LIKE :q3 OR phone LIKE :q4 OR category LIKE :q5 OR coach LIKE :q6)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $k) {
                $params[$k] = $like;
            }
        }

        switch ($status) {
            case 'all':
                break;
            case 'inactive':
                $where[] = 'is_active = 0';
                break;
            case 'active':
                $where[] = 'is_active = 1 AND subscription_expires_on >= CURDATE()';
                break;
            case 'expiring':
                $where[] = 'is_active = 1 AND subscription_expires_on BETWEEN CURDATE() AND (CURDATE() + INTERVAL :soon DAY)';
                $params['soon'] = $soonDays;
                break;
            case 'expired':
                $where[] = 'is_active = 1 AND subscription_expires_on < CURDATE()';
                break;
            case 'none':
                $where[] = 'is_active = 1 AND subscription_expires_on IS NULL';
                break;
            default:
                $where[] = 'is_active = 1';
        }

        $order = match ($sort) {
            'expiry' => 'subscription_expires_on IS NULL, subscription_expires_on ASC, last_name, first_name',
            'joined' => 'joined_on IS NULL, joined_on DESC, last_name, first_name',
            default  => 'last_name, first_name',
        };

        $sql = 'SELECT * FROM members'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY ' . $order;

        return Db::run($sql, $params)->fetchAll();
    }

    public static function stats(int $soonDays = 7): array
    {
        return Db::run(
            'SELECT
                SUM(is_active = 1) AS active_members,
                SUM(is_active = 1 AND subscription_expires_on >= CURDATE()) AS paid,
                SUM(is_active = 1 AND subscription_expires_on BETWEEN CURDATE() AND (CURDATE() + INTERVAL ? DAY)) AS expiring,
                SUM(is_active = 1 AND subscription_expires_on < CURDATE()) AS expired,
                SUM(is_active = 1 AND subscription_expires_on IS NULL) AS no_date,
                SUM(is_active = 1 AND medical_expires_on < CURDATE()) AS medical_expired
             FROM members',
            [$soonDays]
        )->fetch() ?: [];
    }

    /** Membri activi a căror contribuție expiră între azi și azi + N zile. */
    public static function expiringBetween(int $fromDays, int $toDays): array
    {
        return Db::run(
            'SELECT * FROM members
             WHERE is_active = 1
               AND subscription_expires_on BETWEEN (CURDATE() + INTERVAL ? DAY) AND (CURDATE() + INTERVAL ? DAY)
             ORDER BY subscription_expires_on, last_name, first_name',
            [$fromDays, $toDays]
        )->fetchAll();
    }

    public static function expired(): array
    {
        return Db::run(
            'SELECT * FROM members
             WHERE is_active = 1 AND subscription_expires_on < CURDATE()
             ORDER BY subscription_expires_on DESC, last_name, first_name'
        )->fetchAll();
    }

    public static function medicalExpiringWithin(int $days): array
    {
        return Db::run(
            'SELECT * FROM members
             WHERE is_active = 1 AND medical_expires_on IS NOT NULL
               AND medical_expires_on <= (CURDATE() + INTERVAL ? DAY)
             ORDER BY medical_expires_on, last_name, first_name',
            [$days]
        )->fetchAll();
    }

    /**
     * Curăță și validează datele din formular.
     * Întoarce [datele curate, erori pe câmp].
     */
    public static function validate(array $input): array
    {
        $data = [];
        $errors = [];

        foreach (self::FIELDS as $field) {
            $value = $input[$field] ?? null;
            $data[$field] = is_string($value) ? trim($value) : $value;
        }

        foreach (['first_name' => 'Prenumele', 'last_name' => 'Numele'] as $field => $label) {
            if ($data[$field] === '' || $data[$field] === null) {
                $errors[$field] = $label . ' este obligatoriu.';
            } elseif (mb_strlen($data[$field]) > 100) {
                $errors[$field] = $label . ' este prea lung.';
            }
        }

        foreach (self::DATE_FIELDS as $field) {
            if ($data[$field] === '' || $data[$field] === null) {
                $data[$field] = null;
                continue;
            }
            $date = parse_date($data[$field]);
            if (!$date || (int) $date->format('Y') < 1900 || (int) $date->format('Y') > 2200) {
                $errors[$field] = 'Data nu este validă.';
            } else {
                $data[$field] = $date->format('Y-m-d');
            }
        }
        if ($data['birth_date'] && !isset($errors['birth_date']) && $data['birth_date'] > today()->format('Y-m-d')) {
            $errors['birth_date'] = 'Data nașterii nu poate fi în viitor.';
        }

        $data['gender'] = in_array($data['gender'], ['M', 'F'], true) ? $data['gender'] : null;

        if ($data['email'] !== '' && $data['email'] !== null) {
            $data['email'] = mb_strtolower($data['email']);
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Adresa de email nu este validă.';
            }
        }

        if ($data['phone'] && !preg_match('/^[0-9 +().\-\/]{6,40}$/', $data['phone'])) {
            $errors['phone'] = 'Numărul de telefon conține caractere nepermise.';
        }

        if ($data['strava_url'] !== '' && $data['strava_url'] !== null) {
            $urlValue = $data['strava_url'];
            if (!preg_match('~^https?://~i', $urlValue)) {
                $urlValue = 'https://' . $urlValue;
            }
            $host = strtolower((string) parse_url($urlValue, PHP_URL_HOST));
            if (!filter_var($urlValue, FILTER_VALIDATE_URL) || !preg_match('/(^|\.)strava\.(com|app\.link)$/', $host)) {
                $errors['strava_url'] = 'Introdu linkul profilului de Strava (ex. https://www.strava.com/athletes/12345).';
            } else {
                $data['strava_url'] = preg_replace('~^http://~i', 'https://', $urlValue);
            }
        }

        foreach (['address' => 255, 'category' => 100, 'coach' => 100, 'email' => 190] as $field => $max) {
            if ($data[$field] && mb_strlen($data[$field]) > $max) {
                $errors[$field] = 'Textul este prea lung (maxim ' . $max . ' caractere).';
            }
        }

        $data['is_active'] = !empty($input['is_active']) ? 1 : 0;

        foreach ($data as $k => $v) {
            if ($v === '') {
                $data[$k] = null;
            }
        }

        return [$data, $errors];
    }

    public static function create(array $data): int
    {
        $cols = self::FIELDS;
        $sql = 'INSERT INTO members (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')';
        Db::run($sql, array_intersect_key($data, array_flip($cols)));
        return (int) Db::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $set = implode(', ', array_map(static fn ($c) => "$c = :$c", self::FIELDS));
        $params = array_intersect_key($data, array_flip(self::FIELDS));
        $params['id'] = $id;
        Db::run("UPDATE members SET $set WHERE id = :id", $params);
    }

    /**
     * Prelungește contribuția cu un an: de la data actuală de expirare,
     * sau de azi dacă a expirat deja / nu era setată.
     */
    public static function renew(int $id): ?string
    {
        $member = self::find($id);
        if (!$member) {
            return null;
        }
        $current = parse_date($member['subscription_expires_on']);
        $base = ($current && $current >= today()) ? $current : today();
        $new = $base->modify('+1 year')->format('Y-m-d');
        Db::run('UPDATE members SET subscription_expires_on = ? WHERE id = ?', [$new, $id]);
        return $new;
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM members WHERE id = ?', [$id]);
    }
}
