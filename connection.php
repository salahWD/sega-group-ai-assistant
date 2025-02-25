<?php

function get_connection() {
  if (!empty($con)) {
    return $con;
  } else {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "sega-ai";

    try {
      $con = new PDO("mysql:host=" . $servername . ";dbname=" . $dbname . ";charset=utf8", $username, $password);
      $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $ex) {
      echo "Database connection Error ... !!<br>";
      print_r($ex);
      exit();
    }
    return $con;
  }
}
