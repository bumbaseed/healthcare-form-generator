<?php
/**
 * Dashboard view for the currently selected patient. Renders the stats and recent submissions prepared by index.php.
 */
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>Patient: <?php echo escape($patientData['patient_name'] ?? 'Unknown'); ?></h1>
        <p class="text-muted">Patient Number: <strong><?php echo escape($patientNumber); ?></strong> | Staff:
            <strong><?php echo escape($patientData['username'] ?? 'User'); ?></strong>
        </p>
        <form method="POST" action="/patient-entry.php" class="dashboard-header-actions">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="switch">
            <button type="submit" class="btn btn-sm btn-outline">Switch Patient</button>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon--forms">
                <?= icon('file-text', 'icon-lg') ?>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['available_forms'] ?? 0; ?></h3>
                <p>Available Forms</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--completed">
                <?= icon('clipboard-check', 'icon-lg') ?>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['completed_forms'] ?? 0; ?></h3>
                <p>Completed Forms</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions card">
        <div class="card-header">
            <h2>Quick Actions</h2>
        </div>
        <div class="card-body">
            <div class="action-buttons">
                <a href="/form-complete.php" class="action-btn">
                    <?= icon('clipboard-check', 'icon-lg') ?>
                    Complete Form for Patient
                </a>
                <a href="/my-cases.php" class="action-btn">
                    <?= icon('folder', 'icon-lg') ?>
                    View Patient Case File
                </a>
                <a href="/form-list.php" class="action-btn">
                    <?= icon('files', 'icon-lg') ?>
                    Available Forms
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-between align-center">
            <h2>Recent Completed Forms</h2>
            <a href="/my-cases.php" class="btn btn-sm btn-outline">View Case File</a>
        </div>
        <div class="card-body">
            <?php if (empty($recent_submissions)): ?>
                <p class="text-muted text-center">No completed forms for this patient yet.
                    <a href="/form-complete.php">Complete a form for this patient</a>
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Form Name</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_submissions as $submission): ?>
                                <tr>
                                    <td><?php echo escape($submission['form_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo formatDatetime($submission['submitted_at']); ?></td>
                                    <td>
                                        <a href="/submission-view.php?id=<?php echo (int) $submission['submission_id']; ?>"
                                            class="btn btn-sm btn-outline">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

