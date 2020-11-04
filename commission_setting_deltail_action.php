<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 針對 commission_setting_deltail.php 及 add_commission_setting.php 執行對應動作
// File Name:	commission_setting_deltail_action.php
// Author:		Neil
// Related:		對應 commission_setting_deltail.php
// DB Table:  root_commission
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/commission_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

function validatedata($post)
{
  $result = [
    'id' => '',
    'group_name' => '',
    'status' => '0',
    'iscopy' => '0',
    'lowest_bet' => '',
    'lowest_deposit' => '',
    'payoff' => '',
    'effective_member' => '',
    'offer' => '',
    'favorable' => '',
    'commission_setting_json' => '',
    'downline_effective_bet' => '',
    'downline_deposit' => ''
  ];

  if (isset($post['id'])) {
    $id = filter_var($post['id'], FILTER_SANITIZE_STRING);

    if ($id == '') {
      $errtext = '請確認所有欄位皆已正確填入';
    echo "<script>alert('".$errtext."');</script>";
    die();
    }

    $result['id'] = $id;
  }

  // $id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
  $group_name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);

  if ($group_name == '' || $status == '') {
    $errtext = '请确认所有栏位皆已正确填入';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['group_name'] = $group_name;
  $result['status'] = $status;

  if (isset($post['iscopy'])) {
    $iscopy = round(filter_var($post['iscopy'], FILTER_SANITIZE_NUMBER_INT), 0);
    if (filter_var($iscopy, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>1))) === false) {
      $errtext = '新增請求錯誤';
      echo "<script>alert('".$errtext."');</script>";
      die();
    }

    $result['iscopy'] = $iscopy;
  }
  $lowest_bet = round(filter_var($_POST['lowest_bet'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 0);
  if (filter_var($lowest_bet, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>99999999))) === false) {
    $errtext = '有效会员最低投注额 '.$lowest_bet.' , 变量值不在合法范围 (0~99999999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['lowest_bet'] = $lowest_bet;

  $lowest_deposit = round(filter_var($_POST['lowest_deposit'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 0);
  if (filter_var($lowest_deposit, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>99999))) === false) {
    $errtext = '有效会员最低存款金额 '.$lowest_deposit.' , 变量值不在合法范围 (0~9999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['lowest_deposit'] = $lowest_deposit;

  $payoff = round(filter_var($_POST['payoff'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 0);
  if (filter_var($payoff, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>9999999))) === false) {
    $errtext = '派彩金额门槛 '.$payoff.' , 变量值不在合法范围 (0~9999999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['payoff'] = $payoff;

  $effective_member = round(filter_var($_POST['effective_member'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 0);
  if (filter_var($effective_member, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>9999999))) === false) {
    $errtext = '有效会员门槛 '.$effective_member.' , 变量值不在合法范围 (0~9999999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['effective_member'] = $effective_member;

  if (isset($_POST['downline_effective_bet'])) {
    $result['downline_effective_bet'] =
    round(filter_var($_POST['downline_effective_bet'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),0);
  }

  if (isset($_POST['downline_deposit'])) {
    $result['downline_deposit'] =
    // round(filter_var($_POST['downline_deposit'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION)/100,4);
    round(filter_var($_POST['downline_deposit'], FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION)/100,4);
  }
  // var_dump( $result['downline_deposit']);die();

  $offer = round(filter_var($_POST['offer'], FILTER_SANITIZE_NUMBER_INT), 0);
  if (filter_var($offer, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>100))) === false) {
    $errtext = '一级代理承担优惠比例 '.$offer.' , 变量值不在合法范围 (0~100)% 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['offer'] = $offer;

  $favorable = round(filter_var($_POST['favorable'], FILTER_SANITIZE_NUMBER_INT), 0);
  if (filter_var($offer, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>100))) === false) {
    $errtext = '一级代理承担反水比例 '.$favorable.' , 变量值不在合法范围 (0~100)% 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['favorable'] = $favorable;

  $casino_gametype_list = get_casinolist();

  if (!$casino_gametype_list['status']) {
    $errtext = '娱乐城与游戏类别查询错误。';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $commission_json = json_decode($_POST['commission_json'], true);
  $commission_list = get_commission_json($commission_json, $casino_gametype_list);
  $commission_setting_json = json_encode($commission_list['commission_list']);

  $result['commission_setting_json'] = $commission_setting_json;

  return $result;
}

if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = $_GET['a'];
} else {
  die('(x)不合法的測試');
}
//var_dump($_SESSION);
// var_dump($_GET);

if($action == 'add_commission_setting' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // var_dump($_POST);

  $post_validate = validatedata($_POST);

  $name = $post_validate['group_name'];
  // var_dump($post_validate);die();

  if ($post_validate['iscopy']) {
    $setting_name = get_commission_setting_name_byid($post_validate['id']);

    if (!$setting_name) {
      echo "<script>alert('設定名稱查詢錯誤');</script>";
      die();
    }

    $name = $setting_name->name;

    $check_setup_repeatedly_sql = "SELECT * FROM root_commission WHERE (name = '".$name."' AND payoff = '".$post_validate['payoff']."') OR( name = '".$name."' AND downline_effective_bet ='".$post_validate['downline_effective_bet']."');";
    $err_text = $tr['Commission setting'].$name.'已存在相同派彩或相同下线全部有效会员最低投注额。';
  } else {
    $check_setup_repeatedly_sql = "SELECT * FROM root_commission WHERE name = '".$name."';";
    $err_text = $tr['Commission setting'].$name.' 已存在相同设定群组。';
  }

  $check_setup_repeatedly_sql_result = runSQLall($check_setup_repeatedly_sql);
  // 假如已存在佣金設定(name、payout、下線有效投注皆相同)，則刪除此筆，以方便新增
  if (!empty($check_setup_repeatedly_sql_result[0])) {
    if (($check_setup_repeatedly_sql_result[1]->deleted == '0')) {
      echo "<script>alert('" . $err_text . "');</script>";
      die();
    } elseif (($check_setup_repeatedly_sql_result[1]->deleted == '1')) {
      $activated_existed_group_name_sql = "UPDATE root_commission SET deleted = '0' WHERE name = '".$name."' AND deleted = '1';";
      $update_commission_sql_result = runSQL($activated_existed_group_name_sql);
      if ($update_commission_sql_result) {
        echo "<script>alert('".$tr['commission added setting success']."');location.href = './commission_setting.php';</script>";
      } else {
        echo "<script>alert('".$tr['commission added setting fail']."');</script>";
      }
    }
    exit;
  }

  $insert_value = "('".$name."', '".$post_validate['status']."', '".$post_validate['lowest_bet']."', '".$post_validate['lowest_deposit']."', '".$post_validate['payoff']."', '".$post_validate['effective_member']."', '".$post_validate['offer']."', '".$post_validate['favorable']."', '".$post_validate['commission_setting_json']."', '".$post_validate['group_name']."','".$post_validate['downline_effective_bet']."','".$post_validate['downline_deposit']."');";

  $add_commission_sql = 'INSERT INTO root_commission (name, status, lowest_bet, lowest_deposit, payoff, effective_member, offer, favorable, commission, group_name,downline_effective_bet,downline_deposit) VALUES '.$insert_value;
  // echo($add_commission_sql);die();
  $add_commission_sql_result = runSQL($add_commission_sql);

  if ($add_commission_sql_result) {
    echo "<script>alert('".$tr['commission added setting success']."');location.href = './commission_setting.php';</script>";
  } else {
    echo "<script>alert('".$tr['commission added setting fail']."');</script>";
  }
// ----------------------------------------------------------------------------
} elseif($action == 'edit_commission_setting' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // var_dump($_POST);

  $post_validate = validatedata($_POST);
  // var_dump($post_validate);die();
  $commission_setting = get_commission_setting($post_validate['id']);

  if (!$commission_setting['status']) {
    $errtext = '佣金设定 '.$name.' 打码量 '.$payoff.' 设定查询错误或已被删除。';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $check_setup_repeatedly_sql = "SELECT * FROM root_commission WHERE
  (name = '".$commission_setting['result']->name."' AND payoff = '".$post_validate['payoff']."' AND id != '".$post_validate['id']."') OR
  (name = '".$commission_setting['result']->name."' AND downline_effective_bet = '".$post_validate['downline_effective_bet']."' AND id != '".$post_validate['id']."');";
  // echo($check_setup_repeatedly_sql);die();
  $check_setup_repeatedly_sql_result = runSQLall($check_setup_repeatedly_sql);

  if (!empty($check_setup_repeatedly_sql_result[0])) {
    $errtext = '佣金设定 '.$post_validate['group_name'].' 已存在(相同派彩)或(相同下线全有效会员最低投注额)。';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $sql = <<<SQL
  UPDATE root_commission
  SET group_name = '{$post_validate['group_name']}',
      status = '{$post_validate['status']}',
      lowest_bet = '{$post_validate['lowest_bet']}',
      lowest_deposit = '{$post_validate['lowest_deposit']}',
      payoff = '{$post_validate['payoff']}',
      effective_member = '{$post_validate['effective_member']}',
      offer = '{$post_validate['offer']}',
      favorable = '{$post_validate['favorable']}',
      commission = '{$post_validate['commission_setting_json']}',
      downline_effective_bet = '{$post_validate['downline_effective_bet']}',
      downline_deposit = '{$post_validate['downline_deposit']}'
  WHERE id = '{$post_validate['id']}';
SQL;

  $sql .= <<<SQL
  UPDATE root_commission
  SET group_name = '{$post_validate['group_name']}'
  WHERE name = '{$commission_setting['result']->name}';
SQL;

  $transaction_sql = 'BEGIN;'
        .$sql
        .'COMMIT;';

  $debug = 0;
  if($debug) {
    echo '<pre>';
    print_r($transaction_sql);
    echo '</pre>';
  }

  // 執行交易 SQL
  $transaction_result = runSQLtransactions($transaction_sql);

  if ($transaction_result) {
    echo "<script>alert('".$tr['commission setting update success']."');location.href = './commission_setting.php';</script>";
  } else {
    echo "<script>alert('".$tr['commission setting update fail']."');</script>";
  }

// ----------------------------------------------------------------------------
} elseif($action == 'del_commission_setting') {
// ----------------------------------------------------------------------------
// var_dump($_POST);

  $id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
  $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  $payoff = filter_var($_POST['payoff'], FILTER_SANITIZE_STRING);
  $default_commission = default_commission_setting();

  if ($id != '' AND $name != '' AND $payoff != '') {
    $commission_sql = "SELECT * FROM root_commission WHERE id = '$id' AND group_name = '$name' AND payoff = '$payoff' AND deleted = '0';";
    $commission_sql_result = runSQLall($commission_sql);

    if ($commission_sql_result[0] >= 1) {

      $agent_count_sql = "SELECT COUNT(account) AS account_count FROM root_member WHERE commissionrule = '".$commission_sql_result[1]->name."' AND therole = 'A';";
      $agent_count_sql_result = runSQLall($agent_count_sql);

      if ($agent_count_sql_result[0] >= 1) {
        $agent_count = $agent_count_sql_result[1]->account_count;
      } else {
        $agent_count = 'error';
      }

      if ($commission_sql_result[1]->id == $default_commission->id) {
        echo "<script>alert('此为预设佣金设定，无法删除');</script>";
      } elseif ($agent_count > 0) {
        echo "<script>alert('".$tr['can not delete existing agent']."');</script>";
      } elseif ($agent_count === 'error') {
        echo "<script>alert('".$tr['agent query error cannot be deleted']."');</script>";
      } else {
        $update_commission_sql = "UPDATE root_commission SET deleted = '1' WHERE id = '".$id."';";
        $update_commission_sql_result = runSQL($update_commission_sql);

        if ($update_commission_sql_result) {
          echo "<script>alert('".$tr['commission setting delete successfully']."');location.href = './commission_setting.php';</script>";
        } else {
          echo "<script>alert('".$tr['commission setting delete fail']."');</script>";
        }
      }

    } else {
      echo "<script>alert('".$tr['Commission setting']." $name ".$tr['Payout']." $payoff ".$tr['query error or deleted']."');</script>";
    }

  } else {
    echo "<script>alert('".$tr['please confirm name of the commission setting and payout has been filled in']."');</script>";
  }

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  // var_dump($_POST);
  // echo 'ERROR';

}

?>
