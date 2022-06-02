<?php
use PHPMailer\PHPMailer\PHPMailer;

if(
    isset($_GET["exec"])                            &&
    $_GET["exec"] === "gmail"                       &&
    file_exists('../libs/gmail/queue/to.txt')       &&
    file_exists('../libs/gmail/queue/subject.txt')  &&
    file_exists('../libs/gmail/queue/message.txt')
){
    $to = json_decode(file_get_contents('../libs/gmail/queue/to.txt'));
    $subject = file_get_contents('../libs/gmail/queue/subject.txt');
    $message = file_get_contents('../libs/gmail/queue/message.txt');
    (new Mailer)->gmailSync($to, $subject, $message);
    // Clean up queue
    unlink('../libs/gmail/queue/to.txt');
    unlink('../libs/gmail/queue/subject.txt');
    unlink('../libs/gmail/queue/message.txt');

}

class Mailer {
	function __construct(){	}

    function gmail($to, $subject, $message){
        global $prod;
        if($prod) {
            // Live
            $this->gmailAsync($to, $subject, $message);
        } else {
            // Dev
            $message = '<p style="background-color: yellow; padding: 5px">To: '.print_r($to, true).'</p>'.$message;
            $to = DEV_EMAIL;
            $subject = "[DEV] ".$subject;
            $this->gmailSync($to, $subject, $message);
        }
    }

    function gmailAsync($to, $subject, $message){
        $noTries = 0;
        while (file_exists('libs/gmail/queue/to.txt') && $noTries < 5) {
            $noTries++;
            file_put_contents('libs/gmail/queue/notimes.txt', $noTries);
            sleep(2);
        }
        if ($noTries === 8) {
            $this->gmailSync(DEV_EMAIL, '⚠️ JoOS Error Report', 'Gmail Async Failed');
        }
        file_put_contents('libs/gmail/queue/to.txt', json_encode($to));
        file_put_contents('libs/gmail/queue/subject.txt', $subject);
        file_put_contents('libs/gmail/queue/message.txt', $message);
        exec('wget -qO- https://www.grbc.gr/joos-api/classes/mailer.php?exec=gmail &> /dev/null &');
    }

    function gmailSync($to, $subject, $message){
        // path to PHPMailer class
        if(file_exists("libs/gmail/PHPMailer.php")){
            require_once("libs/gmail/PHPMailer.php");
            require_once("libs/gmail/SMTP.php");
            require_once("libs/gmail/Exception.php");
        } else {
            require_once("../libs/gmail/PHPMailer.php");
            require_once("../libs/gmail/SMTP.php");
            require_once("../libs/gmail/Exception.php");
        }
        //Create a new PHPMailer instance
        $mail = new PHPMailer();
        $mail->CharSet = "UTF-8";
        // telling the class to use SMTP
        $mail->IsSMTP();
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        // $mail->SMTPDebug = 2;
        //Set the hostname of the mail server
        $mail->Host = 'smtp.gmail.com';
        // use
        // $mail->Host = gethostbyname('smtp.gmail.com');
        // if your network does not support SMTP over IPv6
        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $mail->Port = 587;
        //Set the encryption system to use - ssl (deprecated) or tls
        $mail->SMTPSecure = 'tls';
        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        //Username to use for SMTP authentication - use full email address for gmail
        $mail->Username = MAIL_SERVER_USERNAME;
        //Password to use for SMTP authentication
        $mail->Password = MAIL_SERVER_PASSWORD;
        // --- Workaround BEGIN
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        // --- Workaround END
        //Set who the message is to be sent from
        $mail->setFrom(MAIL_SERVER_USERNAME, 'JoOS - GrBC');
        //Set an alternative reply-to address
        // $mail->addReplyTo(MAIL_SERVER_USERNAME, 'JoOS');
        //Set who the message is to be sent to
        // $mail->addAddress(DEV_EMAIL, 'Developer');
        //Set the subject line
        $mail->Subject = $subject;
        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        $mail->msgHTML($message, __DIR__);
        $mail->ClearAllRecipients();
        //Set who the message is to be sent to
        // $mail->addAddress(DEV_EMAIL, 'Developer');
        if(is_array($to)) {
            foreach($to as $emailAddress) {
                $mail->addBCC($emailAddress);
            }
        } else {
            $mail->AddAddress($to);
        }
        if(!$mail->Send()) { //couldn't send return false;
            return false;
        } else { //successfully sent return true;
            return true;
        }
    }
}