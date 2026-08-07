<?php
/**
 * Application Configuration
 * General application settings for Healthcare Form Generator
 */

return [
    'app_name' => 'Healthcare Form Generator',
    'version' => '1.0.0',
    'timezone' => 'UTC',

    // Application paths
    'base_path' => dirname(__DIR__),
    'code_path' => dirname(__DIR__) . '/code',
    'views_path' => dirname(__DIR__) . '/code/views',

    // Error handling
    'debug_mode' => false,  // Must stay false in production; only enable locally
    'log_errors' => true,
    'error_log_path' => dirname(__DIR__) . '/logs/error.log',

    // Pagination
    'items_per_page' => 20,

    // Security
    'session_lifetime' => 3600,  // 1 hour in seconds

    // Date formats
    'date_format' => 'Y-m-d',
    'datetime_format' => 'Y-m-d H:i:s',
    'display_date_format' => 'm/d/Y',
    'display_datetime_format' => 'm/d/Y h:i A',

    // Git integration: on form finalize, create a branch off 'main' under `forms/` containing the generated form file. Disable if the working directory is not a git repository.
    'git_integration_enabled' => true,
    'git_base_branch' => 'main',
    'git_branch_prefix' => 'forms/',
    'git_binary' => 'git',
    'git_author_name' => 'Form Builder',
    'git_author_email' => 'forms@healthcare-portal.local',
];
