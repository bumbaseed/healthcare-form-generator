<?php
/**
 * Form builder view, rendered by form-builder.php after auth check and POST handling. $errors is available from the parent scope.
 */
?>

<div class="container container--form">

    <div class="page-header">
        <h1>Form Builder</h1>
        <p class="text-muted">Define a new form. Each field you add will become a column in the database.
        </p>
    </div>

    <!-- Validation errors from PHP -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3 builder-error-list">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="/form-builder.php" id="form-builder-form">
        <?= csrfField() ?>

        <!-- Form metadata -->
        <div class="card builder-card builder-card--meta">
            <h2 class="builder-card-title builder-card-title--first">Form Details</h2>

            <div class="form-group">
                <label for="form_name">Form Name <span class="builder-required-marker">*</span></label>
                <input type="text" id="form_name" name="form_name" class="form-control"
                    placeholder="e.g. Patient Intake Form" value="<?= escape($_POST['form_name'] ?? '') ?>" required>
            </div>

            <div class="form-group mb-0">
                <label for="form_description">Description <span
                        class="builder-optional-marker">(optional)</span></label>
                <textarea id="form_description" name="form_description" class="form-control" rows="2"
                    placeholder="Brief description of what this form is used for"><?= escape($_POST['form_description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Field definitions -->
        <div class="card builder-card">
            <div class="builder-fields-header">
                <h2 class="builder-card-title">Fields</h2>
                <div class="builder-fields-header-actions">
                    <button type="button" id="add-section-btn" class="btn btn-outline btn-sm">+ Add Section</button>
                    <button type="button" id="add-field-btn" class="btn btn-secondary">+ Add Field</button>
                </div>
            </div>

            <!-- Column headers -->
            <div class="field-row field-row--header">
                <div class="field-row-main">
                    <span>Label <span class="builder-required-marker">*</span></span>
                    <span>Database Name <span class="builder-required-marker">*</span></span>
                    <span>Type</span>
                    <span class="builder-required-cell">Req.</span>
                    <span></span>
                </div>
            </div>

            <!-- Field rows injected here by form-builder.js -->
            <div id="fields-container">
                <div class="field-row" data-type="field" data-index="0">
                    <div class="field-row-main">
                        <input type="text" name="fields[0][label]" class="form-control field-label"
                            placeholder="e.g. Date of Birth">
                        <input type="text" name="fields[0][db_name]" class="form-control field-db-name"
                            placeholder="auto">
                        <select name="fields[0][type]" class="form-control field-type">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="datetime">Date &amp; Time</option>
                            <option value="textarea">Textarea</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                        </select>
                        <div class="builder-checkbox-cell">
                            <input type="checkbox" name="fields[0][required]" value="1">
                        </div>
                        <button type="button"
                            class="btn btn-danger btn-sm remove-field-btn builder-remove-btn--hidden">Remove</button>
                    </div>
                </div>
            </div>
            <p id="no-fields-msg" class="builder-no-fields-msg">
                No fields added yet. Click "+ Add Field" to start.
            </p>
        </div>

        <!-- Submit -->
        <div class="builder-submit-row">
            <a href="/form-list.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Form &amp; Create Table</button>
        </div>
    </form>
</div>

<!-- Load the form builder JS -->
<script src="/js/form-builder.js"></script>
