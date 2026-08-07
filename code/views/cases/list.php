<div class="container container--narrow">

    <div class="page-header">
        <div>
            <h1>Patient Case File</h1>
            <p class="page-header__sub">
                Patient: <strong><?= escape($patientNumber) ?></strong>
                &middot; <?= count($submissions) ?> completed form<?= count($submissions) === 1 ? '' : 's' ?>
            </p>
        </div>
        <a href="/form-complete.php" class="btn btn-primary">
            <?= icon('plus') ?> Complete New Form
        </a>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon"><?= icon('clipboard-list') ?></div>
                <h3>No submissions yet</h3>
                <p>This patient has no completed forms on record. Start by completing their first form.</p>
                <div class="empty-actions">
                    <a href="/form-complete.php" class="btn btn-primary">
                        <?= icon('plus') ?> Complete a Form
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Form</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Submitted By</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                        <tr>
                            <td>
                                <a href="/submission-view.php?id=<?= (int) $sub['submission_id'] ?>">
                                    <?= escape($sub['form_name']) ?>
                                </a>
                                <?php if (!empty($sub['form_description'])): ?>
                                    <div class="case-row__desc">
                                        <?= escape($sub['form_description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <?= icon('check') ?> Completed
                                </span>
                            </td>
                            <td><?= formatDatetime($sub['submitted_at']) ?></td>
                            <td><?= escape($sub['submitted_by_name'] ?? '-') ?></td>
                            <td>
                                <a href="/submission-view.php?id=<?= (int) $sub['submission_id'] ?>"
                                    class="btn btn-sm btn-outline">
                                    <?= icon('eye') ?> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>