<?php
    $time_start = microtime(true);
    if( ! $webapp = @include('webapp.php') ){ die('Webapp failure.'); }
    $site = $webapp['site'];
    $page = $webapp['page'];
?>
<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="msapplication-config" content="none"/>
    <meta name="description" content="<?php echo $webapp['description']; ?>"/>
    <meta name="author" content="<?php echo $webapp['author']; ?>"/>
    <meta name="keywords" content="<?php echo $webapp['keywords']; ?>"/>
    <title><?php echo $webapp['title']; ?></title>
    <link rel="shortcut icon" href="favicon.ico"/>
    <link rel="stylesheet" type="text/css" href="base.css"/>
    <link rel="stylesheet" type="text/css" href="custom.css"/>
    <script src="jquery-2.1.1.min.js"></script>
    <script type="text/javascript"><!--
        var serverTime = <?php echo time(); ?>;
    //--></script>
    <script type="text/javascript" src="webapp.js"></script>
    <?php echo $page['head']; ?>
</head>
<body>
    <header id="section_1" class="section section_1">
        <?php echo $site['header']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['header']; ?>
        <div style="clear:both;"></div>
        <div class="menu" style="text-align:center; font-size:x-large;">
<?php
    foreach( $webapp['pages'] as $key => $val ){
        if( $key === 'archives' ){ continue; }
        $href = './';
        if( $key !== 'home' ){ $href .= $key; }
        $link = '';
        if( ! empty($val['link']) ){ $text = $val['link']; }
        else{ $text = ucfirst($key); }
        echo '
            <a title="'.$val['description'].'" href="'.$href.'">'.$text.'</a>';
    }
?>
            <div style="clear:both;"></div>
        </div>
<?php if( ! empty($_SESSION['message']) ){ ?>
        <div style="margin:2px;padding:2px;">
            <?php echo $_SESSION['message']; ?>
            <div style="clear:both;"></div>
        </div>
<?php } ?>
    </header>
    <section id="section_2" class="section section_2">
        <?php echo $site['content']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['content']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['include']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['content2']; ?>
        <div style="clear:both;"></div>
    </section>
    <footer id="section_3" class="section section_3">
        <?php echo $page['footer']; ?>
        <div style="clear:both;"></div>
        <?php echo $site['footer']; ?>
        <small>Page rendered in <?php echo round((microtime(true) - $time_start),2); ?> seconds.<br/>
        Server time: <?php echo date('l, F j, Y, g:i a (T, \G\M\TP)'); ?></small>
        <div style="clear:both;"></div>
    </footer>
    <div id="copyright"><small><?php echo $site['copyright_predate'].date('Y').$site['copyright_postdate']; ?></small></div>
    <div id="vanity">
        <small><?php echo $site['vanity']; ?>
        <a title="Validate HTML" href= "http://validator.w3.org/check?uri=<?php echo rawurlencode( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ); ?>">HTML5</a>, 
        <a title="Validate CSS" href= "http://jigsaw.w3.org/css-validator/validator?uri=<?php echo rawurlencode( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ); ?>">CSS3</a>, & JavaScript</small>
    </div>
</body>
</html>
