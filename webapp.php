<?php

new WEBAPP;
return WEBAPP::$content;

class WEBAPP{
    static $default_page, $user, $content = './content.ini';
    function __construct(){
        
        // User sessions and security.
        static::$user = @include('user.php');
        
        // Get site data.
        if( ! static::$content = $this->load_content(static::$content) ){
            die('Content failure.');
        }
        static::$content['page'] = $this->pick_a_page(static::$content);
        static::$content = $this->prepare_display(static::$content);
    }
    
    private function load_content($file){
        if( ! is_readable($file) ){ sleep(5); }
        if( ! is_readable($file) ){ return; }
        $file = file($file);
        $sect_patt = '/^\[(.+)\]$/';
        $split_patt = '/^([^=]+)=(.*)$/';
        $content = array();
        $curr_sect = '';
        $multi = '';
        $i=1;while( $i <= count($file) ){
            $line = $file[$i-1];
            if( $section = preg_filter($sect_patt,'$1',trim($line)) ){
                $curr_sect = $section;
                if( ! array_key_exists($section,$content) ){
                    $content[$section] = array();
                }
            }elseif( $curr_sect ){
                if( $multi ){
                    if( preg_match('/^"$/', trim($line)) ){
                        $content[$curr_sect][$multi] = ltrim($content[$curr_sect][$multi]);
                        $multi = '';
                    }else{
                        if( empty( $content[$curr_sect][$multi] ) ){
                            $content[$curr_sect][$multi] = PHP_EOL;
                        }
                        $content[$curr_sect][$multi] .= $line;
                    }
                }else{
                    $line = trim($line);
                    $prop = trim(preg_filter($split_patt,'$1',$line));
                    $val = trim(preg_filter($split_patt,'$2',$line));
                    if( $prop ){
                        if( $val AND $prop ){
                            if( $val === '"' ){
                                $multi = $prop;
                            }else{
                                if( $val === '""' ){
                                    $val = '';
                                }
                                $content[$curr_sect][$prop] = trim($val,'"');
                            }
                        }
                    }
                }
            }
            $i++;
        }
        return $content;
    }
    
    
    
    private function pick_a_page($pages){
        if( ! empty($_GET['page']) ){
            foreach( $pages as $page => $val ){
                if( $page === 'site' ){ continue; }
                if( $page === $_GET['page'] ){
                    return $page;
                }
            }
            $uri  = $_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            header('Location: http://'.$uri);
            exit;
        }
        foreach( $pages as $page => $val ){
            if( $page === 'site' ){ continue; }
            return $page;
        }
        die('Failed to pick a page.');
    }
    
    private function prepare_display($data){
        $data['head'] = '';
        $page = $data['page'];
        $data[$page]['include'] = '';
        // Include files...
        $inc_file = $data['page'].'.php'; // ...that match ?page=
        if(
            $page === 'user'
            AND !empty(static::$user)
        ){
            $data[$page]['include'] .= static::$user;
        }elseif( is_readable($inc_file) && !is_dir($inc_file) ){
            $data[$page]['include'] .= include($inc_file);
        }
        $inc_style_file = $page.'.css';
        if( is_readable($inc_style_file) && !is_dir($inc_style_file) ){
            $data['head'] .= '
<link rel="stylesheet" type="text/css" href="'.$inc_style_file.'"/>';
        }
        $inc_js_file = $page.'.js';
        if( is_readable($inc_js_file) && !is_dir($inc_js_file) ){
            $data['head'] .= '
<script type="text/javascript" src="'.$inc_js_file.'"></script>';
        }
        if( !empty($data[$page]['style']) ){
            $data['head'] .= '
<style type="text/css"><!--
    '.$page['style'].'
//--></style>';
        }
        
        // Combine site and page details.
        if( !empty($data[$page]['title']) AND !empty($data['site']['title']) ){
            $data['title'] = $data[$page]['title'].' : '.$data['site']['title'];
        }else{
            $data['title'] = $data['site']['title'];
        }
        $data['description'] = $data['site']['description'];
        if( !empty($data[$page]['description']) ){
            $data['description'] .= ' : '.$data[$page]['description'];
        }
        $data['author'] = $data['site']['author'];
        if( !empty($data[$page]['author']) ){
            $data['author'] .= ' / '.$data[$page]['author'];
        }
        $data['keywords'] = $data['site']['keywords'].','.$data[$page]['keywords'];
        return $data;
    }
}
?>