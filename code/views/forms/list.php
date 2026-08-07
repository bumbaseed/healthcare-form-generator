<div class="container container--narrow">
    <div class="page-header">
        <h1>Available Forms</h1>
        <a href="/form-builder.php" class="btn btn-primary">
            <?= icon('plus') ?> Create New Form
        </a>
    </div>

    <?php if (empty($forms)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon"><?= icon('files') ?></div>
                <h3>No forms yet</h3>
                <p>No forms have been created. Build your first form to start collecting patient data.</p>
                <div class="empty-actions">
                    <a href="/form-builder.php" class="btn btn-primary">
                        <?= icon('plus') ?> Create Form
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Form Name</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Fields</th>
                        <th>Submissions</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                        <tr>
                            <td><?= escape($form['form_name']) ?></td>
                            <td>
                                <?php if (!empty($form['is_active'])): ?>
                                    <span class="badge badge-success">
                                        <?= icon('check') ?> Active
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-muted">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= escape($form['form_description'] ?? '-') ?></td>
                            <td><?= (int) $form['field_count'] ?></td>
                            <td><?= (int) $form['submission_count'] ?></td>
                            <td><?= formatDate($form['created_at']) ?></td>
                            <td>
                                <a href="/form-complete.php?form_id=<?= (int) $form['form_id'] ?>"
                                    class="btn btn-sm btn-primary">
                                    <?= icon('clipboard-check') ?> Complete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
