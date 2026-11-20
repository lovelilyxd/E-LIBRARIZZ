<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Handles library changes: add (CREATE), move between tabs (UPDATE), remove (DELETE).
$uid = require_login();
$back = safe_return($_POST['return'] ?? '', 'library.php');
post_guard($back);

$action = (string) ($_POST['action'] ?? '');
$bookId = (int) ($_POST['book_id'] ?? 0);
$status = (string) ($_POST['status'] ?? 'saved');
$labels = ['saved' => 'Saves', 'current' => 'Current Reads', 'archived' => 'Archives'];

try {
    $book = fetch_book($bookId);
    if (!$book) {
        flash('fail', 'That book no longer exists.');
        redirect($back);
    }

    if ($action === 'add') {
        if (!isset($labels[$status])) $status = 'saved';
        $stmt = db()->prepare('INSERT IGNORE INTO library_items (user_id, book_id, status) VALUES (:u, :b, :s)');
        $stmt->execute([':u' => $uid, ':b' => $bookId, ':s' => $status]);
        flash('ok', $stmt->rowCount() ? '"' . $book['title'] . '" added to ' . $labels[$status] . '.' : 'Already in your library.');

    } elseif ($action === 'move') {
        if (!isset($labels[$status])) {
            flash('fail', 'Invalid section.');
        } else {
            $stmt = db()->prepare('UPDATE library_items SET status = :s WHERE user_id = :u AND book_id = :b');
            $stmt->execute([':s' => $status, ':u' => $uid, ':b' => $bookId]);
            flash('ok', 'Moved to ' . $labels[$status] . '.');
        }

    } elseif ($action === 'remove') {
        $stmt = db()->prepare('DELETE FROM library_items WHERE user_id = :u AND book_id = :b');
        $stmt->execute([':u' => $uid, ':b' => $bookId]);
        flash('ok', 'Removed from your library.');

    } else {
        flash('fail', 'Unknown action.');
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    flash('fail', 'Something went wrong. Please try again later.');
}

redirect($back);
