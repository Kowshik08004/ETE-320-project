<?php
// =====================================================
// MANAGE STUDENTS (REGISTER + EDIT) — OPTION A
// =====================================================

// -------- SESSION CHECK --------
session_start();
if (!isset($_SESSION['Admin-name'])) {
	header("location: login.php");
	exit();
}

// -------- DATABASE --------
require 'connectDB.php';

// -------- EDIT MODE DETECTION --------
$edit_student = null;

if (isset($_GET['student_id'])) {
	$sid = (int)$_GET['student_id'];

	$stmt = $conn->prepare("
		SELECT 
			s.student_id,
			s.name,
			s.mobile,
			s.roll_no,
			s.gender,
			s.department_id,
			s.batch,
			s.level,
			s.term,
			r.card_uid
		FROM students s
		LEFT JOIN rfid_cards r ON r.student_id = s.student_id
		WHERE s.student_id = ?
		LIMIT 1
	");
	$stmt->bind_param("i", $sid);
	$stmt->execute();
	$edit_student = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>

<head>
	<title><?php echo $edit_student ? "Edit Student" : "Register Student"; ?></title>

	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="icon" type="image/png" href="images/favicon.png">
	<link rel="stylesheet" type="text/css" href="css/manageusers.css">

	<script src="js/jquery-2.2.3.min.js"></script>
	<script src="js/bootstrap.js"></script>
	<script src="js/manage_users.js"></script>
</head>

<body>

	<?php include 'header.php'; ?>

	<main>

		<!-- =====================================================
     STUDENT FORM
===================================================== -->
		<div class="form-style-5">
			<form method="POST" action="register_student.php">

				<div class="alert_user"></div>

				<!-- -------- Hidden student_id (EDIT MODE ONLY) -------- -->
				<input type="hidden" name="student_id"
					value="<?php echo $edit_student['student_id'] ?? ''; ?>">

				   <div class="form-flex-row">
					   <fieldset>
						   <legend><span class="number">1</span> Card & Student Info</legend>
						   <!-- RFID -->
						   <input type="text"
							   name="card_uid"
							   id="card_uid"
							   placeholder="Scan / Select RFID Card"
							   value="<?php echo $edit_student['card_uid'] ?? ''; ?>"
							   <?php echo $edit_student ? 'readonly' : 'required'; ?>>
						   <!-- Name -->
						   <input type="text"
							   name="name"
							   placeholder="Student Name"
							   value="<?php echo htmlspecialchars($edit_student['name'] ?? ''); ?>"
							   required>
						   <!-- Mobile Number -->
						   <input type="text"
							   name="mobile"
							   placeholder="Mobile Number"
							   pattern="[0-9]{10,20}"
							   value="<?php echo htmlspecialchars($edit_student['mobile'] ?? ''); ?>"
							   required>
						   <!-- Student ID / Roll -->
						   <input type="text"
							   name="roll_no"
							   placeholder="Student ID"
							   value="<?php echo htmlspecialchars($edit_student['roll_no'] ?? ''); ?>"
							   required>
					   </fieldset>
					   <fieldset>
						   <legend><span class="number">2</span> Academic Info</legend>
						   <!-- Department -->
						   <label><b>Department</b></label>
						   <select name="department_id" required>
							   <option value="">Select Department</option>
							   <?php
							   $res = mysqli_query($conn, "SELECT * FROM departments ORDER BY department_name");
							   while ($d = mysqli_fetch_assoc($res)) {
								   $sel = ($edit_student && $edit_student['department_id'] == $d['department_id'])
									   ? 'selected' : '';
								   echo "<option value='{$d['department_id']}' $sel>{$d['department_name']}</option>";
							   }
							   ?>
						   </select>
						   <!-- Batch -->
						   <label><b>Batch</b></label>
						   <select name="batch" required>
							   <option value="">Select Batch</option>
							   <?php
							   for ($i = 12; $i <= 30; $i++) {
								   $sel = ($edit_student && $edit_student['batch'] == $i) ? 'selected' : '';
								   echo "<option value='$i' $sel>$i</option>";
							   }
							   ?>
						   </select>
						   <!-- Level -->
						   <label><b>Level</b></label>
						   <select name="level" required>
							   <option value="">Select Level</option>
							   <?php
							   for ($i = 1; $i <= 4; $i++) {
								   $sel = ($edit_student && $edit_student['level'] == $i) ? 'selected' : '';
								   echo "<option value='$i' $sel>Level $i</option>";
							   }
							   ?>
						   </select>
						   <!-- Term -->
						   <label><b>Term</b></label>
						   <select name="term" required>
							   <option value="">Select Term</option>
							   <?php
							   for ($i = 1; $i <= 2; $i++) {
								   $sel = ($edit_student && $edit_student['term'] == $i) ? 'selected' : '';
								   echo "<option value='$i' $sel>Term $i</option>";
							   }
							   ?>
						   </select>
						   <!-- Gender -->
						   <label><b>Gender</b></label><br>
						   <input type="radio" name="gender" value="Male"
							   <?php
							   echo (!$edit_student || $edit_student['gender'] === 'Male') ? 'checked' : '';
							   ?>> Male
						   <input type="radio" name="gender" value="Female"
							   <?php
							   echo ($edit_student && $edit_student['gender'] === 'Female') ? 'checked' : '';
							   ?>> Female
					   </fieldset>
				   </div>

					<!-- -------- ACTION -------- -->
					<div style="display: flex; gap: 10px;">
						   <?php if ($edit_student): ?>
							   <button type="submit">
								   Update Student
							   </button>
							   <a href="ManageUsers.php" class="form-cancel-btn" style="margin-left:10px;">Cancel</a>
						   <?php else: ?>
							   <button type="submit">
								   Register Student
							   </button>
						   <?php endif; ?>
					</div>

			</form>
		</div>

		<!-- =====================================================
     UNREGISTERED RFID CARD LIST (REGISTER MODE ONLY)
===================================================== -->
		<?php if (!$edit_student): ?>
			<div class="section">
				<div id="manage_users"></div>
			</div>

			<script>
				$(function() {
					function loadCards() {
						$('#manage_users').load('manage_users_up.php');
					}
					loadCards();
					// setInterval(loadCards, 5000);
				});
			</script>
		<?php endif; ?>

	</main>

</body>

</html>