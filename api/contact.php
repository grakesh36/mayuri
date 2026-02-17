<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  $data = $_POST;
}

function sanitize($value, $maxLen = 5000) {
  $value = is_string($value) ? trim($value) : '';
  $value = strip_tags($value);
  if (strlen($value) > $maxLen) {
    $value = substr($value, 0, $maxLen);
  }
  return $value;
}

$name = sanitize($data['name'] ?? '', 120);
$phone = sanitize($data['phone'] ?? '', 60);
$email = sanitize($data['email'] ?? '', 160);
$eventDate = sanitize($data['eventDate'] ?? '', 40);
$guestCount = sanitize($data['guestCount'] ?? '', 40);
$eventType = sanitize($data['eventType'] ?? '', 60);
$message = sanitize($data['message'] ?? '', 4000);
$honeypot = sanitize($data['company_website'] ?? '', 200);
$captcha = sanitize($data['captcha'] ?? '', 20);
$formStarted = sanitize($data['form_started'] ?? '', 30);

if ($honeypot !== '') {
  echo json_encode(['success' => true]);
  exit;
}

if ($name === '' || $phone === '' || $email === '' || $message === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
  exit;
}

$expectedCaptcha = '8';
if ($captcha !== $expectedCaptcha) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Please answer the spam check question correctly.']);
  exit;
}

$startedTs = is_numeric($formStarted) ? (int) $formStarted : 0;
if ($startedTs > 0) {
  $elapsed = (int) (microtime(true) * 1000) - $startedTs;
  if ($elapsed < 3000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please wait a moment before submitting the form.']);
    exit;
  }
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
  @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $ip) . '.json';
$now = time();
$window = 3600;
$limit = 5;

$entries = [];
if (file_exists($cacheFile)) {
  $json = json_decode(file_get_contents($cacheFile), true);
  if (is_array($json)) {
    $entries = array_filter($json, function ($ts) use ($now, $window) {
      return is_int($ts) && ($now - $ts) < $window;
    });
  }
}

if (count($entries) >= $limit) {
  http_response_code(429);
  echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
  exit;
}

$entries[] = $now;
file_put_contents($cacheFile, json_encode(array_values($entries)));

$subject = 'New Catering Request – Mayuri Website';

$body = "<h2>New Catering Request – Mayuri Indian Restaurant</h2>";
$body .= "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>";
$body .= "<p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>";
$body .= "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
$body .= "<p><strong>Event Type:</strong> " . htmlspecialchars($eventType) . "</p>";
$body .= "<p><strong>Event Date:</strong> " . htmlspecialchars($eventDate) . "</p>";
$body .= "<p><strong>Guests:</strong> " . htmlspecialchars($guestCount) . "</p>";
$body .= "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";

require __DIR__ . '/config.php';
require __DIR__ . '/lib/PHPMailer.php';
require __DIR__ . '/lib/SMTP.php';
require __DIR__ . '/lib/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = SMTP_HOST;
  $mail->SMTPAuth = true;
  $mail->Username = SMTP_USER;
  $mail->Password = SMTP_PASS;
  $mail->SMTPSecure = 'tls';
  $mail->Port = SMTP_PORT;

  $mail->setFrom('noreply@yourdomain.com', 'Mayuri Website');
  $mail->addAddress('mayuriusonline@gmail.com');
  $mail->addReplyTo($email, $name);
  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body = $body;

  $mail->send();

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Unable to send. Please try again later.']);
}
