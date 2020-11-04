<?php
// ----------------------------------------------------------------------------
// Features:	前端 -- 針對 member_edit.php 程式的修改欄位資料做後端的處理
// File Name:	member_edit_action.php
// Author:		Barkley
// Related:   member.php
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
}
$action = $_GET['a'];

//var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);


if($action == 'member_editpersondata' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // var_dump($_POST);

  $pk = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);

  check_permissions($pk);

  // 要檢查的欄位
  $check_item = array('nickname', 'realname', 'mobilenumber', 'email', 'birthday', 'sex', 'wechat', 'qq', 'bankname', 'bankaccount', 'bankprovince', 'bankcounty');

  $update_sql = '';
  for ($i=0; $i < count($check_item) ; $i++) {
    $input = '';

    if (!isset($_POST[$check_item[$i]]) || strlen($_POST[$check_item[$i]]) == 0) {
      continue;
    }

    switch ($check_item[$i]) {
      case 'mobilenumber':
      case 'email':
      case 'wechat':
      case 'qq':
        // $regexp_arr = [
        //   'wechat' => '^[a-zA-Z]\w{5,20}$',
        //   'qq' => '^[0-9]{5,9}$',
        //   'mobilenumber' => '^13[0-9]{1}[0-9]{8}|^15[0-9]{1}[0-9]{8}|^18[8-9]{1}[0-9]{8}'
        // ];

        $colname_arr = [
          'wechat' => $tr['sns1'],
          'qq' => $tr['sns2'],
          'mobilenumber' => $tr['Cell phone'],
          'email' => $tr['Email']
        ];

        // $regexp = ($check_item[$i] != 'email') ? $regexp_arr[$check_item[$i]] : '';

        if (strpos($_POST[$check_item[$i]], ',')) {
          $filter_r = filter_values($_POST[$check_item[$i]], $check_item[$i]);
        } else {
          $input = str_replace(",", '', $_POST[$check_item[$i]]);
          // $filter_r = ($check_item[$i] != 'email') ? filter_var($input, FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/$regexp/"))) : filter_var($input, FILTER_VALIDATE_EMAIL);
          $filter_r = ($check_item[$i] != 'email') ? filter_var($input, FILTER_SANITIZE_STRING) : filter_var($input, FILTER_VALIDATE_EMAIL);
        }
            
        if (!$filter_r) {
          ${$check_item[$i]} = '';

          $msg = $colname_arr[$check_item[$i]].'格式错误，请重新输入';
          echo '<script>alert("'.$msg.'");</script>';
          die();
        }

        ${$check_item[$i]} = $filter_r;
        break;

      case 'bankaccount':
        $regexp = '^[0-9]*$';
        $filter_r = filter_var($_POST[$check_item[$i]], FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/$regexp/")));

        if (!$filter_r) {
          ${$check_item[$i]} = '';

          $msg = $tr['bank number'].'格式错误，请重新输入';
          echo '<script>alert("'.$msg.'");</script>';
          die();
        }

        ${$check_item[$i]} = $filter_r;
        break;
      case 'birthday':
        ${$check_item[$i]} = filter_var($_POST[$check_item[$i]], FILTER_SANITIZE_STRING);
        ${$check_item[$i]} = str_replace('/', '', ${$check_item[$i]});
        break;
      case 'sex':
        ${$check_item[$i]} = filter_var($_POST[$check_item[$i]], FILTER_SANITIZE_NUMBER_INT);
        $msg = $tr['Gender'].'格式错误，请重新输入';        
        !in_array(${$check_item[$i]}, ['0', '1', '2'], true) and die("<script>alert('$msg');</script>");
        break;  
      default:
        ${$check_item[$i]} = filter_var($_POST[$check_item[$i]], FILTER_SANITIZE_STRING);
        break;
    }
    if (${$check_item[$i]} != '') {
      $update_sql = $update_sql."UPDATE root_member SET ".$check_item[$i]." = '".${$check_item[$i]}."', changetime = now() WHERE id = '$pk';";
    }
  }
  
  if ($update_sql != '') {
    // 組合要執行的 sql
    $transaction_sql = 'BEGIN;'
            .$update_sql
            .'COMMIT;';

    // 執行 transaction sql
    $transaction_sql_result = runSQLtransactions($transaction_sql);

    // $tr['Personal and accounting information updated successfully'] = '個人及帳務資料更新成功。';
    if($transaction_sql_result) {
      $logger = "Member id = $pk Change mamber data success.";
      memberlog2db($_SESSION['agent']->account,'member_edit','notice', "$logger");
      $logger = $tr['Personal and accounting information updated successfully'];
      echo '<script>alert("'.$logger.'");location.href="member_account.php?a='.$pk.'";</script>';
      // echo '<script>alert("'.$logger.'");location.reload();</script>';
    }else{
      $logger = "Member id = $pk Change mamber data false.";
      memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
      // $tr['Personal and accounting data update failed'] = '個人及帳務資料更新失敗。';
      $logger = $tr['Personal and accounting data update failed'];
      echo '<script>alert("'.$logger.'");window.location.reload();</script>';
    }
  }

}elseif($action == 'member_editpersondata_passwordm' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// 使用者修改自己的資料, 對應前台 member.php 檔案的功能
// 可以修改的欄位：會員密碼
// ----------------------------------------------------------------------------
  // var_dump($_POST);
  $pk         = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  $current_password       = filter_var($_POST['current_password'], FILTER_SANITIZE_STRING);
  $change_password_valid1       = filter_var($_POST['change_password_valid1'], FILTER_SANITIZE_STRING);
  $change_password_valid2       = filter_var($_POST['change_password_valid2'], FILTER_SANITIZE_STRING);

  if (empty($pk) || empty($current_password) || empty($change_password_valid1) || empty($change_password_valid2)) {
    // $tr['Modified value can not be null, please re-confirm your entry'] = '修改的值不可為空值，請重新確認輸入內容再操作。';
    $logger = $tr['Modified value can not be null, please re-confirm your entry'];
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  check_permissions($pk);

  if ($change_password_valid1 != $change_password_valid2) {
    $logger = $tr['The new password, before and after the input is not the same, please re-enter'];
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  $update_password_sql = "UPDATE root_member SET passwd = '".$change_password_valid1."', changetime = now() WHERE id = '".$pk."' AND passwd = '".$current_password."';";
  // 20181122 yaoyuan 取消執行修改密碼選項，只留一鍵修改密碼 gpk要求，lala 同意
  // $update_password_sql_result = runSQL($update_password_sql);

  if($update_password_sql_result == 1) {
    $logger = "Member account ".$sql_result[1]->account." change password to $change_password_valid1 success.";
    memberlog2db($_SESSION['agent']->account,'member_edit','notice', "$logger");
    // $tr['Member password is modified'] = '會員密碼修改完成。';
    echo '<script>alert("'.$tr['Member password is modified'].'");location.reload();</script>';
  } else {//$tr['member personal password is now incorrectly entered'] = '會員個人現在的密碼輸入錯誤。';
    $logger = $tr['member personal password is now incorrectly entered'];
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

}elseif($action == 'member_editpersondata_passwordw' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// 使用者修改自己的資料, 對應前台 member.php 檔案的功能
// 可以修改的欄位：修改會員提款密碼
// ----------------------------------------------------------------------------
  // var_dump($_POST);
  $pk         = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  $withdrawal_password       = filter_var($_POST['withdrawal_password'], FILTER_SANITIZE_STRING);
  $change_withdrawalpassword_valid1       = filter_var($_POST['change_withdrawalpassword_valid1'], FILTER_SANITIZE_STRING);
  $change_withdrawalpassword_valid2       = filter_var($_POST['change_withdrawalpassword_valid2'], FILTER_SANITIZE_STRING);

  if (empty($pk) || empty($withdrawal_password) || empty($change_withdrawalpassword_valid1) || empty($change_withdrawalpassword_valid2)) {
    // $tr['Modified value can not be null, please re-confirm your entry'] = '修改的值不可為空值，請重新確認輸入內容再操作。';
    $logger = $tr['Modified value can not be null, please re-confirm your entry'];
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  check_permissions($pk);

  if($change_withdrawalpassword_valid1 != $change_withdrawalpassword_valid2) {
    //$tr['The new password, before and after the input is not the same, please re-enter'] = '新的密碼，前後輸入不一樣，請重新輸入。'
    $logger = $tr['The new password, before and after the input is not the same, please re-enter'];
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  $update_password_sql = "UPDATE root_member SET withdrawalspassword = '".$change_withdrawalpassword_valid1."', changetime = now() WHERE id = '".$pk."' AND withdrawalspassword = '".$withdrawal_password."';";
  $update_password_sql_result = runSQL($update_password_sql);

  if($update_password_sql_result == 1) {
    $logger = "Member account ".$sql_result[1]->account." change withdrawals password to $change_withdrawalpassword_valid1 success.";
    memberlog2db($_SESSION['agent']->account,'member_edit','notice', "$logger");
    // $tr['Withdrawal password is modified'] = '提款密碼修改完成。';
    echo '<script>alert("'.$tr['Withdrawal password is modified'].'");location.reload();</script>';
  }else{
    // $tr['Members now make the wrong password for withdrawal'] = '會員現在的提款密碼輸入錯誤。';
    $logger = $tr['Members now make the wrong password for withdrawal'];
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$logger");
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

}elseif($action == 'one_btn_change_password' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // var_dump($_POST);

  $pk = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  $pwtype = filter_var($_POST['pwtype'], FILTER_SANITIZE_STRING);

  $pw_colname = explode('one_btn_change_',$pwtype);

  $member_data = (object)get_memberdata_byid($pk, true);
  if (!$member_data->status) {
    echo '<script>alert("'.$member_data->result.'");location.reload();</script>';
    die();
  }

  $check_result = (object)check_member_therole($member_data->result);
  if (!$check_result->status) {
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$check_result->result");
    echo '<script>alert("'.$check_result->result.'");location.reload();</script>';
    die();
  }

  $change_pw = (object)one_btn_changepw($member_data->result->id, $pw_colname[1]);
  if (!$change_pw->status) {
    memberlog2db($_SESSION['agent']->account,'member_edit','warning', "$change_pw->result");
    echo '<script>alert("'.$change_pw->result.'");location.reload();</script>';
    die();
  }

  memberlog2db($_SESSION['agent']->account,'member_edit','notice', "$change_pw->result");
  echo '<script>alert("'.$change_pw->result.'");</script>';
  echo '<p align="center"><button type="button" class="btn btn-success" onclick="location.reload();">'.$change_pw->result.'</button></p>';


}elseif($action == 'test' AND isset($_SESSION['member']) AND $_SESSION['member']->therole != 'T' ) {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    // var_dump($_POST);
    // echo 'ERROR';

}

function check_permissions($id)
{
  $member_data = (object)get_memberdata_byid($id, true);
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
