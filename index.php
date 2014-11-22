<?php
    if( ! $webapp = include('webapp.php') ){ die('Webapp failure.'); }
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
        <div>
            <?php echo $site['header']; ?>
            <div style="clear:both;"></div>
        </div>
        <div class="menu" style="text-align:center; font-size:x-large;">
<?php
    foreach( $webapp['pages'] as $key => $val ){
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
    </header>
    <div>
        <?php if( ! empty($_SESSION['message']) ){ echo $_SESSION['message']; } ?>
        <div style="clear:both;"></div>
    </div>
    <section id="section_2" class="section section_2">
        <?php echo $site['content']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['header']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['content']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['include']; ?>
        <div style="clear:both;"></div>
        <?php echo $page['footer']; ?>
        <div style="clear:both;"></div>
    </section>
    <footer id="section_3" class="section section_3">
        <div>
            <?php echo $site['footer']; ?>
            <div style="clear:both;"></div>
        </div>
    </footer>
    <div id="copyright"><?php echo $site['copyright_predate'].date('Y').$site['copyright_postdate']; ?></div>
    <div id="vanity">
        <?php echo $site['vanity']; ?>
        <a title="Validate HTML" href= "http://validator.w3.org/check?uri=<?php echo rawurlencode( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ); ?>">HTML5</a>, 
        <a title="Validate CSS" href= "http://jigsaw.w3.org/css-validator/validator?uri=<?php echo rawurlencode( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ); ?>">CSS3</a>, & JavaScript
    </div>
</body>
</html>
