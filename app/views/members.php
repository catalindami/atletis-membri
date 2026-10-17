<?php
use App\Csrf;
use App\Members;
?>
<div class="page-head">
    <h1>Membri <span class="count"><?= count($members) ?></span></h1>
    <div class="actions">
        <a href="<?= e(url('export')) ?>" class="btn">Export CSV</a>
        <a href="<?= e(url('member')) ?>" class="btn btn--primary">+ Membru nou</a>
    </div>
</div>

<form method="get" action="index.php" class="filters card">
    <input type="hidden" name="r" value="members">
    <label class="field field--grow">
        <span>Caută</span>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nume, email, telefon, categorie…">
    </label>
    <label class="field">
        <span>Stare</span>
        <select name="status">
            <?php foreach (Members::STATUS_FILTERS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field">
        <span>Ordonare</span>
        <select name="sort">
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nume</option>
            <option value="expiry" <?= $sort === 'expiry' ? 'selected' : '' ?>>Data expirării</option>
            <option value="joined" <?= $sort === 'joined' ? 'selected' : '' ?>>Cei mai noi</option>
        </select>
    </label>
    <div class="filters__buttons">
        <button type="submit" class="btn btn--primary">Filtrează</button>
        <?php if ($q !== '' || $status !== '' || $sort !== 'name'): ?>
            <a href="<?= e(url('members')) ?>" class="btn">Resetează</a>
        <?php endif; ?>
    </div>
</form>

<?php if (!$members): ?>
    <div class="card empty">
        <p>Niciun membru nu corespunde filtrelor.</p>
        <?php if ($q === '' && $status === ''): ?>
            <p><a href="<?= e(url('member')) ?>" class="btn btn--primary">Adaugă primul membru</a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap card">
        <table class="table">
            <thead>
            <tr>
                <th>Nume</th>
                <th class="hide-sm">Vârstă</th>
                <th class="hide-sm">Categorie</th>
                <th class="hide-md">Contact</th>
                <th>Contribuție</th>
                <th class="hide-md">Viză medicală</th>
                <th class="col-actions"><span class="sr-only">Acțiuni</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m):
                $st = expiry_status($m['subscription_expires_on'], $soon); ?>
                <tr class="<?= $m['is_active'] ? '' : 'is-inactive' ?>">
                    <td data-label="Nume">
                        <a class="strong" href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name'] . ' ' . $m['first_name']) ?></a>
                        <?php if (!$m['is_active']): ?><span class="badge badge--none">retras</span><?php endif; ?>
                        <?php if ($m['strava_url']): ?>
                            <a class="strava" href="<?= e($m['strava_url']) ?>" target="_blank" rel="noopener noreferrer" title="Profil Strava">Strava</a>
                        <?php endif; ?>
                    </td>
                    <td class="hide-sm" data-label="Vârstă"><?= e(age($m['birth_date']) ?? '—') ?></td>
                    <td class="hide-sm" data-label="Categorie"><?= e($m['category'] ?: '—') ?></td>
                    <td class="hide-md" data-label="Contact">
                        <?php if ($m['email']): ?><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><br><?php endif; ?>
                        <?php if ($m['phone']): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $m['phone'])) ?>"><?= e($m['phone']) ?></a><?php endif; ?>
                        <?php if (!$m['email'] && !$m['phone']): ?>—<?php endif; ?>
                    </td>
                    <td data-label="Contribuție">
                        <span class="date"><?= e(fmt_date($m['subscription_expires_on'])) ?></span>
                        <span class="badge badge--<?= e($st) ?>"><?= e(expiry_label($m['subscription_expires_on'], $soon)) ?></span>
                    </td>
                    <td class="hide-md" data-label="Viză medicală">
                        <?php if ($m['medical_expires_on']): ?>
                            <span class="badge badge--<?= e(expiry_status($m['medical_expires_on'], 30)) ?>"><?= e(fmt_date($m['medical_expires_on'])) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="col-actions">
                        <?php if ($m['is_active'] && $st !== 'ok'): ?>
                            <form method="post" action="<?= e(url('member_renew')) ?>" class="inline">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <input type="hidden" name="status" value="<?= e($status) ?>">
                                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                                <button type="submit" class="btn btn--sm" title="Prelungește contribuția cu un an">+1 an</button>
                            </form>
                        <?php endif; ?>
                        <a href="<?= e(url('member', ['id' => $m['id']])) ?>" class="btn btn--sm btn--ghost">Editează</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
