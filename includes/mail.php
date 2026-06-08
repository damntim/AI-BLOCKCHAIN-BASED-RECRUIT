<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function send_mail(string $to, string $subject, string $body, string $htmlBody = ''): bool {
    $cfg    = config();
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) mkdir($logDir, 0750, true);

    // Always log
    $logEntry = sprintf(
        "[%s] TO: %s | SUBJECT: %s | BODY: %s\n",
        date('Y-m-d H:i:s'), $to, $subject,
        substr(strip_tags($body), 0, 200)
    );
    file_put_contents($logDir . '/mail.log', $logEntry, FILE_APPEND);

    if (($cfg['MAIL_ENABLED'] ?? 'false') !== 'true') {
        error_log("Mail logged (disabled): To={$to} Subject={$subject}");
        return true;
    }

    // Require composer autoload (PHPMailer)
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('PHPMailer not installed — run composer require phpmailer/phpmailer');
        return false;
    }
    require_once $autoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['MAIL_USER'] ?? '';
        $mail->Password   = $cfg['MAIL_PASS'] ?? '';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($cfg['MAIL_PORT'] ?? 587);

        $fromAddr = $cfg['MAIL_FROM'] ?? $cfg['MAIL_USER'] ?? 'noreply@recruitchain.app';
        $fromName = $cfg['MAIL_FROM_NAME'] ?? 'RecruitChain';
        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($to);

        $mail->Subject = $subject;

        if ($htmlBody !== '') {
            $mail->isHTML(true);
            $mail->Body    = $htmlBody;
            $mail->AltBody = $body;
        } else {
            $mail->isHTML(false);
            $mail->Body = $body;
        }

        $mail->send();
        file_put_contents($logDir . '/mail.log', "[" . date('Y-m-d H:i:s') . "] SENT OK: {$to}\n", FILE_APPEND);
        return true;
    } catch (\Throwable $e) {
        $err = "[" . date('Y-m-d H:i:s') . "] MAIL ERROR to {$to}: " . $e->getMessage() . "\n";
        file_put_contents($logDir . '/mail.log', $err, FILE_APPEND);
        error_log($err);
        return false;
    }
}

function send_mail_html(string $to, string $subject, string $htmlBody): bool {
    $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
    return send_mail($to, $subject, $plain, $htmlBody);
}
