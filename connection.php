<?php

function get_connection() {
  if (!empty($con)) {
    return $con;
  } else {
    $env = parse_ini_file('.env');
    $servername = "localhost";
    $username = $env["DB_USERNAME"];
    $password = $env["DB_PASS"];
    $dbname = $env["DB_NAME"];

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
