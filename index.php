<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/notes.php';

// Only our own CSS/JS files are allowed. No inline scripts or styles.
header("Content-Security-Policy: default-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

// Read a text field from the form. Anything that is not a plain string (like email[]=x) becomes ''.
function post_text($name)
{
    return is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
}

// Save a message in the session and go back to the home page.
function go_home($message = '', $isError = false)
{
    if ($message !== '') {
        $_SESSION['flash'] = ['text' => $message, 'error' => $isError];
    }
    header('Location: /');
    exit;
}

// Every action that changes data must be a POST with a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!same_origin() || !csrf_valid($_POST['csrf_token'] ?? null)) {
        fail(403, 'Invalid or missing security token. Reload the page and try again.');
    }

    $action = post_text('action');
    $email = trim(post_text('email'));
    $password = post_text('password');

    if ($action === 'register') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < PASSWORD_MIN) {
            go_home('Enter a valid email and a password with at least ' . PASSWORD_MIN . ' characters.', true);
        }
        if (!create_user($email, $password)) {
            go_home('That email can’t be used for a new account.', true);
        }
        go_home('Account created. Sign in with your new password.');
    }

    if ($action === 'login') {
        $user = verify_login($email, $password);
        if (!$user) {
            go_home('Email or password is incorrect.', true); // same message for wrong email or wrong password
        }
        log_in($user);
        go_home();
    }

    if ($action === 'logout') {
        log_out();
        go_home();
    }

    // The actions below need a logged-in user.
    $userId = current_user_id();
    if ($userId === null) {
        fail(401, 'Please sign in first.');
    }

    $id = parse_id(post_text('id'));
    $title = post_text('title');
    $body = post_text('body');

    if ($action === 'save') {
        $error = note_error($title, $body);
        if ($error) {
            go_home($error, true);
        }
        create_note($userId, $title, $body); // owner comes from the session, not the form
        go_home('Note saved.');
    }

    if ($action === 'update') {
        $error = note_error($title, $body);
        if ($error) {
            go_home($error, true);
        }
        if (!$id || !update_note($id, $userId, $title, $body)) {
            fail(404, 'Note not found.');
        }
        go_home('Changes saved.');
    }

    if ($action === 'delete') {
        if (!$id || !delete_note($id, $userId)) {
            fail(404, 'Note not found.');
        }
        go_home('Note deleted.');
    }

    fail(400, 'Unknown action.');
}

// GET: show the page.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$userId = current_user_id();
$notes = [];
$editing = null;
if ($userId !== null) {
    $notes = notes_for_owner($userId);
    if (isset($_GET['edit'])) {
        $editId = parse_id($_GET['edit']);
        $editing = $editId ? find_note($editId, $userId) : null;
        if (!$editing) {
            fail(404, 'Note not found.');
        }
    }
}

require __DIR__ . '/templates/home.php';
