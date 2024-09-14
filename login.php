<?php
ob_start();
session_start();
include('inc/header.php');
$loginError = '';
if (!empty($_POST['email']) && !empty($_POST['pwd'])) {
	include 'Inventory.php';
	$inventory = new Inventory();
	$login = $inventory->login($_POST['email'], $_POST['pwd']);
	if (!empty($login)) {
		$_SESSION['userid'] = $login[0]['userid'];
		$_SESSION['name'] = $login[0]['name'];
		header("Location:index.php");
	} else {
		$loginError = "Invalid email or password!";
	}
}

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
				<form method="post" action="">
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
<?php include('inc/footer.php'); ?>