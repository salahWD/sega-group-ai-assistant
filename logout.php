<?php
session_start();
session_destroy();
$env = parse_ini_file('.env');
header("Location: " . $env["BASE_URL"]);
exit();
