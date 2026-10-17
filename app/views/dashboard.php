<?php
$s = array_map('intval', $stats);
$tiles = [
    ['Membri activi', $s['active_members'] ?? 0, url('members'), ''],
    ['Contribuție la zi', $s['paid'] ?? 0, url('members', ['status' => 'active']), 'ok'],
    ["Expiră în $soon zile", $s['expiring'] ?? 0, url('members', ['status' => 'expiring', 'sort' => 'expiry']), 'soon'],
    ['Expirate', $s['expired'] ?? 0, url('members', ['status' => 'expired', 'sort' => 'expiry']), 'expired'],
];
?>
<div class="page-head">
    <h1>Panou</h1>
    <p class="muted"><?= e(today()->format('d.m.Y')) ?></p>
</div>

<section class="tiles">
    <?php foreach ($tiles as [$label, $value, $href, $tone]): ?>
        <a class="tile <?= $tone ? 'tile--' . e($tone) : '' ?>" href="<?= e($href) ?>">
            <span class="tile__value"><?= e($value) ?></span>
            <span class="tile__label"><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</section>

<?php if (($s['no_date'] ?? 0) > 0): ?>
    <p class="note">
        <?= e($s['no_date']) ?> membri activi nu au data contribuției completată.
        <a href="<?= e(url('members', ['status' => 'none'])) ?>">Vezi lista</a>
    </p>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <h2>Expiră în următoarele <?= e($soon) ?> zile</h2>
        <?php if (!$expiring): ?>
            <p class="muted">Nicio contribuție nu expiră în perioada asta.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($expiring as $m): ?>
                    <li>
                        <a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name'] . ' ' . $m['first_name']) ?></a>
                        <span class="badge badge--soon"><?= e(expiry_label($m['subscription_expires_on'], $soon)) ?></span>
                        <?php if (!$m['email']): ?><span class="badge badge--none" title="Nu va primi email">fără email</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Expirate recent</h2>
        <?php if (!$expired): ?>
            <p class="muted">Nicio contribuție expirată.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($expired as $m): ?>
                    <li>
                        <a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name'] . ' ' . $m['first_name']) ?></a>
                        <span class="badge badge--expired"><?= e(expiry_label($m['subscription_expires_on'], $soon)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (($s['expired'] ?? 0) > count($expired)): ?>
                <p><a href="<?= e(url('members', ['status' => 'expired', 'sort' => 'expiry'])) ?>">Vezi toate (<?= e($s['expired']) ?>)</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php if ($medical): ?>
    <section class="card">
        <h2>Vize medicale expirate sau care expiră în 30 de zile</h2>
        <ul class="list">
            <?php foreach ($medical as $m): ?>
                <li>
                    <a href="<?= e(url('member', ['id' => $m['id']])) ?>"><?= e($m['last_name'] . ' ' . $m['first_name']) ?></a>
                    <span class="badge badge--<?= e(expiry_status($m['medical_expires_on'], 30)) ?>">
                        <?= e(fmt_date($m['medical_expires_on'])) ?> · <?= e(expiry_label($m['medical_expires_on'], 30)) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<p class="footnote muted">
    <?php if ($lastMail): ?>
        Ultimul email automat: <?= e(date('d.m.Y H:i', strtotime($lastMail['sent_at']))) ?>
        către <?= e($lastMail['recipient']) ?> (<?= $lastMail['status'] === 'sent' ? 'trimis' : 'eșuat' ?>).
    <?php else: ?>
        Niciun email automat trimis încă. Verifică dacă ai configurat cron job-ul în cPanel.
    <?php endif; ?>
    · <a href="<?= e(url('export')) ?>">Export CSV</a>
</p>
