<div class="container container--narrow">
    <h1>Select a Form to Complete</h1>
    <p class="text-muted mb-3">
        Patient: <strong><?= escape($patientNumber) ?></strong>
    </p>

    <?php if (empty($activeForms)): ?>
        <div class="card form-select-empty">
            <p>No active forms available.</p>
            <a href="/form-builder.php" class="btn btn-primary mt-3">Create a Form</a>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Form Name</th>
                        <th>Description</th>
                        <th>Fields</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeForms as $f): ?>
                        <tr>
                            <td><?= escape($f['form_name']) ?></td>
                            <td><?= escape($f['form_description'] ?? '-') ?></td>
                            <td><?= (int) $f['field_count'] ?></td>
                            <td>
                                <a href="/form-complete.php?form_id=<?= (int) $f['form_id'] ?>"
                                    class="btn btn-sm btn-primary">Complete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>