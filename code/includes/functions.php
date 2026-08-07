<?php
/**
 * Escape a string for safe rendering inside HTML.
 */
function escape($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Render a Lucide icon by name. Resolves to a <use href="#icon-foo"/> referencing the sprite included by header.php. Stroke and fill live on the wrapping <svg> so symbols stay tiny and every icon picks up the surrounding text colour via currentColor.
 */
function icon(string $name, string $class = ''): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $classAttr = trim('icon ' . $class);
    return '<svg class="' . htmlspecialchars($classAttr, ENT_QUOTES, 'UTF-8') . '"'
        . ' viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">'
        . '<use href="#icon-' . $safeName . '"/>'
        . '</svg>';
}

function redirect($url, $statusCode = 302)
{
    header('Location: ' . $url, true, $statusCode);
    exit();
}

function isPost()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post($key, $default = null)
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function get($key, $default = null)
{
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function setFlash($type, $message)
{
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash()
{
    if (!isset($_SESSION)) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatDate($date, $format = null)
{
    if (empty($date)) {
        return '';
    }

    $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
    $format = $format ?: $appConfig['display_date_format'];

    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

function formatDatetime($datetime, $format = null)
{
    if (empty($datetime)) {
        return '';
    }

    $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
    $format = $format ?: $appConfig['display_datetime_format'];

    $timestamp = strtotime($datetime);
    return date($format, $timestamp);
}


function currentPage()
{
    return basename($_SERVER['PHP_SELF']);
}

function isCurrentPage($page)
{
    return currentPage() === $page;
}


// CSRF protection. Tokens are 256 bits of CSPRNG output, kept in the session for its lifetime, and verified with hash_equals to avoid timing leaks.

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render the hidden token input. Drop into any POST form.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escape(csrfToken()) . '">';
}

function verifyCsrfToken(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify the token and bounce the request with an error flash if it fails. Call at the top of any POST handler.
 */
function requireCsrfToken(): void
{
    if (!verifyCsrfToken()) {
        setFlash('error', 'Invalid or expired form token. Please try again.');
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'));
        exit();
    }
}
