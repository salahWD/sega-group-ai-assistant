<?php

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION["user"])) {
  http_response_code(404);
  echo json_encode(['error' => 'Authentication Required!']);
  exit;
}

// Validate the request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

require_once "./connection.php";

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$user_message = $data["message"];

$con = get_connection();

$stmt = $con->prepare("SELECT * FROM messages WHERE user_id = ?");
$stmt->execute([$_SESSION["user"]["id"]]);

$history = [];

if ($stmt->rowCount() > 0) {
  $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Validate the input
if (!isset($user_message) || !is_string($user_message)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid input']);
  exit;
}

$userData = file_get_contents('data-1.json');
// $userData = json_decode(file_get_contents('data-1.json'), true);

// function findRelevantData($question, $data) {
//   $keywords = explode(' ', strtolower($question));
//   $relevantData = [];
//   foreach ($data as $entry) {
//     foreach ($entry['keywords'] as $keyword) {
//       if (in_array(strtolower($keyword), $keywords)) {
//         $relevantData[] = $entry;
//         break;
//       }
//     }
//   }
//   return $relevantData;
// }

// $relevantData = findRelevantData($user_message, $userData);

function storeTextToFile($text, $filename) {
  try {
    $file = fopen($filename, 'a');
    if ($file) {
      fwrite($file, $text . PHP_EOL);
      fclose($file);
      return true;
    } else {
      return false;
    }
  } catch (Exception $e) {
    error_log("Error storing text to file: " . $e->getMessage());
    return false;
  }
}

$fileName = "log.txt";
// storeTextToFile($user_message . "\n" . json_encode($relevantData), $fileName);
// Prepare the system prompt with JSON data
$systemPrompt = "You are an AI that embodies the information in the following JSON data. Make your answers short.\n" . $userData . "\nUse this data to answer the user's questions in a first-person perspective.";

// Prepare the request payload
$messages = [
  ["role" => "developer", "content" => $systemPrompt]
];

if ($history) {
  foreach ($history as $message) {
    $messages[] = [
      "role" => $message['sender'] == 1 ? "user" : "assistant",
      "content" => $message['message']
    ];
  }
}

$messages[] = [
  "role" => "user",
  "content" => $user_message
];

$payload = [
  // "model" => "gpt-4-turbo",
  // 'model' => 'gpt-4o',
  'model' => 'gpt-3.5-turbo',
  "messages" => $messages
];

// OpenAI API Key
$env = parse_ini_file('.env');
$openaiApiKey = $env["CHATGPT_KEY"];
$url = "https://api.openai.com/v1/chat/completions";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  'Authorization: Bearer ' . $openaiApiKey,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  $responseData = json_decode($response, true);
  if (isset($responseData['choices'][0]['message']['content'])) {
    echo json_encode(['text' => $responseData['choices'][0]['message']['content']]);
    storeTextToFile("\n" . $user_message . "\n" . $responseData['choices'][0]['message']['content'] . "\n===========================", $fileName);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from OpenAI API']);
  }
} else {
  http_response_code($httpCode);
  echo json_encode(['error' => 'Failed to process the request: ' . $response]);
}
