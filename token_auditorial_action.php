<?php
// ----------------------------------------------------------------------------
// Features:	清除稽核動作
// File Name:	token_auditorial_action.php
// Author:		Yuan
// Related:   對應 token_auditorial.php 清除稽核 btn
// Log:
//
// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // var_dump($_POST);
  $sql = '';

  $gid = filter_var($_POST['gid'], FILTER_SANITIZE_STRING);

  if (empty($gid)) {
    $msg = '不合法的单号';
    echo '<script>alert("'.$msg.'");</script>';
    die();
  }

  $gid_list = str_replace('gid=','',$gid);
  $gid_list = explode("&", $gid_list);

  foreach ($gid_list as $v) {
    $sql .= update_token_auditorial_sql($v, '1');
  }

  $r = runSQLtransactions($sql);

  if (!$r) {
    $msg = '稽核清除失败';
    echo '<script>alert("'.$msg.'");</script>';
    die();
  }

  $msg = '稽核清除成功';
  echo '<script>alert("'.$msg.'");location.reload();</script>';
}

function update_token_auditorial_sql($gid, $isclear)
{
  $sql = <<<SQL
  UPDATE root_member_auditreport
  SET isclear = '{$isclear}'
  WHERE gtoken_id = '{$gid}';
SQL;

  // $r = runSQL($sql);

  return $sql;
}

