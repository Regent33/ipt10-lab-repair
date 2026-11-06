<?php
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/notes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// No Access-Control-Allow-* headers on purpose: only pages from this same site may use the API.

// 1. Must be logged in.
$userId = current_user_id();
if ($userId === null) {
    fail(401, 'Authentication required.');
}

// 2. Requests from other websites are rejected.
if (!same_origin()) {
    fail(403, 'Cross-origin requests are not allowed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // user_id is optional, but it can only be your own ID.
    $owner = isset($_GET['user_id']) ? parse_id($_GET['user_id']) : $userId;
    if ($owner !== $userId) {
        fail(403, 'You can only list your own notes.');
    }
    echo json_encode(notes_for_owner($userId));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: the token from the page must be sent in the X-CSRF-Token header.
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        fail(403, 'Invalid or missing CSRF token.');
    }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        fail(415, 'Content-Type must be application/json.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fail(400, 'Malformed JSON.');
    }
    if (!is_array($data) || !isset($data['id'], $data['title'], $data['body'])) {
        fail(400, 'Expected a JSON object with id, title, and body.');
    }

    $id = parse_id($data['id']);
    if (!$id) {
        fail(400, 'id must be a positive integer.');
    }
    $error = note_error($data['title'], $data['body']);
    if ($error) {
        fail(400, $error);
    }

    // Only updates the note if it belongs to the logged-in user.
    if (!update_note($id, $userId, $data['title'], $data['body'])) {
        fail(404, 'Note not found.'); // same answer if missing or not yours
    }
    echo json_encode(['updated' => 1]);
    exit;
}

header('Allow: GET, POST');
fail(405, 'Method not supported.');
