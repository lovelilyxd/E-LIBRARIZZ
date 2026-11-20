<?php
// One-time installer. Safe to re-run (it never drops tables or deletes data).
// Open it once in the browser, then DELETE this file.
declare(strict_types=1);
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    // 1. Make sure the database exists.
    $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Database '" . DB_NAME . "': OK\n";

    $pdo = db();

    // 2. Create every table from schema.sql (skips the CREATE DATABASE / USE lines).
    $sql = (string) file_get_contents(__DIR__ . '/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if (stripos($statement, 'CREATE TABLE') === 0) {
            $pdo->exec($statement);
        }
    }
    echo "Tables: OK\n";

    // 3. Upgrade an older users table (from the first version of the project).
    $have = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('username', $have, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(15) NULL UNIQUE AFTER full_name');
        echo "users.username: added\n";
    }
    if (!in_array('phone', $have, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN phone CHAR(11) NULL AFTER student_id');
        echo "users.phone: added\n";
    }
    if (in_array('age', $have, true)) {
        $pdo->exec('ALTER TABLE users MODIFY age TINYINT UNSIGNED NULL');
    }

    // 4. Sample books (only when the catalog is empty).
    if ((int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn() === 0) {
        $books = [
            ['Introduction to Computing & ICT 1', 'Introduction to Computing & ICT1', 'Computer', 'Core Computing Courses', '#1f5f7a', 120,
             'Computer science studies how to describe, predict properties of, and efficiently implement information processes. This book introduces the most important ideas in computing using the Scheme and Python languages.'],
            ['Computer Programming', 'Computer Programming Fundamentals', 'Computer', 'Core Computing Courses', '#2e7d4f', 150,
             'A first course in programming: variables, control flow, functions, arrays and problem solving, with plenty of worked examples.'],
            ['Mathematics In The Modern World', 'Mathematics in the Modern World', 'Math', 'Foundational Mathematics', '#2b4a7a', 100,
             'Mathematics is the study of patterns and structure. It is a useful tool for understanding nature and predicting phenomena in the world, from the movement of stars and weather cycles to patterns on animals.'],
            ['Discrete Mathematics', 'Discrete and Combinatorial Mathematics', 'Math', 'Foundational Mathematics', '#1d3557', 125,
             'Discrete mathematics is concerned with "discrete" mathematical structures instead of "continuous" ones: graphs, integers, logic-based statements and similar objects with distinct values.'],
            ['Understanding The Self', 'Understanding The Self', 'Science', 'General Education', '#7a3b1d', 100,
             'A required General Education course that helps students understand personality, self and identity by introducing major personality theories.'],
            ['Data Structure', 'Data Structure', 'Computer', 'Core Computing Courses', '#2a5a4a', 180,
             'Comprehensive coverage of the fundamental data structures: stacks, queues and linked lists, trees, graphs, and the algorithms that operate on them.'],
            ['Physical Fitness and Wellness', 'Physical Fitness and Wellness', 'PathFit', 'PathFit', '#7a1d3b', 80,
             'An introduction to physical fitness, exercise principles, and healthy habits for students.'],
        ];
        $ins = $pdo->prepare('INSERT INTO books (title, label, category, section, color, total_pages, description)
                              VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($books as [$title, $label, $cat, $section, $color, $pages, $desc]) {
            $ins->execute([$title, $label, $cat, $section, $color, $pages, $desc]);
        }
        echo "Sample books: " . count($books) . " added\n";
    } else {
        echo "Sample books: already present\n";
    }

    echo "\nDONE. Go to signup.php to create an account.\n";
    echo "IMPORTANT: now delete setup.php.\n";
} catch (PDOException $ex) {
    echo 'FAILED: ' . $ex->getMessage() . "\n";
    echo "Is MySQL running in the XAMPP control panel?\n";
}
