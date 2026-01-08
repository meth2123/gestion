<?php 

if (!isset($_SESSION['teacher_id'])) {
    header("Location: ../../index.php");
    exit();
}
if(!empty($_POST['update'])){
$f=$_REQUEST['mystudent'];
echo $f."o sokhina ";
}

?>