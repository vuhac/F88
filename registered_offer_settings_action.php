<?php
// ----------------------------------------------------------------------------
// Features:	后台-- 會員端設定 - 優惠管理(註冊送彩金) 
// File Name:	registered_offer_settings_action.php
// Author:		
// Related:   
// DB Table:  
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// require_once dirname(__FILE__) ."/protal_setting_lib.php";

// var_dump($_SESSION);
// var_dump($_POST);die();
// var_dump($_GET);


if (!isset($_SESSION['agent']) AND $_SESSION['agent']->therole != 'R') {
  die('帳號權限不合法');
}

$action = '';
if(isset($_POST['action'])) {
  $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
}


$col = [
  'switchs' => [
    'colname' => 'registered_offer_switch_status',
    'description' => '註冊送彩金開關'
  ],
  'gifts' => [
    'colname' => 'registered_offer_gift_amount',
    'description' => '註冊送彩金金額'
  ],
  'reviews' => [
    'colname' => 'registered_offer_review_amount',
    'description' => '住冊送彩金稽核金額'
  ]
];

switch($action) {
  case 'edit':
    $result = [
      'switchs' => false,
      'gifts' => false,
      'reviews' => false
    ];

    foreach ($_POST['data'] as $k => $v) {
      if (!array_key_exists($k, $col)) {
        $error_msg = $tr['no such field,please recheck'];
        echo json_encode(['status' => 'fail', 'result' => $error_msg]);
        die();
      }

      $value = ($v != '') ? filter_var($v, FILTER_SANITIZE_STRING) : '';
      if ($value == '') {
        $errorMsg = $tr['please enter correct content'] ;
        echo json_encode(['status' => 'fail', 'result' => $errorMsg]);
        die();
      }

      $result[$k] = insertUpdateRegisteredOffer($col[$k]['colname'], $value, $col[$k]['description']);
    }

    if ($result['switchs'] && $result['gifts'] && $result['reviews']) {
      echo json_encode(['status' => 'success', 'result' => '更新成功。']);
    }

    break;
  default:
    echo json_encode(['status' => 'fail', 'result' => '(x)不合法的測試']);
    die();
    break;
}


function insertUpdateRegisteredOffer($name, $value, $description)
{
  $sql = <<<SQL
    INSERT INTO root_protalsetting
    (
      setttingname,
      name,
      value,
      status,
      description
    ) VALUES (
      'default',
      '{$name}',
      '{$value}',
      1,
      '{$description}'
    ) ON CONFLICT ON CONSTRAINT "root_protalsetting_setttingname_name"
    DO UPDATE SET value = '{$value}';
SQL;

  $result = runSQL($sql);

  // 強制更新前後台memcache資料
  $memcacheUpdate = memcache_forceupdate();
  // var_dump($result,$update_result);die();
  
  if (!$result) {
    echo json_encode(['status' => 'fail', 'result' => '更新失敗，請重新嘗試。']);
    die();
  }

  return true;
}
?>
