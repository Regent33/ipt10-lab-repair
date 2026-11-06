<?php
// HTML for the home page. index.php sets $message, $userId, $notes and $editing.
// Every value from the database or the user is printed with e() so it shows as text, not HTML.

// This file should only be included by index.php, not opened directly.
if (!function_exists('e')) {
    http_response_code(404);
    exit;
}
$token = e(csrf_token());
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Campus Notes</title>
<meta name="csrf-token" content="<?= $token ?>">
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}article p{white-space:pre-wrap}form.inline{display:inline}</style>
</head>
<body>
<h1>Campus Notes</h1>
<?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?>
<?php if ($userId === null): ?>
<h2>Register or sign in</h2>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= $token ?>">
  <label>Email <input name="email" type="email" required></label>
  <label>Password <input name="password" type="password" required minlength="<?= PASSWORD_MIN ?>"></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<p>User ID: <?= e((string) $userId) ?> | Role: <?= e($_SESSION['role'] ?? '') ?> |
<form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?= $token ?>"><button name="action" value="logout">Log out</button></form></p>
<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= $token ?>">
  <input type="hidden" name="id" value="<?= e((string) $editing['id']) ?>">
  <label>Title <input name="title" value="<?= e($editing['title']) ?>" required maxlength="<?= TITLE_MAX ?>"></label>
  <label>Body <textarea name="body" required maxlength="<?= BODY_MAX ?>"><?= e($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>
<h2>Create a note</h2>
<form method="post">
  <input type="hidden" name="csrf_token" value="<?= $token ?>">
  <label>Title <input name="title" required maxlength="<?= TITLE_MAX ?>"></label>
  <label>Body <textarea name="body" required maxlength="<?= BODY_MAX ?>"></textarea></label>
  <button name="action" value="save">Save</button>
</form>
<h2>My notes</h2>
<?php foreach ($notes as $note): ?>
<article><h3><?= e($note['title']) ?></h3><p><?= e($note['body']) ?></p>
<a href="?edit=<?= e((string) $note['id']) ?>">Edit</a>
<form method="post" class="inline">
  <input type="hidden" name="csrf_token" value="<?= $token ?>">
  <input type="hidden" name="id" value="<?= e((string) $note['id']) ?>">
  <button name="action" value="delete">Delete</button>
</form></article>
<?php endforeach; ?>
<?php endif; ?>
</body></html>
