<?php
require_once __DIR__ . '/code/includes/db_connection.php';
require_once __DIR__ . '/code/includes/functions.php';
require_once __DIR__ . '/code/includes/auth.php';
require_once __DIR__ . '/code/models/FormDefinition.php';

requireAuth(true);

$pageTitle = 'Available Forms';

$formDef = new FormDefinition();
$forms = $formDef->getAll(true);

include __DIR__ . '/code/views/layouts/header.php';
include __DIR__ . '/code/views/forms/list.php';
include __DIR__ . '/code/views/layouts/footer.php';

