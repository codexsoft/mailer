<?php

namespace CodexSoft\Mailer\Templates;

use CodexSoft\Mailer\Mailer;

class MailRu extends Mailer
{

    protected $smtpHost = 'smtp.mail.ru';
    protected $smtpPort = 465;
    protected $smtpSecurity = 'ssl';

    protected $imapSentMailBox = 'Отправленные';
    protected $imapConfig = 'imap.mail.ru:993/imap/ssl/novalidate-cert';

}
