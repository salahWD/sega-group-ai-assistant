<?php

session_start();

if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["id"])) {
  header("Location: login.php");
  exit();
}

include_once "./connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // storing a message
  if (isset($_POST["message"]) && !empty($_POST["message"])) {

    $con = get_connection();
    $message = json_decode($_POST["message"]);
    $message = [
      ":sender" => filter_var($message->user, FILTER_SANITIZE_NUMBER_INT),
      ":text" => filter_var($message->text, FILTER_SANITIZE_SPECIAL_CHARS),
      ":user" => $_SESSION["user"]["id"]
    ];

    $stmt = $con->prepare("INSERT INTO messages (sender, message, user_id) VALUES (:sender, :text, :user)");
    $result = $stmt->execute($message);

    echo json_encode($result);
  }
} else {
  // get request => getting the context of the chat

  $con = get_connection();

  $stmt = $con->prepare("SELECT * FROM messages WHERE user_id = ?");
  $stmt->execute([$_SESSION["user"]["id"]]);

  if ($stmt->rowCount() > 0) {
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $history = [];

    foreach ($data as $value) {
      $history[] = [
        "user" => $value["sender"],
        "text" => $value["message"],
      ];
    }
    echo json_encode($history);
  } else {
    echo json_encode(false);
  }
}
