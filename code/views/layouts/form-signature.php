<?php
/**
 * Electronic-signature attestation block. Included from inside a <form> tag, between the form body and the action footer, so its inputs submit with the rest of the form.
 *
 * Captures two values:
 *   signature_name -  typed full name, used as the electronic signature
 *   signature_confirmed  - attestation checkbox carrying the legal weight
 *
 * Both values are echoed back from $_POST on a re-render so a failed-validation round trip preserves what the user typed.
 *
 * If the surrounding scope defines an $errors array keyed by 'signature_name' or 'signature_confirmed', those entries drive the aria-invalid attribute and a linked .form-error message on the offending input.
 */

$sigNameError = $errors['signature_name'] ?? null;
$sigConfirmError = $errors['signature_confirmed'] ?? null;
?>
<div class="form-signature" aria-labelledby="form-signature-title">
    <h3 id="form-signature-title" class="form-signature__title">Electronic Signature</h3>
    <p class="form-signature__intro">
        By signing below, you confirm the information provided is accurate and
        complete to the best of your knowledge. Your typed name and the
        confirmation are recorded as your electronic signature.
    </p>

    <div class="form-group<?= $sigNameError ? ' form-group--error' : '' ?>">
        <label for="signature_name">
            Full name
            <span class="required" aria-hidden="true">*</span>
            <span class="visually-hidden"> (required)</span>
        </label>
        <input type="text" id="signature_name" name="signature_name" class="form-control"
            placeholder="Type your full name" autocomplete="name" aria-required="true" <?= $sigNameError ? 'aria-invalid="true" aria-describedby="signature_name-error"' : '' ?>
            value="<?= escape($_POST['signature_name'] ?? '') ?>">
        <?php if ($sigNameError): ?>
            <div id="signature_name-error" class="form-error" role="alert">
                <?= escape($sigNameError) ?>
            </div>
        <?php endif; ?>
    </div>

    <label class="form-signature__attest">
        <input type="checkbox" name="signature_confirmed" value="1" <?= $sigConfirmError ? 'aria-invalid="true" aria-describedby="signature_confirmed-error"' : '' ?> <?= !empty($_POST['signature_confirmed']) ? 'checked' : '' ?>>
        <span>
            I confirm I am the named user above and the information being
            submitted is accurate.
        </span>
    </label>
    <?php if ($sigConfirmError): ?>
        <div id="signature_confirmed-error" class="form-error" role="alert">
            <?= escape($sigConfirmError) ?>
        </div>
    <?php endif; ?>
</div>