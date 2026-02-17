<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail.php';

class MailService
{
    private static function getMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM, SMTP_NAME);
        return $mail;
    }

    /**
     * Envoi obligatoire via template.
     */
    public static function send(string $to, string $template, array $vars = []): bool
    {
        [$subject, $html, $text] = self::renderTemplate($template, $vars);

        $mail = self::getMailer();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = $text;

        try {
            return $mail->send();
        } catch (Exception $e) {
            // log éventuel: error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Construit subject/html/text à partir de /config/mail_templates.php
     */
    private static function renderTemplate(string $name, array $vars): array
    {
        $all = require __DIR__ . '/../config/mailTemplates.php';
        if (!isset($all[$name])) {
            throw new RuntimeException("Mail template '$name' introuvable");
        }
        $tpl = $all[$name];

        // Remplacements {{key}} -> valeur
        $replHtml = self::buildReplacements($vars, true);
        $replText = self::buildReplacements($vars, false);

        $subject = strtr($tpl['subject'] ?? '', $replText);
        $html    = strtr($tpl['html']    ?? '', $replHtml);
        $text    = strtr($tpl['text']    ?? strip_tags($tpl['html'] ?? ''), $replText);

        return [$subject, $html, $text];
    }

    private static function buildReplacements(array $vars, bool $forHtml): array
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $val = (string)$v;
            if ($forHtml) {
                $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            }
            $out['{{' . $k . '}}'] = $val;
        }
        return $out;
    }
}
