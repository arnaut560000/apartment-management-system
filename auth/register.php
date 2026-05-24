<?php
require '../config/auth.php';
require '../config/db.php';
require '../config/app.php';

$message = '';
$error = '';
$username = '';
$role = '';
$access_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $access_password = trim($_POST['access_password'] ?? '');

    if ($username === '' || $password === '' || $role === '' || $access_password === '') {
        $error = 'Complete all fields before creating the account.';
    } elseif ($access_password !== app_config('registration_access_password', 'arnaut')) {
        $error = 'Incorrect registration access password.';
    } elseif (strlen($password) < 6) {
        $error = 'Use at least 6 characters for the password.';
    } elseif (!in_array($role, ['admin', 'staff'], true)) {
        $error = 'Select a valid role.';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'That username is already in use.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashedPassword, $role);

            if ($stmt->execute()) {
                $message = "User created successfully. <a href='login.php'>Go to login</a>.";
                $username = '';
                $role = '';
            } else {
                $error = 'Unable to register the user right now.';
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register | Apartment Management System</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=4">
</head>
<body>
  <div class="auth-shell">
    <div class="wrapper">
      <form method="POST" autocomplete="off">
        <div class="auth-header">
          <div class="brand-tag"><i class='bx bxs-user-plus'></i> Create User</div>
          <h1>Register</h1>
          <p class="lead">Add a new account for the system.</p>
        </div>

        <?php if ($message): ?>
          <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="input-box">
          <label class="field-label" for="username">Username</label>
          <div class="input-shell">
            <input
              type="text"
              id="username"
              name="username"
              placeholder="Choose a username"
              value="<?= htmlspecialchars($username) ?>"
              required
            >
            <i class='bx bxs-user'></i>
          </div>
        </div>

        <div class="input-box">
          <label class="field-label" for="password">Password</label>
          <div class="input-shell">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Use at least 6 characters"
              required
            >
            <i class='bx bxs-lock-alt'></i>
          </div>
        </div>

        <div class="input-box">
          <label class="field-label" for="access_password">Registration Access Password</label>
          <div class="input-shell">
            <input
              type="password"
              id="access_password"
              name="access_password"
              placeholder="Enter access password"
              required
            >
            <i class='bx bxs-key'></i>
          </div>
        </div>

        <div class="password-row">
          <label for="togglePassword">
            <input type="checkbox" id="togglePassword">
            Show passwords
          </label>
        </div>

        <div class="input-box">
          <label class="field-label" for="role">Role</label>
          <div class="input-shell">
            <select id="role" name="role" required>
              <option value="" disabled <?= $role === '' ? 'selected' : '' ?>>Select a role</option>
              <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
              <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>
            <i class='bx bxs-badge-check'></i>
          </div>
        </div>

        <div class="action-group">
          <button type="submit" class="btn">Register</button>
          <a href="login.php" class="btn-secondary">Back to Login</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const accessPasswordInput = document.getElementById('access_password');

    togglePassword.addEventListener('change', function () {
      passwordInput.type = this.checked ? 'text' : 'password';
      accessPasswordInput.type = this.checked ? 'text' : 'password';
    });
  </script>
</body>
</html>
