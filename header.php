<head>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel='stylesheet' type='text/css' href="css/bootstrap.css" />
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="css/header.css" />
</head>
<header>
	<div class="header">
		<div class="logo">
			<a href="index.php">RFID Attendance</a>
		</div>
	</div>
	<?php
	if (isset($_GET['error'])) {
		if ($_GET['error'] == "wrongpasswordup") {
			echo '	<script type="text/javascript">
					 	setTimeout(function () {
			                $(".up_info1").fadeIn(200);
			                $(".up_info1").text("The password is wrong!!");
			                $("#admin-account").modal("show");
		              	}, 500);
		              	setTimeout(function () {
		                	$(".up_info1").fadeOut(1000);
		              	}, 3000);
					</script>';
		}
	}
	if (isset($_GET['success'])) {
		if ($_GET['success'] == "updated") {
			echo '	<script type="text/javascript">
			 			setTimeout(function () {
			                $(".up_info2").fadeIn(200);
			                $(".up_info2").text("Your Account has been updated");
              			}, 500);
              			setTimeout(function () {
                			$(".up_info2").fadeOut(1000);
              			}, 3000);
					</script>';
		}
	}
	if (isset($_GET['login'])) {
		if ($_GET['login'] == "success") {
			echo '<script type="text/javascript">
	              
	              setTimeout(function () {
	                $(".up_info2").fadeIn(200);
	                $(".up_info2").text("You successfully logged in");
	              }, 500);

	              setTimeout(function () {
	                $(".up_info2").fadeOut(1000);
	              }, 4000);
	            </script> ';
		}
	}
	?>
	<?php
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
	<div class="topnav" id="myTopnav">
		<a href="index.php" class="nav-btn<?php if($current_page=='index.php') echo ' active'; ?>">Students</a>
		<a href="ManageUsers.php" class="nav-btn<?php if($current_page=='ManageUsers.php') echo ' active'; ?>">Manage Students</a>
		<a href="ManageCourses.php" class="nav-btn<?php if($current_page=='ManageCourses.php') echo ' active'; ?>">Courses</a>
		<a href="userlog.php" class="nav-btn<?php if($current_page=='userlog.php') echo ' active'; ?>">Students Log</a>
		<a href="course_sessions.php" class="nav-btn<?php if($current_page=='course_sessions.php') echo ' active'; ?>">Course Sessions</a>
		<a href="attendance_summary.php" class="nav-btn<?php if($current_page=='attendance_summary.php') echo ' active'; ?>">Attendance</a>
		<a href="classes.php" class="nav-btn<?php if($current_page=='classes.php') echo ' active'; ?>">Classes</a>
		<a href="devices.php" class="nav-btn<?php if($current_page=='devices.php') echo ' active'; ?>">Devices</a>

		<?php
		if (isset($_SESSION['Admin-name'])) {
			echo '<button type="button" class="nav-btn nav-btn-admin" data-toggle="modal" data-target="#admin-account">' . $_SESSION['Admin-name'] . '</button>';
			echo '<a href="logout.php" class="nav-btn">Log Out</a>';
		} else {
			echo '<a href="login.php" class="nav-btn">Log In</a>';
		}
		?>
		<a href="javascript:void(0);" class="icon" onclick="navFunction()">
			<i class="fa fa-bars"></i></a>
	</div>

	<div class="up_info1 alert-danger"></div>
	<div class="up_info2 alert-success"></div>
</header>
<script>
	function navFunction() {
		var x = document.getElementById("myTopnav");
		if (x.className === "topnav") {
			x.className += " responsive";
		} else {
			x.className = "topnav";
		}
	}
</script>

<!-- Account Update -->
<div class="modal fade" id="admin-account" tabindex="-1" role="dialog" aria-labelledby="Admin Update"
	aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h3 class="modal-title" id="exampleModalLongTitle">Update Your Account Info:</h3>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="ac_update.php" method="POST" enctype="multipart/form-data">
				<div class="modal-body">
					<label for="User-mail"><b>Admin Name:</b></label>
					<input type="text" name="up_name" placeholder="Enter your Name..."
						value="<?php echo $_SESSION['Admin-name']; ?>" required /><br>
					<label for="User-mail"><b>Admin E-mail:</b></label>
					<input type="email" name="up_email" placeholder="Enter your E-mail..."
						value="<?php echo $_SESSION['Admin-email']; ?>" required /><br>
					<label for="User-psw"><b>Password</b></label>
					<input type="password" name="up_pwd" placeholder="Enter your Password..." required /><br>
				</div>
				<div class="modal-footer">
					<button type="submit" name="update" class="btn btn-success">Save changes</button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</form>
		</div>
	</div>
</div>
<!-- //Account Update -->

<!-- Add jQuery and Bootstrap JS if not already present -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>
<style>
.topnav {
  overflow: hidden;
  background: #f1f5f9;
  border-radius: 0;
  margin: 0;
  width: 100vw;
  max-width: 100vw;
  box-shadow: 0 6px 24px rgba(0,0,0,0.08);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  padding: 0 32px;
  left: 0;
  right: 0;
  justify-content: space-between;
}
.topnav .nav-btn, .topnav .nav-btn-admin {
  flex: 1 1 0;
  margin: 8px 8px;
  text-align: center;
  min-width: 110px;
  max-width: 160px;
  white-space: normal;
  overflow: visible;
  text-overflow: unset;
  word-break: break-word;
  line-height: 1.2;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 44px;
  color: #0f172a;
  background: #fff;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  font-weight: 800;
  font-size: 15px;
  padding: 10px 22px;
  text-decoration: none !important;
  transition: background 0.18s, color 0.18s, border 0.18s;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.topnav .nav-btn:hover, .topnav .nav-btn-admin:hover {
  background: #0ea5a4;
  color: #fff;
  border: 1.5px solid #0ea5a4;
}
.topnav .nav-btn.active, .topnav .nav-btn:active {
  background: #0ea5a4;
  color: #fff !important;
  border: 1.5px solid #0ea5a4;
}
.topnav .icon {
  display: none;
}
@media screen and (max-width: 900px) {
  .topnav { max-width: 100vw; padding: 0 8px; }
  .topnav .nav-btn, .topnav .nav-btn-admin { font-size: 13px; padding: 8px 4px; margin: 6px 2px; min-width: 80px; max-width: 110px; height: auto; }
}
@media screen and (max-width: 600px) {
  .topnav .nav-btn, .topnav .nav-btn-admin { width: 100%; margin: 4px 0; min-width: unset; max-width: unset; height: auto; }
  .topnav.responsive { flex-direction: column; }
  .topnav .icon { display: block; margin-left: auto; }
}
</style>