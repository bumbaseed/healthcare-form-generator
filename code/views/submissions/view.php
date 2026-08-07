<?php
/** @var array $submission */
/** @var array $form */
/** @var array|null $data */

$fields = $form['fields'] ?? [];

$formatValue = function ($field, $value) {
    if ($value === null || $value === '') {
        return '<span class="text-muted">-</span>';
    }
    switch ($field['field_type']) {
        case 'checkbox':
            $bool = in_array($value, [true, 1, '1', 't', 'true'], true);
            return $bool ? 'Yes' : 'No';
        case 'date':
            return escape(formatDate($value));
        case 'datetime':
        case 'datetime-local':
            return escape(formatDatetime($value));
        case 'textarea':
            return nl2br(escape($value));
        default:
            return escape((string) $value);
    }
};

// Group fields by section
$groups = [];
foreach ($fields as $field) {
    $section = null;
    if (!empty($field['field_options'])) {
        $opts = json_decode($field['field_options'], true);
        if (is_array($opts) && !empty($opts['_section'])) {
            $section = $opts['_section'];
        }
    }
    $key = $section ?? '__default__';
    if (!isset($groups[$key])) {
        $groups[$key] = ['label' => $section ?? 'Details', 'fields' => []];
    }
    $groups[$key]['fields'][] = $field;
}
?>
<div class="form-page">
    <div class="patient-banner">
        <div class="patient-chip">
            Patient: <strong><?= escape($patientNumber) ?></strong>
        </div>
        <div class="submission-actions">
            <a href="/export-fhir.php?id=<?= (int) $submission['submission_id'] ?>"
                class="btn btn-sm btn-outline"><?= icon('shield-check') ?> Export FHIR</a>
            <a href="/my-cases.php" class="btn btn-sm btn-outline">
                <?= icon('arrow-left') ?> Back to Case File
            </a>
        </div>
    </div>

    <div class="card form-body">
        <div class="form-meta-header">
            <div class="submission-title-row">
                <h1><?= escape($submission['form_name']) ?></h1>
                <?php if (($submission['submission_status'] ?? '') === 'completed'): ?>
                    <span class="badge badge-success">
                        <?= icon('check') ?> Completed
                    </span>
                <?php elseif (($submission['submission_status'] ?? '') === 'draft'): ?>
                    <span class="badge badge-warning">
                        <?= icon('save') ?> Draft
                    </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($submission['form_description'])): ?>
                <p class="submission-description">
                    <?= escape($submission['form_description']) ?>
                </p>
            <?php endif; ?>
            <p class="submission-submitted-at">
                Submitted <?= escape(formatDatetime($submission['submitted_at'])) ?>
                <?php if (!empty($submission['submitted_by_name'])): ?>
                    by <strong><?= escape($submission['submitted_by_name']) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <?php if (empty($groups)): ?>
            <p class="text-muted">This form has no fields.</p>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <section class="submission-section">
                    <h3><?= escape($group['label']) ?></h3>
                    <dl class="submission-data">
                        <?php foreach ($group['fields'] as $field): ?>
                            <?php $value = $data[$field['field_name']] ?? null; ?>
                            <div class="submission-row">
                                <dt><?= escape($field['field_label']) ?></dt>
                                <dd><?= $formatValue($field, $value) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($submission['signature_name'])): ?>
            <section class="submission-signature" aria-labelledby="submission-signature-title">
                <h3 id="submission-signature-title">Electronic Signature</h3>
                <dl class="submission-data">
                    <div class="submission-row">
                        <dt>Signed by</dt>
                        <dd><?= escape($submission['signature_name']) ?></dd>
                    </div>
                    <?php if (!empty($submission['signature_confirmed_at'])): ?>
                        <div class="submission-row">
                            <dt>Signed at</dt>
                            <dd><?= escape(formatDatetime($submission['signature_confirmed_at'])) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>
    </div>
</div>