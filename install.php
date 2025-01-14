<?php
session_start();

if (file_exists(__DIR__ . '/.env')) {
    die('The application is already installed.');
}

$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $_SESSION['db_host'] = $_POST['db_host'];
        $_SESSION['db_port'] = $_POST['db_port'];
        $_SESSION['db_name'] = $_POST['db_name'];
        $_SESSION['db_user'] = $_POST['db_user'];
        $_SESSION['db_pass'] = $_POST['db_pass'];
        header('Location: install.php?step=2');
        exit;
    } elseif ($step === 2) {
        $db_host = $_SESSION['db_host'];
        $db_port = $_SESSION['db_port'];
        $db_name = $_SESSION['db_name'];
        $db_user = $_SESSION['db_user'];
        $db_pass = $_SESSION['db_pass'];

        try {
            $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create .env file
            $env_content = "APP_NAME=Paymenter\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=http://localhost\n\nDB_CONNECTION=mysql\nDB_HOST=$db_host\nDB_PORT=$db_port\nDB_DATABASE=$db_name\nDB_USERNAME=$db_user\nDB_PASSWORD=$db_pass\n";
            file_put_contents(__DIR__ . '/.env', $env_content);

            // Run migrations
            exec('php artisan migrate --force');

            header('Location: install.php?step=3');
            exit;
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install Paymenter</title>
</head>
<body>
    <?php if ($step === 1): ?>
        <h1>Step 1: Database Configuration</h1>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="db_host">Database Host:</label>
            <input type="text" id="db_host" name="db_host" required><br>
            <label for="db_port">Database Port:</label>
            <input type="text" id="db_port" name="db_port" value="3306" required><br>
            <label for="db_name">Database Name:</label>
            <input type="text" id="db_name" name="db_name" required><br>
            <label for="db_user">Database User:</label>
            <input type="text" id="db_user" name="db_user" required><br>
            <label for="db_pass">Database Password:</label>
            <input type="password" id="db_pass" name="db_pass" required><br>
            <button type="submit">Next</button>
        </form>
    <?php elseif ($step === 2): ?>
        <h1>Step 2: Finalizing Installation</h1>
        <p>Please wait while we set up the database...</p>
    <?php elseif ($step === 3): ?>
        <h1>Installation Complete</h1>
        <p>The installation is complete. You can now <a href="index.php">login to your application</a>.</p>
    <?php endif; ?>
</body>
</html>