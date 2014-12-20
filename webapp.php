<?php

@include_once('Downloader.php');

new WEBAPP;
return WEBAPP::$content;

class WEBAPP{
    static $content = 'content.ini';
    function __construct(){
        @session_start();
        $content = static::$content;
        if( ! $content = $this->load_content($content) ){ die('Content failure.'); }
        $content['page_name'] = $this->pick_a_page($content);
        $content['page'] = & $content['pages'][$content['page_name']];
        $content = $this->prepare_display($content);
        static::$content = $content;
    }
    
    private function load_content($file){
        if( ! is_readable($file) ){ sleep(5); }
        if( ! is_readable($file) ){ return; }
        $file = file($file);
        $sect_patt = '/^\[(.+)\]$/';
        $split_patt = '/^([^=]+)=(.*)$/';
        $content = array('pages' => array());
        $section = '';
        $multi = '';
        $i=1;while( $i <= count($file) ){
            $line = $file[$i-1];
            if( $try_section = preg_filter($sect_patt,'$1',trim($line)) ){
                $section = $try_section;
            }elseif( $section ){
                if( $section === 'site' ){
                    if( ! array_key_exists($section,$content) ){
                        $content[$section] = array();
                    }
                    $target_prop = & $content[$section];
                }else{
                    if( ! array_key_exists($section,$content['pages']) ){
                        $content['pages'][$section] = array();
                    }
                    $target_prop = & $content['pages'][$section];
                }
                if( $multi ){
                    if( preg_match('/^"$/', trim($line)) ){
                        $target_prop[$multi] = ltrim($target_prop[$multi]);
                        $multi = '';
                    }else{
                        if( empty( $target_prop[$multi] ) ){
                            $target_prop[$multi] = PHP_EOL;
                        }
                        $target_prop[$multi] .= $line;
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
                                $target_prop[$prop] = trim($val,'"');
                            }
                        }
                    }
                }
            }
            $i++;
        }
        foreach( $content['site'] as $prop => $val ){
            foreach( $content['pages'] as $page => $details ){
                if( ! array_key_exists($prop,$content['pages'][$page]) ){
                    $content['pages'][$page][$prop] = '';
                }
            }
        }
        return $content;
    }
    
    
    
    private function pick_a_page($content){
        if( ! empty($_SERVER['REQUEST_URI']) ){
            $request = rawurldecode(preg_replace('/(\?.*)$/', '', $_SERVER['REQUEST_URI']));
            $root_dir = dirname($_SERVER['PHP_SELF']);
            if( $root_dir !== '/' ){
                $root_patt = preg_quote($root_dir,'/');
                $request = preg_replace('/^'.$root_patt.'\//', '', $request);
            }
            if(
                $request
                AND $request !== '/'
            ){
                foreach( $content['pages'] as $page => $val ){
                    if(
                        $page === $request
                        OR '/'.$page === $request
                    ){
                        return $page;
                    }
                }
                $uri  = $_SERVER['HTTP_HOST'].$root_dir;
                header('Location: http://'.$uri);
                exit;
            }
        }
        if( ! empty($content['site']['home_page']) ){
            return $content['site']['home_page'];
        }
        if( ! empty($content['pages']['home']) ){
            return 'home';
        }
        foreach( $content['pages'] as $page => $val ){
            return $page; // First one.
        }
        die('No home page found.');
    }
    
    private function prepare_display($content){
        $page_name = $content['page_name'];
        $site = & $content['site'];
        $page = & $content['page'];
        $page['head'] = '';
        $page['include'] = '';
        
        // Includes.
        if( $page_name !== 'user' ){
            include('user.php');
        }
        $inc_file = $page_name.'.php'; // ...that match ?page=
        if( is_readable($inc_file) && !is_dir($inc_file) ){
            $page['include'] .= include($inc_file);
        }
        if( !empty($page['php']) ){
            eval($page['php']);
        }
        // CSS
        $inc_style_file = $page_name.'.css';
        if( is_readable($inc_style_file) && !is_dir($inc_style_file) ){
            $page['head'] .= '
<link rel="stylesheet" type="text/css" href="'.$inc_style_file.'"/>';
        }
        if( !empty($page['style']) ){
            $page['head'] .= '
<style type="text/css">
    '.$page['style'].'
</style>';
        }
        // JS
        $inc_js_file = $page_name.'.js';
        if( is_readable($inc_js_file) && !is_dir($inc_js_file) ){
            $page['head'] .= '
<script type="text/javascript" src="'.$inc_js_file.'"></script>';
        }
        if( !empty($page['javascript']) ){
            $page['head'] .= '
<script type="text/javascript"><!--
    '.$page['javascript'].'
//--></script>';
        }
        
        // Combine site and page details.
        if( !empty($page['title']) AND !empty($site['title']) ){
            $content['title'] = $page['title'].' : '.$site['title'];
        }else{
            $content['title'] = $site['title'];
        }
        $content['description'] = $site['description'];
        if( !empty($page['description']) ){
            $content['description'] .= ' : '.$page['description'];
        }
        $content['author'] = $site['author'];
        if( !empty($page['author']) ){
            $content['author'] .= ' / '.$page['author'];
        }
        $content['keywords'] = $site['keywords'].','.$page['keywords'];
        
        return $content;
    }
}
?>