<?php

new USER;
//var_dump(@$_SESSION);
return USER::$output;

class USER{
    static
    $db_file = './_gitignore/webapp.sqlite3'
    ,$users_table = 'users'
    ,$cookies_table = 'cookies'
    ,$visitors_table = 'visitors'
    ,$from_email = 'admin@locahost' // Valid email required by most servers.
    ,$bcc_from = false // BCC $from_email in all messages.
    ,$pepper = "wouldn't you like to be one too?" // Change this!
    ,$password_min = 8
    ,$strict_passwords = false // ''. Force numbers & special chars.
    ,$strict_pass_chars = '`~!@#$%^&*()_-+={}[]\|:;"<>,.?'
    ,$allow_cookies = true // Allow persistent login.
    ,$strict_cookies = false // Tie cookies to IP and user agent;
    ,$rate_limit_delay = 3 // Minimum seconds between post attempts for each IP.
    ,$encrypt = '$2y$10$' // Encryption $method$rounds$ (new Blowfish x10 = $2y$10$).
    ,$session_ttl = 3600 // Max seconds to live between requests.
    ,$cookie_days = 30
    ,$cookie_path = '/'
    ,$bad_session = '<p class="error message">Session cookies are required.</p>'
    ,$no_cookie = '<p class="warn message">You\'re logged in for this session only.</p>'
    ,$bad_db = '<p class="error message">Database failure. Please try again.</p>'
    ,$bad_input = '<p class="error message">Incorrect or improper input.</p>'
    ,$bad_username = '<p class="error message">Sorry, that username is not allowed.</p>'
    ,$bad_password = '<p class="error message">Sorry, that password is not allowed.</p>'
    ,$bad_email = '<p class="error message">Sorry, that email is not allowed.</p>'
    ,$good_registration = '<p class="success message">Registration successful. You may now login.</p>'
    ,$good_recovery = '<p class="success message">Your password has been updated.</p>'
    ,$dbcnnx = null
    ,$output = null
    ,$clean_url = null
    ,$mail_site = null
    ,$cookie_ttl = null
    ;
    
    function __construct(){
        if( empty(static::$cookie_ttl) ){
            // First run, setup static vals.
            static::$cookie_ttl = 60*60*24 * static::$cookie_days;
            static::$clean_url = $_SERVER['HTTP_HOST'].preg_replace('/\?.*/i','',$_SERVER['REQUEST_URI']);
            static::$mail_site = strtoupper(preg_replace('/^(www\.)?(.*)\/[^\/]*$/i','$2',static::$clean_url));
            if( !empty($_GET['page']) ){
                static::$clean_url .= '?page='.$_GET['page'];
            }
            static::$pepper = md5(sha1(static::$pepper));
            if( static::$strict_passwords ){
                static::$bad_password .= '<p class="error message">Passwords must be at least '.static::$password_min.' characters long and include at least one each of the following: uppercase and lowercase letters, numbers, and special characters [ '.static::$strict_pass_chars.'/\' ].</p>';
            }else{
                static::$bad_password .= '<p class="error message">Passwords must be at least '.static::$password_min.' characters long.</p>';
            }
        }
        
        // Make sure session cookie is set.
        $this->my_session_start();
        if( empty($_SESSION['crispy']) ){ // Session has no cookie.
            static::$output .= '<p>No session cookie found.</p>';
            return;
        }
        
        if( ! $this->init_database() ){
            return;
        }
        
        // Check for $_POST.
        if( !empty($_POST['user_task']) ){
            $_SESSION['POST'] = array();
            
            // Rate limiting...
            $time = time();
            if(
                $stmt = static::$dbcnnx->prepare("
SELECT * FROM ".static::$visitors_table."
WHERE ip=:ip
")
                AND $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR)
                AND $stmt->execute()
                AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
            ){
                // IP is logged.
                $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$visitors_table." SET time=:time
WHERE ip=:ip
");
                if(
                    ! empty($result['time'])
                    AND $result['time'] <= $time - static::$rate_limit_delay
                ){
                    // NOT too soon.
                }else{
                    // Too soon.
                    $rate_exeeded = true;
                }
            }else{
                // IP is new.
                $stmt = static::$dbcnnx->prepare("
INSERT INTO ".static::$visitors_table." (ip,time)
VALUES (:ip,:time)
");
            }
            if(
                ! empty($stmt)
                AND $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR)
                AND $stmt->bindValue(':time', $time, PDO::PARAM_INT)
                AND $stmt->execute()
            ){
                // IP updated
                $updated_visitors = true;
            }else{
                static::$output .= 'Updating visitor: '.static::$bad_db;
                return;
            }
            if( ! empty($rate_exeeded) ){
                http_response_code(403);
                exit('We\'re sorry, but that isn\'t allowed right now.  Please wait a moment before trying again.');
                return;
            }
            
            foreach($_POST as $key => $val){
                // Basic $_POST scrubbing.
                if(
                    strlen($key)<30
                    AND strlen($val)<255
                    AND ! preg_match('/[\W]/i',$key)
                ){
                    $_SESSION['POST'][$key] = $val;
                }
            }
            $this->reload_url(); // die()!
        }
        
        // Check for in-process work.
        if( !empty($_SESSION['POST']['user_task']) ){
            switch( $_SESSION['POST']['user_task'] ){
                case 'login':
                case 'logout':
                    static::$output .= $this->login();
                break;
                case 'recover':
                    static::$output .= $this->recover();
                break;
                case 'register':
                    static::$output .= $this->register();
                break;
            }
            return;
        }
        
        // Check for login cookie.
        if(
            empty($_SESSION['logged_in'])
            AND count($_COOKIE) > 1
            AND static::$allow_cookies
        ){
            static::$output .= $this->cookie_login();
        }
        
        static::$output .= $this->login();
        if( empty($_SESSION['logged_in']) ){
            static::$output .= $this->recover();
            static::$output .= $this->register();
        }
        
        if( !empty(static::$dbcnnx) ){
            static::$dbcnnx = null;
        }
        session_write_close();
        
        // END of __construct().
    }
    
    private function init_database(){
        if(
            ! is_writable(static::$db_file)
            OR ! is_writable(dirname(static::$db_file))
        ){
            static::$output .= '<p class="error message">'.get_called_class().': Bad DB file/path.</p>';
            return false; // Comment this line to create a new database file and tables.
        }
        try{
            static::$dbcnnx = new PDO('sqlite:'.static::$db_file);
        }
        catch( PDOException $Exception ){
            static::$output .= '<p class="error message">Connect:</p>'.static::$bad_db;
            return false;
        }
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT name FROM sqlite_master
WHERE type='table' AND name='".static::$users_table."';
")
            AND $stmt->execute()
            AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            $table_exists = true;
        }else{
            $stmt_string = "
CREATE TABLE ".static::$users_table."(
id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT
,username TEXT UNIQUE NOT NULL
,email TEXT UNIQUE NOT NULL
,rank INTEGER NOT NULL DEFAULT 1
,joined INTEGER NOT NULL DEFAULT (strftime('%s','now'))
,visited INTEGER
,logged INTEGER
,password TEXT
,question INTEGER
,answer TEXT
);
";
            if(
                $stmt = static::$dbcnnx->prepare($stmt_string)
                AND $stmt->execute()
            ){
                $table_created = true;
                static::$output .= '<p class="warn message">Users table created.</p>';
            }else{
                static::$output .= '<p class="error message">Users table creation:</p> '.static::$bad_db;
                return false;
            }
            $stmt_string = "
INSERT INTO ".static::$users_table." (id, username, email, rank)
VALUES (0, 'Admin', '', 99);
";
            if(
                $stmt = static::$dbcnnx->prepare($stmt_string)
                AND $stmt->execute()
            ){
                $table_created = true;
                static::$output .= '<p class="warn message">Admin user created.</p>';
            }else{
                static::$output .= '<p class="error message">Admin user creation:</p> '.static::$bad_db;
                return false;
            }
            $stmt_string = "
CREATE TABLE ".static::$cookies_table."(
id INTEGER NOT NULL PRIMARY KEY
,batch TEXT UNIQUE NOT NULL
,token TEXT
);
";
            if(
                $stmt = static::$dbcnnx->prepare($stmt_string)
                AND $stmt->execute()
            ){
                $table_created = true;
                static::$output .= '<p class="warn message">Cookies table created.</p>';
            }else{
                static::$output .= '<p class="error message">Cookies table creation:</p> '.static::$bad_db;
                return false;
            }
            $stmt_string = "
CREATE TABLE ".static::$visitors_table."(
ip TEXT NOT NULL
,time INTEGER NOT NULL
);
";
            if(
                $stmt = static::$dbcnnx->prepare($stmt_string)
                AND $stmt->execute()
            ){
                $table_created = true;
                static::$output .= '<p class="warn message">Visitors table created.</p>';
            }else{
                static::$output .= '<p class="error message">Visitors table creation:</p> '.static::$bad_db;
                return false;
            }
        }
        return true;
    }
    
    private function my_session_start(){
        $time = time();
        @session_start();
        // Ensure cookie is set.
        if(SID){ // No session cookie... maybe never!
            if( empty($_GET['bad_session']) ){ // First hit ONLY.
                $url = 'http://'.static::$clean_url;
                if( empty($_GET['page']) ){
                    $url .= '?';
                }else{
                    $url .= '&';
                }
                $url .= 'bad_session=gimmie_cookie';
                @header('Location: '.$url);
            }else{
                $_SESSION['message'] = static::$bad_session;
            }
            return;
        }elseif( isset($_GET['bad_session']) ){
            $this->reload_url();
            return;
        }
        if( !empty($_COOKIE['PHPSESSID']) AND empty($_SESSION['crispy']) ){
            $_SESSION['crispy'] = true;
            unset($_SESSION['message']);
        }
        // Kill inactive sessions.
        if(
            isset($_SESSION['last_access'])
            AND ($time - $_SESSION['last_access']) > static::$session_ttl
        ){
            session_unset();
            session_destroy();
            $this->reload_url();
        }
        $_SESSION['last_access'] = $time;
        // Regenerate old session ids.
        if( empty($_SESSION['born']) ){
            $_SESSION['born'] = $time;
        }elseif( ($time - $_SESSION['born']) > static::$session_ttl ){
            session_regenerate_id(true);
            $_SESSION['born'] = $time;
        }
    }
    private function reload_url(){
        if( !empty(static::$dbcnnx) ){
            static::$dbcnnx = null;
        }
        session_write_close();
        @header('Location: http://'.static::$clean_url);
        exit('<p><a title="Click to continue." href="http://'.static::$clean_url.'">Click here to continue.</a></p>');
    }
    private function rand_salt($length=22){
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $size = strlen($chars);
        $salt='';while($length--){
            $salt .= $chars[rand(0,$size-1)];
        }
        return $salt;
    }
    private function my_hash($inout,$i=9){
        while($i--){
            $inout = sha1(md5($inout));
        }
        return $inout;
    }
    private function do_crypt($string){
        $string = static::$pepper.$string;
        $string = crypt( $string, static::$encrypt.$this->rand_salt() );
        $string = preg_replace('/^'.preg_quote(static::$encrypt,'/').'/i','',$string);
        return $string;
    }
    private function test_crypt($submitted,$stored){
        $submitted = static::$pepper.$submitted;
        return (crypt($submitted,static::$encrypt.$stored) === static::$encrypt.$stored);
    }
    private function remove_cookies(){
        $cookies = explode('; ',$_SERVER['HTTP_COOKIE']);
        foreach($cookies as $key => $cookie){
            $cookie = explode('=',$cookie);
            $cookie_name = $cookie[0];
            $cookie_content = $cookie[1];
            if( $cookie_name === session_name() ){ continue; }
            @setrawcookie( $cookie_name, '', 1, static::$cookie_path );
            @setrawcookie( $cookie_name, '', 1, '/' );
            @setrawcookie( $cookie_name, '', 1 );
        }
    }
    private function send_email(
        $to=null
        ,$subject=null
        ,$message=null
    ){
        if(!$to OR !$subject OR !$message){return false;}
        $from = static::$from_email;
        $eol = "\r\n";
        $boundary = 'MultiPartBoundary_'.uniqid('UID_',true);
        $to = $to.' <'.$to.'>';
        $subject = static::$mail_site.' : '.$subject;
        $message = array(
            'This is a multi-part message in MIME format.'
            ,'--'.$boundary
            ,'Content-type: text/plain; charset=utf-8'
            ,$eol
            ,$message
            ,'--'.$boundary
            ,'Content-type: text/html; charset=utf-8'
            ,$eol
            ,'<html><body><div><small>'
            ,'Originator: <a title="Lookup on DomainTools.com" href="http://whois.domaintools.com/'.
            $_SERVER['REMOTE_ADDR'].'">'.$_SERVER['REMOTE_ADDR'].'</a><br/>'
            ,'Page: <a href="http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'</a><br/>'
            ,'Server Time: '.date('r').'<br/>'
            ,'</small></div></body></html>'
            ,'--'.$boundary.'--'
        );
        $headers = array(
            'From: '.$from
            ,'Reply-To: '.$from
            ,'Return-Path: '.$from
            ,'X-Originating-IP: '.$_SERVER['REMOTE_ADDR']
            ,'MIME-Version: 1.0'
            ,'Content-Type: multipart/mixed; boundary="'.$boundary.'"'
        );
        if( ! empty(static::$bcc_from) ){
            array_push($headers,'BCC: '.static::$from_email);
        }
        $success = @mail(
            $to
            ,$subject
            ,implode($eol,$message)
            ,implode($eol,$headers)
        );
        return $success;
    }
    
    
    
    private function login(){
        $message = '';
        $form = '';
        if(
            !empty($_SESSION['POST']['user_task'])
            AND (
                $_SESSION['POST']['user_task'] === 'login'
                OR $_SESSION['POST']['user_task'] === 'logout'
            )
        ){
            // Form HAS been posted.
            $post = $_SESSION['POST'];
            unset($_SESSION['POST']);
            if(
                !empty($_SESSION['logged_in'])
                AND $post['user_task'] === 'logout'
            ){
                // Logout button clicked.
                // Nullify cookie in db.
                if(
                    !empty($_SESSION['user']['id'])
                    AND $id = $_SESSION['user']['id']
                    AND $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$cookies_table." SET token=NULL
WHERE id=:id
")
                    AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
                    AND $stmt->execute()
                    AND $result = $stmt->rowCount()
                ){
                    $cookie_nulled = true;
                }else{
                    static::$output .= 'Failed to nullify cookie. '.static::$bad_db;
                }
                unset($stmt,$result);
                
                // Logout (kill session).
                $_SESSION = array();
                
                // Kill all HUMANS! - I mean non-session cookies.
                $this->remove_cookies();
                
                $this->reload_url();
                return '<p>Cookies removed. Please reload the page.</p>'; // Message not normally seen.
            }
            
            // Login.
            if( empty($post['username']) OR empty($post['password']) ){
                return static::$bad_input;
            }else{
                // Lookup user.
                if(
                    $stmt = static::$dbcnnx->prepare("
SELECT * FROM ".static::$users_table."
WHERE username=:username
LIMIT 1
")
                    AND $stmt->bindValue(':username', $post['username'], PDO::PARAM_STR)
                    AND $stmt->execute()
                    AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
                ){
                    if(
                        !empty($result['password'])
                        AND $this->test_crypt($post['password'], $result['password'])
                    ){
                        unset($result['password'],$result['answer']);
                        $user_row = $result;
                    }else{
                        // Username exists, but password doesn't match.
                        @include('user_migrate.php');
                    }
                }
                unset($stmt,$result);
                if( empty($user_row) ){
                    // User NOT found.
                    $_SESSION = array();
                    return static::$bad_input;
                }
                    
                // Good login:
                
                // Update users table.
                $time = time();
                $visited = $time;
                $logged = $time;
                if(
                    !empty($user_row['id'])
                    AND $id = $user_row['id']
                    AND $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$users_table." SET visited=:visited, logged=:logged
WHERE id=:id
")
                    AND $stmt->bindValue(':visited', $visited, PDO::PARAM_STR)
                    AND $stmt->bindValue(':logged', $logged, PDO::PARAM_STR)
                    AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
                    AND $stmt->execute()
                ){
                    $updated_users = true;
                }else{
                    return 'User update: '.static::$bad_db;
                }
                unset($stmt);
                
                $_SESSION['logged_in'] = true;
                $_SESSION['login_method'] = 'login';
                $_SESSION['user'] = $user_row;
                @$_SESSION['message'] .= '
<p class="success message">Welcome back, '.$_SESSION['user']['username'].'.</p>
';
                $_SESSION['user']['visited'] = $time;
                $_SESSION['user']['logged'] = $time;
                
                // Kill all HUMANS! - I mean non-session cookies.
                $this->remove_cookies();
                
                if(
                    !empty($post['rememberme'])
                    AND static::$allow_cookies
                ){
                    // Update cookies table.
                    if(
                        !empty($updated_users)
                        AND !empty($user_row['id'])
                        AND $id = $user_row['id']
                        AND $stmt = static::$dbcnnx->prepare("
SELECT * FROM ".static::$cookies_table."
WHERE id=:id
LIMIT 1
")
                        AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
                        AND $stmt->execute()
                        AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
                    ){
                        $cookie_exists = true;
                    }
                    unset($stmt,$result);
                    
                    if( !empty($cookie_exists) ){
                        $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$cookies_table." SET batch=:batch, token=:token
WHERE id=:id
");
                    }else{
                        $stmt = static::$dbcnnx->prepare("
INSERT INTO ".static::$cookies_table." (id, batch, token)
VALUES (:id, :batch, :token)
");
                    }
                    $batch = $this->rand_salt(40);
                    $token = $this->rand_salt(64);
                    $db_batch = $batch;
                    $db_token = $token;
                    if( static::$strict_cookies ){
                        $db_batch .= $_SERVER['REMOTE_ADDR'];
                        $db_token .= $_SERVER['HTTP_USER_AGENT'];
                    }
                    $db_batch = $this->my_hash($db_batch);
                    $db_token = $this->my_hash($db_token);
                    if(
                        $stmt
                        AND !empty($user_row['id'])
                        AND $id = $user_row['id']
                        AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
                        AND $stmt->bindValue(':batch', $db_batch, PDO::PARAM_STR)
                        AND $stmt->bindValue(':token', $db_token, PDO::PARAM_STR)
                        AND $stmt->execute()
                    ){
                        $updated_cookies = true;
                    }else{
                        $_SESSION = array();
                        return 'Can\'t update cookies. '.static::$bad_db;
                    }
                    // Bake new cookie.
                    $cookie_name = $batch;
                    $cookie_content = $token;
                    
                    if( ! @setrawcookie(
                        $cookie_name
                        ,$cookie_content
                        ,time() + static::$cookie_ttl
                        ,static::$cookie_path
                        ,null,null,true
                    ) ){
                        $message = 'Cookie error! '.static::$no_cookie;
                    }else{
                        $this->reload_url();
                    }
                }else{
                    $message = static::$no_cookie;
                }
            }
            // END of form posted.
        }else{
        
            $form = '
<form id="login_form" name="login_form" class="login user" method="post" autocomplete="on"><fieldset>
';
            if( empty($_SESSION['logged_in']) ){
                // NOT logged in.
                $form .= '
    <h3>Login</h3>
    <p>'.$message.'</p>
    <span style="display:inline-block;">
        <label for="username">Username:</label><br/>
        <input type="text" id="username" name="username" size="20" maxlength="20"/>
    </span><br/>
    <span style="display:inline-block;">
        <label for="password">Password:</label><br/>
        <input type="password" id="password" name="password" size="40"/>
    </span><br/>
    <span style="display:inline-block;">';
                if( static::$allow_cookies ){
                    $form .= '<label for="rememberme">Remember Me:</label>
        <input type="checkbox" id="rememberme" name="rememberme" checked="checked"/><br/>';
                }
                $form .= '<input type="hidden" name="user_task" value="login"/>
        <input type="submit" name="submit" value="Login"/>
    </span>
';
            }else{
                // Logged in.
                $form .= '
    <h3>Logout</h3>
    <p>'.$message.'</p>
    <input type="hidden" name="user_task" value="logout"/>
    <input type="submit" name="submit" value="Logout"/>
';
            }
            $form .= '
</fieldset></form>
';
        }
        return $form;
        // END of login().
    }
    
    
    
    private function cookie_login(){
        if(
            !empty($_SESSION['logged_in'])
            OR count($_COOKIE)<2
        ){ return; }
        
        // Lookup all cookies.
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT * FROM ".static::$cookies_table."
WHERE id>0
")
            AND $stmt->execute()
            AND $result = $stmt->fetchAll(PDO::FETCH_ASSOC)
        ){
            $cookie_rows = $result;
        }else{
            return;
        }
        unset($stmt,$result);
        
        $cookies = explode('; ',$_SERVER['HTTP_COOKIE']);
        
        $cookie_limit = 9;
        foreach($cookies as $cookie){
            if( !$cookie_limit ){ continue; }
            $cookie_limit--;
            $cookie = explode('=',$cookie);
            if( $cookie[0] === session_name() ){ continue; }
            $cookie_name = $cookie[0];
            $cookie_content = $cookie[1];
            if( static::$strict_cookies ){
                $cookie_name .= $_SERVER['REMOTE_ADDR'];
                $cookie_content .= $_SERVER['HTTP_USER_AGENT'];
            }
            $cookie_name = $this->my_hash($cookie_name);
            $cookie_content = $this->my_hash($cookie_content);
            // Compare cookie to each row.
            foreach($cookie_rows as $row){
                if( empty($row) ){ continue; }
                if( $row['batch'] === $cookie_name ){
                    if( $row['token'] === $cookie_content ){
                        $cookie_row = $row;
                        $cookie_row['name'] = $cookie[0];
                        break 2;
                    }else{
// Stolen cookie?
                    }
                }
            }unset($row);
        }
        if( empty($cookie_row['id']) ){ return; }
        
        // Login cookie found!
        
        // Lookup user.
        $id = $cookie_row['id'];
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT * FROM ".static::$users_table."
WHERE id=:id
LIMIT 1
")
            AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
            AND $stmt->execute()
            AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            unset($result['password'],$result['answer']);
            $user_row = $result;
        }
        
        $time = time();
        
        if( empty($user_row['id']) ){
            return;
        }elseif( $user_row['logged']+0 < ($time-static::$cookie_ttl) ){
            // Expired cookie.
            $this->remove_cookies();
            return;
        }
        unset($stmt,$result);
        
        // Update users table.
        $visited = $time;
        if(
            !empty($user_row['id'])
            AND $id = $user_row['id']
            AND $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$users_table." SET visited=:visited
WHERE id=:id
")
            AND $stmt->bindValue(':visited', $visited, PDO::PARAM_STR)
            AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
            AND $stmt->execute()
        ){
            $user_updated = true;
        }else{
            return 'Users table update: '.static::$bad_db;
        }
        $_SESSION['logged_in'] = true;
        $_SESSION['login_method'] = 'cookie';
        $_SESSION['user'] = $user_row;
        $_SESSION['user']['visited'] = $time;
        $_SESSION['message'] = '
<span class="success message">Welcome back, '.$_SESSION['user']['username'].'.</span>';
        
        // Update cookies table.
        $token = $this->rand_salt(64);
        $db_token = $token;
        if( static::$strict_cookies ){
            $db_token .= $_SERVER['HTTP_USER_AGENT'];
        }
        $db_token = $this->my_hash($db_token);
        if(
            $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$cookies_table." SET token=:token
WHERE id=:id
")
            AND $stmt->bindValue(':token', $db_token, PDO::PARAM_STR)
            AND $stmt->bindValue(':id', $id, PDO::PARAM_INT)
            AND $stmt->execute()
        ){
            $cookies_table_updated = true;
        }else{
            return 'Cookies table update: '.static::$bad_db;
        }
        
        // Bake new cookie.
        $cookie_name = $cookie_row['name'];
        $cookie_content = $token;
        if( ! @setrawcookie(
            $cookie_name
            ,$cookie_content
            ,time() + static::$cookie_ttl
            ,static::$cookie_path
            ,null,null,true
        ) ){
            return 'Cookie send failure: '.static::$no_cookie;
        }
        
        $this->reload_url();
        // END of cookie_login().
    }
    
    
    
    private function recover(){
        if( !empty($_SESSION['logged_in']) ){ return; }
        if(
            !empty($_SESSION['POST']['user_task'])
            AND $_SESSION['POST']['user_task'] === 'recover'
        ){
            $post = $_SESSION['POST'];
            unset($_SESSION['POST']);
        }
        if( !empty($_SESSION['recover']['step']) AND !empty($post) ){
            $step = $_SESSION['recover']['step'];
        }else{
            $step = 0;
        }
        if( empty($post) ){
            unset($_SESSION['recover']);
            $form = '
<form id="recover_form" name="recover_form" class="recover user" method="post"><fieldset>
    <h3>Recover</h3>
    <span style="display:inline-block;">
        <label for="username">Username:</label><br/>
        <input type="text" id="username" name="username" size="20" maxlength="20"/>
    </span><br/>
    <span style="display:inline-block;">
        <label for="email">Email:</label><br/>
        <input type="text" id="email" name="email" size="30" maxlength="255"/>
    </span><br/>
    <input type="hidden" name="user_task" value="recover"/>
    <input type="submit" name="submit" value="Recover"/>
</fieldset></form>
';
        }else{
            switch( $step ){
                case 0: // Username AND Email address were entered.
                    if( empty($post['username']) OR empty($post['email']) ){
                        unset($_SESSION['recover']);
                        $form = static::$bad_input;
                        break;
                    }
                    $_SESSION['recover'] = array();
                    $_SESSION['recover']['step'] = 1;
                    $form = '
<form id="recover_form" name="recover_form" class="recover user" method="post"><fieldset>
    <h3>Enter your token:</h3>
    <p>Step 2 of 4</p>
    <p>An email with your token should arrive in a few minutes.</p>
    <span style="display:inline-block;">
        <label for="token">Enter the token that was emailed to you:</label><br/>
        <input type="text" id="token" name="token" size="40" maxlength="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="recover"/>
    <input type="submit" name="submit" value="Next"/>
</fieldset></form>
';
                    $username = $post['username'];
                    $email = $post['email'];
                    if(
                        $stmt = static::$dbcnnx->prepare("
SELECT username,email FROM ".static::$users_table."
WHERE username=:username AND email=:email
LIMIT 1
")
                        AND $stmt->bindValue(':username',$username,PDO::PARAM_STR)
                        AND $stmt->bindValue(':email',$email,PDO::PARAM_STR)
                        AND $stmt->execute()
                        AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
                    ){
                        $user_row = $result;
                    }
                    unset($stmt,$result);
                        
                    if( !empty($user_row) ){
                        $_SESSION['recover']['username'] = $user_row['username'];
                        $_SESSION['recover']['email'] = $user_row['email'];
                        $_SESSION['recover']['token'] = $this->rand_salt();
                        $this->send_email(
                            $_SESSION['recover']['email']
                            ,'Token request.'
                            ,"A token was requested for this email address.\r\n"
                            ."\r\n"
                            ."Token:\r\n"
                            .$_SESSION['recover']['token']."\r\n"
                            ."\r\n"
                            ."If you didn't trigger this request, please reply here to report a possible hack attempt."
                        );
                    }
                break;
                case 1: // Username and email are good.
                    if(
                        empty($post['token'])
                        OR empty($_SESSION['recover']['token'])
                        OR $post['token'] !== $_SESSION['recover']['token']
                        OR empty($_SESSION['recover']['username'])
                        OR empty($_SESSION['recover']['email'])
                    ){
                        unset($_SESSION['recover']);
                        $form = static::$bad_input;
                        break;
                    }
                    $_SESSION['recover']['step'] = 2;
                    $username = $_SESSION['recover']['username'];
                    $email = $_SESSION['recover']['email'];
                    $form = '
<form id="recover_form" name="recover_form" class="recover user" method="post"><fieldset>
    <h3>Choose a NEW password:</h3>
    <p>Last step!</p>
    <span style="display:inline-block;">
        <label for="password">NEW password (Min '.static::$password_min.' chars):</label><br/>
        <input type="password" id="password" name="password" size="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="recover"/>
    <input type="submit" name="submit" value="Finish"/>
</fieldset></form>
';
                break;
                case 2: // New password was entered.
                    if( empty($post['password']) ){
                        unset($_SESSION['recover']);
                        $form = static::$bad_input;
                        break;
                    }
                    if( // Password quality test.
                        strlen($post['password']) < static::$password_min
                        OR(
                            static::$strict_passwords
                            AND(
                                ! preg_match('/\d/i',$post['password'])
                                OR ! preg_match('/[a-z]/i',$post['password'])
                                OR ! preg_match('/[A-Z]/i',$post['password'])
                                OR ! preg_match('/['.preg_quote(static::$strict_pass_chars).'\/\']/i',$post['password'])
                            )
                        )
                    ){ // Password no good.
                        $form = '
<form id="recover_form" name="recover_form" class="recover user" method="post"><fieldset>
    <h3>Choose a NEW password:</h3>
    <p>Last step!</p>
    '.static::$bad_password.'<br/>
    <span style="display:inline-block;">
        <label for="password">NEW password (Min '.static::$password_min.' chars):</label><br/>
        <input type="password" id="password" name="password" size="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="recover"/>
    <input type="submit" name="submit" value="Finish"/>
</fieldset></form>
';
                    }else{
                        $password = $this->do_crypt($post['password']);
                        $username = $_SESSION['recover']['username'];
                        $email = $_SESSION['recover']['email'];
                        if(
                            $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$users_table." SET password=:password
WHERE username=:username AND email=:email
")
                            AND $stmt->bindValue(':password',$password,PDO::PARAM_STR)
                            AND $stmt->bindValue(':username',$username,PDO::PARAM_STR)
                            AND $stmt->bindValue(':email',$email,PDO::PARAM_STR)
                            AND $stmt->execute()
                        ){
                            $form = static::$good_recovery;
                        }else{
                            $form = static::$bad_db;
                        }
                        unset($stmt,$result);
                        unset($_SESSION['recover']);
                    }
                break;
            }
        }
        return $form;
    }
    
    private function register(){
        if( !empty($_SESSION['logged_in']) ){ return; }
        if(
            !empty($_SESSION['POST'])
            AND $_SESSION['POST']['user_task'] === 'register'
        ){
            $post = $_SESSION['POST'];
            unset($_SESSION['POST']);
        }
        if( !empty($_SESSION['register']['step']) AND !empty($post) ){
            $step = $_SESSION['register']['step'];
        }else{
            $step = 0;
        }
        if( empty($post) ){
            unset($_SESSION['register']);
            $form = '
<form id="register_form" name="register_form" class="register user" method="post"><fieldset>
    <h3>Register</h3>
    <span style="display:inline-block;">
        <label for="username">Username (5-20 chars):</label><br/>
        <input type="text" id="username" name="username" size="20" maxlength="20"/>
    </span><br/>
    <span style="display:inline-block;">
        <label for="email">Email:</label><br/>
        <input type="text" id="email" name="email" size="30" maxlength="255"/>
    </span><br/>
    <input type="hidden" name="user_task" value="register"/>
    <input type="submit" name="submit" value="Register"/>
</fieldset></form>
';
        }else{
            switch( $step ){
                case 0: // Username AND Email address were entered.
                    if(
                        strlen($post['username'])<5
                        OR strlen($post['username'])>20
                        OR strlen($post['email'])<5
                        OR strlen($post['email'])>255
                    ){
                        unset($_SESSION['register']);
                        $form = static::$bad_input;
                        break;
                    }
                    $form = '
<form id="register_form" name="register_form" class="register user" method="post"><fieldset>
    <h3>Verify your email account:</h3>
    <p>Step 2 of 4</p>
    <p>An email with your token should arrive in a few minutes.</p>
    <span style="display:inline-block;">
        <label for="token">Enter the token that was emailed to you:</label><br/>
        <input type="text" id="token" name="token" size="40" maxlength="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="register"/>
    <input type="submit" name="submit" value="Next"/>
</fieldset></form>
';
                    if(
                        $stmt = static::$dbcnnx->prepare("
SELECT email FROM ".static::$users_table."
WHERE email=:email
LIMIT 1
")
                        AND $stmt->bindValue(':email',$post['email'],PDO::PARAM_STR)
                        AND $stmt->execute()
                        AND $row = $stmt->fetch(PDO::FETCH_ASSOC)
                    ){
                        $email = $row['email'];
                    }
                    if( ! empty($email) ){
                        // Email taken.
                        unset($_SESSION['register']);
                        $this->send_email(
                            $email
                            ,'Token request.'
                            ,"A token was requested for this email address, but it's already registered.\r\n"
                            ."If you need to recover your account, please use the recover form.\r\n"
                            ."If you didn't trigger this request, please reply here to report a possible hack attempt."
                        );
                        break;
                    }
                    
                    // New user.
                    $_SESSION['register'] = array();
                    $_SESSION['register']['step'] = 1;
                    $_SESSION['register']['token'] = $this->rand_salt();
                    $_SESSION['register']['username'] = $post['username'];
                    $_SESSION['register']['email'] = $post['email'];
                    $this->send_email(
                        $_SESSION['register']['email']
                        ,'Token request.'
                        ,"A token was requested for this email address.\r\n"
                        ."\r\n"
                        ."Token:\r\n"
                        .$_SESSION['register']['token']."\r\n"
                        ."\r\n"
                        ."If you didn't trigger this request, please reply here to report a possible hack attempt."
                    );
                break;
                case 1: // Token or new username was entered.
                    if(
                        $post['token'] !== $_SESSION['register']['token']
                    ){
                        unset($_SESSION['register']);
                        $form = static::$bad_input;
                        break;
                    }
                    // Check username.
                    if( !empty($post['username']) ){
                        $_SESSION['register']['username'] = $post['username'];
                    }
                    $db_username = $_SESSION['register']['username'];
                    if(
                        $stmt = static::$dbcnnx->prepare("
SELECT username FROM ".static::$users_table."
WHERE username=:username
LIMIT 1
")
                        AND $stmt->bindValue(':username',$db_username,PDO::PARAM_STR)
                        AND $stmt->execute()
                        AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
                    ){
                        // Username taken.
                        $form = '
<form id="register_form" name="register_form" class="register user" method="post"><fieldset>
    <h3>Choose a different username:</h3>
    <p>Step 2b of 4</p>
    <p>'.static::$bad_username.'</p>
    <span style="display:inline-block;">
        <label for="username">Username (5-20 chars):</label><br/>
        <input type="text" id="username" name="username" size="20" maxlength="20"/>
    </span><br/>
    <span style="display:inline-block;">
        <label for="token">Re-enter your token:</label><br/>
        <input type="text" id="token" name="token" size="40" maxlength="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="register"/>
    <input type="submit" name="submit" value="Next"/>
</fieldset></form>
';
                        break;
                    }
                    // Username acceptable.
                    $_SESSION['register']['step'] = 2;
                    $form = '
<form id="register_form" name="register_form" class="register user" method="post"><fieldset>
    <h3>Choose a password:</h3>
    <p>Step 3 of 4</p>
    <span style="display:inline-block;">
        <label for="password">Password (Min '.static::$password_min.' chars):</label><br/>
        <input type="password" id="password" name="password" size="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="register"/>
    <input type="submit" name="submit" value="Next"/>
</fieldset></form>
';
                break;
                case 2: // Password entered.
                    if(
                        empty($post['password'])
                    ){
                        unset($_SESSION['register']);
                        $form = static::$bad_input;
                        break;
                    }
                    if( // Password quality test.
                        strlen($post['password']) < static::$password_min
                        OR(
                            static::$strict_passwords
                            AND(
                                strlen($post['password']) < static::$password_min
                                OR !preg_match('/\d/i',$post['password'])
                                OR !preg_match('/[a-z]/i',$post['password'])
                                OR !preg_match('/[A-Z]/i',$post['password'])
                                OR !preg_match('/['.preg_quote(static::$strict_pass_chars).'\/\']/i',$post['password'])
                            )
                        )
                    ){
                        // Password no good.
                        $form = '
<form id="register_form" name="register_form" class="register user" method="post"><fieldset>
    <h3>Choose a different password:</h3>
    <p>Step 3 of 4</p>
    <p>'.static::$bad_password.'</p>
    <span style="display:inline-block;">
        <label for="password">Password (Min '.static::$password_min.' chars):</label><br/>
        <input type="password" id="password" name="password" size="40"/>
    </span><br/>
    <input type="hidden" name="user_task" value="register"/>
    <input type="submit" name="submit" value="Next"/>
</fieldset></form>
';
                        break;
                    }
                    // Password acceptable.
                    $reg = $_SESSION['register'];
                    $reg['password'] = $this->do_crypt($post['password']);
                    if(
                        $stmt = static::$dbcnnx->prepare("
INSERT INTO ".static::$users_table." (username, email, visited, password)
VALUES (:username, :email, :visited, :password)
")
                        AND $stmt->bindValue(':username',$reg['username'],PDO::PARAM_STR)
                        AND $stmt->bindValue(':email',$reg['email'],PDO::PARAM_STR)
                        AND $stmt->bindValue(':visited',time(),PDO::PARAM_INT)
                        AND $stmt->bindValue(':password',$reg['password'],PDO::PARAM_STR)
                        AND $stmt->execute()
                    ){
                        $form = static::$good_registration;
                    }else{
                        $form = '<p class="error message">User create:</p>'.static::$bad_db;
                    }
                    unset($_SESSION['register']);
                break;
                // END of switch $step.
            }
        }
        return $form;
        // END of register().
    }
    
    // END of USER class.
}
?>