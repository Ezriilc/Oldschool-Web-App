<?php

new DOWNLOADER;

CLASS DOWNLOADER{
    static
        $db_file = './_sqlite/Oldschool.sqlite3'
        , $zip_prefix = 'Oldschool-DLC_'
        , $downloads_table = 'downloads'
        , $cache_time = 300 // Seconds
        , $types = array(
            'image'=>array('jpg','jpeg','png','gif','bmp')
            ,'video'=>array('mp4','mpg','mpeg','avi','flv')
            ,'audio'=>array('mp3','wav','flac','wma')
            ,'text'=>array('txt','craft','cfg','sfs')
            ,'special'=>array('zip','dll')
        )
        ,$dbcnnx
    ;
    function __construct(){
        if( ! isset(static::$types['all']) ){
            static::$types['all'] = array();
            foreach( static::$types as $key => $val ){
                if( $key === 'all' ){ continue; }
                static::$types['all'] = array_merge(static::$types['all'],$val);
            }
        }
        static::get_download();
    }
    
    static public function get_info($pathname){
        $return = null;
        if( ! static::init_database() ){ return; }
        $user_file_patt = '^.*\/?users\/([^\/]+)\/(.*)$';
        $username = preg_filter('/'.$user_file_patt.'/i', '$1', $pathname);
        $logname = basename($pathname);
        if( $username ){ $logname = $username.'/'.$logname; }
        
        $dirname = dirname($pathname);
        $basename = basename($pathname);
        $dir = basename($dirname);
        $name_ext_patt = '/^(.*)\.([^\.\/]+)$/';
        $name = preg_filter($name_ext_patt,'$1',$basename);
        $ext = preg_filter($name_ext_patt,'$2',$basename);
        
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT * FROM ".static::$downloads_table."
WHERE file=:file
")
            AND $stmt->bindValue(':file', $logname, PDO::PARAM_STR)
            AND $stmt->execute()
            AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            // Download info found.
        }
        if( empty($result) ){ $result = array(); }
        foreach( static::$types['image'] as $type ){
            $image_pathname = './'.$dirname.'/'.$name.'.'.$type;
            if( is_readable($image_pathname) ){
                $result['image'] = $image_pathname;
            }
        }
        $text_pathname = './'.$dirname.'/'.$name.'.txt';
        if( is_readable($text_pathname) ){
            $result['desc'] = '<pre>'.htmlentities(file_get_contents($text_pathname)).'</pre>';
        }
        return $result;
    }
    
    static private function get_download(){
        $zip_prefix = static::$zip_prefix;
        $types = static::$types;
        $request = preg_replace('/(\?.*)$/', '', rawurldecode($_SERVER['REQUEST_URI']));
        $root_dir = dirname($_SERVER['PHP_SELF']);
        if( $root_dir !== '/' ){
            $root_patt = preg_quote($root_dir,'/');
            $request = preg_replace('/^'.$root_patt.'/', '', $request);
        }
        $request = '.'.$request; // ./ Required to find a file.
        $pathname = $request;
        $dirname = dirname($request);
        $basename = basename($request);
        $dir = basename($dirname);
        $name_ext_patt = '/^(.*)\.([^\.\/]+)$/';
        $name = preg_filter($name_ext_patt,'$1',$basename);
        $ext = preg_filter($name_ext_patt,'$2',$basename);
        if(
            is_dir($pathname)
            OR(
                $basename !== $zip_prefix
                AND(
                    ! $name
                    OR ! $ext
                    OR ! in_array($ext, $types['all'])
                )
            )
        ){ return; }
        if( $basename === $zip_prefix ){
            $zip_filename = $zip_prefix.$dir.'.zip';
            $zip_obj = new ZipArchive();
            if( ! $zip_obj->open($zip_filename, ZIPARCHIVE::OVERWRITE) ){
                die(get_called_class().': ZIPARCHIVE error.');
            }
            $scandir = scandir($dirname);
            foreach( $scandir as $scan_file ){
                $scan_pathname = $dirname.'/'.$scan_file;
                $scan_ext = preg_filter($name_ext_patt,'$2',basename($scan_file));
                if( ! is_readable($scan_pathname) ){ sleep(5); }
                if(
                    is_readable($scan_pathname)
                    && ! is_dir($scan_pathname)
                    && in_array($scan_ext, $types['all'])
                ){
                    $zip_obj->addFile($scan_pathname, $scan_file);
                    $pathname = './'.$zip_filename;
                    $ext = 'zip';
                }
            }
            $zip_obj->close();
        }
        if( ! is_readable($pathname) ){ sleep(5); }
        if( ! is_readable($pathname) ){
            die(get_called_class().': Invalid resource.');
        }
        // Download is ready.
        
        $logname = $basename;
        $user_file_patt = '^.*\/users\/([^\/]+)\/(.*)$';
        $username = preg_filter('/'.$user_file_patt.'/i', '$1', $pathname);
        if( $username ){
            $logname = $username.'/'.$logname;
        }
        $mime_type = finfo_file(finfo_open(FILEINFO_MIME_TYPE),$pathname);
        if( $ext === 'sfs' ){ $mime_type = 'text/plain'; }
        $is_direct = false;
        $is_local = false;
        $is_approved = false;
        if( empty($_SERVER['HTTP_REFERER']) ){
            // Direct access.
            $is_direct = true;
        }else{
            if(
                preg_match('/^'.preg_quote('http://'.$_SERVER['HTTP_HOST'],'/').'/i', $_SERVER['HTTP_REFERER'])
            ){ $is_local = true; }
            if(
                preg_match('/^'.preg_quote('http://forum.kerbalspaceprogram.com','/').'/i', $_SERVER['HTTP_REFERER'])
            ){ $is_approved = true; }
        }
        if(
            preg_match('/hyperedit\/?$/i', $dirname)
            OR preg_match('/hyperedit\/archives\/?$/i', $dirname)
        ){
            $is_approved = true;
        }
        if(
            ! $username
            AND ! $is_local
            AND ! $is_approved
        ){
            die(get_called_class().': Sorry, no direct access.');
        }
        if(
            $is_local
            AND preg_match('/^image\//i',$mime_type)
        ){
            // Don't log.
        }else{
            if( $log = static::log_download($logname) !== true ){
                die($log);
            }
        }
        if(
            $username
            AND(
                preg_match('/^(text|image|audio|video)\//i',$mime_type)
            )
        ){
            header( 'Content-Type: '.$mime_type );
        }else{
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Description: File Transfer' );
            $filename = preg_replace('/;/i', '_', $basename);
                // No semi-colons inside HTTP headers - it ends the line.
            header( 'Content-Disposition: attachment; filename="'.$filename.'"' );
            header( 'Content-Transfer-Encoding: binary' );
        }
        header( 'Vary:Accept-Encoding' );
        header( 'Last-Modified: '.date('r',filemtime($pathname)) );
        header( 'Cache-Control:no-transform,public,max-age:'.static::$cache_time.', s-maxage:'.static::$cache_time );
        header( 'Expires: '.date('r',(time()+static::$cache_time)) );
        header( 'Content-Length: ' .filesize($pathname) );
        readfile($pathname);
        exit;
    }
    
    static private function log_download($name){
        if( $init = static::init_database() !== true ){
            return $init;
        }
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT count FROM ".static::$downloads_table."
WHERE file=:file
")
            AND $stmt->bindValue(':file', $name, PDO::PARAM_STR)
            AND $stmt->execute()
            AND $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            // File exists.
            $stmt = static::$dbcnnx->prepare("
UPDATE ".static::$downloads_table." SET count = count + 1, last = (strftime('%s','now'))
WHERE file=:file
");
        }else{
            // File is new.
            $stmt = static::$dbcnnx->prepare("
INSERT INTO ".static::$downloads_table." (file)
VALUES (:file)
");
        }
        if(
            ! empty($stmt)
            AND $stmt->bindValue(':file', $name, PDO::PARAM_STR)
            AND $stmt->execute()
            AND $stmt->rowCount()
        ){
            // File counted.
        }else{
            return get_called_class().': DB: Can\'t add/update file.';
        }
        return true;
    }
    
    static private function init_database(){
        if( ! is_readable(static::$db_file) ){ sleep(5); }
        if(
            ! is_writable(static::$db_file)
            OR ! is_writable(dirname(static::$db_file))
        ){
            return get_called_class().': Bad DB file/path.';
        }
        try{
            static::$dbcnnx = new PDO('sqlite:'.static::$db_file);
        }catch( PDOException $Exception ){
            return get_called_class().': DB Connect: PDO exception.';
        }
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT name FROM sqlite_master
WHERE type='table' AND name='".static::$downloads_table."';
")
            AND $stmt->execute()
            AND $result = $stmt->fetch(PDO::FETCH_ASSOC)
        ){
            // Downloads table exists.
        }else{
            $stmt_string = "
CREATE TABLE ".static::$downloads_table."(
id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT
,file TEXT UNIQUE NOT NULL
,count INTEGER NOT NULL DEFAULT 1
,first INTEGER NOT NULL DEFAULT (strftime('%s','now'))
,last INTEGER NOT NULL DEFAULT (strftime('%s','now'))
);
";
            if(
                $stmt = static::$dbcnnx->prepare($stmt_string)
                AND $stmt->execute()
            ){
                // Downloads table created.
            }else{
                return get_called_class().': Can\'t create downloads table.';
            }
        }
        return true;
    }
    
}
?>