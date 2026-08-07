<?php
/**
 * Shared form-actions footer used by every generated form.
 *
 * Included from inside the <form> tag so Save Draft and Save & Complete both submit the surrounding form. Print fires client-side only and Cancel is a link, so neither submits.
 *
 * The two submit buttons share name="_action" so the form handler can tell which was pressed: value="draft" or value="complete".
 */

$backUrl ??= '/form-complete.php';
?>
<div class="form-footer">
    <div class="form-footer-info">
        <p class="form-footer-note">
            Required fields must be completed before submitting. This record will be
            linked to patient <strong><?= escape($patientNumber) ?></strong>.
        </p>
        <?php if (!empty($draftSavedAt)): ?>
            <p class="form-footer-draft">
                Draft saved <?= escape($draftSavedAt) ?>
            </p>
        <?php endif; ?>
    </div>
    <div class="form-actions">
        <a href="<?= escape($backUrl) ?>" class="btn btn-outline">
            <?= icon('x') ?> Cancel
        </a>
        <button type="button" class="btn btn-outline" onclick="window.print()">
            <?= icon('printer') ?> Print
        </button>
        <button type="submit" name="_action" value="draft" class="btn btn-secondary">
            <?= icon('save') ?> Save Draft
        </button>
        <button type="submit" name="_action" value="complete" class="btn btn-primary">
            <?= icon('check') ?> Save &amp; Complete
        </button>
    </div>
</div>