<?php include '../includes/header.php'; ?>
<?php
if (!isset($_SESSION['user_id'])) header("Location: login.php");
echo "User dashboard – Szia " . $_SESSION['name'];
?>
<?php include '../includes/footer.php'; ?>