<?php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_GET['logout'])) {
    unset($_SESSION['author_id'], $_SESSION['author_name'], $_SESSION['author_email']);
    session_write_close();
    header("Location: ../index.php");
    exit();
}

if (isset($_SESSION['author_id'])) {
    header("Location: dashboard.php");
    exit();
}

$mode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';
$error = "";
$success = "";

/* ---------- LOGIN ---------- */
if (isset($_POST['author_login'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $q = mysqli_query($conn, "SELECT * FROM authors WHERE email='$email' LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $author = mysqli_fetch_assoc($q);
        if (empty($author['password'])) {
            $error = "This author account has no login password. Contact the admin.";
        } elseif ($password !== $author['password']) {
            $error = "Invalid password.";
        } elseif ($author['status'] === 'pending') {
            $error = "Your account is still pending admin approval.";
        } elseif ($author['status'] === 'rejected') {
            $error = "Your registration was rejected. Please contact the admin.";
        } else {
            $_SESSION['author_id'] = $author['author_id'];
            $_SESSION['author_name'] = $author['author_name'];
            $_SESSION['author_email'] = $author['email'];
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $error = "No author found with that email.";
    }
    $mode = 'login';
}

/* ---------- REGISTER ---------- */
if (isset($_POST['author_register'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['author_name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 4) {
        $error = "Password must be at least 4 characters.";
    } elseif ($password !== $password2) {
        $error = "Passwords do not match.";
    } else {
        $exists = mysqli_query($conn, "SELECT author_id FROM authors WHERE email='$email' LIMIT 1");
        if ($exists && mysqli_num_rows($exists) > 0) {
            $error = "An account with this email already exists.";
        } else {
            $pass_esc = mysqli_real_escape_string($conn, $password);
            $avatar = 'avatar1.png';
            if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $original = basename($_FILES['avatar_file']['name']);
                $clean = time() . "_" . preg_replace("/[^A-Za-z0-9.\-_]/", "", $original);
                $dest = __DIR__ . '/../' . $clean;
                if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $dest)) {
                    $avatar = $clean;
                }
            }
            $ok = mysqli_query($conn, "
                INSERT INTO authors (author_name, avatar_url, email, password, status)
                VALUES ('$name', '$avatar', '$email', '$pass_esc', 'pending')
            ");
            if ($ok) {
                $success = "Registration submitted! An admin will review and approve your account shortly.";
                $mode = 'login';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
    if ($error) $mode = 'register';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'register' ? 'Author Register' : 'Author Sign In'; ?> · TechSpace</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../admin/admin.css">
</head>
<body>

<div class="login-container">
    <div class="login-card" style="width: 440px; max-width: 94vw;">

        <h1>
            <i class="fa-solid <?php echo $mode === 'register' ? 'fa-user-plus' : 'fa-feather-pointed'; ?>" style="padding-right: 10px;"></i>
            <?php echo $mode === 'register' ? 'Become an Author' : 'Author Sign In'; ?>
        </h1>

        <p style="text-align:center; color: var(--text-secondary); font-size: 12px; margin: -12px 0 22px; line-height: 1.5;">
            <?php if ($mode === 'register') { ?>
                Register and start writing. An admin will approve your request.
            <?php } else { ?>
                Sign in with your approved author credentials.
            <?php } ?>
        </p>

        <?php if ($error !== "") { ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>
        <?php if ($success !== "") { ?>
            <div class="error" style="background: rgba(52, 211, 153, 0.12); border-color: rgba(52, 211, 153, 0.3); color: #34d399;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php } ?>

        <div style="display:flex; gap:8px; margin-bottom: 18px;">
            <a href="index.php" class="nav-auth-btn" style="flex:1; justify-content:center; <?php echo $mode === 'login' ? 'background:rgba(96,165,250,0.15); color:var(--accent-blue); border-color:rgba(96,165,250,0.35);' : ''; ?>">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </a>
            <a href="index.php?mode=register" class="nav-auth-btn" style="flex:1; justify-content:center; <?php echo $mode === 'register' ? 'background:rgba(96,165,250,0.15); color:var(--accent-blue); border-color:rgba(96,165,250,0.35);' : ''; ?>">
                <i class="fa-solid fa-user-plus"></i> Register
            </a>
        </div>

        <?php if ($mode === 'login') { ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
            <div class="password-field-wrap">
                <input type="password" name="password" id="authorPassword" placeholder="Password" required autocomplete="current-password">
                <button type="button" class="toggle-password-btn" id="toggleAuthorPassword" aria-label="Show password">
                    <i class="fa-solid fa-eye" id="toggleAuthorPasswordIcon"></i>
                </button>
            </div>
            <button type="submit" name="author_login">Sign In</button>
        </form>
        <?php } else { ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="author_name" placeholder="Display name" required>
            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
            <div class="password-field-wrap">
                <input type="password" name="password" id="regPassword" placeholder="Password" required autocomplete="new-password">
                <button type="button" class="toggle-password-btn" data-target="regPassword" aria-label="Show password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="password-field-wrap">
                <input type="password" name="password_confirm" id="regPassword2" placeholder="Confirm password" required autocomplete="new-password">
                <button type="button" class="toggle-password-btn" data-target="regPassword2" aria-label="Show password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <label style="font-size:12px; color:var(--text-secondary); margin-top:4px;">Avatar (optional)</label>
            <input type="file" name="avatar_file" accept="image/*">
            <button type="submit" name="author_register">Submit Registration</button>
        </form>
        <?php } ?>

        <p style="text-align:center; margin-top: 22px; font-size: 13px;">
            <a href="../index.php" style="color: var(--accent-blue); text-decoration: none;">
                <i class="fa-solid fa-arrow-left"></i> Back to TechSpace
            </a>
        </p>
    </div>
</div>

<script>
function bindToggle(btnId, inputId, iconId) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
        } else {
            input.type = 'password';
            if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
    });
}
bindToggle('toggleAuthorPassword', 'authorPassword', 'toggleAuthorPasswordIcon');

document.querySelectorAll('.toggle-password-btn[data-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(this.getAttribute('data-target'));
        var icon = this.querySelector('i');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
        } else {
            input.type = 'password';
            if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
    });
});
</script>

</body>
</html>
