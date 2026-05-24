<?php
require '../config/auth.php';
require '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error = '';
$demoUsername = 'admin';
$demoPassword = 'admin123';
$username = $demoUsername;
$passwordValue = $demoPassword;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordValue = '';

    if ($username === '' || $password === '') {
        $error = 'Enter both your username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashed_password, $role);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['role'] = $role;
                $_SESSION['username'] = $username;
                header("Location: ../index.php");
                exit;
            }

            $error = 'Incorrect password.';
        } else {
            $error = 'User not found.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Apartment Management System</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=4">
</head>
<body>
  <div class="auth-shell">
    <div class="wrapper">
      <form method="POST" autocomplete="on">
        <div class="auth-header">
          <div class="brand-tag"><i class='bx bxs-building-house'></i> Apartment Management System</div>
          <h1>Login</h1>
          <p class="lead">Demo access is ready to use.</p>
        </div>

        <div class="demo-box">
          <span>Portfolio demo account</span>
          <strong><?= htmlspecialchars($demoUsername) ?> / <?= htmlspecialchars($demoPassword) ?></strong>
        </div>

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
              placeholder="Enter your username"
              value="<?= htmlspecialchars($username) ?>"
              autocomplete="username"
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
              placeholder="Enter your password"
              value="<?= htmlspecialchars($passwordValue) ?>"
              autocomplete="current-password"
              required
            >
            <i class='bx bxs-lock-alt'></i>
          </div>
        </div>

        <div class="password-row">
          <label for="togglePassword">
            <input type="checkbox" id="togglePassword">
            Show password
          </label>
        </div>

        <div class="action-group">
          <button type="submit" class="btn">Enter Demo</button>
          <a href="register.php" class="btn-secondary">Create New User</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('change', function () {
      passwordInput.type = this.checked ? 'text' : 'password';
    });
  </script>
</body>
</html>
