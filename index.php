<?php


$URL = [];
if (isset($_GET["url"]) && !empty($_GET["url"])) {
  $URL = explode("/", $_GET["url"]);
}

if (isset($URL[0]) && $URL[0] == "store") {
  require_once "./store.php";
} elseif (isset($URL[0]) && $URL[0] == "gemini") {
  require_once "./gemini.php";
} elseif (isset($URL[0]) && $URL[0] == "chatGPT") {
  require_once "./chatGPT.php";
} else {

  session_start();
  if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit();
  }

  $messages = [];

  require_once "./connection.php";

  $con = get_connection();
  try {
    $stmt = $con->prepare("SELECT * FROM messages WHERE user_id = ?");
    $stmt->execute([$_SESSION["user"]["id"]]);
    if ($stmt->rowCount() > 0) {
      $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch (PDOException $e) {
    echo 'faild to connect' . $e->getMessage();
  }

?>

  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sega Group | AI Assistant</title>
    <link rel="stylesheet" href="./style.css" />
  </head>

  <body>
    <div class="container">
      <h1 class="mt-5">Sega AI Assistant</h1>
      <hr />
      <div id="mycustomchat"></div>
    </div>

    <script>
      let chatContext = JSON.parse(`<?= json_encode($messages) ?>`);
    </script>
    <script type="importmap">
      {
      "imports": {
        "@google/generative-ai": "https://esm.run/@google/generative-ai",
        "@sabox": "./sa-box.js",
        "jquery": "http://code.jquery.com/jquery-1.11.3.min.js"
      }
    }
  </script>
    <script type="module" src="./index.js"></script>
  </body>

  </html>
<?php
}
?>