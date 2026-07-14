<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Send an e-mail.
 *
 * In dev (or when PHPMailer/SMTP is unavailable) the message is appended to a
 * local mail log file so flows can be verified without a real mail server.
 * In production it is delivered over SMTP via PHPMailer.
 *
 * @return bool true if the message was handed off (or logged) without error.
 */
function vg_send_mail(string $toEmail, string $toName, string $subject, string $textBody): bool
{
    $cfg = vg_config();
    $mail = $cfg['mail'];

    $autoload = __DIR__ . '/../../vendor/autoload.php';
    $hasPhpMailer = is_file($autoload);
    if ($hasPhpMailer) {
        require_once $autoload;
    }

    $smtpConfigured = $hasPhpMailer
        && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)
        && !empty($mail['smtp']['host'])
        && !empty($mail['smtp']['username']);

    if (!$smtpConfigured || vg_is_dev()) {
        return vg_log_mail($toEmail, $subject, $textBody);
    }

    try {
        $m = new \PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $mail['smtp']['host'];
        $m->Port       = (int) $mail['smtp']['port'];
        $m->SMTPAuth   = true;
        $m->Username   = $mail['smtp']['username'];
        $m->Password   = $mail['smtp']['password'];
        $m->SMTPSecure = $mail['smtp']['encryption'] === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet    = 'UTF-8';
        $m->setFrom($mail['from_email'], $mail['from_name']);
        $m->addAddress($toEmail, $toName);
        $m->Subject = $subject;
        $m->Body    = $textBody;
        $m->send();
        return true;
    } catch (\Throwable $e) {
        error_log('[ViscosiGuide] mail send failed: ' . $e->getMessage());
        return false;
    }
}

/** Append a message to the dev mail log (created next to config). */
function vg_log_mail(string $to, string $subject, string $body): bool
{
    $dir = __DIR__ . '/../var';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    $line = sprintf(
        "==== %s ====\nTo: %s\nSubject: %s\n\n%s\n\n",
        date('c'),
        $to,
        $subject,
        $body
    );
    return (bool) @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
}
