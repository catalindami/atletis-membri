<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Db;
use App\Members;

// Antete de securitate
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

Auth::startSession();

$route = (string) ($_GET['r'] ?? 'dashboard');
$soon = (int) config('notifications.days_before', 7);

try {
    switch ($route) {

        // ---------- Prima configurare: crearea contului de admin ----------
        case 'setup':
            $token = (string) config('app.setup_token', '');
            $given = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
            if (Auth::userCount() > 0 || strlen($token) < 20 || str_starts_with($token, 'SCHIMBA-MA')
                || !hash_equals($token, $given)) {
                http_response_code(404);
                exit('Pagina nu există.');
            }
            $errors = [];
            $email = '';
            if (is_post()) {
                Csrf::check();
                $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                $pass = (string) ($_POST['password'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Adresa de email nu este validă.';
                }
                if ($p = Auth::passwordProblem($pass)) {
                    $errors['password'] = $p;
                } elseif ($pass !== (string) ($_POST['password_confirm'] ?? '')) {
                    $errors['password_confirm'] = 'Parolele nu coincid.';
                }
                if (!$errors) {
                    Auth::createUser($email, $pass, 'admin');
                    flash('success', 'Contul de admin a fost creat. Te poți autentifica.');
                    redirect('login');
                }
            }
            render('setup', ['errors' => $errors, 'email' => $email, 'token' => $given, 'title' => 'Configurare inițială'], true);
            break;

        // ---------- Autentificare ----------
        case 'login':
            if (Auth::user()) {
                redirect('dashboard');
            }
            $error = null;
            $email = '';
            if (is_post()) {
                Csrf::check();
                $email = trim((string) ($_POST['email'] ?? ''));
                if (Auth::isLockedOut(mb_strtolower($email))) {
                    $error = 'Prea multe încercări nereușite. Încearcă din nou peste 15 minute.';
                } elseif (Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
                    redirect('dashboard');
                } else {
                    $error = 'Email sau parolă incorectă.';
                }
            }
            render('login', ['error' => $error, 'email' => $email, 'title' => 'Autentificare']);
            break;

        case 'logout':
            if (is_post()) {
                Csrf::check();
                Auth::logout();
            }
            redirect('login');

        // ---------- Panou principal ----------
        case 'dashboard':
            Auth::requireAdmin();
            render('dashboard', [
                'title'    => 'Panou',
                'stats'    => Members::stats($soon),
                'expiring' => Members::expiringBetween(0, $soon),
                'expired'  => array_slice(Members::expired(), 0, 10),
                'medical'  => Members::medicalExpiringWithin(30),
                'soon'     => $soon,
                'lastMail' => Db::run('SELECT * FROM notification_log ORDER BY id DESC LIMIT 1')->fetch() ?: null,
            ]);
            break;

        // ---------- Membri ----------
        case 'members':
            Auth::requireAdmin();
            $q = trim((string) ($_GET['q'] ?? ''));
            $status = (string) ($_GET['status'] ?? '');
            $status = array_key_exists($status, Members::STATUS_FILTERS) ? $status : '';
            $sort = in_array($_GET['sort'] ?? '', ['name', 'expiry', 'joined'], true) ? $_GET['sort'] : 'name';
            render('members', [
                'title'   => 'Membri',
                'members' => Members::search($q, $status, $sort, $soon),
                'q' => $q, 'status' => $status, 'sort' => $sort, 'soon' => $soon,
            ]);
            break;

        case 'member':
            Auth::requireAdmin();
            $id = (int) ($_GET['id'] ?? 0);
            $member = $id ? Members::find($id) : null;
            if ($id && !$member) {
                flash('error', 'Membrul nu a fost găsit.');
                redirect('members');
            }
            $errors = [];
            if (is_post()) {
                Csrf::check();
                [$data, $errors] = Members::validate($_POST);
                if (!$errors) {
                    if ($member) {
                        Members::update($id, $data);
                        flash('success', 'Datele membrului au fost salvate.');
                    } else {
                        $id = Members::create($data);
                        flash('success', 'Membrul a fost adăugat.');
                    }
                    redirect('member', ['id' => $id]);
                }
                $member = array_merge($member ?? [], $data, ['id' => $id ?: null]);
            }
            $member ??= ['is_active' => 1, 'joined_on' => today()->format('Y-m-d')];
            render('member_form', [
                'title'  => !empty($member['id']) ? $member['last_name'] . ' ' . $member['first_name'] : 'Membru nou',
                'member' => $member,
                'errors' => $errors,
                'soon'   => $soon,
                'emails' => !empty($member['id'])
                    ? Db::run('SELECT * FROM notification_log WHERE member_id = ? ORDER BY id DESC LIMIT 10', [$member['id']])->fetchAll()
                    : [],
            ]);
            break;

        case 'member_renew':
            Auth::requireAdmin();
            if (!is_post()) {
                redirect('members');
            }
            Csrf::check();
            $id = (int) ($_POST['id'] ?? 0);
            $new = Members::renew($id);
            if ($new) {
                flash('success', 'Contribuția a fost prelungită până la ' . fmt_date($new) . '.');
            } else {
                flash('error', 'Membrul nu a fost găsit.');
            }
            $back = (string) ($_POST['back'] ?? '');
            $back === 'member' ? redirect('member', ['id' => $id]) : redirect('members', array_filter([
                'q' => $_POST['q'] ?? '', 'status' => $_POST['status'] ?? '', 'sort' => $_POST['sort'] ?? '',
            ]));

        case 'member_delete':
            Auth::requireAdmin();
            if (!is_post()) {
                redirect('members');
            }
            Csrf::check();
            $id = (int) ($_POST['id'] ?? 0);
            $member = Members::find($id);
            if ($member && ($_POST['confirm'] ?? '') === 'STERGE') {
                Members::delete($id);
                flash('success', 'Membrul ' . $member['last_name'] . ' ' . $member['first_name'] . ' a fost șters definitiv.');
                redirect('members');
            }
            flash('error', 'Pentru ștergere scrie STERGE în căsuța de confirmare.');
            redirect('member', ['id' => $id]);

        case 'export':
            Auth::requireAdmin();
            $rows = Members::search('', 'all', 'name');
            $filename = 'membri-' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM, ca Excel să afișeze corect diacriticele
            $headers = ['Nume', 'Prenume', 'Data nașterii', 'Sex', 'Email', 'Telefon', 'Adresă', 'Categorie',
                'Antrenor', 'Data înscrierii', 'Strava', 'Viză medicală până la', 'Contribuție până la', 'Activ', 'Observații'];
            fputcsv($out, $headers, ';', '"', '');
            foreach ($rows as $r) {
                $line = [$r['last_name'], $r['first_name'], $r['birth_date'], $r['gender'], $r['email'], $r['phone'],
                    $r['address'], $r['category'], $r['coach'], $r['joined_on'], $r['strava_url'],
                    $r['medical_expires_on'], $r['subscription_expires_on'], $r['is_active'] ? 'da' : 'nu', $r['notes']];
                // Protecție împotriva formulelor injectate în Excel
                $line = array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $line);
                fputcsv($out, $line, ';', '"', '');
            }
            fclose($out);
            exit;

        // ---------- Import CSV ----------
        case 'import':
            Auth::requireAdmin();
            $error = null;
            if (is_post()) {
                Csrf::check();
                $file = $_FILES['file'] ?? null;
                $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $error = ($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? null) === UPLOAD_ERR_FORM_SIZE
                        ? 'Fișierul este prea mare.' : 'Alege un fișier CSV.';
                } elseif (in_array($ext, ['xlsx', 'xls', 'ods'], true)) {
                    $error = 'Acesta e un fișier Excel. Salvează-l ca CSV (în Google Sheets: Fișier → Descarcă → .csv) și încearcă din nou.';
                } elseif ($file['size'] > App\Import::MAX_BYTES) {
                    $error = 'Fișierul este prea mare (maxim 2 MB).';
                } else {
                    try {
                        $parsed = App\Import::parse($file['tmp_name']);
                        $_SESSION['import'] = [
                            'filename' => mb_substr((string) $file['name'], 0, 120),
                            'headers'  => $parsed['headers'],
                            'map'      => $parsed['map'],
                            'plan'     => App\Import::plan($parsed),
                        ];
                        redirect('import_preview');
                    } catch (\RuntimeException $ex) {
                        $error = $ex->getMessage();
                    }
                }
            }
            unset($_SESSION['import']);
            render('import', ['title' => 'Import membri', 'plan' => null, 'error' => $error]);
            break;

        case 'import_preview':
            Auth::requireAdmin();
            if (empty($_SESSION['import'])) {
                redirect('import');
            }
            $imp = $_SESSION['import'];
            render('import', [
                'title' => 'Import membri', 'plan' => $imp['plan'], 'error' => null,
                'headers' => $imp['headers'], 'mappedColumns' => $imp['map'], 'filename' => $imp['filename'],
            ]);
            break;

        case 'import_confirm':
            Auth::requireAdmin();
            if (!is_post() || empty($_SESSION['import'])) {
                redirect('import');
            }
            Csrf::check();
            [$created, $updated] = App\Import::apply($_SESSION['import']['plan']);
            unset($_SESSION['import']);
            flash('success', "Import finalizat: $created membri noi, $updated actualizați.");
            redirect('members');

        case 'import_cancel':
            Auth::requireAdmin();
            if (is_post()) {
                Csrf::check();
                unset($_SESSION['import']);
            }
            redirect('import');

        case 'import_template':
            Auth::requireAdmin();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="sablon-membri.csv"');
            echo App\Import::templateCsv();
            exit;

        case 'password':
            Auth::requireAdmin();
            $errors = [];
            if (is_post()) {
                Csrf::check();
                $user = Auth::user();
                $hash = Db::run('SELECT password_hash FROM users WHERE id = ?', [$user['id']])->fetchColumn();
                $new = (string) ($_POST['new_password'] ?? '');
                if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $hash)) {
                    $errors['current_password'] = 'Parola actuală nu este corectă.';
                } elseif ($p = Auth::passwordProblem($new)) {
                    $errors['new_password'] = $p;
                } elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) {
                    $errors['new_password_confirm'] = 'Parolele nu coincid.';
                }
                if (!$errors) {
                    Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                    session_regenerate_id(true);
                    flash('success', 'Parola a fost schimbată.');
                    redirect('dashboard');
                }
            }
            render('password', ['title' => 'Schimbă parola', 'errors' => $errors]);
            break;

        default:
            http_response_code(404);
            render('error', ['title' => 'Pagina nu există', 'message' => 'Pagina căutată nu există.']);
    }
} catch (\PDOException $e) {
    error_log('DB: ' . $e->getMessage());
    http_response_code(500);
    $msg = config('app.debug') ? $e->getMessage() : 'Eroare la baza de date. Verifică datele de conectare din config.php și dacă tabelele au fost importate.';
    render('error', ['title' => 'Eroare', 'message' => $msg], Auth::user() !== null);
}
