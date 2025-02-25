<?php

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION["user"])) {
  http_response_code(404);
  echo json_encode(['error' => 'Authentication Required !']);
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

$userData = json_decode(file_get_contents('data-1.json'), true);
// $userData = file_get_contents('data-1.json');

function findRelevantData($question, $data) {
  $keywords = explode(' ', strtolower($question));
  $relevantData = [];
  foreach ($data as $entry) {
    foreach ($entry['keywords'] as $keyword) {
      if (in_array(strtolower($keyword), $keywords)) {
        $relevantData[] = $entry; // Return the whole entry, not just the fact
        break;
      }
    }
  }
  return $relevantData;
}

$relevantData = findRelevantData($user_message, $userData); // Use the whole entry

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

// Prepare the system prompt with JSON data
$systemPrompt = "You are an AI that embodies the information in the following JSON data. Make your answers short. Speak as if this information is about you personally. For example, if the data says 'my name is Sega AI Assistant', you should respond with 'I am Sega Ai Assistant'.\n" . /* $userData */ json_encode($relevantData) . "\nUse this data to answer the user's questions in a first-person perspective.";

// Your Gemini API key (keep this secure)
$env = parse_ini_file('.env');
$geminiApiKey = $env["GEMINI_KEY"];

// Gemini API endpoint
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$geminiApiKey";
// $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=$geminiApiKey";
// $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent?key=$geminiApiKey";

// Prepare the request payload
$payload = [
  'contents' => [
    [
      'role' => 'user',
      'parts' => [
        ['text' => $systemPrompt],
      ],
    ],
  ],
];

if ($history) {
  foreach ($history as $message) {
    $payload['contents'][] = [
      'role' => $message['sender'] == 1 ? "user" : "model",
      'parts' => [
        ['text' => $message['message']],
      ],
    ];
  }
}

$payload['contents'][] = [
  'role' => "user",
  'parts' => [
    ['text' => $user_message],
  ],
];

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle the response
if ($httpCode === 200) {
  $responseData = json_decode($response, true);
  if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode(['text' => $responseData['candidates'][0]['content']['parts'][0]['text']]);
    storeTextToFile($user_message . "\n" . $responseData['candidates'][0]['content']['parts'][0]['text'], $fileName);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from Gemini API']);
  }
} else {
  http_response_code($httpCode);
  echo json_encode(['error' => 'Failed to process the request: ' . $response]);
}
