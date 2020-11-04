<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 針對 preferential_calculation_config_deltail.php 執行對應動作
// File Name:	preferential_calculation_config_deltail_action.php
// Author:		Yuan
// Related:		對應 preferential_calculation_config_deltail.php
// DB Table:  root_favorable.casino_list
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/preferential_calculation_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = $_GET['a'];
} else {
  die('(x)不合法的測試');
}
//var_dump($_SESSION);
//var_dump($_POST);
// var_dump($_GET);

function get_favorable_setting_name_byid($id)
{
  $sql = "SELECT DISTINCT group_name, name FROM root_favorable WHERE deleted = '0' AND id = '".$id."' ORDER BY group_name;";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  return $result[1];
}

function get_favorable_setting_byid($id)
{
  $sql = <<<SQL
  SELECT * 
  FROM root_favorable 
  WHERE id = '{$id}' 
  AND deleted = '0';
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  return $result[1];
}

function get_casinolist()
{
  $casino_gametype_sql = 'SELECT casinoid, game_flatform_list FROM casino_list WHERE "open" <> 5;';
  $casino_gametype_sql_result = runSQLall($casino_gametype_sql);

  if (empty($casino_gametype_sql_result[0])) {
    return false;
  }

  unset($casino_gametype_sql_result[0]);
  foreach ($casino_gametype_sql_result as $v) {
    $casino_list[] = $v->casinoid;
    $game_flatform_list[$v->casinoid] = json_decode($v->game_flatform_list, true);
  }

  $result = [
    'status' => true,
    'casino' => $casino_list,
    'game_flatform' => $game_flatform_list
  ];

  return $result;
}

function compare_filter_favorable_json($favorablerate_json, $casino_gametype_list)
{
  foreach ($favorablerate_json as $key => $value) {
    $casino_gametype_text = explode('_',$key);
    $casino = strtoupper($casino_gametype_text[0]);
    $gametype = $casino_gametype_text[1];

    // 比對娛樂城是否和定義檔一樣
    $casino = compare_filter_casino($casino, $casino_gametype_list['casino']);

    // 比對遊戲類別是否和定義檔一樣
    $gametype = compare_filter_gametype($gametype, $casino_gametype_list['game_flatform'], $casino);

    // 過濾各遊戲類型參數值
    $favorable_value = (string)round(filter_var($value, FILTER_SANITIZE_STRING), 2);

    $list['gametype_list'][$gametype] = $favorable_value;
    $list['favorablerate_list'][$casino] = $list['gametype_list'];
  }

  return $list;
}

/**
 * 比對娛樂城是否和定義檔一樣
 *
 * @param [type] $casino - 娛樂城
 * @param [type] $casino_list - 娛樂城列表
 * @return string
 */
function compare_filter_casino($casino, $casino_list)
{
  if (in_array($casino,$casino_list)) {

    $casino = filter_var($casino, FILTER_SANITIZE_STRING);

    if ($casino == '') {
      echo "<script>alert('不合法的娱乐城。');</script>";
      die();
    }
    
  } else {
    echo "<script>alert('不合法的娱乐城。');</script>";
    die();
  }

  return $casino;
}

/**
 * 比對遊戲類別是否和定義檔一樣
 *
 * @param [type] $gametype - 遊戲類型
 * @param [type] $game_flatform_list - 娛樂城個遊戲類型
 * @param [type] $casino - 娛樂城
 * @return string
 */
function compare_filter_gametype($gametype, $game_flatform_list, $casino)
{
  if (in_array($gametype, $game_flatform_list[$casino])) {
    
    $gametype = filter_var($gametype, FILTER_SANITIZE_STRING);

    if ($gametype == '') {
      echo "<script>alert('不合法的游戏类别。');</script>";
      die();
    }

  } else {
    echo "<script>alert('不合法的游戏类别。');</script>";
    die();
  }
  
  return $gametype;
}

function validatedata($post)
{
  $result = [
    'id' => '',
    'group_name' => '',
    'status' => '0',
    'iscopy' => '0',
    'wager' => '',
    'upperlimit' => '',
    'audit' => '',
    'notes' => '',
    'favorablerate_setting_json' => ''
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

  $group_name = filter_var($post['name'], FILTER_SANITIZE_STRING);
  $status = filter_var($post['status'], FILTER_SANITIZE_STRING);

  $notes = filter_var($post['notes'], FILTER_SANITIZE_STRING);

  if ($group_name == '' || $status == '') {
    $errtext = '請確認所有欄位皆已正確填入';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['group_name'] = $group_name;
  $result['status'] = $status;
  $result['notes'] = $notes;

  if (isset($post['iscopy'])) {
    $iscopy = round(filter_var($post['iscopy'], FILTER_SANITIZE_NUMBER_INT), 0);
    if (filter_var($iscopy, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>1))) === false) {
      $errtext = '新增請求錯誤';
      echo "<script>alert('".$errtext."');</script>";
      die();
    }

    $result['iscopy'] = $iscopy;
  }

  $wager = round(filter_var($post['wager'], FILTER_SANITIZE_NUMBER_INT), 0);
  if (filter_var($wager, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>9999999))) === false) {
    $errtext = '打码量 '.$wager.' , 变量值不在合法范围 (0~9999999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['wager'] = $wager;

  $upperlimit = round(filter_var($post['upperlimit'], FILTER_SANITIZE_NUMBER_INT), 0);
  if (filter_var($upperlimit, FILTER_VALIDATE_INT, array("options" => array("min_range"=>0, "max_range"=>9999999))) === false) {
    $errtext = '反水上限 '.$upperlimit.' , 变量值不在合法范围 (0~9999999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['upperlimit'] = $upperlimit;

  $audit = round(filter_var($post['audit'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION), 2);
  if (filter_var($audit, FILTER_VALIDATE_FLOAT, array("options" => array("min_range"=>0, "max_range"=>999))) === false) {
    $errtext = '稽核倍数 '.$audit.' , 变量值不在合法范围 (0~999) 内';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $result['audit'] = $audit;

  $casino_gametype_list = get_casinolist();

  if (!$casino_gametype_list) {
    echo "<script>alert('娛樂城與遊戲類別查詢錯誤。');</script>";
    die();
  }

  $favorablerate_json = json_decode($post['favorablerate_json'], true);
  $favorablerate_setting = compare_filter_favorable_json($favorablerate_json, $casino_gametype_list);
  $favorablerate_setting_json = json_encode($favorablerate_setting['favorablerate_list']);

  $result['favorablerate_setting_json'] = $favorablerate_setting_json;

  return $result;
}

if($action == 'add_favorable_setting' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// var_dump($_POST);
  
  $post_validate = validatedata($_POST);

  $name = $post_validate['group_name'];

  if ($post_validate['iscopy']) {
    $setting_name = get_favorable_setting_name_byid($post_validate['id']);

    if (!$setting_name) {
      echo "<script>alert('設定名稱查詢錯誤');</script>";
      die();
    }

    $name = $setting_name->group_name;

    $check_setup_repeatedly_sql = "SELECT name, wager FROM root_favorable WHERE group_name = '".$name."' AND wager = '".$post_validate['wager']."' AND deleted = '0';";
    $err_text = '反水等級 '.$post_validate['group_name'].' 已存在相同打碼量設定。';
  } else {
    $check_setup_repeatedly_sql = "SELECT name, wager FROM root_favorable WHERE (group_name = '".$name."' OR (group_name = '".$name."' AND wager = '".$post_validate['wager']."')) AND deleted = '0';";
    $err_text = '反水等級 '.$post_validate['group_name'].' 已存在相同設定群組。';
  }

  $check_setup_repeatedly_sql_result = runSQLall($check_setup_repeatedly_sql);

  if (!empty($check_setup_repeatedly_sql_result[0])) {
    echo "<script>alert('".$err_text."');</script>";
    die();
  }
  /*
  if ($post_validate['notes'] != '') {
    $insert_value = "('".$name."', '".$post_validate['status']."', '".$post_validate['wager']."', '".$post_validate['upperlimit']."', '".$post_validate['audit']."', '".$post_validate['favorablerate_setting_json']."', '".$post_validate['notes']."', '".$post_validate['group_name']."');";
  } else {
    $insert_value = "('".$name."', '".$post_validate['status']."', '".$post_validate['wager']."', '".$post_validate['upperlimit']."', '".$post_validate['audit']."', '".$post_validate['favorablerate_setting_json']."', NULL, '".$post_validate['group_name']."');";
  }
  */
  
  $check_deleted_setup = "SELECT MIN(id) AS id FROM root_favorable WHERE group_name = '".$name."' AND deleted = '1';";
  $check_deleted_setup_result = runSQLall($check_deleted_setup);

  if(!is_null($check_deleted_setup_result[1]->id) && $check_deleted_setup_result[0] == 1){
    $add_favorable_sql = <<<SQL
      UPDATE root_favorable
      SET deleted = '0', status='{$post_validate['status']}'
      WHERE id='{$check_deleted_setup_result[1]->id}' AND group_name='{$name}'
    SQL;
  }else{
    $add_favorable_sql = <<<SQL
      INSERT INTO root_favorable (name, status, wager, upperlimit, audit, favorablerate, notes, group_name) 
      VALUES ('{$name}', '{$post_validate['status']}', '{$post_validate['wager']}', '{$post_validate['upperlimit']}', '{$post_validate['audit']}', '{$post_validate['favorablerate_setting_json']}', '{$post_validate['notes']}', '{$post_validate['group_name']}')
    SQL;
  }

  $add_favorable_sql_result = runSQL($add_favorable_sql);

  if ($add_favorable_sql_result) {
    echo "<script>alert('反水設定新增成功。');location.href = './preferential_calculation_config.php';</script>";
  } else {
    echo "<script>alert('反水設定新增失敗。');</script>";
  }

// ----------------------------------------------------------------------------
} elseif($action == 'edit_favorable_setting' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//  var_dump($_POST);

  $post_validate = validatedata($_POST);

  $favorable_setting = get_favorable_setting_byid($post_validate['id']);

  if (!$favorable_setting) {
    $errtext = '反水設定 '.$post_validate['name'].' 打碼量 '.$post_validate['wager'].' 設定查詢錯誤或已被刪除。';
    echo "<script>alert('".$errtext."');</script>";
    die();
  }

  $check_setup_repeatedly_sql = "SELECT name, wager FROM root_favorable WHERE name = '".$favorable_setting->name."' AND wager = '".$post_validate['wager']."' AND id != '".$post_validate['id']."' AND deleted = '0';";
  $check_setup_repeatedly_sql_result = runSQLall($check_setup_repeatedly_sql);

  if (!empty($check_setup_repeatedly_sql_result[0])) {
    echo "<script>alert('反水等級 ".$post_validate['group_name']." 已存在相同打碼量設定。');</script>";
    die();
  }

  $sql = <<<SQL
  UPDATE root_favorable 
  SET group_name = '{$post_validate['group_name']}', 
      status = '{$post_validate['status']}', 
      wager = '{$post_validate['wager']}', 
      upperlimit = '{$post_validate['upperlimit']}', 
      audit = '{$post_validate['audit']}', 
      favorablerate = '{$post_validate['favorablerate_setting_json']}', 
      notes = '{$post_validate['notes']}' 
  WHERE id = '{$post_validate['id']}';
SQL;

  $sql .= <<<SQL
  UPDATE root_favorable 
  SET group_name = '{$post_validate['group_name']}' 
  WHERE name = '{$favorable_setting->name}';
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

  $transaction_result = runSQLtransactions($transaction_sql);

  if ($transaction_result) {
    echo "<script>alert('反水設定更新成功。');location.href = './preferential_calculation_config.php';</script>";
  } else {
    echo "<script>alert('反水設定更新失敗。');</script>";
  }

// ----------------------------------------------------------------------------
} elseif($action == 'del_favorable_setting') {
// ----------------------------------------------------------------------------
// var_dump($_POST);

  $id = filter_var($_POST['id'], FILTER_SANITIZE_STRING);
  $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  $wager = filter_var($_POST['wager'], FILTER_SANITIZE_STRING);
  $default_favorable = default_favorable_setting();

  if ($id != '' AND $name != '' AND $wager != '') {
    $favorable_sql = "SELECT * FROM root_favorable WHERE id = '$id' AND group_name = '$name' AND wager = '$wager' AND deleted = '0';";
    $favorable_sql_result = runSQLall($favorable_sql);

    if ($favorable_sql_result[0] >= 1) {

      $member_count_sql = "SELECT COUNT(account) AS account_count FROM root_member WHERE favorablerule = '".$name."' AND therole = 'M';";
      $member_count_sql_result = runSQLall($member_count_sql);

      if ($member_count_sql_result[0] >= 1) {
        $member_count = $member_count_sql_result[1]->account_count;
      } else {
        $member_count = 'error';
      }

      $agent_count_sql = "SELECT COUNT(account) AS account_count FROM root_member WHERE favorablerule = '".$name."' AND therole = 'A';";
      $agent_count_sql_result = runSQLall($agent_count_sql);

      if ($agent_count_sql_result[0] >= 1) {
        $agent_count = $agent_count_sql_result[1]->account_count;
      } else {
        $agent_count = 'error';
      }

      if ($favorable_sql_result[1]->id == $default_favorable->id) {
        echo "<script>alert('此为预设反水设定，无法删除');</script>";
      } elseif ($member_count > 0 OR $agent_count > 0) {
        echo "<script>alert('已有會員及代理不可刪除。');</script>";
      } elseif ($member_count === 'error' OR $agent_count === 'error') {
        echo "<script>alert('會員或代理查詢錯誤不可刪除。');</script>";
      } else {
        $update_favorable_sql = "UPDATE root_favorable SET deleted = '1' WHERE id = '".$id."';";
        $update_favorable_sql_result = runSQL($update_favorable_sql);

        if ($update_favorable_sql_result) {
          echo "<script>alert('反水設定刪除成功。');location.href = './preferential_calculation_config.php';</script>";
        } else {
          echo "<script>alert('反水設定刪除失敗。');</script>";
        }
      }

    } else {
      echo "<script>alert('反水等級 $name 打碼量 $wager 設定查詢錯誤或已被刪除。');</script>";
    }
    
  } else {
    echo "<script>alert('請確認反水設定名稱及打碼量已正確填入。');</script>";
  }

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  // var_dump($_POST);
  // echo 'ERROR';

}

?>
