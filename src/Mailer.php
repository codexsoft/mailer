<?php

namespace CodexSoft\Mailer;

use Swift_Attachment;
use Swift_Mailer;
use Swift_MailTransport;
use Swift_Message;
use Swift_Mime_Message;
use Swift_SmtpTransport;

class Mailer
{

    protected $smtpHost; // 'smtp.mail.ru'
    protected $smtpPort = 25; // 465
    protected $smtpSecurity; // 'ssl'
    protected $smtpUser;
    protected $smtpPassword;

    protected $imapSentMailBox; // 'Отправленные'
    protected $imapConfig; // imap.mail.ru:993/imap/ssl/novalidate-cert

    protected $messageFrom; // [ 'john@doe.com' => 'John Doe' ]
    protected $messageTo; // [ 'receiver@domain.org', 'other@domain.org' => 'A name' ]

    protected $title;
    protected $body;

    protected $attachedFiles = [];

    /** @var Swift_Mime_Message $message */
    protected $message;

    public function __construct()
    {
    }

    public function attachFiles(array $files)
    {
        if ($files) {
            foreach ($files as $file) {
                $this->attachFile($file);
            }
        }
        return $this;
    }

    public function attachFile($file)
    {
        $this->attachedFiles[] = $file;
        return $this;
    }

    public function setRecipients($to)
    {
        if (is_string($to)) {
            $to = [$to];
        }
        $this->messageTo = $to;
        return $this;
    }

    /**
     * @param string $body
     *
     * @return static
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setPassword($password)
    {
        $this->smtpPassword = $password;
        return $this;
    }

    public function authorize($user, $password)
    {
        $this->setUser($user);
        $this->setPassword($password);
        return $this;
    }

    public function setUser($user)
    {
        $this->smtpUser = $user;

        if (!$this->messageFrom && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            $this->messageFrom = $user;
        }

        return $this;
    }

    public function setFrom($from)
    {
        if (is_string($from)) {
            $from = [$from];
        }
        $this->messageFrom = $from;
        return $this;
    }

    public function configSmtp($host, $port = 25, $security = null)
    {
        $this->smtpHost = $host;
        $this->smtpPort = $port;
        $this->smtpSecurity = $security;

        return $this;
    }

    public function configImap($config, $mailbox)
    {
        $this->imapConfig = $config;
        $this->imapSentMailBox = $mailbox;
        return $this;
    }

    /**
     * @return int
     * @deprecated Swift_MailTransport is deprecated
     */
    public function sendViaMail()
    {
        $transport = Swift_MailTransport::newInstance();

        $this->message = Swift_Message::newInstance($this->title)
            ->setFrom($this->messageFrom)
            ->setTo($this->messageTo)
            ->setBody($this->body)//->setFormat()
        ;

        $mailer = Swift_Mailer::newInstance($transport);

        return $mailer->send($this->message);
    }

    /**
     * @param bool $addToSentBox
     *
     * @return int
     * @throws \Exception
     */
    public function send($addToSentBox = true)
    {
        $transport = Swift_SmtpTransport::newInstance(
            $this->smtpHost,
            $this->smtpPort,
            $this->smtpSecurity
        );

        // optional authorization
        if ($this->smtpUser) {
            $transport->setUsername($this->smtpUser);
            $transport->setPassword($this->smtpPassword);
        }

        $mailer = Swift_Mailer::newInstance($transport);

        $this->message = Swift_Message::newInstance($this->title)
            ->setFrom($this->messageFrom)
            ->setTo($this->messageTo)
            ->setBody($this->body, 'text/html');

        if ($this->attachedFiles) {
            foreach ($this->attachedFiles as $attachedFile) {
                $message = $this->message;
                $message->attach(Swift_Attachment::fromPath($attachedFile));
            }
        }

        $result = $mailer->send($this->message);
        if ($addToSentBox && $result) {
            // todo: make this optional...
            $this->addMessageToSentBox();
        }

        return $result;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function addMessageToSentBox()
    {
        if (!function_exists('imap_open')) {
            throw new \Exception('PHP IMAP library can be not installed!');
        }

        if (!$this->imapConfig || !$this->imapSentMailBox) {
            throw new \Exception('Cannot add message to inbox! No IMAP config provided!');
        }

        $mailBox = '{'.$this->imapConfig.'}'.\mb_convert_encoding(
                $this->imapSentMailBox,
                'UTF7-IMAP',
                'UTF-8'
            );

        $stream = \imap_open($mailBox, $this->smtpUser, $this->smtpPassword);

        if (!$stream) {
            throw new \Exception('Could not connect to IMAP server!');
        }

        if (!$check = imap_check($stream)) {
            throw new \Exception('Could not check mailbox status!');
        }

        if (\substr($check->Mailbox, -\strlen('<no_mailbox>')) === '<no_mailbox>') {
            throw new \Exception('Incorrect IMAP mailbox!');
        }

        $messageCountBeforeSending = $check->Nmsgs;

        if (!imap_append($stream, $mailBox, $this->message->toString()."\r\n", "\\Seen")) {
            throw new \Exception('Failed to append message to sentbox!');
        }

        if (!$check = imap_check($stream)) {
            throw new \Exception('Could not check mailbox status!');
        }

        \imap_close($stream);

        return ($check->Nmsgs > $messageCountBeforeSending);
    }

}
