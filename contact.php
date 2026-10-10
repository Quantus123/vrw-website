<?php
// VRW website enquiry form handler. Upload next to index.html in public_html.

// Where enquiries are delivered. Add more addresses separated by commas.
$to = 'sonia@vrwservices.com, info@vrwservices.com';
// Sender address. Must be on vrwservices.com so the email passes spam checks.
$from = 'website@vrwservices.com';

date_default_timezone_set('Asia/Kolkata');

$wantsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

function finish($ok, $error = '') {
    global $wantsJson;
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'error' => $error]);
    } else {
        header('Location: /' . ($ok ? '#sent' : '#contact'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /#contact');
    exit;
}

// Spam trap: real visitors never see or fill this field.
if (!empty($_POST['website'])) {
    finish(true);
}

function field($key, $max = 200) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
}

$name     = str_replace("\n", ' ', field('name', 100));
$phone    = str_replace("\n", ' ', field('phone', 30));
$email    = str_replace("\n", '', field('email', 150));
$business = str_replace("\n", ' ', field('business', 150));
$need     = str_replace("\n", ' ', field('need', 100));
$message  = field('message', 3000);

if ($name === '' || $phone === '') {
    finish(false, 'Please add your name and phone number so we can reach you.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    finish(false, 'Please check your email address.');
}

$subject = 'Website enquiry: ' . $need . ' from ' . $name;
$body  = "New enquiry from vrwservices.com\n\n";
$body .= "Name:        $name\n";
$body .= "Phone:       $phone\n";
$body .= "Email:       " . ($email ?: '-') . "\n";
$body .= "Business:    " . ($business ?: '-') . "\n";
$body .= "Interested:  $need\n\n";
$body .= "Message:\n" . ($message ?: '-') . "\n\n";
$body .= "Sent " . date('d M Y, H:i') . " IST\n";

$headers  = "From: VRW Website <$from>\r\n";
if ($email !== '') {
    $headers .= "Reply-To: " . str_replace(['<', '>', ','], '', $name) . " <$email>\r\n";
}
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);

finish($sent, $sent ? '' : 'Your enquiry could not be sent. Please call +91 92140 69690 or email info@vrwservices.com.');
