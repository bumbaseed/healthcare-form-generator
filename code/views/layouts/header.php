<?php
if (!isset($_SESSION)) {
    session_start();
}

$appConfig = require dirname(__DIR__, 3) . '/config/app.php';
$pageTitle = $pageTitle ?? 'Healthcare Form Generator';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo escape($pageTitle); ?> - <?php echo escape($appConfig['app_name']); ?></title>

    <!-- Inter is the UI typeface. Preconnect plus a subset of weights keeps the load under ~20KB. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/css/main.css">
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body<?= !empty($bodyClass) ? ' class="' . escape($bodyClass) . '"' : '' ?>>
    <?php include __DIR__ . '/icon-sprite.php'; ?>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="wrapper">
        <?php include __DIR__ . '/nav.php'; ?>

        <?php
        // Display flash messages
        $flash = getFlash();
        if ($flash):
            ?>
            <div class="flash-message flash-<?php echo escape($flash['type']); ?>" role="status">
                <?php echo escape($flash['message']); ?>
                <button class="flash-close" aria-label="Dismiss message" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <main class="main-content" id="main-content" tabindex="-1">