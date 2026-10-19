<?php
declare(strict_types=1);

namespace App;

/**
 * Import de membri din CSV (exportat din Google Sheets sau Excel).
 *
 * Pași: parse() citește fișierul și recunoaște coloanele, plan() compară fiecare rând
 * cu membrii existenți (nou / actualizare / neschimbat / eroare), apply() scrie în bază.
 */
final class Import
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_ROWS  = 3000;

    /** Antetele șablonului, în ordinea din export. */
    public const TEMPLATE_HEADERS = [
        'Nume', 'Prenume', 'Data nașterii', 'Sex', 'Email', 'Telefon', 'Adresă', 'Categorie',
        'Antrenor', 'Data înscrierii', 'Strava', 'Contribuție până la', 'Activ', 'Observații',
    ];

    public const FIELD_LABELS = [
        'last_name' => 'Nume', 'first_name' => 'Prenume', 'full_name' => 'Nume complet',
        'birth_date' => 'Data nașterii', 'gender' => 'Sex', 'email' => 'Email', 'phone' => 'Telefon',
        'address' => 'Adresă', 'category' => 'Categorie', 'coach' => 'Antrenor',
        'joined_on' => 'Data înscrierii', 'strava_url' => 'Strava',
        'subscription_expires_on' => 'Contribuție până la', 'is_active' => 'Activ', 'notes' => 'Observații',
    ];

    /**
     * Antet normalizat (fără diacritice, litere mici, doar a-z0-9) → câmp.
     * Pentru fiecare câmp, primul antet găsit în fișier câștigă, în ordinea din listă.
     * Cheile care se termină cu * se potrivesc ca prefix.
     */
    private const ALIASES = [
        'last_name'  => ['nume', 'numefamilie', 'lastname', 'userlastname', 'surname'],
        'first_name' => ['prenume', 'firstname', 'userfirstname', 'givenname'],
        'full_name'  => ['numeprenume', 'numesiprenume', 'numecomplet', 'membru', 'membri', 'name', 'fullname'],
        'birth_date' => ['datanasterii*', 'datanastere*', 'birthdate', 'dataofbirth', 'dob'],
        'gender'     => ['sex', 'gen', 'gender'],
        'email'      => ['email', 'adresaemail', 'adresadeemail', 'mail', 'useremail', 'emailaddress'],
        'phone'      => ['telefon', 'nrtelefon', 'numartelefon', 'numardetelefon', 'tel', 'phone', 'mobil'],
        'address'    => ['adresa', 'domiciliu', 'address', 'localitate'],
        'category'   => ['categorie', 'grupa', 'category'],
        'coach'      => ['antrenor', 'coach'],
        'joined_on'  => ['datainscrierii', 'datainscriere', 'inscris*', 'membrudin', 'joined*', 'timestamp', 'marcajtemporal'],
        'strava_url' => ['strava*', 'profilstrava'],
        'subscription_expires_on' => ['contributie*', 'cotizatie*', 'abonament*', 'valabilpanala', 'activpanala', 'expira*', 'subscription*'],
        'is_active'  => ['activ', 'active', 'status'],
        'notes'      => ['observatii', 'alteobservatii', 'note', 'notes', 'mentiuni'],
    ];

    /**
     * Citește CSV-ul. Întoarce ['headers' => [...], 'map' => [index => câmp], 'rows' => [[linie, valori]...]]
     * @throws \RuntimeException cu mesaj pentru utilizator
     */
    public static function parse(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException('Fișierul este gol.');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            // Excel românesc salvează uneori în Windows-1250
            $converted = @iconv('Windows-1250', 'UTF-8//IGNORE', $raw);
            $raw = $converted !== false ? $converted : mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-2');
        }

        $firstLine = strtok($raw, "\r\n") ?: '';
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);
        if ($counts[$delimiter] === 0) {
            throw new \RuntimeException('Nu am putut recunoaște coloanele. Salvează fișierul ca CSV (valori separate prin virgulă).');
        }

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $headers = null;
        $rows = [];
        $line = 0;
        while (($cells = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            $line++;
            $cells = array_map(static fn ($c) => trim((string) $c), $cells);
            if ($headers === null) {
                if (count(array_filter($cells, static fn ($c) => $c !== '')) === 0) {
                    continue; // rânduri goale deasupra antetului
                }
                $headers = $cells;
                continue;
            }
            if (count(array_filter($cells, static fn ($c) => $c !== '')) === 0) {
                continue;
            }
            $rows[] = [$line, $cells];
            if (count($rows) > self::MAX_ROWS) {
                throw new \RuntimeException('Fișierul are prea multe rânduri (maxim ' . self::MAX_ROWS . ').');
            }
        }
        fclose($fh);

        if ($headers === null) {
            throw new \RuntimeException('Fișierul nu conține un rând de antet.');
        }

        $map = self::mapHeaders($headers);
        $fields = array_values($map);
        if (!(in_array('last_name', $fields, true) && in_array('first_name', $fields, true)) && !in_array('full_name', $fields, true)) {
            throw new \RuntimeException('Nu am găsit coloanele „Nume” și „Prenume” (sau „Nume Prenume”). Verifică primul rând al fișierului.');
        }

        return ['headers' => $headers, 'map' => $map, 'rows' => $rows];
    }

    /** @return array<int,string> index coloană → câmp */
    public static function mapHeaders(array $headers): array
    {
        $normalized = array_map([self::class, 'normalizeHeader'], $headers);
        $map = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $prefix = str_ends_with($alias, '*');
                $alias = rtrim($alias, '*');
                foreach ($normalized as $i => $h) {
                    if (isset($map[$i]) || $h === '') {
                        continue;
                    }
                    if ($h === $alias || ($prefix && str_starts_with($h, $alias))) {
                        $map[$i] = $field;
                        continue 3;
                    }
                }
            }
        }
        // „Nume Prenume” are sens doar dacă lipsesc coloanele separate
        if (in_array('last_name', $map, true) && in_array('first_name', $map, true)) {
            $map = array_filter($map, static fn ($f) => $f !== 'full_name');
        }
        ksort($map);
        return $map;
    }

    public static function normalizeHeader(string $h): string
    {
        return preg_replace('/[^a-z0-9]/', '', self::fold($h));
    }

    /** Litere mici, fără diacritice. */
    public static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'í' => 'i',
            'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u', 'ç' => 'c',
        ]);
        return $s;
    }

    /** Cheie pentru potrivirea după nume: „popescu ioana”. */
    public static function nameKey(?string $last, ?string $first): string
    {
        $s = self::fold(trim((string) $last) . ' ' . trim((string) $first));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim($s);
    }

    /**
     * Transformă valorile brute dintr-un rând în câmpuri ale membrului.
     * Câmpurile goale nu apar în rezultat (nu șterg date existente).
     */
    public static function rowToFields(array $cells, array $map): array
    {
        $out = [];
        foreach ($map as $i => $field) {
            $v = trim((string) ($cells[$i] ?? ''));
            if ($v === '' || $v === '-' || $v === '.' || $v === '—') {
                continue;
            }
            $out[$field] = $v;
        }

        if (isset($out['full_name']) && !isset($out['last_name'], $out['first_name'])) {
            // „Nume Prenume”: primul cuvânt = numele de familie
            $parts = preg_split('/\s+/', $out['full_name'], 2);
            $out['last_name'] ??= $parts[0];
            $out['first_name'] ??= $parts[1] ?? '';
        }
        unset($out['full_name']);

        foreach (['last_name', 'first_name'] as $f) {
            if (isset($out[$f])) {
                $out[$f] = preg_replace('/\s+/', ' ', $out[$f]);
            }
        }

        foreach (['birth_date', 'joined_on', 'subscription_expires_on'] as $f) {
            if (isset($out[$f])) {
                $out[$f] = self::normalizeDate($out[$f]) ?? $out[$f];
            }
        }

        if (isset($out['gender'])) {
            $g = self::fold($out['gender']);
            $out['gender'] = match (true) {
                in_array($g, ['m', 'masculin', 'barbat', 'b', 'male'], true) => 'M',
                in_array($g, ['f', 'feminin', 'femeie', 'female'], true)    => 'F',
                default => null,
            };
            if ($out['gender'] === null) {
                unset($out['gender']);
            }
        }

        if (isset($out['phone'])) {
            $p = $out['phone'];
            // Sheets/Excel pierd adesea 0-ul din față: 757780411 → 0757780411
            if (preg_match('/^7\d{8}$/', preg_replace('/\D/', '', $p)) && !str_starts_with($p, '+')) {
                $p = '0' . preg_replace('/\D/', '', $p);
            }
            $out['phone'] = $p;
        }

        if (isset($out['is_active'])) {
            $a = self::fold($out['is_active']);
            if (in_array($a, ['da', 'activ', 'yes', 'y', '1', 'true', 'ok', 'x'], true)) {
                $out['is_active'] = 1;
            } elseif (in_array($a, ['nu', 'inactiv', 'retras', 'no', 'n', '0', 'false'], true)) {
                $out['is_active'] = 0;
            } else {
                unset($out['is_active']); // valoare necunoscută (ex. coloana „Status” din formular): o ignorăm
            }
        }

        return $out;
    }

    /** Acceptă 2026-10-17, 17.10.2026, 17/10/2026, 17-10-2026, 31-Jan-2026, 17.10.2026 14:30 ... */
    public static function normalizeDate(string $value): ?string
    {
        $v = trim($value);
        $v = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?.*$/', '', $v); // scoate ora
        $months = [
            'ian' => 1, 'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mai' => 5, 'may' => 5, 'iun' => 6, 'jun' => 6,
            'iul' => 7, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'noi' => 11, 'nov' => 11, 'dec' => 12,
        ];
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{4})$/', $v, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[ .\-]([a-zA-ZăîâșțĂÎÂȘȚ]{3,})[ .\-,]*(\d{4})$/u', $v, $m)
            && isset($months[substr(self::fold($m[2]), 0, 3)])) {
            [$d, $mo, $y] = [(int) $m[1], $months[substr(self::fold($m[2]), 0, 3)], (int) $m[3]];
        } else {
            return null;
        }
        if (!checkdate($mo, $d, $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /**
     * Compară rândurile cu membrii existenți.
     * Fiecare element: ['line', 'action' => new|update|same|error, 'member_id', 'data', 'changes', 'errors', 'name']
     */
    public static function plan(array $parsed): array
    {
        $existing = Db::run('SELECT * FROM members')->fetchAll();
        $byEmail = [];
        $byName = [];
        foreach ($existing as $m) {
            if ($m['email']) {
                $byEmail[mb_strtolower($m['email'])] = $m;
            }
            $byName[self::nameKey($m['last_name'], $m['first_name'])][] = $m;
            $byName[self::nameKey($m['first_name'], $m['last_name'])][] = $m; // ordine inversată
        }

        $seenEmail = [];
        $seenName = [];
        $plan = [];

        foreach ($parsed['rows'] as [$line, $cells]) {
            $fields = self::rowToFields($cells, $parsed['map']);
            $name = trim(($fields['last_name'] ?? '') . ' ' . ($fields['first_name'] ?? ''));
            $item = ['line' => $line, 'name' => $name, 'action' => 'new', 'member_id' => null,
                'data' => [], 'changes' => [], 'errors' => []];

            $email = isset($fields['email']) ? mb_strtolower($fields['email']) : null;
            $nk = self::nameKey($fields['last_name'] ?? '', $fields['first_name'] ?? '');

            // Duplicat în același fișier
            if (($email && isset($seenEmail[$email])) || ($nk !== '' && isset($seenName[$nk]))) {
                $item['action'] = 'error';
                $item['errors'][] = 'Apare de mai multe ori în fișier (vezi rândul ' . ($email && isset($seenEmail[$email]) ? $seenEmail[$email] : $seenName[$nk]) . ').';
                $plan[] = $item;
                continue;
            }
            if ($email) {
                $seenEmail[$email] = $line;
            }
            if ($nk !== '') {
                $seenName[$nk] = $line;
            }

            // Potrivire cu un membru existent: întâi după email, apoi după nume
            $match = null;
            if ($email && isset($byEmail[$email])) {
                $match = $byEmail[$email];
            } elseif ($nk !== '' && !empty($byName[$nk])) {
                $candidates = array_values(array_unique(array_column($byName[$nk], 'id')));
                if (count($candidates) === 1) {
                    $match = $byName[$nk][0];
                } else {
                    $item['action'] = 'error';
                    $item['errors'][] = 'Există mai mulți membri cu acest nume. Adaugă emailul în fișier ca să știu pe care îl actualizez.';
                    $plan[] = $item;
                    continue;
                }
            }

            if ($match) {
                // Același om cu numele scris invers („Mihai Ionescu” vs „Ionescu Mihai”): păstrăm ordinea din aplicație
                if ($nk !== '' && $nk === self::nameKey($match['first_name'], $match['last_name'])
                    && $nk !== self::nameKey($match['last_name'], $match['first_name'])) {
                    unset($fields['last_name'], $fields['first_name']);
                    $item['name'] = $match['last_name'] . ' ' . $match['first_name'];
                }
                // Data înscrierii nu se suprascrie (ex. „Timestamp” din formular e data completării, nu a înscrierii)
                if (!empty($match['joined_on'])) {
                    unset($fields['joined_on']);
                }
            }

            $base = $match ?? ['is_active' => 1];
            $merged = array_merge($base, $fields);
            [$data, $errors] = Members::validate($merged + ['is_active' => $base['is_active'] ?? 1]);

            if ($errors) {
                $item['action'] = 'error';
                foreach ($errors as $f => $msg) {
                    $label = self::FIELD_LABELS[$f] ?? $f;
                    $raw = $fields[$f] ?? '';
                    $item['errors'][] = $label . ': ' . $msg . ($raw !== '' ? ' („' . $raw . '”)' : '');
                }
                $plan[] = $item;
                continue;
            }

            $item['data'] = $data;
            if ($match) {
                $item['member_id'] = (int) $match['id'];
                foreach (Members::FIELDS as $f) {
                    $old = $match[$f] === null ? null : (string) $match[$f];
                    $new = $data[$f] === null ? null : (string) $data[$f];
                    if ($old !== $new) {
                        $item['changes'][] = $f;
                    }
                }
                $item['action'] = $item['changes'] ? 'update' : 'same';
            }
            $plan[] = $item;
        }
        return $plan;
    }

    /** Scrie în bază rândurile noi și actualizările. Întoarce [create, update]. */
    public static function apply(array $plan): array
    {
        $created = 0;
        $updated = 0;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($plan as $item) {
                if ($item['action'] === 'new') {
                    Members::create($item['data']);
                    $created++;
                } elseif ($item['action'] === 'update' && $item['member_id']) {
                    Members::update((int) $item['member_id'], $item['data']);
                    $updated++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return [$created, $updated];
    }

    public static function templateCsv(): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, self::TEMPLATE_HEADERS, ',', '"', '');
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }
}
