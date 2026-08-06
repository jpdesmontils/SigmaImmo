<?php
/**
 * Envoi d'emails transactionnels. Utilise mail() si le serveur est configuré
 * pour l'envoi (sendmail/postfix). Sans configuration, l'email est journalisé
 * dans storage/log/mail.log (jamais exposé à l'utilisateur) pour rester
 * exploitable en développement tant qu'aucun fournisseur SMTP n'est branché.
 */
class MailerService
{
    private $fromAddress;
    private $fromName;

    public function __construct($fromAddress = 'no-reply@sigmaimmo.local', $fromName = 'SigmaImmo')
    {
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    public function send($to, $subject, $body)
    {
        if (function_exists('mail') && $this->transportAvailable()) {
            $headers = 'From: ' . $this->fromName . ' <' . $this->fromAddress . '>' . "\r\n"
                . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
            if (@mail($to, $subject, $body, $headers)) {
                return true;
            }
        }
        $this->logToFile($to, $subject, $body);
        return false;
    }

    /** Évite d'invoquer mail() (et son sh bruyant en stderr) quand aucun binaire n'est installé. */
    private function transportAvailable()
    {
        $sendmailPath = ini_get('sendmail_path');
        if (!is_string($sendmailPath) || trim($sendmailPath) === '') {
            return false;
        }
        $binary = strtok(trim($sendmailPath), ' ');
        return $binary !== false && is_executable($binary);
    }

    private function logToFile($to, $subject, $body)
    {
        $dir = dirname(dirname(__DIR__)) . '/storage/log/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        $record = array(
            'timestamp' => gmdate('c'),
            'to' => $to,
            'subject' => $subject,
            'body' => $body
        );
        file_put_contents($dir . 'mail.log', json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}
