<?php
// Ensure these settings are applied before session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');  // Add this line

// Force PHP to use cookies for session
ini_set('session.use_trans_sid', 0);

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'  // This was already set, but we're keeping it for clarity
]);

// Function to set HttpOnly cookies
function setSecureCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = true, $httponly = true) {
    if (PHP_VERSION_ID < 70300) {
        setcookie($name, $value, $expire, $path . '; HttpOnly; Secure; SameSite=Lax', $domain, $secure, $httponly);
    } else {
        setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    }
}

// Start the session
session_start();

// Function to set HttpOnly cookies
function setHttpOnlyCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = true, $httponly = true) {
    if (PHP_VERSION_ID < 70300) {
        setcookie($name, $value, $expire, $path . '; HttpOnly; Secure; SameSite=Lax', $domain, $secure, $httponly);
    } else {
        setcookie($name, $value, [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ]);
    }
}

ob_start();
include('inc/header.php');
$loginError = '';

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['_token'])) {
        $loginError = "Invalid request. Please try again.";
    } else {
        // Regenerate CSRF token after valid form submission to prevent reuse
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Login logic
        if (!empty($_POST['email']) && !empty($_POST['pwd'])) {
            include 'Inventory.php';
            $inventory = new Inventory();
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $password = filter_input(INPUT_POST, 'pwd', FILTER_SANITIZE_STRING);
            $login = $inventory->login($email, $password);
            
            if (!empty($login)) {
                $_SESSION['userid'] = $login[0]['userid'];
                $_SESSION['name'] = $login[0]['name'];
                
                // Set any additional cookies with HttpOnly flag
                setSecureCookie('user_id', $login[0]['userid'], time() + 3600 * 24 * 30); // Example: 30 days expiration
                
                header("Location:index.php");
                exit;
            } else {
                $loginError = "Invalid email or password!";
            }
        }

        // Google login logic
        if (!empty($_POST['google_userid']) && !empty($_POST['google_name'])) {
            // Sanitize input data
            $googleUserId = htmlspecialchars($_POST['google_userid'], ENT_QUOTES, 'UTF-8');
            $googleName = htmlspecialchars($_POST['google_name'], ENT_QUOTES, 'UTF-8');

            // Set session variables
            $_SESSION['userid'] = $googleUserId;
            $_SESSION['name'] = $googleName;

            // Redirect to homepage or another page
            header("Location:index.php");
            exit;
        }
    }
}
?>

<style>
    html, body, body>.container {
        height: 95%;
        width: 100%;
    }
    body>.container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    #title {
        text-shadow: 2px 2px 5px #000;
    }
</style>

<?php include('inc/container.php'); ?>

<h1 class="text-center my-4 py-3 text-light" id="title">Sirigampola IMS</h1>
<div class="col-lg-4 col-md-5 col-sm-10 col-xs-12">
    <div class="card rounded-0 shadow">
        <div class="card-header">
            <div class="card-title h3 text-center mb-0 fw-bold">Login</div>
        </div>
        <div class="card-body">
            <div class="container-fluid">
                <div class="page_settings">
                    <form method="post" action="login.php" class="config-form disableAjax">
                        <!-- Add CSRF token hidden field -->
                        <input type="hidden" name="_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <!-- Add tab_hash hidden field -->
                        <input type="hidden" name="tab_hash" value="">

                        <div class="form-group">
                            <?php if ($loginError) { ?>
                                <div class="alert alert-danger rounded-0 py-1"><?php echo $loginError; ?></div>
                            <?php } ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="control-label">Email</label>
                            <input name="email" id="email" type="email" class="form-control rounded-0" placeholder="Email address" autofocus="" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="control-label">Password</label>
                            <input type="password" class="form-control rounded-0" id="password" name="pwd" placeholder="Password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="login" class="btn btn-primary rounded-0">Login</button>
                            <br>
                            <button type="button" id="googleLoginBtn" class="btn btn-primary rounded-0">Login using Google</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('inc/footer.php'); ?>
