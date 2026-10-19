<?php
use App\Csrf;
use App\Import;

$labels = [
    'new'    => ['Nou', 'ok'],
    'update' => ['Actualizare', 'soon'],
    'same'   => ['Neschimbat', 'none'],
    'error'  => ['Eroare', 'expired'],
];
?>
<div class="page-head">
    <div>
        <a href="<?= e(url('members')) ?>" class="back">← Membri</a>
        <h1>Import membri</h1>
    </div>
</div>

<?php if (!$plan): ?>
    <div class="grid-2">
        <form method="post" action="<?= e(url('import')) ?>" enctype="multipart/form-data" class="card stack">
            <?= Csrf::field() ?>
            <h2>Alege fișierul CSV</h2>
            <label class="field">
                <span>Fișier .csv (maxim 2 MB)</span>
                <input type="file" name="file" accept=".csv,text/csv,.txt" required>
            </label>
            <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="actions">
                <button type="submit" class="btn btn--primary">Verifică fișierul</button>
            </div>
            <p class="hint">Nimic nu se salvează la acest pas: vei vedea întâi ce se va importa.</p>
        </form>

        <section class="card">
            <h2>Cum pregătești fișierul</h2>
            <ol class="steps">
                <li>În Google Sheets: <strong>Fișier → Descarcă → Valori separate prin virgulă (.csv)</strong>. Se exportă doar tab-ul deschis.</li>
                <li>Primul rând trebuie să conțină numele coloanelor. Obligatorii sunt <strong>Nume</strong> și <strong>Prenume</strong> (sau o singură coloană <strong>Nume Prenume</strong>).</li>
                <li>Celelalte coloane sunt opționale și pot fi în orice ordine. Le recunosc după nume: Data nașterii, Email, Telefon, Sex, Adresă, Categorie, Antrenor, Data înscrierii, Strava, Contribuție până la, Activ, Observații. Recunosc și coloanele din formularul Google de înscriere (ex. „Nr. telefon”, „Data nașterii [opțional]”).</li>
                <li>Datele pot fi scrise <code>17.10.2026</code>, <code>17/10/2026</code>, <code>2026-10-17</code> sau <code>31-Jan-2026</code>.</li>
                <li>Membrii existenți sunt recunoscuți după email, apoi după nume. Pentru ei, se completează doar câmpurile care au valoare în fișier; celulele goale nu șterg nimic, iar data înscrierii, dacă există deja, nu se modifică.</li>
            </ol>
            <p><a href="<?= e(url('import_template')) ?>" class="btn">Descarcă șablonul CSV</a></p>
        </section>
    </div>
<?php else:
    $counts = array_count_values(array_column($plan, 'action'));
    $mapped = $mappedColumns; ?>

    <section class="tiles">
        <?php foreach ($labels as $key => [$label, $tone]): ?>
            <div class="tile tile--<?= e($tone === 'none' ? '' : ($tone === 'expired' ? 'expired' : $tone)) ?>">
                <span class="tile__value"><?= e($counts[$key] ?? 0) ?></span>
                <span class="tile__label"><?= e($label) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Coloane recunoscute din „<?= e($filename) ?>”</h2>
        <ul class="chips">
            <?php foreach ($headers as $i => $h): if ($h === '') continue; ?>
                <?php if (isset($mapped[$i])): ?>
                    <li class="chip chip--ok"><?= e($h) ?> → <?= e(Import::FIELD_LABELS[$mapped[$i]] ?? $mapped[$i]) ?></li>
                <?php else: ?>
                    <li class="chip" title="Coloana nu este folosită"><?= e($h) ?> · ignorată</li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="table-wrap card">
        <table class="table">
            <thead>
            <tr><th>Rând</th><th>Nume</th><th>Rezultat</th><th>Detalii</th></tr>
            </thead>
            <tbody>
            <?php foreach ($plan as $item): [$label, $tone] = $labels[$item['action']]; ?>
                <tr>
                    <td class="muted"><?= e($item['line']) ?></td>
                    <td class="strong">
                        <?php if ($item['member_id']): ?>
                            <a href="<?= e(url('member', ['id' => $item['member_id']])) ?>"><?= e($item['name'] ?: '—') ?></a>
                        <?php else: ?><?= e($item['name'] ?: '—') ?><?php endif; ?>
                    </td>
                    <td><span class="badge badge--<?= e($tone) ?>"><?= e($label) ?></span></td>
                    <td class="details">
                        <?php if ($item['errors']): ?>
                            <?= e(implode(' ', $item['errors'])) ?>
                        <?php elseif ($item['action'] === 'update'): ?>
                            Se modifică: <?= e(implode(', ', array_map(static fn ($f) => mb_strtolower(Import::FIELD_LABELS[$f] ?? $f), $item['changes']))) ?>
                        <?php elseif ($item['action'] === 'new'): ?>
                            <?= e(implode(' · ', array_filter([
                                $item['data']['email'] ?? null,
                                $item['data']['phone'] ?? null,
                                isset($item['data']['subscription_expires_on']) ? 'contribuție ' . fmt_date($item['data']['subscription_expires_on']) : null,
                            ]))) ?>
                        <?php else: ?>
                            <span class="muted">Datele sunt deja la zi.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions">
        <?php if (($counts['new'] ?? 0) + ($counts['update'] ?? 0) > 0): ?>
            <form method="post" action="<?= e(url('import_confirm')) ?>" class="inline">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--primary">
                    Confirmă importul (noi: <?= e($counts['new'] ?? 0) ?>, actualizări: <?= e($counts['update'] ?? 0) ?>)
                </button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('import_cancel')) ?>" class="inline">
            <?= Csrf::field() ?>
            <button type="submit" class="btn">Renunță</button>
        </form>
    </div>
    <?php if (!empty($counts['error'])): ?>
        <p class="hint">Rândurile cu erori nu se importă. Le poți corecta în fișier și importa din nou: membrii deja importați vor apărea ca „Neschimbat”.</p>
    <?php endif; ?>
<?php endif; ?>
