<?php
// ----------------------------------------------------------------------------
// Features:	後台-- login 範本檔
// File Name:	login.tmpl.php
// Author:		Barkley
// Related:
// Log:
//
// ----------------------------------------------------------------------------

// 有變數才執行，沒有變數就是不正常進入此 tmpl 檔案
if(isset($tmpl)) {
	// 正常
}else{
	die('ERROR tmpl');
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" href="favicon.ico">

  <meta name="description" content="<?php echo $tmpl['html_meta_description']; ?>">
	<meta name="author" content="<?php echo $tmpl['html_meta_author']; ?>" >
	<title><?php echo $tmpl['html_meta_title']; ?></title>

  <?php echo assets_include(); ?>
	
	<!-- for login use -->
	<link rel="stylesheet"  href="ui/login_style.css?version_key=20200817" >

	<?php echo $tmpl['extend_head']; ?>
	</head>

	<body>
    <div class="container">
    <?php
    echo $tmpl['panelbody_content'];
    ?>
    </div>

  </body>
</html>