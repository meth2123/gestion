<?php  
include_once('main.php');
 $em = $_REQUEST['id'];
 $check = $_SESSION['student_id'];


$courseinfo = "SELECT * FROM grade WHERE courseid='$em' and studentid='$check'";
$resc = mysql_query($courseinfo);

echo "<tr> <th>Grade</th> </tr>";
while($r=mysql_fetch_array($resc))
{
 echo "<tr> <td>",$r['grade'],"<td></tr>";

}


?>
