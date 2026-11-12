<?php

declare(strict_types=1);

/**
 * Halaman login (ditampilkan hanya bila APP_AUTH_PASSWORD diisi).
 *
 * @var string $error pesan error login (opsional)
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Bank Jatim SNAP API Tester</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="login-card">
        <h1>Bank Jatim SNAP API Tester</h1>
        <p class="muted">Masuk untuk mengakses dashboard pengujian.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="?action=login">
            <?= \App\Security\Auth::csrfField() ?>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>
    </main>
</body>
</html>
