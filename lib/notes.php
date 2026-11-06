<?php
// Database functions for users and notes.
// All queries use prepared statements, and every note query also checks user_id (the owner).

const TITLE_MAX = 120; // same as notes.title VARCHAR(120)
const BODY_MAX = 10000;
const PASSWORD_MIN = 8;

// Returns false if the email is already taken.
// The role is always 'student' - the user cannot choose it.
function create_user($email, $password)
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return false;
    }
    $stmt = db()->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'student']);
    return true;
}

// Returns the user row if the email and password are correct, otherwise null.
function verify_login($email, $password)
{
    $stmt = db()->prepare('SELECT id, email, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return null;
}

function notes_for_owner($ownerId)
{
    $stmt = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

// Returns the note only if it belongs to $ownerId.
function find_note($id, $ownerId)
{
    $stmt = db()->prepare('SELECT id, user_id, title, body FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $ownerId]);
    return $stmt->fetch() ?: null;
}

function create_note($ownerId, $title, $body)
{
    $stmt = db()->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
    $stmt->execute([$ownerId, $title, $body]);
}

// Returns false if the note does not exist or belongs to someone else.
function update_note($id, $ownerId, $title, $body)
{
    if (!find_note($id, $ownerId)) {
        return false;
    }
    $stmt = db()->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $body, $id, $ownerId]);
    return true;
}

function delete_note($id, $ownerId)
{
    $stmt = db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $ownerId]);
    return $stmt->rowCount() === 1;
}

// Returns an error message, or null if the title and body are OK.
function note_error($title, $body)
{
    if (!is_string($title) || !is_string($body)) {
        return 'Title and body must be text.';
    }
    if (trim($title) === '' || trim($body) === '') {
        return 'Title and body are required.';
    }
    if (mb_strlen($title) > TITLE_MAX || mb_strlen($body) > BODY_MAX) {
        return 'Title or body is too long.';
    }
    return null;
}

// Turns user input into a positive whole number, or null if it is not one.
function parse_id($value)
{
    if (!is_int($value) && !is_string($value)) {
        return null; // e.g. JSON true, arrays
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}
