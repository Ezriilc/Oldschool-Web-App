<?php
if(
    pathinfo($_SERVER['PHP_SELF'])['filename'] === pathinfo(__FILE__)['filename']
    && pathinfo($_SERVER['PHP_SELF'])['extension'] === pathinfo(__FILE__)['extension']
){
    header('Location: ./');
    die('No direct access.');
}

$contact = new CONTACT('admin@localhost');
return $contact->output;

CLASS Contact{
    function my_specialchars($in_out){
        $flags = defined('ENT_HTML5') ? ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5 : ENT_NOQUOTES;
        return htmlspecialchars($in_out, $flags, 'UTF-8', false);
    }
    function __construct(
        $to_email = null
        ,$to_name = 'Site Admin'
        ,$antibot_quest = 'What is the name of this website?'
        ,$antibot_key_1 = null
        ,$antibot_key_2 = null
    ){
        $to_email = $to_email ? : 'Contact@'.$_SERVER['HTTP_HOST'];
        $antibot_key_1 = $antibot_key_1 ? : $_SERVER['HTTP_HOST'];
        
        $success = '<div style="background-color:green; color:white; padding:1em;"><h3 style="text-align:center;">Thank you for your message.</h3>
                <p style="text-align:center;">If necessary, we\'ll get back to you as soon as we can.</p></div>';
        $error_prefix = '
            <div style="background-color:red; color:black; padding:1em;"><h3>OOPS! Your message could not be sent.</h3>';
        $badbot = '
            <p>Your answer to the Anti-Spam Test Question is incorrect.</p>';
        $badcommand = '
            <p>You did everything right, but the server wouldn\'t cooperate.</p>';
        $error_suffix = '<p>Please try again. If you continue to have problems, please contact us a different way.</p></div>';
        
        // Begin processing.
        $form = '';
        $status = '';
        $form_name = '';
        $form_email = '';

        // CHECK submission.
        if(isset($_POST['antibot'], $_POST['name'], $_POST['email'], $_POST['subject'], $_POST['message'])){
            $form_posted = true;
            $form_name = $this->my_specialchars($_POST['name']);
            $form_email = $_POST['email'];
            $form_subject = $this->my_specialchars($_POST['subject']);
            $form_message = $this->my_specialchars($_POST['message']);
            $form_antibot_answer = $this->my_specialchars($_POST['antibot']);
            if( // Check anti-spam.
                stristr($form_antibot_answer,$antibot_key_1)
                || stristr($form_antibot_answer,@$antibot_key_2)
            ){ // Form is valid
                $form_valid = true;
                // Mail format : Updated 2014-04-07 @ 14:51, works on Windows AND Linux.
                $mail_url = $_SERVER['HTTP_HOST'].preg_replace('/\?.*/i','',$_SERVER['REQUEST_URI']);
                $mail_site = strtoupper(preg_replace('/^www\./i','',$mail_url));
                $mail_site = preg_replace('/\/*$/i','',$mail_site);
                $mail_eol = "\r\n"; // Good for both.
                $mail_from = $form_name.' <'.$form_email.'>';
                $mail_to = $to_name.' <'.$to_email.'>';
                $mail_subject = $mail_site.': '.$form_subject;
                $mail_boundary = 'MultiPartBoundary_'.uniqid('UID_',true);
                $mail_attachments = array();
                if( count($_FILES) ){
                    foreach( $_FILES as $file ){
                        if( empty($file['tmp_name']) ){ continue; }
                        array_push($mail_attachments,
                            '--'.$mail_boundary
                            ,'Content-Type: application/octet-stream; name="'.$file['name'].'"'
                            ,'Content-Transfer-Encoding: base64'
                            ,'Content-Disposition: attachment; filename="'.$file['name'].'"'
                            ,$mail_eol
                            ,chunk_split(base64_encode(file_get_contents($file['tmp_name'])))
                        );
                    }
                }
                $mail_headers = array(
                    'From: '.$mail_from
                    ,'Reply-To: '.$mail_from
                    ,'Return-Path: '.$mail_from
                    ,'X-Identified-User: '.$mail_from
                    ,'X-Originating-IP: '.$_SERVER['REMOTE_ADDR']
                    ,'MIME-Version: 1.0'
                    ,'Content-Type: multipart/mixed; boundary="'.$mail_boundary.'"'
                );
                $mail_message = array(
                    'This is a multi-part message in MIME format.'
                    ,'--'.$mail_boundary
                    ,'Content-type: text/plain; charset=utf-8'
                    ,$mail_eol
                    ,$form_message
                    ,'--'.$mail_boundary
                    ,'Content-type: text/html; charset=utf-8'
                    ,$mail_eol
                    ,'<html><body><div><small>'
                    ,'Originator: <a title="Lookup on DomainTools.com" href="http://whois.domaintools.com/'.
                    $_SERVER['REMOTE_ADDR'].'">'.$_SERVER['REMOTE_ADDR'].'</a><br/>'
                    ,'Page: <a href="http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'</a>'
                    ,'</small></div></body></html>'
                );
                $mail_message = array_merge($mail_message, $mail_attachments);
                array_push($mail_message,'--'.$mail_boundary.'--');
                
                $mail_success = mail(
                    $mail_to
                    ,$mail_subject
                    ,implode($mail_eol,$mail_message) // Windows SMTP requires non-NULL message.
                    ,implode($mail_eol,$mail_headers) // Linux sendmail can have message in headers.
                );
                
                if( $mail_success ){
                    $status .= $success;
                }else{
                    $status .=  $error_prefix .$badcommand .$error_suffix;
                }
            }else{ // Form is invalid
                $form_valid = false;
                $status .= $error_prefix. $badbot. $error_suffix;
            }
        }else{
            $form_posted = false;
            if( !empty($_SESSION['user']) ){
                $form_name = $_SESSION['user']['username'];
                $form_email = $_SESSION['user']['email'];
            }
            $form_subject = '';
            $form_message = '';
            $form_antibot_answer = @$_SESSION['logged_in'] ? @$_SERVER['HTTP_HOST'] : '';
        }

        // Render the page.
        if(!$form_posted OR !$form_valid OR !$mail_success){
            $form .= '
            <script type="text/javascript"><!--//
                function validateForm( frm ) {
                    nameRegex = /^[\w\.-]{2,}$/;
                    emailRegex = /^\w[\w\.-]*@([\w\.-]+\.)+[\w]{2,7}$/;
                    alertText = "";
                    
                    if (frm.name.value =="") {
                        alertText += "Your name is missing.\r\n";
                    }
                    if (frm.email.value =="") {
                        alertText += "Your email address is missing.\r\n";
                    }
                    if (typeof(frm.email) != "undefined" && frm.email.value != "" && !emailRegex.test(
                        frm.email.value)) { alertText += "Your e-mail address is not valid.\r\n";
                    }
                    if (frm.subject.value =="") {
                        alertText += "Your subject line is missing.\r\n";
                    }
                    if (frm.message.value =="") {
                        alertText += "Your message is missing.\r\n";
                    }
                    if (frm.antibot.value =="") {
                        alertText += "Your answer to the anti-spam question is missing.\r\n";
                    }
                    if (alertText != "") {
                        alert("ERROR!\r\n\r\n"+alertText+"\r\nPlease correct these errors and try again.");
                        return false;
                    }
                    else{ return true; }
                }
            //--></script>
            <form enctype="multipart/form-data" method="post" style="text-align:left;" class="contact_display" onsubmit="return validateForm(this);"><fieldset>
                <div style="position:relative;left:-2px;top:-4px;"><small><strong>Email Form</strong> - <em>Fields with * are required.</em></small></div>
                <div style="clear:both;"></div>
                <div style="clear:both;">
                    <label for="name" style="display:inline-block;min-width:6em; float:left;">Your name: *</label>
                    <input type="text" id="name" name="name" value="'.$form_name.'" maxlength="50" style="width:33%;"/>
                </div>
                <div style="clear:both;"></div>
                <div style="clear:both;">
                    <label for="email" style="display:inline-block;min-width:6em; float:left;">Your email: *</label>
                    <input type="text" id="email" name="email" value="'.$form_email.'" maxlength="50" style="width:33%;"/>
                </div>
                <div style="clear:both;"></div>
                <div style="clear:both;">
                    <label for="subject" style="display:inline-block;min-width:6em; float:left;">Subject: *</label>
                    <input type="text" id="subject" name="subject" value="'.$form_subject.'" maxlength="100" style="width:66%;"/>
                </div>
                <div style="clear:both;"></div>
                <div style="clear:both;">
                    <label for="message" style="clear:both;">Message: *</label>
                    <textarea id="message" name="message" cols="40" rows="10" style="width:99%;height:10em;">'.$form_message.'</textarea>
                </div>
                <div style="clear:both;"></div>
                <div style="clear:both;">
                    <label for="contact_file">Attach a file (Optional, 10 MB max)</label>
                    <input type="hidden" name="MAX_FILE_SIZE" value="10480000" />
                    <input type="file" name="contact_file" id="contact_file" style="width:97%;"/>
                </div>
                <div style="clear:both;"></div>
                <hr/>
                <div style="clear:both;">
                    <label for="antibot" style="display:block;clear:both;">'.$antibot_quest.'</label>
                    <div style="clear:both;"></div>
                    <input type="text" id="antibot" name="antibot" value="'.$form_antibot_answer.'" maxlength="100" style="width:66%;" />
                </div>
                <div style="clear:both;"></div>
                <hr/>
                <div>
                    <input type="submit" id="submit_button" name="submit" value="Send" />
                </div>
                <div style="clear:both;"></div>
            </fieldset></form>
        ';
        }
        $this->output = $status.$form;
    }
}
?>
