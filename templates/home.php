<?php
// HTML for the home page. index.php sets $flash, $userId, $notes and $editing.
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
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="csrf-token" content="<?= $token ?>">
<title>Campus Notes</title>
<link rel="stylesheet" href="/assets/style.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<?php if ($userId === null): ?>
<main class="welcome">
  <section class="intro">
    <p class="brand">Campus Notes</p>
    <h1>Your course notes, kept to yourself.</h1>
    <p class="lead">Write down reminders, study plans and project ideas. Each note can only be seen by the account that wrote it.</p>
  </section>

  <section class="panel" aria-labelledby="signin-title">
    <h2 id="signin-title">Sign in</h2>
    <?php if ($flash): ?>
    <p class="flash<?= $flash['error'] ? ' error' : '' ?>" role="<?= $flash['error'] ? 'alert' : 'status' ?>"><?= e($flash['text']) ?></p>
    <?php endif; ?>
    <?php // Sign in is the first button, so pressing Enter signs in instead of registering. ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf_token" value="<?= $token ?>">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" placeholder="name@example.test" maxlength="254" required>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" minlength="<?= PASSWORD_MIN ?>" aria-describedby="password-hint" required>
        <p class="hint" id="password-hint">New accounts need at least <?= PASSWORD_MIN ?> characters.</p>
      </div>
      <div class="actions">
        <button class="btn primary" name="action" value="login">Sign in</button>
        <button class="btn" name="action" value="register">Create account</button>
      </div>
    </form>
  </section>
</main>
<?php else: ?>
<header class="topbar">
  <p class="brand">Campus Notes</p>
  <div class="account">
    <span class="who"><?= e($_SESSION['email'] ?? '') ?></span>
    <span class="role"><?= e($_SESSION['role'] ?? '') ?></span>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= $token ?>">
      <button class="btn small" name="action" value="logout">Sign out</button>
    </form>
  </div>
</header>

<main class="workspace">
  <section class="panel composer" aria-labelledby="composer-title">
    <h2 id="composer-title"><?= $editing ? 'Edit note' : 'New note' ?></h2>
    <?php if ($flash): ?>
    <p class="flash<?= $flash['error'] ? ' error' : '' ?>" role="<?= $flash['error'] ? 'alert' : 'status' ?>"><?= e($flash['text']) ?></p>
    <?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf_token" value="<?= $token ?>">
      <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?= e($editing['id']) ?>">
      <?php endif; ?>
      <div class="field">
        <label for="title">Title</label>
        <input id="title" name="title" value="<?= e($editing['title'] ?? '') ?>" placeholder="IPT quiz on Friday" maxlength="<?= TITLE_MAX ?>" required>
      </div>
      <div class="field">
        <label for="body">Note</label>
        <textarea id="body" name="body" rows="7" maxlength="<?= BODY_MAX ?>" required><?= e($editing['body'] ?? '') ?></textarea>
      </div>
      <div class="actions">
        <?php if ($editing): ?>
        <button class="btn primary" name="action" value="update">Save changes</button>
        <a class="btn" href="/">Cancel</a>
        <?php else: ?>
        <button class="btn primary" name="action" value="save">Save note</button>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="notes" aria-labelledby="notes-title">
    <div class="notes-head">
      <h2 id="notes-title">Notes</h2>
      <p class="count"><?= count($notes) ?> <?= count($notes) === 1 ? 'note' : 'notes' ?></p>
    </div>

    <?php if (!$notes): ?>
    <div class="empty">
      <p class="empty-title">No notes yet</p>
      <p>Write a title and a few lines under New note, then select Save note.</p>
    </div>
    <?php endif; ?>

    <?php foreach ($notes as $note): ?>
    <?php $isEditing = $editing && (int) $editing['id'] === (int) $note['id']; ?>
    <article class="note<?= $isEditing ? ' editing' : '' ?>">
      <h3><?= e($note['title']) ?></h3>
      <p><?= e($note['body']) ?></p>
      <div class="note-actions">
        <?php if ($isEditing): ?>
        <span class="tag">Editing</span>
        <?php else: ?>
        <a class="btn small" href="?edit=<?= e($note['id']) ?>" aria-label="Edit <?= e($note['title']) ?>">Edit</a>
        <?php endif; ?>
        <form method="post" data-confirm="Delete this note? This can’t be undone.">
          <input type="hidden" name="csrf_token" value="<?= $token ?>">
          <input type="hidden" name="id" value="<?= e($note['id']) ?>">
          <button class="btn small danger" name="action" value="delete" aria-label="Delete <?= e($note['title']) ?>">Delete</button>
        </form>
      </div>
    </article>
    <?php endforeach; ?>
  </section>
</main>
<?php endif; ?>
<footer class="foot">Local lab build. Synthetic data only.</footer>
</body>
</html>
