<?php
if(
    pathinfo($_SERVER['PHP_SELF'])['filename'] === pathinfo(__FILE__)['filename']
    && pathinfo($_SERVER['PHP_SELF'])['extension'] === pathinfo(__FILE__)['extension']
){
//    header('Location: ./');
//    die('No direct access.');
}

new WEBAPP;
//var_dump(@$_SESSION);
return WEBAPP::$data;

class WEBAPP{
    static
        $default_page = 'home'
        ,$user = null
        ,$data = null
    ;
    function __construct(){
        
        // Get site data.
        if(
            @include('content.php')
        ){
            static::$data['site'] = $site;
            static::$data['page'] = $this->pick_a_page($pages, static::$default_page);
        }else{
            die('Content failure.');
        }
        
        // User sessions and security.
        static::$user = @include('user.php');
        
        static::$data = $this->prepare_display(static::$data);
    }
    
    
    
    private function pick_a_page($pages,$default){
        if( !empty($_GET['page']) ){
            $get_page = $_GET['page'];
            foreach( $pages as $val ){
                if(
                    !empty($val['page'])
                    AND $val['page'] === $get_page
                ){
                    $page = $val;
                    break;
                }
            }
            if( empty($page) ){
                // Page doesn't exist, go home.
                $uri  = $_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                header('Location: http://'.$uri);
                exit;
            }
        }
        if( empty($page) ){
            // Still no page, use the first.
            if( empty($pages[$default]) ){
                foreach( $pages as $val ){
                    $page = $val;
                    break;
                }
            }else{
                $page = $pages[$default];
            }
        }
        return $page;
    }
    
    private function prepare_display($data){
        $data['head'] = '';
        $page = &$data['page'];
        $page['include'] = '';
        
        // Include files...
        $inc_file = $page['page'].'.php'; // ...that match ?page=
        if(
            $page['page'] === 'user'
            AND !empty(static::$user)
        ){
            $page['include'] .= static::$user;
        }elseif( is_readable($inc_file) && !is_dir($inc_file) ){
            $page['include'] .= include($inc_file);
        }
        $inc_style_file = $page['page'].'.css';
        if( is_readable($inc_style_file) && !is_dir($inc_style_file) ){
            $data['head'] .= '
<link rel="stylesheet" type="text/css" href="'.$inc_style_file.'"/>';
        }
        $inc_js_file = $page['page'].'.js';
        if( is_readable($inc_js_file) && !is_dir($inc_js_file) ){
            $data['head'] .= '
<script type="text/javascript" src="'.$inc_js_file.'"></script>';
        }
        if( !empty($page['style']) ){
            $data['head'] .= '
<style type="text/css"><!--
    '.$page['style'].'
//--></style>';
        }
        
        // Combine site and page details.
        if( !empty($page['title']) AND !empty($data['site']['title']) ){
            $data['title'] = $page['title'].' : '.$data['site']['title'];
        }else{
            $data['title'] = $data['site']['title'];
        }
        $data['description'] = $data['site']['description'];
        if( !empty($page['description']) ){
            $data['description'] .= ' : '.$page['description'];
        }
        $data['author'] = $data['site']['author'];
        if( !empty($page['author']) ){
            $data['author'] .= ' / '.$page['author'];
        }
        $data['keywords'] = $data['site']['keywords'].','.$page['keywords'];
        return $data;
    }
}
?>