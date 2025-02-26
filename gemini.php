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

// Load OAuth credentials from a secure location
$credentials = json_decode(file_get_contents('client_secret.json'), true);
$client_id = $credentials['web']['client_id'];
$client_secret = $credentials['web']['client_secret'];
$redirect_uri = 'https://sega-ai.salahbakhash.com/callback.php';

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$user_message = $data["message"];

// Database connection for history
$con = get_connection();
$stmt = $con->prepare("SELECT * FROM messages WHERE user_id = ?");
$stmt->execute([$_SESSION["user"]["id"]]);
$history = $stmt->rowCount() > 0 ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Validate the input
if (!isset($user_message) || !is_string($user_message)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid input']);
  exit;
}

function storeTextToFile($text, $filename) {
  try {
    $file = fopen($filename, 'a');
    if ($file) {
      fwrite($file, $text . PHP_EOL);
      fclose($file);
      return true;
    }
    return false;
  } catch (Exception $e) {
    error_log("Error storing text to file: " . $e->getMessage());
    return false;
  }
}

$fileName = "log.txt";

// Function to get or refresh OAuth access token
function getAccessToken($client_id, $client_secret, $redirect_uri) {
  $token_file = 'token.json';
  if (file_exists($token_file)) {
    $token_data = json_decode(file_get_contents($token_file), true);
    if ($token_data['expires_in'] > time()) {
      return $token_data['access_token'];
    }
  }

  // If no valid token, redirect to OAuth flow (simplified here)
  $auth_url = "https://accounts.google.com/o/oauth2/v2/auth?scope=https://www.googleapis.com/auth/generative-language&access_type=offline&response_type=code&client_id=$client_id&redirect_uri=$redirect_uri";
  if (!isset($_GET['code'])) {
    header("Location: $auth_url");
    exit;
  }

  // Exchange code for token
  $code = $_GET['code'];
  $token_url = "https://oauth2.googleapis.com/token";
  $post_data = [
    'code' => $code,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'grant_type' => 'authorization_code'
  ];

  $ch = curl_init($token_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
  $response = curl_exec($ch);
  curl_close($ch);

  $token_data = json_decode($response, true);
  $token_data['expires_in'] = time() + $token_data['expires_in'];
  file_put_contents($token_file, json_encode($token_data));
  return $token_data['access_token'];
}

// Get the access token
$access_token = getAccessToken($client_id, $client_secret, $redirect_uri);

// Gemini API endpoint for tuned model (no API key, use OAuth token)
// $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=$geminiApiKey";
$url = "https://generativelanguage.googleapis.com/v1beta/models/tunedModels/segaaiassistant-39lez6wm4ll4:generateContent";

// Prepare the request payload
$payload = [
  "contents" => [
    [
      "role" => "user",
      "parts" => [["text" => $user_message]]
    ]
  ],
  "generationConfig" => [
    "temperature" => 1,
    "topK" => 40,
    "topP" => 0.95,
    "maxOutputTokens" => 1000,
    "responseMimeType" => "text/plain"
  ]
];

// Initialize cURL with OAuth token
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Content-Type: application/json',
  "Authorization: Bearer $access_token"
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
    $response_text = $responseData['candidates'][0]['content']['parts'][0]['text'];
    echo json_encode(['text' => $response_text]);
    storeTextToFile("Gemini \n$user_message\n$response_text\n===========================", $fileName);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from Gemini API']);
  }
} else {
  http_response_code($httpCode);
  echo json_encode(['error' => 'Failed to process the request: ' . $response]);
}
