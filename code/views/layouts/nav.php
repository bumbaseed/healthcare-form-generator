<?php
require_once dirname(__DIR__, 3) . '/code/includes/functions.php';
require_once dirname(__DIR__, 3) . '/code/includes/auth.php';
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/index.php">
                <span class="brand-icon"><?= icon('heart-pulse') ?></span>
                <span class="brand-text">Healthcare Portal</span>
            </a>
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation"
                aria-expanded="false" aria-controls="navMenu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="nav-menu" id="navMenu">
            <ul class="nav-items">
                <li class="nav-item <?php echo isCurrentPage('index.php') ? 'active' : ''; ?>">
                    <a href="/index.php"><?= icon('dashboard') ?> Dashboard</a>
                </li>

                <li class="nav-item dropdown">
                    <button type="button" class="dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                        <?= icon('clipboard-list') ?> Forms <?= icon('chevron-down', 'icon-sm') ?>
                    </button>
                    <ul class="dropdown-menu" role="menu">
                        <li role="none"><a role="menuitem" href="/form-list.php"><?= icon('files') ?> Available Forms</a></li>
                        <li role="none"><a role="menuitem" href="/form-complete.php"><?= icon('clipboard-check') ?> Complete Form</a></li>
                        <?php if (isAdmin()): ?>
                            <li role="none"><a role="menuitem" href="/form-builder.php"><?= icon('plus-circle') ?> Form Builder</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <li class="nav-item <?php echo isCurrentPage('my-cases.php') ? 'active' : ''; ?>">
                    <a href="/my-cases.php"><?= icon('folder') ?> Case File</a>
                </li>

                <li class="nav-item nav-item-spacer">
                    <form method="POST" action="/patient-entry.php" class="nav-inline-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="switch">
                        <button type="submit" class="nav-link-button nav-link-button--primary">
                            <?= icon('switch') ?> Switch Patient
                        </button>
                    </form>
                </li>

                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <span class="nav-user-label">
                            <?= icon('user') ?>
                            <?= escape($_SESSION['full_name'] ?? $_SESSION['username'] ?? '') ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="/logout.php" class="nav-inline-form">
                            <?= csrfField() ?>
                            <button type="submit" class="nav-link-button nav-link-button--danger">
                                <?= icon('log-out') ?> Logout
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
