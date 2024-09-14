<?php
ob_start();
session_start();
include('inc/header.php');

// Directly establishing the database connection
$servername = "sql211.infinityfree.com";
$username = "if0_35151025";
$password = "ZBAS8ug2jP";
$dbname = "if0_35151025_ims_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $type = 'user';
    $status = 1;

    // File upload logic
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);

    // Check the file size (1MB = 1024 * 1024 bytes)
    if ($_FILES["profile_picture"]["size"] > 1024 * 1024) {
        echo "File size exceeds the limit (1MB). Please choose a smaller file.";
    } else {
        // Check the file type (allowed extensions: jpg, jpeg, png, gif)
        $allowed_extensions = array("jpg", "jpeg", "png", "gif");
        $uploaded_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));

        if (!in_array($uploaded_extension, $allowed_extensions)) {
            echo "Invalid file format. Please choose a valid image file (JPEG, PNG, GIF).";
        } else {
            move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file);

            $sql = "INSERT INTO ims_user (name, password, email, type, status, profile_picture) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssis", $name, $password, $email, $type, $status, $target_file);

            if ($stmt->execute()) {
                // Signup successful, redirect to login page
                header("Location: login.php");
                exit(); // Ensure script termination after redirection
            } else {
                // Handle signup failure, display an error message, etc.
                echo "Signup failed!";
            }
        }
    }
}
?>

<style>
    /* Your existing CSS and additional styling */
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

<h1 class="text-center my-4 py-3 text-light" id="title">Sirigampola IMS</h1>
<div class="col-lg-4 col-md-5 col-sm-10 col-xs-12">
    <div class="card rounded-0 shadow">
        <div class="card-header">
            <div class="card-title h3 text-center mb-0 fw-bold">Signup</div>
        </div>
        <div class="card-body">
            <div class="container-fluid">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                    <div class="form-group">
                    </div>
                    <div class="mb-3">
                        <label for="username" class="control-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control rounded-0" placeholder="Username" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="control-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control rounded-0" placeholder="Email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="control-label">Password</label>
                        <input type="password" class="form-control rounded-0" id="password" name="password" placeholder="Password" required>
                    </div>
                    <div class="mb-3">
                        <label for="profile_picture" class="control-label">Profile Picture</label>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control rounded-0" accept="" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="signup" class="btn btn-primary rounded-0">Signup</button>
                    </div><br>
                    <div class="d-grid">
                        <a href="login.php">Go to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include('inc/footer.php'); ?>
