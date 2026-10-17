<div class="card narrow empty">
    <h1><?= e($title ?? 'Eroare') ?></h1>
    <p><?= e($message ?? '') ?></p>
    <p><a href="<?= e(url('dashboard')) ?>" class="btn">Înapoi la panou</a></p>
</div>
