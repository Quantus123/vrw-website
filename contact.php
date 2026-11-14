<?php
// VRW website enquiry form handler. Upload next to index.html in public_html.

// Where enquiries are delivered. Add more addresses separated by commas.
$to = 'sonia@vrwservices.com, info@vrwservices.com';
// Sender address: VRW's real Google Workspace mailbox. Hostinger is allowed to send for vrwservices.com by the SPF record.
$from = 'info@vrwservices.com';

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

// Preferred: send through VRW's Google Workspace account, so Gmail trusts the email.
// The login lives in vrw-mail-config.php one folder ABOVE public_html (never in this repo):
//   <?php return ['user' => 'info@vrwservices.com', 'pass' => 'the 16-letter app password'];
$sent = false;
$config = dirname(__DIR__) . '/vrw-mail-config.php';
if (is_readable($config)) {
    $cfg = include $config;
    require __DIR__ . '/lib/PHPMailer/Exception.php';
    require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require __DIR__ . '/lib/PHPMailer/SMTP.php';
    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = 'smtp.gmail.com';
        $m->SMTPAuth   = true;
        $m->Username   = $cfg['user'];
        $m->Password   = $cfg['pass'];
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $m->Port       = 465;
        $m->Timeout    = 15;
        $m->CharSet    = 'UTF-8';
        $m->setFrom($cfg['user'], 'VRW Website');
        foreach (array_map('trim', explode(',', $to)) as $addr) {
            $m->addAddress($addr);
        }
        if ($email !== '') {
            $m->addReplyTo($email, $name);
        }
        $m->Subject = $subject;
        $m->Body    = $body;
        $m->send();
        $sent = true;
    } catch (Exception $e) {
        error_log('VRW contact form SMTP failed: ' . $e->getMessage());
    }
}

// Fallback: the server's own mail (works, but Gmail may file it in Spam).
if (!$sent) {
    $headers  = "From: VRW Website <$from>\r\n";
    if ($email !== '') {
        $headers .= "Reply-To: " . str_replace(['<', '>', ','], '', $name) . " <$email>\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $sent = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . $from);
}

finish($sent, $sent ? '' : 'Your enquiry could not be sent. Please call +91 92140 69690 or email info@vrwservices.com.');
