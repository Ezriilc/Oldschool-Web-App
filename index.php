<?php if( ! $webapp = @include('webapp.php') ){ die('Webapp failure.'); } ?>
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
    <?php echo $webapp['head']; ?>
</head>
<body>
    <header id="section_1" class="section section_1">
        <div>
            <?php echo $webapp['site']['header']; ?>
            <div style="clear:both;"></div>
        </div>
        <div>
            <?php echo @$_SESSION['message']; ?>
            <div style="clear:both;"></div>
        </div>
    </header>
    <section id="section_2" class="section section_2">
        <?php echo $webapp['site']['content']; ?>
        <div style="clear:both;"></div>
        <?php echo $webapp['page']['header']; ?>
        <div style="clear:both;"></div>
        <?php echo $webapp['page']['content']; ?>
        <div style="clear:both;"></div>
        <?php echo $webapp['page']['include']; ?>
        <div style="clear:both;"></div>
        <?php echo $webapp['page']['footer']; ?>
        <div style="clear:both;"></div>
    </section>
    <footer id="section_3" class="section section_3">
        <div>
            <?php echo $webapp['site']['footer']; ?>
            <div style="clear:both;"></div>
        </div>
    </footer>
    <div id="copyright"><?php echo $webapp['site']['copyright_predate'].date('Y').$webapp['site']['copyright_postdate']; ?></div>
    <div id="vanity">
        <?php echo $webapp['site']['vanity']; ?>
        <a title="Validate HTML" href= "http://validator.w3.org/check?uri=<?php echo rawurlencode( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ); ?>">HTML5</a>, 
        <a title="Validate CSS" href= "http://jigsaw.w3.org/css-validator/validator?uri=<?php echo rawurlencode( 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ); ?>">CSS3</a>, & JavaScript
    </div>
</body>
</html>
<? //var_dump(@$_SESSION,@$_COOKIE,'END OF DEBUG'); ?>
