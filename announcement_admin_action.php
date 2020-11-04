<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公告訊息管理 對應 announcement_admin.php
// File Name:	announcement_admin_action.php
// Author:		Yuan
// Related:   服務 announcement_admin.php
// DB Table:  root_announcement
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
//  var_dump($_GET); 
// $tr['Illegal test'] = '(x)不合法的測試。';
} else {
    die($tr['Illegal test']);
}
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

// ----------------------------------------------------------------------------
// 首頁更改 公告狀態
//-----------------------------------------------------------------------------
if($action == 'edit_status' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){

  $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
  $is_open = filter_var($_POST['is_open'], FILTER_SANITIZE_NUMBER_INT);

  if ($id != '' AND $is_open != '') {
    $search_id_sql = "SELECT id  FROM root_announcement WHERE id = '".$id."' AND status != 2;";
    //var_dump($search_id_sql);
    $search_id_sql_result = runSQLALL($search_id_sql);
    // var_dump($search_id_sql_result);

    if ($search_id_sql_result[0] == 1) {
      $edit_sql = "UPDATE root_announcement SET status = '".$is_open."' WHERE id = '".$search_id_sql_result[1]->id."';";
      // var_dump($edit_sql);
      $edit_result = runSQL($edit_sql);
    } else {//$tr['Query error or the data has been deleted'] = '查詢錯誤或者該筆資料已被刪除。';
      $logger = $tr['Query error or the data has been deleted'];
      echo '<script>alert("'.$logger.'");location.reload();</script>';
    }
  } else {//$tr['Wrong attempt'] = '(x)錯誤的嘗試。';
    $logger = $tr['Wrong attempt'];
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }


  // ----------------------------------------------------------------------------
  // 首頁 刪除公告
  //-----------------------------------------------------------------------------
} elseif($action == 'delete' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){

  $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

  if ($id != '') {
    $search_id_sql = "SELECT id FROM root_announcement WHERE id = '".$id."' AND status != 2;";
    // var_dump($search_id_sql);
    $search_id_sql_result = runSQLALL($search_id_sql);
    // var_dump($search_id_sql_result);

    if ($search_id_sql_result[0] == 1) {
      $delete_sql = "UPDATE root_announcement SET status = '2' WHERE id = '".$search_id_sql_result[1]->id."';";
      // var_dump($delete_sql);
      $delete_result = runSQL($delete_sql);

      if ($delete_result) {//$tr['Delete successfully'] = '刪除成功。';
        $logger = $tr['Delete successfully'];
        echo '<script>alert("'.$logger.'");location.reload();</script>';
      } else {//$tr['delete failed'] = '刪除失敗。';
        $logger = $tr['delete failed'];
        echo '<script>alert("'.$logger.'");</script>';
      }

    } else {//$tr['Query error or the data has been deleted'] = '查詢錯誤或者該筆資料已被刪除。';
      $logger = $tr['Query error or the data has been deleted'];
      echo '<script>alert("'.$logger.'");location.reload();</script>';
    }

  } else {//$tr['Wrong attempt'] = '(x)錯誤的嘗試。';
    $logger = $tr['Wrong attempt'];
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
//    var_dump($_POST);

}

?>
