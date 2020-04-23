<?php
    set_time_limit(0);
    $mysql_hostname = "localhost"; //host name
    $mysql_user = "root";        //database User
    $mysql_password = "root"; //database password
    $mysql_database = "dropbox"; //database Name
    //connect to database
    $con = mysqli_connect($mysql_hostname, $mysql_user, $mysql_password) or die("Opps some thing went wrong");
    //select database
    mysqli_select_db($con, $mysql_database) or die("Opps some thing went wrong");
      
?>
