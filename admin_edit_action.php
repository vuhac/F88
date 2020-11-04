<?php
// ----------------------------------------------------------------------------
// Features:	管理員個人資料修改
// File Name:	admin_edit_action.php
// Author:		Mavis
// Related:
// Log:

// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/member_lib.php";


if(!isset($_GET['a']) || !check_searchid($_GET['a'])) {
  die($tr['Illegal test']);
}else{
  $action = $_GET['a'];
}

//var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);

// 修改管理員資料
if($action == 'edit_admin_data' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  $pk = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);

  check_permissions($pk);

  // 要檢查的欄位
  $to_check_item = array('realname','mobilenumber','email');

  $update_sql = '';
  for($i = 0;$i < count($to_check_item); $i++){
    $input = '';

    if(!isset($_POST[$to_check_item[$i]]) || empty($_POST[$to_check_item[$i]])){
      continue;
    }

    switch($to_check_item[$i]){
      case 'mobilenumber':
      case 'email:':
        $regexp_arr = [
          'mobilenumber'=> '^13[0-9]{1}[0-9]{8}|^15[0-9]{1}[0-9]{8}|^18[8-9]{1}[0-9]{8}'
        ];

        $colname_arr = [
          'mobilenumber'=> $tr['Cell phone'],
          'email'=> $tr['Email']
        ];

        $regexp = ($to_check_item[$i] != 'email') ? $regexp_arr[$to_check_item[$i]] : '';
        
        if (strpos($_POST[$to_check_item[$i]], ',')) {
          $filter_r = filter_values($_POST[$to_check_item[$i]], $to_check_item[$i]);
        } else {
          $input = str_replace(",", '', $_POST[$to_check_item[$i]]);
          $filter_r = ($to_check_item[$i] != 'email') ? filter_var($input, FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/$regexp/"))) : filter_var($input, FILTER_VALIDATE_EMAIL);
        }

        if (!$filter_r) {
          ${$to_check_item[$i]} = '';

          $msg = $colname_arr[$to_check_item[$i]].'格式错误，请重新输入';
          echo '<script>alert("'.$msg.'");</script>';
          die();
        }

        ${$to_check_item[$i]} = $filter_r;
        break;

      default:
        ${$to_check_item[$i]} = filter_var($_POST[$to_check_item[$i]], FILTER_SANITIZE_STRING);
        break;

    }

    if (${$to_check_item[$i]} != '') {
      $update_sql = $update_sql."UPDATE root_member SET ".$to_check_item[$i]." = '".${$to_check_item[$i]}."', changetime = now() WHERE id = '$pk';";
    } 
  }

  if($update_sql != ''){
    // 組合要執行的 sql
    $transaction_sql = 'BEGIN;'
            .$update_sql
            .'COMMIT;';

    // 執行 transaction sql
    $transaction_sql_result = runSQLtransactions($transaction_sql);

    // $tr['Personal and accounting information updated successfully'] = '個人及帳務資料更新成功。';
    if($transaction_sql_result) {
      $logger = "Member id = $pk Change mamber data success.";
      memberlog2db($_SESSION['agent']->account,'admin_edit','notice', "$logger");
      $logger = $tr['Personal and accounting information updated successfully'];
      echo '<script>alert("'.$logger.'");location.href="admin_edit.php?i='.$pk.'";</script>';
      // echo '<script>alert("'.$logger.'");location.reload();</script>';
    }else{
      $logger = "Member id = $pk Change mamber data false.";
      memberlog2db($_SESSION['agent']->account,'admin_edit','warning', "$logger");
      // $tr['Personal and accounting data update failed'] = '個人及帳務資料更新失敗。';
      $logger = $tr['Personal and accounting data update failed'];
      echo '<script>alert("'.$logger.'");window.location.reload();</script>';
    }
  }
    
}elseif($action == 'one_btn_change_password' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // 一鍵改密碼
  $pk = filter_var($_POST['pk'],FILTER_SANITIZE_NUMBER_INT);
  $pwtype = filter_var($_POST['pwtype'],FILTER_SANITIZE_STRING);

  $pw_colname = explode('one_btn_change_',$pwtype);

  $member_data = (object)get_memberdata_byid($pk);
  if (!$member_data->status) {
    echo '<script>alert("'.$member_data->result.'");location.reload();</script>';
    die();
  }

  $check_result = (object)check_member_therole($member_data->result);
  if (!$check_result->status) {
    memberlog2db($_SESSION['agent']->account,'admin_edit','warning', "$check_result->result");
    echo '<script>alert("'.$check_result->result.'");location.reload();</script>';
    die();
  }

  $change_pw = (object)one_btn_changepw($member_data->result->id, $pw_colname[1]);
  if (!$change_pw->status) {
    memberlog2db($_SESSION['agent']->account,'admin_edit','warning', "$change_pw->result");
    echo '<script>alert("'.$change_pw->result.'");location.reload();</script>';
    die();
  }

  memberlog2db($_SESSION['agent']->account,'admin_edit','notice', "$change_pw->result");
  echo '<script>alert("'.$change_pw->result.'");</script>';
  echo '<p align="center"><button type="button" class="btn btn-success" onclick="location.reload();">'.$change_pw->result.'</button></p>';

}elseif($action == 'test' AND isset($_SESSION['member']) AND $_SESSION['member']->therole != 'T'){

}

function check_permissions($id)
{
  $member_data = (object)get_memberdata_byid($id);
  if (!$member_data->status) {
    $error_mag = $member_data['result'];
    echo '<script>alert("'.$error_mag.'");</script>';
    die();
  }

  $check_therole_result = (object)check_member_therole($member_data->result);
  if (!$check_therole_result->status) {
    $error_mag = $check_therole_result->result;
    echo '<script>alert("'.$error_mag.'");</script>';
    die();
  }
}

function one_btn_changepw($id, $colname)
{
  $new_passwd = mt_rand(10000000,99999999);
  $new_passwd_sha1 = sha1($new_passwd);

  $sql = <<<SQL
  UPDATE root_member 
  SET $colname = '$new_passwd_sha1', 
      changetime = now() 
  WHERE id = '$id'
SQL;

  $result = runSQL($sql);

  if (!$result) {
    $error_msg = '修改密碼失敗';
    return array('status' => false, 'result' => $error_msg);
  }

  $success_msg = '修改密碼成功，新密碼為 : '.$new_passwd;
  return array('status' => true, 'result' => $success_msg);
}

function filter_values($input, $colname)
{
  $result = '';
  $filter_r = array();
  $input_list = explode(',', $input);

  foreach ($input_list as $k => $v) {
    if ($v == '') {
      continue;
    }

    switch ($colname) {
      case 'mobilenumber':
        $regexp = '^13[0-9]{1}[0-9]{8}|^15[0-9]{1}[0-9]{8}|^18[8-9]{1}[0-9]{8}';
        $mobilenumber = filter_var($v, FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/$regexp/")));

        if ($mobilenumber != '') {
          $filter_r[] = $mobilenumber;
        }
        break;
      case 'email':
        $email = filter_var($v, FILTER_VALIDATE_EMAIL);
        
        if ($email != '') {
          $filter_r[] = $email;
        }
        break;
      default:
        $input_v = filter_var($v, FILTER_SANITIZE_STRING);

        if ($input_v != '') {
          $filter_r[] = $input_v;
        }
        break;
    }
  }

  $result = implode(",", $filter_r);

  return $result;
}

?>