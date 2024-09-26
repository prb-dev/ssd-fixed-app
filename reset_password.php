<?php
require_once 'init.php';
ob_start();
session_start();
include('inc/header.php');

// Load environment variables from .env file
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Retrieve database credentials from environment variables
$servername = $_ENV['DB_SERVERNAME'];
$username = $_ENV['DB_USERNAME'];
$password = $_ENV['DB_PASSWORD'];
$dbname = $_ENV['DB_NAME'];

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$resetError = '';  // Initialize here to avoid undefined variable warning

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $new_password = rand(100000, 999999); // Generate a random new password
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "UPDATE ims_user SET password=? WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashedPassword, $email);

    if ($stmt->execute()) {
        $resetError = "Password has been reset. New password is $new_password";
    } else {
        $resetError = "Failed to reset password: " . $stmt->error;
    }
}
?>

<style>

    html,
    body,
    body>.container {
        height: 95%;
        width: 100%;
    }
    body>.container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    #title{
        text-shadow: 2px 2px 5px #000;
    }
</style>

<?php include('inc/container.php'); ?>

<h1 class="text-center my-4 py-3 text-light" id="title">Inventory Management System - PHP</h1>
<div class="col-lg-4 col-md-5 col-sm-10 col-xs-12">
    <div class="card rounded-0 shadow">
        <div class="card-header">
            <div class="card-title h3 text-center mb-0 fw-bold">Reset Password</div>
        </div>
        <div class="card-body">
            <div class="container-fluid">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <?php if ($resetError) { ?>
                            <div class="alert alert-info rounded-0 py-1"><?php echo $resetError; ?></div>
                        <?php } ?>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="control-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control rounded-0" placeholder="Email" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="reset_password" class="btn btn-primary rounded-0">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include('inc/footer.php'); ?>
