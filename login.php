<?php

session_start();

if (isset($_SESSION["user"])) {
  header("Location: index.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  require_once "./connection.php";

  $con = get_connection();

  $username = $_POST['username'] ?? '';

  try {

    $stmt = $con->prepare("SELECT id, username FROM users WHERE username = ?");
    $stmt->execute([$username]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) { // التحقق من أن المتغيّر ليس فارغاً

      $_SESSION['user'] = $user;

      header("Location: index.php");
      exit();
    } else {
      $stmt = $con->prepare("INSERT INTO users (username) VALUES (?)");
      $stmt->execute([$username]);

      $stmt = $con->prepare("SELECT id, username FROM users WHERE username = ?");
      $stmt->execute([$username]);

      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      $_SESSION["user"] = $user;

      header("Location: index.php");
      exit();
    }
  } catch (PDOException $e) {
    // عرض رسالة الخطأ الخاص بقاعدة البيانات في حال وجوده
    $error = "Database error: " . $e->getMessage();
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
      <h1 class="text-2xl font-bold mb-6 text-center">Login</h1>

      <?php if (isset($error) && $error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <div>
          <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
            Username
          </label>
          <input class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"
            type="text" name="username" id="username" required>
        </div>
        <button class="w-full bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-600 focus:outline-none focus:shadow-outline"
          type="submit">
          Log In
        </button>
      </form>
    </div>
  </div>
</body>

</html>