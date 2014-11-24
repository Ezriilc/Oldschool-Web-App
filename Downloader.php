<?php

new DOWNLOADER;

CLASS DOWNLOADER{
    static
        $db_file = './_sqlite/Kerbaltek_TESTING.sqlite3'
        , $downloads_table = 'downloads'
        , $zip_prefix = 'Kerbaltek-DLC_'
        , $types = array(
            'image'=>array('jpg','jpeg','png','gif','bmp')
            ,'video'=>array('mp4','mpg','mpeg','avi','flv')
            ,'audio'=>array('mp3','wav','flac','wma')
            ,'special'=>array('zip','craft')
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
    
    static private function get_download(){
        $zip_prefix = static::$zip_prefix;
        $types = static::$types;
        $name_ext_patt = '/^(.*)\.([^\.\/]+)$/';
        $root_patt = preg_quote(dirname($_SERVER['PHP_SELF']), '/');
        $request = preg_replace('/'.$root_patt.'\//', '', rawurldecode($_SERVER['REQUEST_URI']));
        $download = $request;
        $dirname = dirname($request);
        $filename = basename($request);
        $dir = basename(dirname($request));
        $name = preg_filter($name_ext_patt,'$1',$filename);
        $ext = preg_filter($name_ext_patt,'$2',$filename);
        if( $filename === $zip_prefix ){
            $zip_filename = $zip_prefix.$dir.'.zip';
            $zip_obj = new ZipArchive();
            if( ! $zip_obj->open($zip_filename, ZIPARCHIVE::OVERWRITE) ){
                die('ZIPARCHIVE error.');
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
                    $download = $zip_filename;
                    $ext = 'zip';
                }
            }
            $zip_obj->close();
        }
        
        if( ! is_readable($download) ){ sleep(5); }
        if(
            is_readable($download)
            AND ! is_dir($download)
            AND in_array($ext, $types['all'])
        ){
            if( $log = static::log_download($download) === true ){
//die($download);
                // Serve the file already!
                $filename = preg_replace('/;/i', '_', basename($download));
                    // No semi-colons inside HTTP headers - it ends the line.
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: application/octet-stream' );
                header( 'Content-Disposition: attachment; filename="'.$filename.'"' );
                    // filename string double-quoted to handle spaces.
                header( 'Content-Transfer-Encoding: binary' );
                header( 'Expires: 0' );
                header( 'Cache-Control: must-revalidate' );
                header( 'Pragma: public' );
                header( 'Content-Length: ' .filesize($download) );
                readfile($download);
                exit;
            }
            die($log);
        }
        die(get_called_class().': Bad file.<br/>');
    }
    
    static private function log_download($download){
        if( $init = static::init_database() !== true ){
            return $init;
        }
        $filename = basename($download);
        if(
            $stmt = static::$dbcnnx->prepare("
SELECT count FROM ".static::$downloads_table."
WHERE file=:file
")
            AND $stmt->bindValue(':file', $filename, PDO::PARAM_STR)
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
            AND $stmt->bindValue(':file', $filename, PDO::PARAM_STR)
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
            // Table exists.
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
                // Table created.
            }else{
                return get_called_class().': Can\'t create table.';
            }
        }
        return true;
    }
    
}
?>