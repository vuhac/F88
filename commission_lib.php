<?php
// ----------------------------------------------------------------------------
// Features:	佣金設定 lib
// File Name:	commission_lib.php
// Author:		Neil
// Related:		
// DB Table:  root_commission.root_member.casino_list
// Log:
// ----------------------------------------------------------------------------

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";

/**
 * 取得所有佣金設定名稱
 *
 * @return array
 */
function get_all_commission_setting_name()
{
  $default_commission_setting_to_first = "CASE WHEN name='預設佣金設定' THEN 0 ELSE 1 END";//將"預設佣金設定"置頂
  $commission_sql = "SELECT DISTINCT name, group_name, $default_commission_setting_to_first FROM root_commission WHERE deleted = '0' ORDER BY $default_commission_setting_to_first ASC, name;";
  $commission_sql_result = runSQLall($commission_sql);

  if ($commission_sql_result[0] >= 1) {
    $commission_name_list = $commission_sql_result;
  } else {
    $commission_name_list = null;
  }

  return $commission_name_list;
}

function get_commission_setting_name_byid($id)
{
  $sql = "SELECT DISTINCT group_name, name FROM root_commission WHERE deleted = '0' AND id = '".$id."' ORDER BY group_name;";
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

/**
 * 取得指定名稱所有佣金設定
 *
 * @return array
 */
function get_specifyname_commission_setting($commission_name,$sel_setting=null)
{
  if($sel_setting=='deposit_bet'){
    $commission_sql = "SELECT * FROM root_commission WHERE name = '" . $commission_name . "' AND deleted = '0' ORDER BY downline_effective_bet;";
  }else{
    $commission_sql = "SELECT * FROM root_commission WHERE name = '".$commission_name."' AND deleted = '0' ORDER BY payoff;";
  }
	$commission_sql_result = runSQLall($commission_sql);
  
  if ($commission_sql_result[0] >= 1) {
    $commission_setting = $commission_sql_result;
  } else {
    $commission_setting = null;
  }

  return $commission_setting;
}

/**
 * 取得所有佣金設定
 *
 * @return array
 */
function get_all_commission_setting()
{
  $sql = <<<SQL
  SELECT * FROM root_commission WHERE deleted = '0' ORDER BY name;
SQL;
	$result = runSQLall($sql);
  
  if (empty($result[0])) {
    $err_text = '佣金設定查詢錯誤';
    return array('status' => false, 'result' => $err_text);
  }

  unset($result[0]);
  foreach ($result as $k => $v) {
    $setting[$v->name][$v->payoff] = $v;
  }

  return $setting;
}

/**
 * 取得單筆佣金設定
 *
 * @param [type] $id - 該筆佣金設定id
 * @return array
 */
function get_commission_setting($id)
{
  $sql = <<<SQL
  SELECT * 
  FROM root_commission 
  WHERE id = '{$id}' 
  AND deleted = '0';
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    $errtext = '佣金設定查詢錯誤';
    return array('status' => false, 'result' => $errtext);
  }

  return array('status' => true, 'result' => $result[1]);
}

/**
 * 判斷等級狀態是否啟用
 *
 * @param [type] $status - 開啟狀態
 * @return string
 */
function commission_isopen($status)
{
  if ($status == '1') {
    $status_checked = 'checked';
  } else {
    $status_checked = '';
  }

  return $status_checked;
}

/**
 * 生成佣金設定啟用狀態html
 *
 * @param [type] $status - 開啟狀態
 * @return string
 */
function get_commission_isopen_html($id, $status)
{
  global $tr;
  $default_commission = default_commission_setting();

  if ($status == 1 && $id == $default_commission->id){
    $status_html = '<span class="label label-info">'.$tr['grade default'].'</span>';
  } elseif ($status == 1) {
    $status_html = '<span class="label label-success">'.$tr['open'].'</span>';
  } else {
    $status_html = '<span class="label label-danger">'.$tr['off'].'</span>';
  }

  return $status_html;
}

/**
 * 取得使用這個佣金等級的代理數量
 *
 * @param [type] $name - 佣金設定名稱
 * @return string
 */
function get_agent_count($name)
{
  $agent_count_sql = "SELECT COUNT(account) AS account_count FROM root_member WHERE commissionrule = '".$name."' AND therole = 'A';";
  $agent_count_sql_result = runSQLall($agent_count_sql);

  if ($agent_count_sql_result[0] >= 1) {
    $agent_count = $agent_count_sql_result[1]->account_count;
  } else {
    $agent_count = 'error';
  }

  return $agent_count;
}

/**
 * 取得娛樂城及遊戲類型列表
 *
 * @return array
 */
function get_casinolist()
{
  $casinoLib = new casino_switch_process_lib();
  $casino_gametype_sql = 'SELECT casinoid, game_flatform_list, display_name FROM casino_list WHERE "open" <> 5;';
  $casino_gametype_sql_result = runSQLall($casino_gametype_sql);
  // var_dump($casino_gametype_sql_result);

  if (empty($casino_gametype_sql_result[0])) {
    $result['status'] = false;
    return $result;
  }

  $casinoNames = [];
  for ($i=1; $i <= $casino_gametype_sql_result[0]; $i++) {
    $casino_list[] = $casino_gametype_sql_result[$i]->casinoid;
    $casinoNames[$casino_gametype_sql_result[$i]->casinoid] = $casinoLib->getCurrentLanguageCasinoName($casino_gametype_sql_result[$i]->display_name, $_SESSION['lang']);
    $game_flatform_list[$casino_gametype_sql_result[$i]->casinoid] = json_decode($casino_gametype_sql_result[$i]->game_flatform_list, true);
  }

  $result['status'] = true;
  $result['casino'] = $casino_list;
  $result['game_flatform'] = $game_flatform_list;
  $result['casinoNames'] = $casinoNames;

  return $result;
}

/**
 * 根據 DB table : casino_list 設定
 * 動態生成退佣比列表
 *
 * @param array  $casino_list        - 娛樂城列表
 * @param array  $game_flatform_list - 各娛樂城擁有的遊戲類型列表
 * @param array  $commission         - 退佣比設定
 * @param string $action             - 由哪個頁面呼叫此方法, edit : commission_setting_deltail.php, add : add_preferential_calculation.php
 * @param array $casinoNames 娛樂城語系名稱
 *
 * @return string
 */

function get_commission_list(array $casino_list, array $game_flatform_list, $commission, $action, $casinoNames)
{
  global $tr;
  $commission_content_html = '';
  foreach ($casino_list as $casino_key => $casino_value) {

    $gametype_html = '';
    $gametype_value_html = '';

    foreach ($game_flatform_list[$casino_value] as $game_flatform_key => $game_flatform_value) {
      // // 娛樂城的分類翻譯
      $tr_casino_list['live']    = $tr['live'];
      $tr_casino_list['game']    = $tr['game'];
      $tr_casino_list['html5']   = $tr['html5'];
      $tr_casino_list['fish']    = $tr['fish'];
      $tr_casino_list['lotto']   = $tr['lotto'];
      $tr_casino_list['lottery'] = $tr['lottery'];
      $tr_casino_list['sports']   = $tr['sports'];
      $tr_casino_list['card']    = $tr['card'];

      // 翻譯後的值, 存在的話使用翻譯的數值. 不存在使用預設
      if(isset($tr_casino_list[$game_flatform_value])){
        $tr_game_flatform_value = $tr_casino_list[$game_flatform_value];
      }else{
        $tr_game_flatform_value = $game_flatform_value;
      }
      $gametype_html = $gametype_html.'<td>'.$tr_game_flatform_value.'(%)</td>';

      if ($action == 'edit') {
        $game_type_value = commission_gametype_value_setting($commission, $casino_value, $game_flatform_value);

        $gametype_value_html = $gametype_value_html.'
        <td>
          <input type="number" step=".01" min="0" class="form-control commission" placeholder="" id="'.strtolower($casino_value).'_'.$game_flatform_value.'" value="'.$game_type_value.'">
        </td>
        ';
      } else {
        $gametype_value_html = $gametype_value_html.'
        <td>
          <input type="number" step=".01" min="0" class="form-control commission" placeholder="" id="'.strtolower($casino_value).'_'.$game_flatform_value.'" value="">
        </td>
        ';
      }
    }

    $commission_content_html = $commission_content_html.'
    <tr>
      <td>
        <strong>'. $casinoNames[$casino_value] .'</strong>
      </td>
      <td>
        <table class="table table-bordered">
          <tr class="active text-center">
            '.$gametype_html.'
          </tr>
          <tr>
            '.$gametype_value_html.'
          </tr>
        </table>
      </td>
    </tr>
    ';
  }

  return $commission_content_html;
}

/**
 * 判斷退佣設定及DB定義是否一致
 *
 * @param array $commission - 退佣比設定
 * @param [type] $casino_value - 娛樂城
 * @param [type] $game_flatform_value - 遊戲類型
 * @return int
 */
function commission_gametype_value_setting($commission, $casino_value, $game_flatform_value)
{
  // 判斷定義的娛樂城是否存在退佣比設定
  // 不存在表示沒被設定過, 該娛樂城所有遊戲類型預設填入0
  // var_dump($commission);
  if (array_key_exists($casino_value,$commission)) {
    
    $game_type_name = $game_flatform_value;

    // 判斷定義的娛樂城遊戲類型是否存在退佣比設定
    // 不存在表沒被設定過, 該遊戲類型退佣比預設填入0
    if (array_key_exists($game_flatform_value,$commission[$casino_value])) {

      $game_type_value = $commission[$casino_value][$game_flatform_value];
    } else {
      $game_type_value = 0;
    }

  } else {
    $game_type_value = 0;
  }

  return $game_type_value;
}

/**
 * 過濾遊戲類別設定值
 *
 * @param [type] $commission_json - 佣金比設定
 * @param [type] $casino_gametype_list - 娛樂城及遊戲類型列表
 * @return array
 */
function get_commission_json($commission_json, $casino_gametype_list)
{
  foreach ($commission_json as $key => $value) {
    $casino_gametype_text = explode('_',$key);
    $casino = strtoupper($casino_gametype_text[0]);
    $gametype = $casino_gametype_text[1];

    // 比對娛樂城是否和定義檔一樣
    $casino = compare_casino($casino,$casino_gametype_list['casino']);

    // 比對遊戲類別是否和定義檔一樣
    $gametype = compare_gametype($gametype, $casino_gametype_list['game_flatform'], $casino);

    // 過濾各遊戲類型參數值
    $commission = (string)round(filter_var($value, FILTER_SANITIZE_STRING), 2);


    $list['gametype_list'][$gametype] = $commission;
    $list['commission_list'][$casino] = $list['gametype_list'];
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
function compare_casino($casino, $casino_list)
{
  global $tr;
  if (in_array($casino,$casino_list)) {

    $casino = filter_var($casino, FILTER_SANITIZE_STRING);
    $logger = $tr['Unlawful casino'];
    if ($casino == '') {
      echo "<script>alert('.$logger.');</script>";
      die();
    }
    
  } else {
    echo "<script>alert('.$logger.');</script>";
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
function compare_gametype($gametype, $game_flatform_list, $casino)
{
  global $tr;
  if (in_array($gametype, $game_flatform_list[$casino])) {
    
    $gametype = filter_var($gametype, FILTER_SANITIZE_STRING);
    $logger = $tr['Illegal game category'];
    if ($gametype == '') {
      echo "<script>alert('.$logger.');</script>";
      die();
    }

  } else {
    echo "<script>alert('.$logger.');</script>";
    die();
  }
  
  return $gametype;
}

// 會員端 存款投注
function get_depositbet(){

  $switch = [];
  $sql=<<<SQL
    SELECT * FROM root_protalsetting
    WHERE name = 'depositbet_calculation'
  SQL;
  $result = runSQLall($sql);
  unset($result[0]);

  foreach($result as $k => $v){

    if($v->value == 'on'){
      // 顯示
      $switch['status'] = 'on';
      $switch['input_type'] = 'number';
      $switch['value'] = '';
      
    }else{
      // 隱藏
      $switch['status'] = 'off';
      $switch['input_type'] = 'hidden';
      $switch['value'] = '0';
    };
    
  };

  return $switch;
}

// 顯示
function depositbet_html_on($data){
  global $tr;
  $html = '';

  $html= <<<HTML
    <hr>
    <div class="row" >
      <div class="col-12 col-md-12">
        <span class="label label-default">{$tr['Deposit betting commission setting']}</span>
      </div>
    </div>
    <br>

    <div class="row" >
      <div class="col-12 col-md-3">
        <strong>{$tr['Offline full effective member minimum bet amount']}</strong>
      </div>
      <div class="col-12 col-md-3">
        <input type="{$data['input_type']}" min="0" class="form-control" id="downline_effective_bet" placeholder="{$tr['Offline full effective member minimum bet amount']}" value="{$data['value']}">
      </div>
    </div>
    <br>

    <div class="row">
      <div class="col-12 col-md-3">
        <strong>{$tr['Downline full effective member deposit rebate ratio (%)']}</strong>
      </div>
      <div class="col-12 col-md-3">
        <input type="{$data['input_type']}" min="0" class="form-control" id="downline_deposit" placeholder="{$tr['Downline full effective member deposit rebate ratio (%)']}" value="{$data['value']}">
      </div>
    </div>
    <br>
HTML;

  return $html;
}

// 隱藏
function depositbet_html_off($data){

  global $tr;
  $html = '';

  $html=<<<HTML
    <div style='display:none'>
      <hr>
        <div class="row">
          <div class="col-12 col-md-12">
            <span class="label label-default">{$tr['Deposit betting commission setting']}</span>
          </div>
        </div>
        <br>
    
        <div class="row" >
          <div class="col-12 col-md-3">
            <strong>{$tr['Offline full effective member minimum bet amount']}</strong>
          </div>
          <div class="col-12 col-md-3">
            <input type="{$data['input_type']}" min="0" class="form-control" id="downline_effective_bet" placeholder="{$tr['Offline full effective member minimum bet amount']}" value="{$data['value']}">
          </div>
        </div>
        <br>
    
        <div class="row">
          <div class="col-12 col-md-3">
            <strong>{$tr['Downline full effective member deposit rebate ratio (%)']}</strong>
          </div>
          <div class="col-12 col-md-3">
            <input type="{$data['input_type']}" min="0" class="form-control" id="downline_deposit" placeholder="{$tr['Downline full effective member deposit rebate ratio (%)']}" value="{$data['value']}">
          </div>
        </div>
        <br>
    </div>
HTML;
  return $html;
}

/*
  *抓取預設佣金設定資訊
*/
function default_commission_setting(){
  $sql = "SELECT * FROM root_commission WHERE id=(SELECT min(id) FROM root_commission WHERE name='預設佣金設定');";
  $result = runSQLall($sql);

  return $result[1];
}
?>