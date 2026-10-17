<?php
use App\Csrf;

$isNew = empty($member['id']);
$v = static fn (string $k): string => e($member[$k] ?? '');
$err = static fn (string $k): string => isset($errors[$k]) ? '<small class="error">' . e($errors[$k]) . '</small>' : '';
$cls = static fn (string $k): string => isset($errors[$k]) ? 'field has-error' : 'field';
$action = $isNew ? url('member') : url('member', ['id' => $member['id']]);
?>
<div class="page-head">
    <div>
        <a href="<?= e(url('members')) ?>" class="back">← Membri</a>
        <h1><?= $isNew ? 'Membru nou' : e($member['last_name'] . ' ' . $member['first_name']) ?></h1>
    </div>
    <?php if (!$isNew): ?>
        <div class="status-pill status-pill--<?= e(expiry_status($member['subscription_expires_on'] ?? null, $soon)) ?>">
            Contribuție: <?= e(fmt_date($member['subscription_expires_on'] ?? null)) ?>
            · <?= e(expiry_label($member['subscription_expires_on'] ?? null, $soon)) ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert--error" role="alert">Verifică câmpurile marcate cu roșu.</div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="form-grid" novalidate>
    <?= Csrf::field() ?>

    <section class="card">
        <h2>Date personale</h2>
        <div class="row">
            <label class="<?= $cls('last_name') ?>"><span>Nume *</span>
                <input name="last_name" value="<?= $v('last_name') ?>" required maxlength="100" autocomplete="off"><?= $err('last_name') ?></label>
            <label class="<?= $cls('first_name') ?>"><span>Prenume *</span>
                <input name="first_name" value="<?= $v('first_name') ?>" required maxlength="100" autocomplete="off"><?= $err('first_name') ?></label>
        </div>
        <div class="row">
            <label class="<?= $cls('birth_date') ?>"><span>Data nașterii</span>
                <input type="date" name="birth_date" value="<?= $v('birth_date') ?>" max="<?= e(today()->format('Y-m-d')) ?>"><?= $err('birth_date') ?>
                <?php if (!$isNew && ($a = age($member['birth_date'] ?? null)) !== null): ?><small class="hint"><?= e($a) ?> ani</small><?php endif; ?>
            </label>
            <label class="field"><span>Sex</span>
                <select name="gender">
                    <option value="">—</option>
                    <option value="F" <?= ($member['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Feminin</option>
                    <option value="M" <?= ($member['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Masculin</option>
                </select>
            </label>
        </div>
        <label class="<?= $cls('address') ?>"><span>Adresă</span>
            <input name="address" value="<?= $v('address') ?>" maxlength="255"><?= $err('address') ?></label>
    </section>

    <section class="card">
        <h2>Contact</h2>
        <label class="<?= $cls('email') ?>"><span>Email</span>
            <input type="email" name="email" value="<?= $v('email') ?>" maxlength="190"><?= $err('email') ?>
            <small class="hint">Necesar pentru mementoul automat de expirare.</small></label>
        <label class="<?= $cls('phone') ?>"><span>Telefon</span>
            <input type="tel" name="phone" value="<?= $v('phone') ?>" maxlength="40"><?= $err('phone') ?></label>
        <label class="<?= $cls('strava_url') ?>"><span>Profil Strava</span>
            <input type="url" name="strava_url" value="<?= $v('strava_url') ?>" placeholder="https://www.strava.com/athletes/…" maxlength="255"><?= $err('strava_url') ?>
            <?php if (!$isNew && !empty($member['strava_url']) && empty($errors['strava_url'])): ?>
                <small class="hint"><a href="<?= $v('strava_url') ?>" target="_blank" rel="noopener noreferrer">Deschide profilul ↗</a></small>
            <?php endif; ?>
        </label>
    </section>

    <section class="card">
        <h2>Club</h2>
        <div class="row">
            <label class="<?= $cls('category') ?>"><span>Categorie / grupă</span>
                <input name="category" value="<?= $v('category') ?>" maxlength="100" placeholder="ex. Seniori, Masters 40+"><?= $err('category') ?></label>
            <label class="<?= $cls('coach') ?>"><span>Antrenor</span>
                <input name="coach" value="<?= $v('coach') ?>" maxlength="100"><?= $err('coach') ?></label>
        </div>
        <div class="row">
            <label class="<?= $cls('joined_on') ?>"><span>Data înscrierii</span>
                <input type="date" name="joined_on" value="<?= $v('joined_on') ?>"><?= $err('joined_on') ?></label>
            <label class="<?= $cls('medical_expires_on') ?>"><span>Viză medicală valabilă până la</span>
                <input type="date" name="medical_expires_on" value="<?= $v('medical_expires_on') ?>"><?= $err('medical_expires_on') ?></label>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="is_active" value="1" <?= !empty($member['is_active']) ? 'checked' : '' ?>>
            <span>Membru activ <small class="hint">(debifează dacă s-a retras; nu mai primește emailuri)</small></span>
        </label>
    </section>

    <section class="card card--accent">
        <h2>Contribuție anuală</h2>
        <label class="<?= $cls('subscription_expires_on') ?>"><span>Activă până la</span>
            <input type="date" name="subscription_expires_on" value="<?= $v('subscription_expires_on') ?>"><?= $err('subscription_expires_on') ?>
            <small class="hint">Membrul primește un email cu <?= e($soon) ?> zile înainte de această dată.</small>
        </label>
    </section>

    <section class="card span-2">
        <h2>Observații</h2>
        <label class="field"><span class="sr-only">Observații</span>
            <textarea name="notes" rows="4"><?= $v('notes') ?></textarea></label>
    </section>

    <div class="form-actions span-2">
        <button type="submit" class="btn btn--primary"><?= $isNew ? 'Adaugă membrul' : 'Salvează modificările' ?></button>
        <a href="<?= e(url('members')) ?>" class="btn">Renunță</a>
    </div>
</form>

<?php if (!$isNew): ?>
    <div class="grid-2 after-form">
        <section class="card">
            <h2>Reînnoire rapidă</h2>
            <p class="muted">Prelungește cu un an de la data actuală de expirare (sau de azi, dacă a expirat deja).</p>
            <form method="post" action="<?= e(url('member_renew')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e($member['id']) ?>">
                <input type="hidden" name="back" value="member">
                <button type="submit" class="btn">Prelungește cu 1 an</button>
            </form>
        </section>

        <section class="card">
            <h2>Emailuri trimise</h2>
            <?php if (!$emails): ?>
                <p class="muted">Niciun email automat trimis acestui membru.</p>
            <?php else: ?>
                <ul class="list list--compact">
                    <?php foreach ($emails as $em): ?>
                        <li>
                            <?= e(date('d.m.Y H:i', strtotime($em['sent_at']))) ?> · memento expirare <?= e(fmt_date($em['ref_date'])) ?>
                            <span class="badge badge--<?= $em['status'] === 'sent' ? 'ok' : 'expired' ?>"><?= $em['status'] === 'sent' ? 'trimis' : 'eșuat' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <details class="card danger">
        <summary>Șterge membrul definitiv</summary>
        <p>Toate datele membrului vor fi șterse și nu mai pot fi recuperate. Dacă membrul doar s-a retras, debifează „Membru activ” în loc să-l ștergi.</p>
        <form method="post" action="<?= e(url('member_delete')) ?>" class="inline-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= e($member['id']) ?>">
            <label class="field"><span>Scrie STERGE pentru confirmare</span>
                <input name="confirm" autocomplete="off" pattern="STERGE" required></label>
            <button type="submit" class="btn btn--danger">Șterge definitiv</button>
        </form>
    </details>
<?php endif; ?>
