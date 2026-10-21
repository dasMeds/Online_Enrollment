<?php
if(isset($_GET['accid']))
{
	$num=$_GET['accid'];
	require('includes/conn.php');
	$getval=mysqli_query($conn, "SELECT * FROM studenttbl WHERE ID = $num");
	$rowval=mysqli_fetch_array($getval);
	$accountID	= $rowval['accountID'];
	$query2=mysqli_query($conn, "DELETE FROM usertbl WHERE ID = $accountID");
	$query=mysqli_query($conn, "DELETE FROM studenttbl WHERE ID = $num");
	
	echo"<script>window.alert('Successfully Removed the selected account')</script>";
	echo"<script>window.location='userreg.php'</script>";
}
else
{
	echo"<script>window.location='userreg.php'</script>";
}

?>