<?php
// ----------------------------------------------------------------------------
// Features:	修改稽核動作
// File Name:	token_auditorial_edit_action.php
// Author:		Yuan
// Related:   對應 token_auditorial_edit.php
// Log:
//
// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $sql = '';

  foreach ($_POST['obj'] as $k => $v) {
    $colname = '';

    if (isset($v['deposit'])) {
      $amount_v = $v['deposit'];
      $colname = 'withdrawal_fee';
    } elseif (isset($v['offer'])) {
      $amount_v = $v['offer'];
      $colname = 'offer_deduction_amount';
    }

    $pk = filter_var($k, FILTER_SANITIZE_NUMBER_INT);
    $audit_amount = filter_var($amount_v, FILTER_SANITIZE_STRING);

    if ($pk == '' || $audit_amount == '' || $colname == '') {
      $msg = '不合法的稽核资讯，请确认后再行尝试';
      echo '<script>alert("'.$msg.'");</script>';
      die();
    }

    $audit_amount = round($audit_amount, 2);

    if ($audit_amount < 0) {
      $msg = '稽核金额不可小于0';
      echo '<script>alert("'.$msg.'");</script>';
      die();
    }

    $sql .= update_token_auditorial_sql($pk, $colname, $audit_amount);
  }

  $r = runSQLtransactions($sql);

  if (!$r) {
    $msg = '稽核修改失败';
    echo '<script>alert("'.$msg.'");</script>';
    die();
  }

  $msg = '稽核修改成功';
  echo '<script>alert("'.$msg.'");location.reload();</script>';
}

function update_token_auditorial_sql($gid, $colname, $audit_amount)
{
  $sql = <<<SQL
  UPDATE root_member_auditreport
  SET {$colname} = '{$audit_amount}'
  WHERE gtoken_id = '{$gid}';
SQL;

  return $sql;
}


