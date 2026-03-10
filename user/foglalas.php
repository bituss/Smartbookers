<?php
include '../config/db.php';
session_start();
// feltételezzük: $_SESSION['user_id'] létezik


if (isset($_POST['lemond'])) {
$id = $_POST['id'];
$conn->query("DELETE FROM appointments WHERE id=$id");
}
?>
<!DOCTYPE html>
<html>
<body>
<h2>Időpontjaim</h2>
<?php
$res = $conn->query("SELECT * FROM appointments WHERE user_id=".$_SESSION['user_id']);
while($row = $res->fetch_assoc()): ?>
<div>
<?= $row['date'] ?> <?= $row['time'] ?>
<form method="post">
<input type="hidden" name="id" value="<?= $row['id'] ?>">
<button name="lemond">Lemondás</button>
</form>
</div>
<?php endwhile; ?>
</body>
</html>