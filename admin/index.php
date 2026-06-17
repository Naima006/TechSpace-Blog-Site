<?php

session_start();
require_once __DIR__ . '/../db.php';
/** @var mysqli $conn */

if(isset($_GET['logout'])){

    session_destroy();

    header("Location: ../index.php");
    exit();
}

if(isset($_SESSION['admin_id'])){

    header("Location: dashboard.php");
    exit();
}

$error = "";

if(isset($_POST['login'])){

    $username = mysqli_real_escape_string(
        $conn,
        $_POST['username']
    );

    $password = $_POST['password'];

    $query = mysqli_query(
        $conn,
        "SELECT * FROM admins WHERE username='$username'"
    );

    if(mysqli_num_rows($query) > 0){

        $admin = mysqli_fetch_assoc($query);

        if($password == $admin['password']){

            $_SESSION['admin_id'] =
                $admin['admin_id'];

            $_SESSION['admin_username'] =
                $admin['username'];

            header("Location: dashboard.php");
            exit();

        }else{

            $error = "Invalid Password";
        }

    }else{

        $error = "Invalid Username";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Login</title>

    <link rel="stylesheet" href="admin.css">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <h1>
            <i class="fa-solid fa-user-shield"></i>
            Admin Login
        </h1>

        <?php if($error != ""){ ?>

            <div class="error">
                <?php echo $error; ?>
            </div>

        <?php } ?>

        <form method="POST">

            <input
                type="text"
                name="username"
                placeholder="Username"
                required>

            <input
                type="password"
                name="password"
                placeholder="Password"
                required>

            <button
                type="submit"
                name="login">

                Login

            </button>

        </form>

    </div>

</div>

</body>
</html>