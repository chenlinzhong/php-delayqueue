<?php
class DqMailer{
    public static function sendMail($email, $subject, $msg, $file='') {
        $mailConfig = DqMysql::select('dq_alert');
        if(!empty($mailConfig)){
            $mailConfig = $mailConfig[0];
        }else{
            DqLog::writeLog('empty alert mail conf,plear check',DqLog::LOG_TYPE_EXCEPTION);
            return false;
        }
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->SMTPAuth = true;
        $mail->Port     = $mailConfig['port'];
        $mail->Host     = $mailConfig['host'];
        $mail->Username = $mailConfig['user'];    
        $mail->Password = $mailConfig['pwd'];
        $mail->CharSet  = "utf-8";
        $mail->From     = $mailConfig['user'];
        $mail->IsHTML(true);
        $mail->ClearAddresses();
        $mail->FromName = $mailConfig['user'];
        if (is_array($email)) {
            foreach ($email as $v) {
                $mail->AddAddress($v);
            }
        } else {
            $mail->AddAddress($email);
        }
        if (is_array($file) && !empty($file)) {
            foreach ($file as $key=>$value) {
                $mail->AddAttachment($value);
            }
        } else {
            if (!empty($file)) {
                $mail->AddAttachment($file);
            }
        }
        $mail->Subject = $subject;
        $mail->Body    = $msg;
        $res = $mail->Send();
        if($message = $mail->ErrorInfo) {
            error_log(time()."\n",3,'/tmp/mail');
        }
        return $res;
    }
}



class PHPMailer {
    /**
     * Email priority (1 = High, 3 = Normal, 5 = low).
     * @var int
     */
    public $Priority          = 3;

    /**
     * Sets the CharSet of the message.
     * @var string
     */
    public $CharSet           = 'iso-8859-1';

    /**
     * Sets the Content-type of the message.
     * @var string
     */
    public $ContentType       = 'text/plain';

    /**
     * Sets the Encoding of the message. Options for this are "8bit",
     * "7bit", "binary", "base64", and "quoted-printable".
     * @var string
     */
    public $Encoding          = '8bit';

    /**
     * Holds the most recent mailer error message.
     * @var string
     */
    public $ErrorInfo         = '';

    /**
     * Sets the From email address for the message.
     * @var string
     */
    public $From              = 'root@localhost';

    /**
     * Sets the From name of the message.
     * @var string
     */
    public $FromName          = 'Root User';

    /**
     * Sets the Sender email (Return-Path) of the message.  If not empty,
     * will be sent via -f to sendmail or as 'MAIL FROM' in smtp mode.
     * @var string
     */
    public $Sender            = '';

    /**
     * Sets the Subject of the message.
     * @var string
     */
    public $Subject           = '';

    /**
     * Sets the Body of the message.  This can be either an HTML or text body.
     * If HTML then run IsHTML(true).
     * @var string
     */
    public $Body              = '';

    /**
     * Sets the text-only body of the message.  This automatically sets the
     * email to multipart/alternative.  This body can be read by mail
     * clients that do not have HTML email capability such as mutt. Clients
     * that can read HTML will view the normal Body.
     * @var string
     */
    public $AltBody           = '';

    /**
     * Sets word wrapping on the body of the message to a given number of
     * characters.
     * @var int
     */
    public $WordWrap          = 0;

    /**
     * Method to send mail: ("mail", "sendmail", or "smtp").
     * @var string
     */
    public $Mailer            = 'mail';

    /**
     * Sets the path of the sendmail program.
     * @var string
     */
    public $Sendmail          = '/usr/sbin/sendmail';

    /**
     * Path to PHPMailer plugins.  This is now only useful if the SMTP class
     * is in a different directory than the PHP include path.
     * @var string
     */
    public $PluginDir         = '';

    /**
     * Holds PHPMailer version.
     * @var string
     */
    public $Version           = "2.0.0 rc1";

    /**
     * Sets the email address that a reading confirmation will be sent.
     * @var string
     */
    public $ConfirmReadingTo  = '';

    /**
     * Sets the hostname to use in Message-Id and Received headers
     * and as default HELO string. If empty, the value returned
     * by SERVER_NAME is used or 'localhost.localdomain'.
     * @var string
     */
    public $Hostname          = '';

    /////////////////////////////////////////////////
    // PROPERTIES FOR SMTP
    /////////////////////////////////////////////////

    /**
     * Sets the SMTP hosts.  All hosts must be separated by a
     * semicolon.  You can also specify a different port
     * for each host by using this format: [hostname:port]
     * (e.g. "smtp1.example.com:25;smtp2.example.com").
     * Hosts will be tried in order.
     * @var string
     */
    public $Host        = 'localhost';

    /**
     * Sets the default SMTP server port.
     * @var int
     */
    public $Port        = 25;

    /**
     * Sets the SMTP HELO of the message (Default is $Hostname).
     * @var string
     */
    public $Helo        = '';

    /**
     * Sets connection prefix.
     * Options are "", "ssl" or "tls"
     * @var string
     */
    public $SMTPSecure = "";

    /**
     * Sets SMTP authentication. Utilizes the Username and Password variables.
     * @var bool
     */
    public $SMTPAuth     = false;

    /**
     * Sets SMTP username.
     * @var string
     */
    public $Username     = '';

    /**
     * Sets SMTP password.
     * @var string
     */
    public $Password     = '';

    /**
     * Sets the SMTP server timeout in seconds. This function will not
     * work with the win32 version.
     * @var int
     */
    public $Timeout      = 10;

    /**
     * Sets SMTP class debugging on or off.
     * @var bool
     */
    public $SMTPDebug    = false;

    /**
     * Prevents the SMTP connection from being closed after each mail
     * sending.  If this is set to true then to close the connection
     * requires an explicit call to SmtpClose().
     * @var bool
     */
    public $SMTPKeepAlive = true;

    /**
     * Provides the ability to have the TO field process individual
     * emails, instead of sending to entire TO addresses
     * @var bool
     */
    public $SingleTo = false;

    /////////////////////////////////////////////////
    // PROPERTIES, PRIVATE
    /////////////////////////////////////////////////

    public $smtp            = NULL;
    public $to              = array();
    public $cc              = array();
    public $bcc             = array();
    public $ReplyTo         = array();
    public $attachment      = array();
    public $CustomHeader    = array();
    public $message_type    = '';
    public $boundary        = array();
    public $language        = array();
    public $error_count     = 0;
    public $LE              = "\n";

    /////////////////////////////////////////////////
    // METHODS, VARIABLES
    /////////////////////////////////////////////////

    /**
     * Sets message type to HTML.
     * @param bool $bool
     * @return void
     */
    function IsHTML($bool) {
        if($bool == true) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    function ClearAddress(){
        $this->to =array();
    }
    /**
     * Sets Mailer to send message using SMTP.
     * @return void
     */
    function IsSMTP() {
        $this->Mailer = 'smtp';
    }

    /**
     * Sets Mailer to send message using PHP mail() function.
     * @return void
     */
    function IsMail() {
        $this->Mailer = 'mail';
    }

    /**
     * Sets Mailer to send message using the $Sendmail program.
     * @return void
     */
    function IsSendmail() {
        $this->Mailer = 'sendmail';
    }

    /**
     * Sets Mailer to send message using the qmail MTA.
     * @return void
     */
    function IsQmail() {
        $this->Sendmail = '/var/qmail/bin/sendmail';
        $this->Mailer = 'sendmail';
    }

    /////////////////////////////////////////////////
    // METHODS, RECIPIENTS
    /////////////////////////////////////////////////

    /**
     * Adds a "To" address.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddAddress($address, $name = '') {
        $cur = count($this->to);
        $this->to[$cur][0] = trim($address);
        $this->to[$cur][1] = $name;
    }

    /**
     * Adds a "Cc" address. Note: this function works
     * with the SMTP mailer on win32, not with the "mail"
     * mailer.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddCC($address, $name = '') {
        $cur = count($this->cc);
        $this->cc[$cur][0] = trim($address);
        $this->cc[$cur][1] = $name;
    }

    /**
     * Adds a "Bcc" address. Note: this function works
     * with the SMTP mailer on win32, not with the "mail"
     * mailer.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddBCC($address, $name = '') {
        $cur = count($this->bcc);
        $this->bcc[$cur][0] = trim($address);
        $this->bcc[$cur][1] = $name;
    }

    /**
     * Adds a "Reply-to" address.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddReplyTo($address, $name = '') {
        $cur = count($this->ReplyTo);
        $this->ReplyTo[$cur][0] = trim($address);
        $this->ReplyTo[$cur][1] = $name;
    }

    /////////////////////////////////////////////////
    // METHODS, MAIL SENDING
    /////////////////////////////////////////////////

    /**
     * Creates message and assigns Mailer. If the message is
     * not sent successfully then it returns false.  Use the ErrorInfo
     * variable to view description of the error.
     * @return bool
     */
    function Send() {
        $header = '';
        $body = '';
        $result = true;

        if((count($this->to) + count($this->cc) + count($this->bcc)) < 1) {
            $this->SetError($this->Lang('provide_address'));
            return false;
        }

        /* Set whether the message is multipart/alternative */
        if(!empty($this->AltBody)) {
            $this->ContentType = 'multipart/alternative';
        }

        $this->error_count = 0; // reset errors
        $this->SetMessageType();
        $header .= $this->CreateHeader();
        $body = $this->CreateBody();

        if($body == '') {
            return false;
        }

        /* Choose the mailer */
        switch($this->Mailer) {
            case 'sendmail':
                $result = $this->SendmailSend($header, $body);
                break;
            case 'smtp':
                $result = $this->SmtpSend($header, $body);
                break;
            case 'mail':
                $result = $this->MailSend($header, $body);
                break;
            default:
                $result = $this->MailSend($header, $body);
                break;
            //$this->SetError($this->Mailer . $this->Lang('mailer_not_supported'));
            //$result = false;
            //break;
        }

        return $result;
    }

    /**
     * Sends mail using the $Sendmail program.
     * @access private
     * @return bool
     */
    function SendmailSend($header, $body) {
        if ($this->Sender != '') {
            $sendmail = sprintf("%s -oi -f %s -t", escapeshellcmd($this->Sendmail), escapeshellarg($this->Sender));
        } else {
            $sendmail = sprintf("%s -oi -t", escapeshellcmd($this->Sendmail));
        }

        if(!@$mail = popen($sendmail, 'w')) {
            $this->SetError($this->Lang('execute') . $this->Sendmail);
            return false;
        }

        fputs($mail, $header);
        fputs($mail, $body);

        $result = pclose($mail) >> 8 & 0xFF;
        if($result != 0) {
            $this->SetError($this->Lang('execute') . $this->Sendmail);
            return false;
        }

        return true;
    }

    /**
     * Sends mail using the PHP mail() function.
     * @access private
     * @return bool
     */
    function MailSend($header, $body) {

        $to = '';
        for($i = 0; $i < count($this->to); $i++) {
            if($i != 0) { $to .= ', '; }
            $to .= $this->AddrFormat($this->to[$i]);
        }

        $toArr = explode(',', $to);

        if ($this->Sender != '' && strlen(ini_get('safe_mode'))< 1) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->Sender);
            $params = sprintf("-oi -f %s", $this->Sender);
            if ($this->SingleTo === true && count($toArr) > 1) {
                foreach ($toArr as $key => $val) {
                    $rt = @mail($val, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
                }
            } else {
                $rt = @mail($to, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
            }
        } else {
            if ($this->SingleTo === true && count($toArr) > 1) {
                foreach ($toArr as $key => $val) {
                    $rt = @mail($val, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header, $params);
                }
            } else {
                $rt = @mail($to, $this->EncodeHeader($this->SecureHeader($this->Subject)), $body, $header);
            }
        }

        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }

        if(!$rt) {
            $this->SetError($this->Lang('instantiate'));
            return false;
        }

        return true;
    }

    /**
     * Sends mail via SMTP using PhpSMTP (Author:
     * Chris Ryan).  Returns bool.  Returns false if there is a
     * bad MAIL FROM, RCPT, or DATA input.
     * @access private
     * @return bool
     */
    function SmtpSend($header, $body) {
        $error = '';
        $bad_rcpt = array();

        if(!$this->SmtpConnect()) {
            return false;
        }

        $smtp_from = ($this->Sender == '') ? $this->From : $this->Sender;
        if(!$this->smtp->Mail($smtp_from)) {
            $error = $this->Lang('from_failed') . $smtp_from;
            $this->SetError($error);
            $this->smtp->Reset();
            return false;
        }

        /* Attempt to send attach all recipients */
        for($i = 0; $i < count($this->to); $i++) {
            if(!$this->smtp->Recipient($this->to[$i][0])) {
                $bad_rcpt[] = $this->to[$i][0];
            }
        }
        for($i = 0; $i < count($this->cc); $i++) {
            if(!$this->smtp->Recipient($this->cc[$i][0])) {
                $bad_rcpt[] = $this->cc[$i][0];
            }
        }
        for($i = 0; $i < count($this->bcc); $i++) {
            if(!$this->smtp->Recipient($this->bcc[$i][0])) {
                $bad_rcpt[] = $this->bcc[$i][0];
            }
        }

        if(count($bad_rcpt) > 0) { // Create error message
            for($i = 0; $i < count($bad_rcpt); $i++) {
                if($i != 0) {
                    $error .= ', ';
                }
                $error .= $bad_rcpt[$i];
            }
            $error = $this->Lang('recipients_failed') . $error;
            $this->SetError($error);
            $this->smtp->Reset();
            return false;
        }

        if(!$this->smtp->Data($header . $body)) {
            $this->SetError($this->Lang('data_not_accepted'));
            $this->smtp->Reset();
            return false;
        }
        if($this->SMTPKeepAlive == true) {
            $this->smtp->Reset();
        } else {
            $this->SmtpClose();
        }

        return true;
    }

    /**
     * Initiates a connection to an SMTP server.  Returns false if the
     * operation failed.
     * @access private
     * @return bool
     */
    function SmtpConnect() {
        if($this->smtp == NULL) {
            $this->smtp = new SMTP();
        }
        $this->smtp->do_debug = $this->SMTPDebug;
        $hosts = explode(';', $this->Host);
        $index = 0;
        $connection = ($this->smtp->Connected());

        /* Retry while there is no connection */
        while($index < count($hosts) && $connection == false) {
            $hostinfo = array();
            if(preg_match('/^(.+):([0-9]+)$/', $hosts[$index], $hostinfo)) {
                $host = $hostinfo[1];
                $port = $hostinfo[2];
            } else {
                $host = $hosts[$index];
                $port = $this->Port;
            }

            if($this->smtp->Connect(((!empty($this->SMTPSecure))?$this->SMTPSecure.'://':'').$host, $port, $this->Timeout)) {
                if ($this->Helo != '') {
                    $this->smtp->Hello($this->Helo);
                } else {
                    $this->smtp->Hello($this->ServerHostname());
                }

                $connection = true;
                if($this->SMTPAuth) {
                    if(!$this->smtp->Authenticate($this->Username, $this->Password)) {
                        $this->SetError($this->Lang('authenticate'));
                        $this->smtp->Reset();
                        $connection = false;
                    }
                }
            }
            $index++;
        }
        if(!$connection) {
            $this->SetError($this->Lang('connect_host'));
        }

        return $connection;
    }

    /**
     * Closes the active SMTP session if one exists.
     * @return void
     */
    function SmtpClose() {
        if($this->smtp != NULL) {
            if($this->smtp->Connected()) {
                $this->smtp->Quit();
                $this->smtp->Close();
            }
        }
    }

    /**
     * Sets the language for all class error messages.  Returns false
     * if it cannot load the language file.  The default language type
     * is English.
     * @param string $lang_type Type of language (e.g. Portuguese: "br")
     * @param string $lang_path Path to the language file directory
     * @access public
     * @return bool
     */
    function SetLanguage($lang_type, $lang_path = 'language/') {
        if(file_exists($lang_path.'phpmailer.lang-'.$lang_type.'.php')) {
            include($lang_path.'phpmailer.lang-'.$lang_type.'.php');
        } elseif (file_exists($lang_path.'phpmailer.lang-en.php')) {
            include($lang_path.'phpmailer.lang-en.php');
        } else {
            $this->SetError('Could not load language file');
            return false;
        }
        $this->language = $PHPMAILER_LANG;

        return true;
    }

    /////////////////////////////////////////////////
    // METHODS, MESSAGE CREATION
    /////////////////////////////////////////////////

    /**
     * Creates recipient headers.
     * @access private
     * @return string
     */
    function AddrAppend($type, $addr) {
        $addr_str = $type . ': ';
        $addr_str .= $this->AddrFormat($addr[0]);
        if(count($addr) > 1) {
            for($i = 1; $i < count($addr); $i++) {
                $addr_str .= ', ' . $this->AddrFormat($addr[$i]);
            }
        }
        $addr_str .= $this->LE;

        return $addr_str;
    }

    /**
     * Formats an address correctly.
     * @access private
     * @return string
     */
    function AddrFormat($addr) {
        if(empty($addr[1])) {
            $formatted = $this->SecureHeader($addr[0]);
        } else {
            $formatted = $this->EncodeHeader($this->SecureHeader($addr[1]), 'phrase') . " <" . $this->SecureHeader($addr[0]) . ">";
        }

        return $formatted;
    }

    /**
     * Wraps message for use with mailers that do not
     * automatically perform wrapping and for quoted-printable.
     * Original written by philippe.
     * @access private
     * @return string
     */
    function WrapText($message, $length, $qp_mode = false) {
        $soft_break = ($qp_mode) ? sprintf(" =%s", $this->LE) : $this->LE;

        $message = $this->FixEOL($message);
        if (substr($message, -1) == $this->LE) {
            $message = substr($message, 0, -1);
        }

        $line = explode($this->LE, $message);
        $message = '';
        for ($i=0 ;$i < count($line); $i++) {
            $line_part = explode(' ', $line[$i]);
            $buf = '';
            for ($e = 0; $e<count($line_part); $e++) {
                $word = $line_part[$e];
                if ($qp_mode and (strlen($word) > $length)) {
                    $space_left = $length - strlen($buf) - 1;
                    if ($e != 0) {
                        if ($space_left > 20) {
                            $len = $space_left;
                            if (substr($word, $len - 1, 1) == '=') {
                                $len--;
                            } elseif (substr($word, $len - 2, 1) == '=') {
                                $len -= 2;
                            }
                            $part = substr($word, 0, $len);
                            $word = substr($word, $len);
                            $buf .= ' ' . $part;
                            $message .= $buf . sprintf("=%s", $this->LE);
                        } else {
                            $message .= $buf . $soft_break;
                        }
                        $buf = '';
                    }
                    while (strlen($word) > 0) {
                        $len = $length;
                        if (substr($word, $len - 1, 1) == '=') {
                            $len--;
                        } elseif (substr($word, $len - 2, 1) == '=') {
                            $len -= 2;
                        }
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);

                        if (strlen($word) > 0) {
                            $message .= $part . sprintf("=%s", $this->LE);
                        } else {
                            $buf = $part;
                        }
                    }
                } else {
                    $buf_o = $buf;
                    $buf .= ($e == 0) ? $word : (' ' . $word);

                    if (strlen($buf) > $length and $buf_o != '') {
                        $message .= $buf_o . $soft_break;
                        $buf = $word;
                    }
                }
            }
            $message .= $buf . $this->LE;
        }

        return $message;
    }

    /**
     * Set the body wrapping.
     * @access private
     * @return void
     */
    function SetWordWrap() {
        if($this->WordWrap < 1) {
            return;
        }

        switch($this->message_type) {
            case 'alt':
                /* fall through */
            case 'alt_attachments':
                $this->AltBody = $this->WrapText($this->AltBody, $this->WordWrap);
                break;
            default:
                $this->Body = $this->WrapText($this->Body, $this->WordWrap);
                break;
        }
    }

    /**
     * Assembles message header.
     * @access private
     * @return string
     */
    function CreateHeader() {
        $result = '';

        /* Set the boundaries */
        $uniq_id = md5(uniqid(time()));
        $this->boundary[1] = 'b1_' . $uniq_id;
        $this->boundary[2] = 'b2_' . $uniq_id;

        $result .= $this->HeaderLine('Date', $this->RFCDate());
        if($this->Sender == '') {
            $result .= $this->HeaderLine('Return-Path', trim($this->From));
        } else {
            $result .= $this->HeaderLine('Return-Path', trim($this->Sender));
        }

        /* To be created automatically by mail() */
        if($this->Mailer != 'mail') {
            if(count($this->to) > 0) {
                $result .= $this->AddrAppend('To', $this->to);
            } elseif (count($this->cc) == 0) {
                $result .= $this->HeaderLine('To', 'undisclosed-recipients:;');
            }
            if(count($this->cc) > 0) {
                $result .= $this->AddrAppend('Cc', $this->cc);
            }
        }

        $from = array();
        $from[0][0] = trim($this->From);
        $from[0][1] = $this->FromName;
        $result .= $this->AddrAppend('From', $from);

        /* sendmail and mail() extract Cc from the header before sending */
        if((($this->Mailer == 'sendmail') || ($this->Mailer == 'mail')) && (count($this->cc) > 0)) {
            $result .= $this->AddrAppend('Cc', $this->cc);
        }

        /* sendmail and mail() extract Bcc from the header before sending */
        if((($this->Mailer == 'sendmail') || ($this->Mailer == 'mail')) && (count($this->bcc) > 0)) {
            $result .= $this->AddrAppend('Bcc', $this->bcc);
        }

        if(count($this->ReplyTo) > 0) {
            $result .= $this->AddrAppend('Reply-to', $this->ReplyTo);
        }

        /* mail() sets the subject itself */
        if($this->Mailer != 'mail') {
            $result .= $this->HeaderLine('Subject', $this->EncodeHeader($this->SecureHeader($this->Subject)));
        }

        $result .= sprintf("Message-ID: <%s@%s>%s", $uniq_id, $this->ServerHostname(), $this->LE);
        $result .= $this->HeaderLine('X-Priority', $this->Priority);
        $result .= $this->HeaderLine('X-Mailer', 'PHPMailer (phpmailer.sourceforge.net) [version ' . $this->Version . ']');

        if($this->ConfirmReadingTo != '') {
            $result .= $this->HeaderLine('Disposition-Notification-To', '<' . trim($this->ConfirmReadingTo) . '>');
        }

        // Add custom headers
        for($index = 0; $index < count($this->CustomHeader); $index++) {
            $result .= $this->HeaderLine(trim($this->CustomHeader[$index][0]), $this->EncodeHeader(trim($this->CustomHeader[$index][1])));
        }
        $result .= $this->HeaderLine('MIME-Version', '1.0');

        switch($this->message_type) {
            case 'plain':
                $result .= $this->HeaderLine('Content-Transfer-Encoding', $this->Encoding);
                $result .= sprintf("Content-Type: %s; charset=\"%s\"", $this->ContentType, $this->CharSet);
                break;
            case 'attachments':
                /* fall through */
            case 'alt_attachments':
                if($this->InlineImageExists()){
                    $result .= sprintf("Content-Type: %s;%s\ttype=\"text/html\";%s\tboundary=\"%s\"%s", 'multipart/related', $this->LE, $this->LE, $this->boundary[1], $this->LE);
                } else {
                    $result .= $this->HeaderLine('Content-Type', 'multipart/mixed;');
                    $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                }
                break;
            case 'alt':
                $result .= $this->HeaderLine('Content-Type', 'multipart/alternative;');
                $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
        }

        if($this->Mailer != 'mail') {
            $result .= $this->LE.$this->LE;
        }

        return $result;
    }

    /**
     * Assembles the message body.  Returns an empty string on failure.
     * @access private
     * @return string
     */
    function CreateBody() {
        $result = '';

        $this->SetWordWrap();

        switch($this->message_type) {
            case 'alt':
                $result .= $this->GetBoundary($this->boundary[1], '', 'text/plain', '');
                $result .= $this->EncodeString($this->AltBody, $this->Encoding);
                $result .= $this->LE.$this->LE;
                $result .= $this->GetBoundary($this->boundary[1], '', 'text/html', '');
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE.$this->LE;
                $result .= $this->EndBoundary($this->boundary[1]);
                break;
            case 'plain':
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                break;
            case 'attachments':
                $result .= $this->GetBoundary($this->boundary[1], '', '', '');
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE;
                $result .= $this->AttachAll();
                break;
            case 'alt_attachments':
                $result .= sprintf("--%s%s", $this->boundary[1], $this->LE);
                $result .= sprintf("Content-Type: %s;%s" . "\tboundary=\"%s\"%s", 'multipart/alternative', $this->LE, $this->boundary[2], $this->LE.$this->LE);
                $result .= $this->GetBoundary($this->boundary[2], '', 'text/plain', '') . $this->LE; // Create text body
                $result .= $this->EncodeString($this->AltBody, $this->Encoding);
                $result .= $this->LE.$this->LE;
                $result .= $this->GetBoundary($this->boundary[2], '', 'text/html', '') . $this->LE; // Create the HTML body
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE.$this->LE;
                $result .= $this->EndBoundary($this->boundary[2]);
                $result .= $this->AttachAll();
                break;
        }
        if($this->IsError()) {
            $result = '';
        }

        return $result;
    }

    /**
     * Returns the start of a message boundary.
     * @access private
     */
    function GetBoundary($boundary, $charSet, $contentType, $encoding) {
        $result = '';
        if($charSet == '') {
            $charSet = $this->CharSet;
        }
        if($contentType == '') {
            $contentType = $this->ContentType;
        }
        if($encoding == '') {
            $encoding = $this->Encoding;
        }
        $result .= $this->TextLine('--' . $boundary);
        $result .= sprintf("Content-Type: %s; charset = \"%s\"", $contentType, $charSet);
        $result .= $this->LE;
        $result .= $this->HeaderLine('Content-Transfer-Encoding', $encoding);
        $result .= $this->LE;

        return $result;
    }

    /**
     * Returns the end of a message boundary.
     * @access private
     */
    function EndBoundary($boundary) {
        return $this->LE . '--' . $boundary . '--' . $this->LE;
    }

    /**
     * Sets the message type.
     * @access private
     * @return void
     */
    function SetMessageType() {
        if(count($this->attachment) < 1 && strlen($this->AltBody) < 1) {
            $this->message_type = 'plain';
        } else {
            if(count($this->attachment) > 0) {
                $this->message_type = 'attachments';
            }
            if(strlen($this->AltBody) > 0 && count($this->attachment) < 1) {
                $this->message_type = 'alt';
            }
            if(strlen($this->AltBody) > 0 && count($this->attachment) > 0) {
                $this->message_type = 'alt_attachments';
            }
        }
    }

    /* Returns a formatted header line.
     * @access private
     * @return string
     */
    function HeaderLine($name, $value) {
        return $name . ': ' . $value . $this->LE;
    }

    /**
     * Returns a formatted mail line.
     * @access private
     * @return string
     */
    function TextLine($value) {
        return $value . $this->LE;
    }

    /////////////////////////////////////////////////
    // CLASS METHODS, ATTACHMENTS
    /////////////////////////////////////////////////

    /**
     * Adds an attachment from a path on the filesystem.
     * Returns false if the file could not be found
     * or accessed.
     * @param string $path Path to the attachment.
     * @param string $name Overrides the attachment name.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return bool
     */
    function AddAttachment($path, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {
        if(!@is_file($path)) {
            $this->SetError($this->Lang('file_access') . $path);
            return false;
        }

        $filename = basename($path);
        if($name == '') {
            $name = $filename;
        }

        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $path;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $name;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = false; // isStringAttachment
        $this->attachment[$cur][6] = 'attachment';
        $this->attachment[$cur][7] = 0;

        return true;
    }

    /**
     * Attaches all fs, string, and binary attachments to the message.
     * Returns an empty string on failure.
     * @access private
     * @return string
     */
    function AttachAll() {
        /* Return text of body */
        $mime = array();

        /* Add all attachments */
        for($i = 0; $i < count($this->attachment); $i++) {
            /* Check for string attachment */
            $bString = $this->attachment[$i][5];
            if ($bString) {
                $string = $this->attachment[$i][0];
            } else {
                $path = $this->attachment[$i][0];
            }

            $filename    = $this->attachment[$i][1];
            $name        = $this->attachment[$i][2];
            $encoding    = $this->attachment[$i][3];
            $type        = $this->attachment[$i][4];
            $disposition = $this->attachment[$i][6];
            $cid         = $this->attachment[$i][7];

            $mime[] = sprintf("--%s%s", $this->boundary[1], $this->LE);
            $mime[] = sprintf("Content-Type: %s; name=\"%s\"%s", $type, $name, $this->LE);
            $mime[] = sprintf("Content-Transfer-Encoding: %s%s", $encoding, $this->LE);

            if($disposition == 'inline') {
                $mime[] = sprintf("Content-ID: <%s>%s", $cid, $this->LE);
            }

            $mime[] = sprintf("Content-Disposition: %s; filename=\"%s\"%s", $disposition, $name, $this->LE.$this->LE);

            /* Encode as string attachment */
            if($bString) {
                $mime[] = $this->EncodeString($string, $encoding);
                if($this->IsError()) {
                    return '';
                }
                $mime[] = $this->LE.$this->LE;
            } else {
                $mime[] = $this->EncodeFile($path, $encoding);
                if($this->IsError()) {
                    return '';
                }
                $mime[] = $this->LE.$this->LE;
            }
        }

        $mime[] = sprintf("--%s--%s", $this->boundary[1], $this->LE);

        return join('', $mime);
    }

    /**
     * Encodes attachment in requested format.  Returns an
     * empty string on failure.
     * @access private
     * @return string
     */
    function EncodeFile ($path, $encoding = 'base64') {
        if(!@$fd = fopen($path, 'rb')) {
            $this->SetError($this->Lang('file_open') . $path);
            return '';
        }
        $magic_quotes = get_magic_quotes_runtime();
        set_magic_quotes_runtime(0);
        $file_buffer = fread($fd, filesize($path));
        $file_buffer = $this->EncodeString($file_buffer, $encoding);
        fclose($fd);
        set_magic_quotes_runtime($magic_quotes);

        return $file_buffer;
    }

    /**
     * Encodes string to requested format. Returns an
     * empty string on failure.
     * @access private
     * @return string
     */
    function EncodeString ($str, $encoding = 'base64') {
        $encoded = '';
        switch(strtolower($encoding)) {
            case 'base64':
                /* chunk_split is found in PHP >= 3.0.6 */
                $encoded = chunk_split(base64_encode($str), 76, $this->LE);
                break;
            case '7bit':
            case '8bit':
                $encoded = $this->FixEOL($str);
                if (substr($encoded, -(strlen($this->LE))) != $this->LE)
                    $encoded .= $this->LE;
                break;
            case 'binary':
                $encoded = $str;
                break;
            case 'quoted-printable':
                $encoded = $this->EncodeQP($str);
                break;
            default:
                $this->SetError($this->Lang('encoding') . $encoding);
                break;
        }
        return $encoded;
    }

    /**
     * Encode a header string to best of Q, B, quoted or none.
     * @access private
     * @return string
     */
    function EncodeHeader ($str, $position = 'text') {
        $x = 0;

        switch (strtolower($position)) {
            case 'phrase':
                if (!preg_match('/[\200-\377]/', $str)) {
                    /* Can't use addslashes as we don't know what value has magic_quotes_sybase. */
                    $encoded = addcslashes($str, "\0..\37\177\\\"");
                    if (($str == $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) {
                        return ($encoded);
                    } else {
                        return ("\"$encoded\"");
                    }
                }
                $x = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
                break;
            case 'comment':
                $x = preg_match_all('/[()"]/', $str, $matches);
            /* Fall-through */
            case 'text':
            default:
                $x += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
                break;
        }

        if ($x == 0) {
            return ($str);
        }

        $maxlen = 75 - 7 - strlen($this->CharSet);
        /* Try to select the encoding which should produce the shortest output */
        if (strlen($str)/3 < $x) {
            $encoding = 'B';
            $encoded = base64_encode($str);
            $maxlen -= $maxlen % 4;
            $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
        } else {
            $encoding = 'Q';
            $encoded = $this->EncodeQ($str, $position);
            $encoded = $this->WrapText($encoded, $maxlen, true);
            $encoded = str_replace('='.$this->LE, "\n", trim($encoded));
        }

        $encoded = preg_replace('/^(.*)$/m', " =?".$this->CharSet."?$encoding?\\1?=", $encoded);
        $encoded = trim(str_replace("\n", $this->LE, $encoded));

        return $encoded;
    }

    /**
     * Encode string to quoted-printable.
     * @access public
     * @param string $string the text to encode
     * @param integer $line_max Number of chars allowed on a line before wrapping
     * @return string
     */
    public function EncodeQP($string, $line_max = 74) {
        $fp = fopen('php://temp/', 'r+');
        $string = preg_replace('/\r\n?/', $this->LE, $string); //Normalise line breaks
        $params = array('line-length' => $line_max, 'line-break-chars' => $this->LE);
        stream_filter_append($fp, 'convert.quoted-printable-encode', STREAM_FILTER_READ, $params);
        fputs($fp, $string);
        rewind($fp);
        $out = stream_get_contents($fp);
        $out = preg_replace('/^\./m', '=2E', $out); //Encode . if it is first char on a line
        fclose($fp);
        return $out;
    }

    /**
     * Encode string to q encoding.
     * @access private
     * @return string
     */
    function EncodeQ ($str, $position = 'text') {
        /* There should not be any EOL in the string */
        $encoded = preg_replace("[\r\n]", '', $str);

        switch (strtolower($position)) {
            case 'phrase':
                $encoded = preg_replace("/([^A-Za-z0-9!*+\/ -])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
                break;
            case 'comment':
                $encoded = preg_replace("/([\(\)\"])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
            case 'text':
            default:
                /* Replace every high ascii, control =, ? and _ characters */
                $encoded = preg_replace('/([\000-\011\013\014\016-\037\075\077\137\177-\377])/e',
                    "'='.sprintf('%02X', ord('\\1'))", $encoded);
                break;
        }

        /* Replace every spaces to _ (more readable than =20) */
        $encoded = str_replace(' ', '_', $encoded);

        return $encoded;
    }

    /**
     * Adds a string or binary attachment (non-filesystem) to the list.
     * This method can be used to attach ascii or binary data,
     * such as a BLOB record from a database.
     * @param string $string String attachment data.
     * @param string $filename Name of the attachment.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return void
     */
    function AddStringAttachment($string, $filename, $encoding = 'base64', $type = 'application/octet-stream') {
        /* Append to $attachment array */
        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $string;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $filename;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = true; // isString
        $this->attachment[$cur][6] = 'attachment';
        $this->attachment[$cur][7] = 0;
    }

    /**
     * Adds an embedded attachment.  This can include images, sounds, and
     * just about any other document.  Make sure to set the $type to an
     * image type.  For JPEG images use "image/jpeg" and for GIF images
     * use "image/gif".
     * @param string $path Path to the attachment.
     * @param string $cid Content ID of the attachment.  Use this to identify
     *        the Id for accessing the image in an HTML form.
     * @param string $name Overrides the attachment name.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return bool
     */
    function AddEmbeddedImage($path, $cid, $name = '', $encoding = 'base64', $type = 'application/octet-stream') {

        if(!@is_file($path)) {
            $this->SetError($this->Lang('file_access') . $path);
            return false;
        }

        $filename = basename($path);
        if($name == '') {
            $name = $filename;
        }

        /* Append to $attachment array */
        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $path;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $name;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = false;
        $this->attachment[$cur][6] = 'inline';
        $this->attachment[$cur][7] = $cid;

        return true;
    }

    /**
     * Returns true if an inline attachment is present.
     * @access private
     * @return bool
     */
    function InlineImageExists() {
        $result = false;
        for($i = 0; $i < count($this->attachment); $i++) {
            if($this->attachment[$i][6] == 'inline') {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /////////////////////////////////////////////////
    // CLASS METHODS, MESSAGE RESET
    /////////////////////////////////////////////////

    /**
     * Clears all recipients assigned in the TO array.  Returns void.
     * @return void
     */
    function ClearAddresses() {
        $this->to = array();
    }

    /**
     * Clears all recipients assigned in the CC array.  Returns void.
     * @return void
     */
    function ClearCCs() {
        $this->cc = array();
    }

    /**
     * Clears all recipients assigned in the BCC array.  Returns void.
     * @return void
     */
    function ClearBCCs() {
        $this->bcc = array();
    }

    /**
     * Clears all recipients assigned in the ReplyTo array.  Returns void.
     * @return void
     */
    function ClearReplyTos() {
        $this->ReplyTo = array();
    }

    /**
     * Clears all recipients assigned in the TO, CC and BCC
     * array.  Returns void.
     * @return void
     */
    function ClearAllRecipients() {
        $this->to = array();
        $this->cc = array();
        $this->bcc = array();
    }

    /**
     * Clears all previously set filesystem, string, and binary
     * attachments.  Returns void.
     * @return void
     */
    function ClearAttachments() {
        $this->attachment = array();
    }

    /**
     * Clears all custom headers.  Returns void.
     * @return void
     */
    function ClearCustomHeaders() {
        $this->CustomHeader = array();
    }

    /////////////////////////////////////////////////
    // CLASS METHODS, MISCELLANEOUS
    /////////////////////////////////////////////////

    /**
     * Adds the error message to the error container.
     * Returns void.
     * @access private
     * @return void
     */
    private function SetError($msg) {
        $this->error_count++;
        $this->ErrorInfo = $msg;
    }

    /**
     * Returns the proper RFC 822 formatted date.
     * @access private
     * @return string
     */
    private static function RFCDate() {
        $tz = date('Z');
        $tzs = ($tz < 0) ? '-' : '+';
        $tz = abs($tz);
        $tz = (int)($tz/3600)*100 + ($tz%3600)/60;
        $result = sprintf("%s %s%04d", date('D, j M Y H:i:s'), $tzs, $tz);

        return $result;
    }

    /**
     * Returns the server hostname or 'localhost.localdomain' if unknown.
     * @access private
     * @return string
     */
    private function ServerHostname() {
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $result = $_SERVER['SERVER_NAME'];
        } else {
            $result = "localhost.localdomain";
        }

        return $result;
    }

    /**
     * Returns a message in the appropriate language.
     * @access private
     * @return string
     */
    private function Lang($key) {
        if(count($this->language) < 1) {
            $this->SetLanguage('en'); // set the default language
        }

        if(isset($this->language[$key])) {
            return $this->language[$key];
        } else {
            return 'Language string failed to load: ' . $key;
        }
    }

    /**
     * Returns true if an error occurred.
     * @return bool
     */
    function IsError() {
        return ($this->error_count > 0);
    }

    /**
     * Changes every end of line from CR or LF to CRLF.
     * @access private
     * @return string
     */
    private function FixEOL($str) {
        $str = str_replace("\r\n", "\n", $str);
        $str = str_replace("\r", "\n", $str);
        $str = str_replace("\n", $this->LE, $str);
        return $str;
    }

    /**
     * Adds a custom header.
     * @return void
     */
    function AddCustomHeader($custom_header) {
        $this->CustomHeader[] = explode(':', $custom_header, 2);
    }

    /**
     * Evaluates the message and returns modifications for inline images and backgrounds
     * @access public
     * @return $message
     */
    function MsgHTML($message) {
        preg_match_all("/(src|background)=\"(.*)\"/Ui", $message, $images);
        if(isset($images[2])) {
            foreach($images[2] as $i => $url) {
                $filename  = basename($url);
                $directory = dirname($url);
                $cid       = 'cid:' . md5($filename);
                $fileParts = split("\.", $filename);
                $ext       = $fileParts[1];
                $mimeType  = $this->_mime_types($ext);
                $message = preg_replace("/".$images[1][$i]."=\"".preg_quote($url, '/')."\"/Ui", $images[1][$i]."=\"".$cid."\"", $message);
                $this->AddEmbeddedImage($url, md5($filename), $filename, 'base64', $mimeType);
            }
        }
        $this->IsHTML(true);
        $this->Body = $message;
        $textMsg = trim(strip_tags($message));
        if ( !empty($textMsg) && empty($this->AltBody) ) {
            $this->AltBody = $textMsg;
        }
        if ( empty($this->AltBody) ) {
            $this->AltBody = 'To view this email message, open the email in with HTML compatibility!' . "\n\n";
        }
    }

    /**
     * Gets the mime type of the embedded or inline image
     * @access private
     * @return mime type of ext
     */
    function _mime_types($ext = '') {
        $mimes = array(
            'hqx'  =>  'application/mac-binhex40',
            'cpt'   =>  'application/mac-compactpro',
            'doc'   =>  'application/msword',
            'bin'   =>  'application/macbinary',
            'dms'   =>  'application/octet-stream',
            'lha'   =>  'application/octet-stream',
            'lzh'   =>  'application/octet-stream',
            'exe'   =>  'application/octet-stream',
            'class' =>  'application/octet-stream',
            'psd'   =>  'application/octet-stream',
            'so'    =>  'application/octet-stream',
            'sea'   =>  'application/octet-stream',
            'dll'   =>  'application/octet-stream',
            'oda'   =>  'application/oda',
            'pdf'   =>  'application/pdf',
            'ai'    =>  'application/postscript',
            'eps'   =>  'application/postscript',
            'ps'    =>  'application/postscript',
            'smi'   =>  'application/smil',
            'smil'  =>  'application/smil',
            'mif'   =>  'application/vnd.mif',
            'xls'   =>  'application/vnd.ms-excel',
            'ppt'   =>  'application/vnd.ms-powerpoint',
            'wbxml' =>  'application/vnd.wap.wbxml',
            'wmlc'  =>  'application/vnd.wap.wmlc',
            'dcr'   =>  'application/x-director',
            'dir'   =>  'application/x-director',
            'dxr'   =>  'application/x-director',
            'dvi'   =>  'application/x-dvi',
            'gtar'  =>  'application/x-gtar',
            'php'   =>  'application/x-httpd-php',
            'php4'  =>  'application/x-httpd-php',
            'php3'  =>  'application/x-httpd-php',
            'phtml' =>  'application/x-httpd-php',
            'phps'  =>  'application/x-httpd-php-source',
            'js'    =>  'application/x-javascript',
            'swf'   =>  'application/x-shockwave-flash',
            'sit'   =>  'application/x-stuffit',
            'tar'   =>  'application/x-tar',
            'tgz'   =>  'application/x-tar',
            'xhtml' =>  'application/xhtml+xml',
            'xht'   =>  'application/xhtml+xml',
            'zip'   =>  'application/zip',
            'mid'   =>  'audio/midi',
            'midi'  =>  'audio/midi',
            'mpga'  =>  'audio/mpeg',
            'mp2'   =>  'audio/mpeg',
            'mp3'   =>  'audio/mpeg',
            'aif'   =>  'audio/x-aiff',
            'aiff'  =>  'audio/x-aiff',
            'aifc'  =>  'audio/x-aiff',
            'ram'   =>  'audio/x-pn-realaudio',
            'rm'    =>  'audio/x-pn-realaudio',
            'rpm'   =>  'audio/x-pn-realaudio-plugin',
            'ra'    =>  'audio/x-realaudio',
            'rv'    =>  'video/vnd.rn-realvideo',
            'wav'   =>  'audio/x-wav',
            'bmp'   =>  'image/bmp',
            'gif'   =>  'image/gif',
            'jpeg'  =>  'image/jpeg',
            'jpg'   =>  'image/jpeg',
            'jpe'   =>  'image/jpeg',
            'png'   =>  'image/png',
            'tiff'  =>  'image/tiff',
            'tif'   =>  'image/tiff',
            'css'   =>  'text/css',
            'html'  =>  'text/html',
            'htm'   =>  'text/html',
            'shtml' =>  'text/html',
            'txt'   =>  'text/plain',
            'text'  =>  'text/plain',
            'log'   =>  'text/plain',
            'rtx'   =>  'text/richtext',
            'rtf'   =>  'text/rtf',
            'xml'   =>  'text/xml',
            'xsl'   =>  'text/xml',
            'mpeg'  =>  'video/mpeg',
            'mpg'   =>  'video/mpeg',
            'mpe'   =>  'video/mpeg',
            'qt'    =>  'video/quicktime',
            'mov'   =>  'video/quicktime',
            'avi'   =>  'video/x-msvideo',
            'movie' =>  'video/x-sgi-movie',
            'doc'   =>  'application/msword',
            'word'  =>  'application/msword',
            'xl'    =>  'application/excel',
            'eml'   =>  'message/rfc822'
        );
        return ( ! isset($mimes[strtolower($ext)])) ? 'application/x-unknown-content-type' : $mimes[strtolower($ext)];
    }

    /**
     * Set (or reset) Class Objects (variables)
     *
     * Usage Example:
     * $page->set('X-Priority', '3');
     *
     * @access public
     * @param string $name Parameter Name
     * @param mixed $value Parameter Value
     * NOTE: will not work with arrays, there are no arrays to set/reset
     */
    function set ( $name, $value = '' ) {
        if ( isset($this->$name) ) {
            $this->$name = $value;
        } else {
            $this->SetError('Cannot set or reset variable ' . $name);
            return false;
        }
    }

    /**
     * Read a file from a supplied filename and return it.
     *
     * @access public
     * @param string $filename Parameter File Name
     */
    function getFile($filename) {
        $return = '';
        if ($fp = fopen($filename, 'rb')) {
            while (!feof($fp)) {
                $return .= fread($fp, 1024);
            }
            fclose($fp);
            return $return;
        } else {
            return false;
        }
    }

    /**
     * Strips newlines to prevent header injection.
     * @access private
     * @param string $str String
     * @return string
     */
    function SecureHeader($str) {
        $str = trim($str);
        $str = str_replace("\r", "", $str);
        $str = str_replace("\n", "", $str);
        return $str;
    }

}

class SMTP
{
    /**
     * The PHPMailer SMTP version number.
     *
     * @var string
     */
    const VERSION = '6.0.5';
    /**
     * SMTP line break constant.
     *
     * @var string
     */
    const LE = "\r\n";
    /**
     * The SMTP port to use if one is not specified.
     *
     * @var int
     */
    const DEFAULT_PORT = 25;
    /**
     * The maximum line length allowed by RFC 2822 section 2.1.1.
     *
     * @var int
     */
    const MAX_LINE_LENGTH = 998;
    /**
     * Debug level for no output.
     */
    const DEBUG_OFF = 0;
    /**
     * Debug level to show client -> server messages.
     */
    const DEBUG_CLIENT = 1;
    /**
     * Debug level to show client -> server and server -> client messages.
     */
    const DEBUG_SERVER = 2;
    /**
     * Debug level to show connection status, client -> server and server -> client messages.
     */
    const DEBUG_CONNECTION = 3;
    /**
     * Debug level to show all messages.
     */
    const DEBUG_LOWLEVEL = 4;
    /**
     * Debug output level.
     * Options:
     * * self::DEBUG_OFF (`0`) No debug output, default
     * * self::DEBUG_CLIENT (`1`) Client commands
     * * self::DEBUG_SERVER (`2`) Client commands and server responses
     * * self::DEBUG_CONNECTION (`3`) As DEBUG_SERVER plus connection status
     * * self::DEBUG_LOWLEVEL (`4`) Low-level data output, all messages.
     *
     * @var int
     */
    public $do_debug = self::DEBUG_OFF;
    /**
     * How to handle debug output.
     * Options:
     * * `echo` Output plain-text as-is, appropriate for CLI
     * * `html` Output escaped, line breaks converted to `<br>`, appropriate for browser output
     * * `error_log` Output to error log as configured in php.ini
     * Alternatively, you can provide a callable expecting two params: a message string and the debug level:
     *
     * ```php
     * $smtp->Debugoutput = function($str, $level) {echo "debug level $level; message: $str";};
     * ```
     *
     * Alternatively, you can pass in an instance of a PSR-3 compatible logger, though only `debug`
     * level output is used:
     *
     * ```php
     * $mail->Debugoutput = new myPsr3Logger;
     * ```
     *
     * @var string|callable|\Psr\Log\LoggerInterface
     */
    public $Debugoutput = 'echo';
    /**
     * Whether to use VERP.
     *
     * @see http://en.wikipedia.org/wiki/Variable_envelope_return_path
     * @see http://www.postfix.org/VERP_README.html Info on VERP
     *
     * @var bool
     */
    public $do_verp = false;
    /**
     * The timeout value for connection, in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     * This needs to be quite high to function correctly with hosts using greetdelay as an anti-spam measure.
     *
     * @see http://tools.ietf.org/html/rfc2821#section-4.5.3.2
     *
     * @var int
     */
    public $Timeout = 300;
    /**
     * How long to wait for commands to complete, in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2.
     *
     * @var int
     */
    public $Timelimit = 300;
    /**
     * Patterns to extract an SMTP transaction id from reply to a DATA command.
     * The first capture group in each regex will be used as the ID.
     * MS ESMTP returns the message ID, which may not be correct for internal tracking.
     *
     * @var string[]
     */
    protected $smtp_transaction_id_patterns = [
        'exim' => '/[\d]{3} OK id=(.*)/',
        'sendmail' => '/[\d]{3} 2.0.0 (.*) Message/',
        'postfix' => '/[\d]{3} 2.0.0 Ok: queued as (.*)/',
        'Microsoft_ESMTP' => '/[0-9]{3} 2.[\d].0 (.*)@(?:.*) Queued mail for delivery/',
        'Amazon_SES' => '/[\d]{3} Ok (.*)/',
        'SendGrid' => '/[\d]{3} Ok: queued as (.*)/',
        'CampaignMonitor' => '/[\d]{3} 2.0.0 OK:([a-zA-Z\d]{48})/',
    ];
    /**
     * The last transaction ID issued in response to a DATA command,
     * if one was detected.
     *
     * @var string|bool|null
     */
    protected $last_smtp_transaction_id;
    /**
     * The socket for the server connection.
     *
     * @var ?resource
     */
    protected $smtp_conn;
    /**
     * Error information, if any, for the last SMTP command.
     *
     * @var array
     */
    protected $error = [
        'error' => '',
        'detail' => '',
        'smtp_code' => '',
        'smtp_code_ex' => '',
    ];
    /**
     * The reply the server sent to us for HELO.
     * If null, no HELO string has yet been received.
     *
     * @var string|null
     */
    protected $helo_rply = null;
    /**
     * The set of SMTP extensions sent in reply to EHLO command.
     * Indexes of the array are extension names.
     * Value at index 'HELO' or 'EHLO' (according to command that was sent)
     * represents the server name. In case of HELO it is the only element of the array.
     * Other values can be boolean TRUE or an array containing extension options.
     * If null, no HELO/EHLO string has yet been received.
     *
     * @var array|null
     */
    protected $server_caps = null;
    /**
     * The most recent reply received from the server.
     *
     * @var string
     */
    protected $last_reply = '';
    /**
     * Output debugging info via a user-selected method.
     *
     * @param string $str   Debug string to output
     * @param int    $level The debug level of this message; see DEBUG_* constants
     *
     * @see SMTP::$Debugoutput
     * @see SMTP::$do_debug
     */
    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        //Is this a PSR-3 logger?
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);
            return;
        }
        //Avoid clash with built-in function names
        if (!in_array($this->Debugoutput, ['error_log', 'html', 'echo']) and is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                //Don't output, just log
                error_log($str);
                break;
            case 'html':
                //Cleans up output a bit for a better looking, HTML-safe output
                echo gmdate('Y-m-d H:i:s'), ' ', htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ), "<br>\n";
                break;
            case 'echo':
            default:
                //Normalize line breaks
                $str = preg_replace('/\r\n|\r/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s'),
                "\t",
                    //Trim trailing space
                trim(
                //Indent for readability, except for trailing break
                    str_replace(
                        "\n",
                        "\n                   \t                  ",
                        trim($str)
                    )
                ),
                "\n";
        }
    }
    /**
     * Connect to an SMTP server.
     *
     * @param string $host    SMTP server IP or host name
     * @param int    $port    The port number to connect to
     * @param int    $timeout How long to wait for the connection to open
     * @param array  $options An array of options for stream_context_create()
     *
     * @return bool
     */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        static $streamok;
        //This is enabled by default since 5.0.0 but some providers disable it
        //Check this once and cache the result
        if (null === $streamok) {
            $streamok = function_exists('stream_socket_client');
        }
        // Clear errors to avoid confusion
        $this->setError('');
        // Make sure we are __not__ connected
        if ($this->connected()) {
            // Already connected, generate error
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        // Connect to the SMTP server
        $this->edebug(
            "Connection: opening to $host:$port, timeout=$timeout, options=" .
            (count($options) > 0 ? var_export($options, true) : 'array()'),
            self::DEBUG_CONNECTION
        );
        $errno = 0;
        $errstr = '';
        if ($streamok) {
            $socket_context = stream_context_create($options);
            set_error_handler([$this, 'errorHandler']);
            $this->smtp_conn = stream_socket_client(
                $host . ':' . $port,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $socket_context
            );
            restore_error_handler();
        } else {
            //Fall back to fsockopen which should work in more places, but is missing some features
            $this->edebug(
                'Connection: stream_socket_client not available, falling back to fsockopen',
                self::DEBUG_CONNECTION
            );
            set_error_handler([$this, 'errorHandler']);
            $this->smtp_conn = fsockopen(
                $host,
                $port,
                $errno,
                $errstr,
                $timeout
            );
            restore_error_handler();
        }
        // Verify we connected properly
        if (!is_resource($this->smtp_conn)) {
            $this->setError(
                'Failed to connect to server',
                '',
                (string) $errno,
                (string) $errstr
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error']
                . ": $errstr ($errno)",
                self::DEBUG_CLIENT
            );
            return false;
        }
        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);
        // SMTP server can take longer to respond, give longer timeout for first read
        // Windows does not have support for this timeout function
        if (substr(PHP_OS, 0, 3) != 'WIN') {
            $max = ini_get('max_execution_time');
            // Don't bother if unlimited
            if (0 != $max and $timeout > $max) {
                @set_time_limit($timeout);
            }
            stream_set_timeout($this->smtp_conn, $timeout, 0);
        }
        // Get any announcement
        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);
        return true;
    }
    /**
     * Initiate a TLS (encrypted) session.
     *
     * @return bool
     */
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        //Allow the best TLS version(s) we can
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        //PHP 5.6.7 dropped inclusion of TLS 1.1 and 1.2 in STREAM_CRYPTO_METHOD_TLS_CLIENT
        //so add them back in manually if we can
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        // Begin encrypted connection
        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto(
            $this->smtp_conn,
            true,
            $crypto_method
        );
        restore_error_handler();
        return (bool) $crypto_ok;
    }
    /**
     * Perform SMTP authentication.
     * Must be run after hello().
     *
     * @see    hello()
     *
     * @param string $username The user name
     * @param string $password The password
     * @param string $authtype The auth type (CRAM-MD5, PLAIN, LOGIN, XOAUTH2)
     * @param OAuth  $OAuth    An optional OAuth instance for XOAUTH2 authentication
     *
     * @return bool True if successfully authenticated
     */
    public function authenticate(
        $username,
        $password,
        $authtype = null,
        $OAuth = null
    ) {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed before HELO/EHLO');
            return false;
        }
        if (array_key_exists('EHLO', $this->server_caps)) {
            // SMTP extensions are available; try to find a proper authentication method
            if (!array_key_exists('AUTH', $this->server_caps)) {
                $this->setError('Authentication is not allowed at this stage');
                // 'at this stage' means that auth may be allowed after the stage changes
                // e.g. after STARTTLS
                return false;
            }
            $this->edebug('Auth method requested: ' . ($authtype ? $authtype : 'UNSPECIFIED'), self::DEBUG_LOWLEVEL);
            $this->edebug(
                'Auth methods available on the server: ' . implode(',', $this->server_caps['AUTH']),
                self::DEBUG_LOWLEVEL
            );
            //If we have requested a specific auth type, check the server supports it before trying others
            if (null !== $authtype and !in_array($authtype, $this->server_caps['AUTH'])) {
                $this->edebug('Requested auth method not available: ' . $authtype, self::DEBUG_LOWLEVEL);
                $authtype = null;
            }
            if (empty($authtype)) {
                //If no auth mechanism is specified, attempt to use these, in this order
                //Try CRAM-MD5 first as it's more secure than the others
                foreach (['CRAM-MD5', 'LOGIN', 'PLAIN', 'XOAUTH2'] as $method) {
                    if (in_array($method, $this->server_caps['AUTH'])) {
                        $authtype = $method;
                        break;
                    }
                }
                if (empty($authtype)) {
                    $this->setError('No supported authentication methods found');
                    return false;
                }
                self::edebug('Auth method selected: ' . $authtype, self::DEBUG_LOWLEVEL);
            }
            if (!in_array($authtype, $this->server_caps['AUTH'])) {
                $this->setError("The requested authentication method \"$authtype\" is not supported by the server");
                return false;
            }
        } elseif (empty($authtype)) {
            $authtype = 'LOGIN';
        }
        switch ($authtype) {
            case 'PLAIN':
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN', 334)) {
                    return false;
                }
                // Send encoded username and password
                if (!$this->sendCommand(
                    'User & Password',
                    base64_encode("\0" . $username . "\0" . $password),
                    235
                )
                ) {
                    return false;
                }
                break;
            case 'LOGIN':
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                    return false;
                }
                break;
            case 'CRAM-MD5':
                // Start authentication
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                // Get the challenge
                $challenge = base64_decode(substr($this->last_reply, 4));
                // Build the response
                $response = $username . ' ' . $this->hmac($challenge, $password);
                // send encoded credentials
                return $this->sendCommand('Username', base64_encode($response), 235);
            case 'XOAUTH2':
                //The OAuth instance must be set up prior to requesting auth.
                if (null === $OAuth) {
                    return false;
                }
                $oauth = $OAuth->getOauth64();
                // Start authentication
                if (!$this->sendCommand('AUTH', 'AUTH XOAUTH2 ' . $oauth, 235)) {
                    return false;
                }
                break;
            default:
                $this->setError("Authentication method \"$authtype\" is not supported");
                return false;
        }
        return true;
    }
    /**
     * Calculate an MD5 HMAC hash.
     * Works like hash_hmac('md5', $data, $key)
     * in case that function is not available.
     *
     * @param string $data The data to hash
     * @param string $key  The key to hash with
     *
     * @return string
     */
    protected function hmac($data, $key)
    {
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $data, $key);
        }
        // The following borrowed from
        // http://php.net/manual/en/function.mhash.php#27225
        // RFC 2104 HMAC implementation for php.
        // Creates an md5 HMAC.
        // Eliminates the need to install mhash to compute a HMAC
        // by Lance Rushing
        $bytelen = 64; // byte length for md5
        if (strlen($key) > $bytelen) {
            $key = pack('H*', md5($key));
        }
        $key = str_pad($key, $bytelen, chr(0x00));
        $ipad = str_pad('', $bytelen, chr(0x36));
        $opad = str_pad('', $bytelen, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;
        return md5($k_opad . pack('H*', md5($k_ipad . $data)));
    }
    /**
     * Check connection state.
     *
     * @return bool True if connected
     */
    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                // The socket is valid but we are not connected
                $this->edebug(
                    'SMTP NOTICE: EOF caught while checking if connected',
                    self::DEBUG_CLIENT
                );
                $this->close();
                return false;
            }
            return true; // everything looks good
        }
        return false;
    }
    /**
     * Close the socket and clean up the state of the class.
     * Don't use this function without first trying to use QUIT.
     *
     * @see quit()
     */
    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            // close the connection and cleanup
            fclose($this->smtp_conn);
            $this->smtp_conn = null; //Makes for cleaner serialization
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }
    /**
     * Send an SMTP DATA command.
     * Issues a data command and sends the msg_data to the server,
     * finializing the mail transaction. $msg_data is the message
     * that is to be send with the headers. Each header needs to be
     * on a single line followed by a <CRLF> with the message headers
     * and the message body being separated by an additional <CRLF>.
     * Implements RFC 821: DATA <CRLF>.
     *
     * @param string $msg_data Message data to send
     *
     * @return bool
     */
    public function data($msg_data)
    {
        //This will use the standard timelimit
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        /* The server is ready to accept data!
         * According to rfc821 we should not send more than 1000 characters on a single line (including the LE)
         * so we will break the data up into lines by \r and/or \n then if needed we will break each of those into
         * smaller lines to fit within the limit.
         * We will also look for lines that start with a '.' and prepend an additional '.'.
         * NOTE: this does not count towards line-length limit.
         */
        // Normalize line breaks before exploding
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        /* To distinguish between a complete RFC822 message and a plain message body, we check if the first field
         * of the first line (':' separated) does not contain a space then it _should_ be a header and we will
         * process all lines before a blank line as headers.
         */
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) and strpos($field, ' ') === false) {
            $in_headers = true;
        }
        foreach ($lines as $line) {
            $lines_out = [];
            if ($in_headers and $line == '') {
                $in_headers = false;
            }
            //Break this line up into several smaller lines if it's too long
            //Micro-optimisation: isset($str[$len]) is faster than (strlen($str) > $len),
            while (isset($line[self::MAX_LINE_LENGTH])) {
                //Working backwards, try to find a space within the last MAX_LINE_LENGTH chars of the line to break on
                //so as to avoid breaking in the middle of a word
                $pos = strrpos(substr($line, 0, self::MAX_LINE_LENGTH), ' ');
                //Deliberately matches both false and 0
                if (!$pos) {
                    //No nice break found, add a hard break
                    $pos = self::MAX_LINE_LENGTH - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos);
                } else {
                    //Break at the found point
                    $lines_out[] = substr($line, 0, $pos);
                    //Move along by the amount we dealt with
                    $line = substr($line, $pos + 1);
                }
                //If processing headers add a LWSP-char to the front of new line RFC822 section 3.1.1
                if ($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;
            //Send the lines to the server
            foreach ($lines_out as $line_out) {
                //RFC2821 section 4.5.2
                if (!empty($line_out) and $line_out[0] == '.') {
                    $line_out = '.' . $line_out;
                }
                $this->client_send($line_out . static::LE, 'DATA');
            }
        }
        //Message data has been sent, complete the command
        //Increase timelimit for end of DATA command
        $savetimelimit = $this->Timelimit;
        $this->Timelimit = $this->Timelimit * 2;
        $result = $this->sendCommand('DATA END', '.', 250);
        $this->recordLastTransactionID();
        //Restore timelimit
        $this->Timelimit = $savetimelimit;
        return $result;
    }
    /**
     * Send an SMTP HELO or EHLO command.
     * Used to identify the sending server to the receiving server.
     * This makes sure that client and server are in a known state.
     * Implements RFC 821: HELO <SP> <domain> <CRLF>
     * and RFC 2821 EHLO.
     *
     * @param string $host The host name or IP to connect to
     *
     * @return bool
     */
    public function hello($host = '')
    {
        //Try extended hello first (RFC 2821)
        return (bool) ($this->sendHello('EHLO', $host) or $this->sendHello('HELO', $host));
    }
    /**
     * Send an SMTP HELO or EHLO command.
     * Low-level implementation used by hello().
     *
     * @param string $hello The HELO string
     * @param string $host  The hostname to say we are
     *
     * @return bool
     *
     * @see    hello()
     */
    protected function sendHello($hello, $host)
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        } else {
            $this->server_caps = null;
        }
        return $noerror;
    }
    /**
     * Parse a reply to HELO/EHLO command to discover server extensions.
     * In case of HELO, the only parameter that can be discovered is a server name.
     *
     * @param string $type `HELO` or `EHLO`
     */
    protected function parseHelloFields($type)
    {
        $this->server_caps = [];
        $lines = explode("\n", $this->helo_rply);
        foreach ($lines as $n => $s) {
            //First 4 chars contain response code followed by - or space
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name = $type;
                    $fields = $fields[0];
                } else {
                    $name = array_shift($fields);
                    switch ($name) {
                        case 'SIZE':
                            $fields = ($fields ? $fields[0] : 0);
                            break;
                        case 'AUTH':
                            if (!is_array($fields)) {
                                $fields = [];
                            }
                            break;
                        default:
                            $fields = true;
                    }
                }
                $this->server_caps[$name] = $fields;
            }
        }
    }
    /**
     * Send an SMTP MAIL command.
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more recipient
     * commands may be called followed by a data command.
     * Implements RFC 821: MAIL <SP> FROM:<reverse-path> <CRLF>.
     *
     * @param string $from Source address of this message
     *
     * @return bool
     */
    public function mail($from)
    {
        $useVerp = ($this->do_verp ? ' XVERP' : '');
        return $this->sendCommand(
            'MAIL FROM',
            'MAIL FROM:<' . $from . '>' . $useVerp,
            250
        );
    }
    /**
     * Send an SMTP QUIT command.
     * Closes the socket if there is no error or the $close_on_error argument is true.
     * Implements from RFC 821: QUIT <CRLF>.
     *
     * @param bool $close_on_error Should the connection close if an error occurs?
     *
     * @return bool
     */
    public function quit($close_on_error = true)
    {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->error; //Save any error
        if ($noerror or $close_on_error) {
            $this->close();
            $this->error = $err; //Restore any error from the quit command
        }
        return $noerror;
    }
    /**
     * Send an SMTP RCPT command.
     * Sets the TO argument to $toaddr.
     * Returns true if the recipient was accepted false if it was rejected.
     * Implements from RFC 821: RCPT <SP> TO:<forward-path> <CRLF>.
     *
     * @param string $address The address the message is being sent to
     *
     * @return bool
     */
    public function recipient($address)
    {
        return $this->sendCommand(
            'RCPT TO',
            'RCPT TO:<' . $address . '>',
            [250, 251]
        );
    }
    /**
     * Send an SMTP RSET command.
     * Abort any transaction that is currently in progress.
     * Implements RFC 821: RSET <CRLF>.
     *
     * @return bool True on success
     */
    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }
    /**
     * Send a command to an SMTP server and check its return code.
     *
     * @param string    $command       The command name - not sent to the server
     * @param string    $commandstring The actual command to send
     * @param int|array $expect        One or more expected integer success codes
     *
     * @return bool True on success
     */
    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command without being connected");
            return false;
        }
        //Reject line breaks in all commands
        if (strpos($commandstring, "\n") !== false or strpos($commandstring, "\r") !== false) {
            $this->setError("Command '$command' contained line breaks");
            return false;
        }
        $this->client_send($commandstring . static::LE, $command);
        $this->last_reply = $this->get_lines();
        // Fetch SMTP code and possible error code explanation
        $matches = [];
        if (preg_match('/^([0-9]{3})[ -](?:([0-9]\\.[0-9]\\.[0-9]) )?/', $this->last_reply, $matches)) {
            $code = $matches[1];
            $code_ex = (count($matches) > 2 ? $matches[2] : null);
            // Cut off error code from each response line
            $detail = preg_replace(
                "/{$code}[ -]" .
                ($code_ex ? str_replace('.', '\\.', $code_ex) . ' ' : '') . '/m',
                '',
                $this->last_reply
            );
        } else {
            // Fall back to simple parsing if regex fails
            $code = substr($this->last_reply, 0, 3);
            $code_ex = null;
            $detail = substr($this->last_reply, 4);
        }
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);
        if (!in_array($code, (array) $expect)) {
            $this->setError(
                "$command command failed",
                $detail,
                $code,
                $code_ex
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply,
                self::DEBUG_CLIENT
            );
            return false;
        }
        $this->setError('');
        return true;
    }
    /**
     * Send an SMTP SAML command.
     * Starts a mail transaction from the email address specified in $from.
     * Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more recipient
     * commands may be called followed by a data command. This command
     * will send the message to the users terminal if they are logged
     * in and send them an email.
     * Implements RFC 821: SAML <SP> FROM:<reverse-path> <CRLF>.
     *
     * @param string $from The address the message is from
     *
     * @return bool
     */
    public function sendAndMail($from)
    {
        return $this->sendCommand('SAML', "SAML FROM:$from", 250);
    }
    /**
     * Send an SMTP VRFY command.
     *
     * @param string $name The name to verify
     *
     * @return bool
     */
    public function verify($name)
    {
        return $this->sendCommand('VRFY', "VRFY $name", [250, 251]);
    }
    /**
     * Send an SMTP NOOP command.
     * Used to keep keep-alives alive, doesn't actually do anything.
     *
     * @return bool
     */
    public function noop()
    {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }
    /**
     * Send an SMTP TURN command.
     * This is an optional command for SMTP that this class does not support.
     * This method is here to make the RFC821 Definition complete for this class
     * and _may_ be implemented in future.
     * Implements from RFC 821: TURN <CRLF>.
     *
     * @return bool
     */
    public function turn()
    {
        $this->setError('The SMTP TURN command is not implemented');
        $this->edebug('SMTP NOTICE: ' . $this->error['error'], self::DEBUG_CLIENT);
        return false;
    }
    /**
     * Send raw data to the server.
     *
     * @param string $data    The data to send
     * @param string $command Optionally, the command this is part of, used only for controlling debug output
     *
     * @return int|bool The number of bytes sent to the server or false on error
     */
    public function client_send($data, $command = '')
    {
        //If SMTP transcripts are left enabled, or debug output is posted online
        //it can leak credentials, so hide credentials in all but lowest level
        if (self::DEBUG_LOWLEVEL > $this->do_debug and
            in_array($command, ['User & Password', 'Username', 'Password'], true)) {
            $this->edebug('CLIENT -> SERVER: <credentials hidden>', self::DEBUG_CLIENT);
        } else {
            $this->edebug('CLIENT -> SERVER: ' . $data, self::DEBUG_CLIENT);
        }
        set_error_handler([$this, 'errorHandler']);
        $result = fwrite($this->smtp_conn, $data);
        restore_error_handler();
        return $result;
    }
    /**
     * Get the latest error.
     *
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }
    /**
     * Get SMTP extensions available on the server.
     *
     * @return array|null
     */
    public function getServerExtList()
    {
        return $this->server_caps;
    }
    /**
     * Get metadata about the SMTP server from its HELO/EHLO response.
     * The method works in three ways, dependent on argument value and current state:
     *   1. HELO/EHLO has not been sent - returns null and populates $this->error.
     *   2. HELO has been sent -
     *     $name == 'HELO': returns server name
     *     $name == 'EHLO': returns boolean false
     *     $name == any other string: returns null and populates $this->error
     *   3. EHLO has been sent -
     *     $name == 'HELO'|'EHLO': returns the server name
     *     $name == any other string: if extension $name exists, returns True
     *       or its options (e.g. AUTH mechanisms supported). Otherwise returns False.
     *
     * @param string $name Name of SMTP extension or 'HELO'|'EHLO'
     *
     * @return mixed
     */
    public function getServerExt($name)
    {
        if (!$this->server_caps) {
            $this->setError('No HELO/EHLO was sent');
            return;
        }
        if (!array_key_exists($name, $this->server_caps)) {
            if ('HELO' == $name) {
                return $this->server_caps['EHLO'];
            }
            if ('EHLO' == $name || array_key_exists('EHLO', $this->server_caps)) {
                return false;
            }
            $this->setError('HELO handshake was used; No information about server extensions available');
            return;
        }
        return $this->server_caps[$name];
    }
    /**
     * Get the last reply from the server.
     *
     * @return string
     */
    public function getLastReply()
    {
        return $this->last_reply;
    }
    /**
     * Read the SMTP server's response.
     * Either before eof or socket timeout occurs on the operation.
     * With SMTP we can tell if we have more lines to read if the
     * 4th character is '-' symbol. If it is a space then we don't
     * need to read anything else.
     *
     * @return string
     */
    protected function get_lines()
    {
        // If the connection is bad, give up straight away
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        $selR = [$this->smtp_conn];
        $selW = null;
        while (is_resource($this->smtp_conn) and !feof($this->smtp_conn)) {
            //Must pass vars in here as params are by reference
            if (!stream_select($selR, $selW, $selW, $this->Timelimit)) {
                $this->edebug(
                    'SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)',
                    self::DEBUG_LOWLEVEL
                );
                break;
            }
            //Deliberate noise suppression - errors are handled afterwards
            $str = @fgets($this->smtp_conn, 515);
            $this->edebug('SMTP INBOUND: "' . trim($str) . '"', self::DEBUG_LOWLEVEL);
            $data .= $str;
            // If response is only 3 chars (not valid, but RFC5321 S4.2 says it must be handled),
            // or 4th character is a space, we are done reading, break the loop,
            // string array access is a micro-optimisation over strlen
            if (!isset($str[3]) or (isset($str[3]) and $str[3] == ' ')) {
                break;
            }
            // Timed-out? Log and break
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                $this->edebug(
                    'SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)',
                    self::DEBUG_LOWLEVEL
                );
                break;
            }
            // Now check if reads took too long
            if ($endtime and time() > $endtime) {
                $this->edebug(
                    'SMTP -> get_lines(): timelimit reached (' .
                    $this->Timelimit . ' sec)',
                    self::DEBUG_LOWLEVEL
                );
                break;
            }
        }
        return $data;
    }
    /**
     * Enable or disable VERP address generation.
     *
     * @param bool $enabled
     */
    public function setVerp($enabled = false)
    {
        $this->do_verp = $enabled;
    }
    /**
     * Get VERP address generation mode.
     *
     * @return bool
     */
    public function getVerp()
    {
        return $this->do_verp;
    }
    /**
     * Set error messages and codes.
     *
     * @param string $message      The error message
     * @param string $detail       Further detail on the error
     * @param string $smtp_code    An associated SMTP error code
     * @param string $smtp_code_ex Extended SMTP code
     */
    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }
    /**
     * Set debug output method.
     *
     * @param string|callable $method The name of the mechanism to use for debugging output, or a callable to handle it
     */
    public function setDebugOutput($method = 'echo')
    {
        $this->Debugoutput = $method;
    }
    /**
     * Get debug output method.
     *
     * @return string
     */
    public function getDebugOutput()
    {
        return $this->Debugoutput;
    }
    /**
     * Set debug output level.
     *
     * @param int $level
     */
    public function setDebugLevel($level = 0)
    {
        $this->do_debug = $level;
    }
    /**
     * Get debug output level.
     *
     * @return int
     */
    public function getDebugLevel()
    {
        return $this->do_debug;
    }
    /**
     * Set SMTP timeout.
     *
     * @param int $timeout The timeout duration in seconds
     */
    public function setTimeout($timeout = 0)
    {
        $this->Timeout = $timeout;
    }
    /**
     * Get SMTP timeout.
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->Timeout;
    }
    /**
     * Reports an error number and string.
     *
     * @param int    $errno   The error number returned by PHP
     * @param string $errmsg  The error message returned by PHP
     * @param string $errfile The file the error occurred in
     * @param int    $errline The line number the error occurred on
     */
    protected function errorHandler($errno, $errmsg, $errfile = '', $errline = 0)
    {
        $notice = 'Connection failed.';
        $this->setError(
            $notice,
            $errmsg,
            (string) $errno
        );
        $this->edebug(
            "$notice Error #$errno: $errmsg [$errfile line $errline]",
            self::DEBUG_CONNECTION
        );
    }
    /**
     * Extract and return the ID of the last SMTP transaction based on
     * a list of patterns provided in SMTP::$smtp_transaction_id_patterns.
     * Relies on the host providing the ID in response to a DATA command.
     * If no reply has been received yet, it will return null.
     * If no pattern was matched, it will return false.
     *
     * @return bool|null|string
     */
    protected function recordLastTransactionID()
    {
        $reply = $this->getLastReply();
        if (empty($reply)) {
            $this->last_smtp_transaction_id = null;
        } else {
            $this->last_smtp_transaction_id = false;
            foreach ($this->smtp_transaction_id_patterns as $smtp_transaction_id_pattern) {
                if (preg_match($smtp_transaction_id_pattern, $reply, $matches)) {
                    $this->last_smtp_transaction_id = trim($matches[1]);
                    break;
                }
            }
        }
        return $this->last_smtp_transaction_id;
    }
    /**
     * Get the queue/transaction ID of the last SMTP transaction
     * If no reply has been received yet, it will return null.
     * If no pattern was matched, it will return false.
     *
     * @return bool|null|string
     *
     * @see recordLastTransactionID()
     */
    public function getLastTransactionID()
    {
        return $this->last_smtp_transaction_id;
    }
}
