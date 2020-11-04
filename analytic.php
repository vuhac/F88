<?php
// ----------------------------------------
// Features:	後台 -- 網站分析嵌入程式
// File Name:	analytic.php
// Author:		Barkley
// Related:
// piwiki: http://analytics.gpk17.com/
// google analytic: https://analytics.google.com
// Log:
// -----------------------------------------------------------------------------


//session_start();

// 主機及資料庫設定
//require_once dirname(__FILE__) ."/config.php";


// 網站前台網址
$website_domainname       = $_SERVER["HTTP_HOST"];
//var_dump($website_domainname);
//var_dump($config);

// Google Analytic 的代碼 , 如果有自訂的話使用自訂
if(isset($config['google_analytics_id']) AND $config['google_analytics_id'] != '' ) {
  $default_ua = $config['google_analytics_id'];
}else{
  // 沒有定義的話, 使用系統預設值
  $default_ua = 'UA-82276651-1';
  switch ($website_domainname)
  {
  case 'www.playgt.com':
    $default_ua = 'UA-108456708-1';
    break;
  case 'be.playgt.com':
    $default_ua = 'UA-108456708-2';
    break;
  case 'demo.playgt.com':
    $default_ua = 'UA-108456708-3';
    break;
  case 'bedemo.playgt.com':
    $default_ua = 'UA-108456708-4';
    break;
  case 'www.gtdemo.vip':
    $default_ua = 'UA-108456708-5';
    break;
  case 'ab.gtdemo.vip':
    $default_ua = 'UA-108456708-6';
    break;

  case 'www.gpk17.com':
    $default_ua = 'UA-82276651-3';
    break;
  case 'be.gpk17.com':
    $default_ua = 'UA-82276651-3';
    break;
  case 'demo.gpk17.com' :
    $default_ua = 'UA-82276651-2';
    break;
  case 'bedemo.gpk17.com':
    $default_ua = 'UA-82276651-2';
    break;
  case 'dev.gpk17.com':
    $default_ua = 'UA-82276651-4';
    break;
  case 'bedev.gpk17.com':
    $default_ua = 'UA-82276651-4';
    break;

  case 'mtchang.jutainet.com':
    $default_ua = 'UA-103838458-2';
    break;
  case 'bemtchang.jutainet.com':
    $default_ua = 'UA-103838458-2';
    break;

  default:
    $default_ua = 'UA-82276651-1';
  }
}


// 使用 google 的分析工具
// <!-- Global site tag (gtag.js) - Google Analytics -->
$google_analytic_code = "
<script async src=\"https://www.googletagmanager.com/gtag/js?id=$default_ua\"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '$default_ua');
</script>
";

/*
$google_analytic_code = "
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

  ga('create', '".$default_ua."', 'auto');
  ga('send', 'pageview');

</script>
";
*/

// var_dump($google_analytic_code);
echo $google_analytic_code;



?>
