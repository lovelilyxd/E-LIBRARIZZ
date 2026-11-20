<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'auth_demo';
const DB_USER = 'root';
const DB_PASS = '';
const HOME_PAGE = 'home.php';
const CATEGORIES = ['Math', 'Computer', 'PathFit', 'Science'];
const CHAPTER_SIZE = 25; // pages per chapter in the reader

date_default_timezone_set('Asia/Manila');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/** Returns the logged-in user's id, or sends the visitor to the login page. */
function require_login(): int
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
    return (int) $_SESSION['user_id'];
}

/** For POST-only handlers: bounce back unless this is a POST with a valid CSRF token. */
function post_guard(string $fallback): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($fallback);
    }
    if (!csrf_valid()) {
        flash('fail', 'Your session expired. Please try again.');
        redirect($fallback);
    }
}

/** Only allow redirects to our own simple pages (blocks open-redirects). */
function safe_return(?string $to, string $fallback): string
{
    $to = (string) $to;
    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $to) ? $to : $fallback;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="message ' . ($f['type'] === 'ok' ? 'success' : 'fail') . '">' . e($f['msg']) . '</div>';
}

function fetch_book(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM books WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();
    return $book ?: null;
}

/** Book cover: uploaded image if there is one, otherwise a coloured gradient with the label. */
function cover_html(array $book, string $class = 'cover'): string
{
    $img   = (string) ($book['cover_image'] ?? '');
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($book['color'] ?? '')) ? (string) $book['color'] : '#1f5f7a';
    if ($img !== '' && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $img)) {
        return '<div class="' . $class . ' has-img" style="background-image:url(\'uploads/covers/' . e($img) . '\')"'
            . ' role="img" aria-label="' . e((string) $book['title']) . ' cover"></div>';
    }
    $label = (string) (($book['label'] ?? '') !== '' ? $book['label'] : $book['title']);
    return '<div class="' . $class . '" style="background:linear-gradient(160deg,' . e($color) . ',#0b1d2b)">'
        . '<span>' . e($label) . '</span></div>';
}

function format_time(string $timestamp): string
{
    $t = strtotime($timestamp);
    if ($t === false) {
        return '';
    }
    return date('Y-m-d', $t) === date('Y-m-d') ? date('g:i A', $t) : date('M j · g:i A', $t);
}

function theme_attrs(): string
{
    $theme = (string) ($_COOKIE['theme'] ?? 'dark');
    if (!in_array($theme, ['light', 'dark'], true)) {
        $theme = 'dark';
    }
    if (($_COOKIE['time_adaptive'] ?? '') === '1') {
        $hour = (int) date('G');
        $theme = ($hour >= 18 || $hour < 6) ? 'dark' : 'light';
    } elseif (($_COOKIE['light_adaptive'] ?? '') === '1') {
        $theme = 'auto';
    }
    $pure = $theme === 'dark' && ($_COOKIE['pure_black'] ?? '') === '1';
    return 'data-theme="' . $theme . '"' . ($pure ? ' data-pure="1"' : '');
}

function brand(): string
{
    return '<div class="brand">'
        . '<div class="logo"><svg viewBox="0 0 24 24" fill="none" stroke="#08202f" stroke-width="1.6" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M12 6.5C10.3 5.3 8 4.8 4 5v13c4-.2 6.3.3 8 1.5 1.7-1.2 4-1.7 8-1.5V5c-4-.2-6.3.3-8 1.5z"/>'
        . '<path d="M12 6.5v13"/></svg></div>'
        . '<h1>WELCOME</h1>'
        . '<p class="tagline">READ, LEARN, VISUALIZE</p>'
        . '</div>';
}
