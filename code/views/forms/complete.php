<div class="form-page">

    <div class="form-back-link-wrap">
        <a href="/form-complete.php" class="form-back-link">
            &larr; Back to form list
        </a>
    </div>

    <!-- Patient Banner -->
    <div class="patient-banner">
        <div class="patient-banner-left">
            <div class="patient-avatar">
                <?= strtoupper(substr($patientData['patient_name'] ?? 'P', 0, 1)) ?>
            </div>
            <div>
                <p class="patient-banner-name"><?= escape($patientData['patient_name'] ?? 'Unknown Patient') ?></p>
                <p class="patient-banner-sub">Selected patient</p>
            </div>
        </div>
        <div class="patient-banner-right">
            <div class="patient-chip">
                <span class="patient-chip-label">MRN</span>
                <span class="patient-chip-value"><?= escape($patientNumber) ?></span>
            </div>
            <div class="patient-chip">
                <span class="patient-chip-label">Staff</span>
                <span class="patient-chip-value"><?= escape($patientData['username'] ?? '-') ?></span>
            </div>
            <div class="patient-chip">
                <span class="patient-chip-label">Date</span>
                <span class="patient-chip-value"><?= date('d M Y') ?></span>
            </div>
            <div class="patient-chip">
                <span class="patient-chip-label">Time</span>
                <span class="patient-chip-value"><?= date('H:i') ?></span>
            </div>
        </div>
    </div>

    <!-- Form Meta Header -->
    <div class="form-meta-header">
        <div class="form-meta-title">
            <h1><?= escape($form['form_name']) ?></h1>
            <?php if (!empty($form['form_description'])): ?>
                <p><?= escape($form['form_description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-meta-details">
            <span><span class="patient-chip-label">Form ID</span> <strong>#<?= (int) $form['form_id'] ?></strong></span>
            <span><span class="patient-chip-label">Fields</span>
                <strong><?= (int) $form['field_count'] ?></strong></span>
            <span><span class="patient-chip-label">Version</span>
                <strong><?= (int) ($form['form_version'] ?? 1) ?></strong></span>
        </div>
    </div>

    <!-- Form Body -->
    <form method="POST" action="/form-complete.php?form_id=<?= (int) $form['form_id'] ?>">
        <?= csrfField() ?>
        <div class="form-body">

            <?php if (!empty($errors)): ?>
                <div class="error-list" role="alert" tabindex="-1" id="error-summary">
                    <strong>Please correct the following before submitting:</strong>
                    <ul>
                        <?php foreach ($errors as $fieldName => $error): ?>
                            <li>
                                <?php if (is_string($fieldName) && $fieldName[0] !== '_'): ?>
                                    <a href="#<?= escape($fieldName) ?>"><?= escape($error) ?></a>
                                <?php else: ?>
                                    <?= escape($error) ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <script>
                    // Move focus to the error summary so screen readers
                    // announce it and keyboard users land on the right place.
                    document.getElementById('error-summary').focus();
                </script>
            <?php endif; ?>
            <div class="form-section-divider">Form Fields</div>
            <?= $renderer->renderForm($form, $prefillData ?? ($_POST ?: []), false, $errors ?? []) ?>
        </div>

        <!-- Electronic Signature, in the same position on every form. -->
        <?php include __DIR__ . '/../layouts/form-signature.php'; ?>

        <!-- Form Footer, shared partial used by every completion flow. -->
        <?php include __DIR__ . '/../layouts/form-actions.php'; ?>
    </form>
</div>