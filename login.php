<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$layer1_error = false;

// Handle Layer 1 validation
if (empty($_SESSION['layer1_ok'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['layer1_password'])) {
        if (hash_equals(FIRST_LAYER_PASSWORD, (string)$_POST['layer1_password'])) {
            session_regenerate_id(true);
            $_SESSION['layer1_ok'] = true;
            header('Location: login.php');
            exit;
        }
        $layer1_error = true;
    }

    if (empty($_SESSION['layer1_ok'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Layer 1 Access</title>
            <link rel="stylesheet" href="asset/bootstrap.css">
            <link rel="stylesheet" href="asset/style.css">
        </head>
        <body>
            <div class="container" style="padding-top: 4rem;">
                <div class="empty-state">
                    <div style="font-weight: 600; margin-bottom: 0.5rem;">Layer 1 Access Required</div>
                    <div class="meta-line">A browser prompt will appear. Enter the first password.</div>
                    <?php if ($layer1_error) : ?>
                        <div class="alert alert-danger mt-3">Access denied. Try again.</div>
                    <?php endif; ?>
                </div>
            </div>
            <form id="layer1Form" method="POST" style="display:none;">
                <input type="hidden" name="layer1_password" id="layer1Password">
            </form>
            <script>
                (function () {
                    var pwd = prompt('Enter first layer password');
                    if (pwd === null) {
                        window.location.href = 'index.php';
                        return;
                    }
                    document.getElementById('layer1Password').value = pwd;
                    document.getElementById('layer1Form').submit();
                })();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// Handle Layer 2 validation with brute-force protection
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['layer2_password'])) {
    $attempts = $_SESSION['layer2_attempts'] ?? 0;

    if ($attempts >= 5) {
        $error = 'Too many failed attempts. Please wait and try again.';
    } elseif (hash_equals(SECOND_LAYER_PASSWORD, (string)$_POST['layer2_password'])) {
        session_regenerate_id(true);
        unset($_SESSION['layer2_attempts']);
        $_SESSION['admin_logged_in'] = true;
        header('Location: controller.php');
        exit;
    } else {
        sleep(1);
        $_SESSION['layer2_attempts'] = $attempts + 1;
        $error = 'Incorrect password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="asset/bootstrap.css">
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
    <div class="container" style="padding-top: 4rem; max-width: 480px;">
        <h2 class="mb-3">Admin Login</h2>
        <p class="meta-line mb-4">Layer 2 password required to access the dashboard.</p>

        <?php if ($error) : ?>
            <div class="alert alert-danger mb-3"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label class="form-label" for="layer2_password">Second Layer Password</label>
            <input class="form-control mb-3" type="password" id="layer2_password" name="layer2_password" required>
            <button class="btn btn-primary" type="submit">Enter</button>
        </form>
    </div>
</body>
</html>
