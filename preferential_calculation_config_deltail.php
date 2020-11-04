<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 反水設定詳細
// File Name:	preferential_calculation_config_deltail.php
// Author:		Yuan
// Related:		對應 preferential_calculation_config.php
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
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";
require_once dirname(__FILE__) ."/preferential_calculation_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(isset($_GET['i']) AND $_SESSION['agent']->therole == 'R') {
  $id = filter_var($_GET['i'], FILTER_SANITIZE_STRING);
}else{
  die('(x)不合法的測試');
}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
global $tr;
// 功能標題，放在標題列及meta
$function_title 		= $tr['Preferential level setting'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['System Management'] . '</a></li>
  <li><a href="preferential_calculation_config.php">' . $tr['Preferential setting'] . '</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

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
    $errtext = '反水設定查詢錯誤';
    return array('status' => false, 'result' => $errtext);
  }

  return array('status' => true, 'result' => $result[1]);
}

function get_casinolist()
{
  $casinoLib = new casino_switch_process_lib();

  $casino_gametype_sql = 'SELECT casinoid, game_flatform_list, display_name FROM casino_list WHERE "open" <> 5;';
  $casino_gametype_sql_result = runSQLall($casino_gametype_sql);

  if (empty($casino_gametype_sql_result[0])) {
    return false;
  }

  unset($casino_gametype_sql_result[0]);
  $casinoNames = [];
  foreach ($casino_gametype_sql_result as $k => $v) {
    $casino_list[] = $v->casinoid;
    $casinoNames[$v->casinoid] = $casinoLib->getCurrentLanguageCasinoName($v->display_name, $_SESSION['lang']);
    $game_flatform_list[$v->casinoid] = json_decode($v->game_flatform_list, true);
  }

  $result = [
    'status' => true,
    'casino' => $casino_list,
    'game_flatform' => $game_flatform_list,
	'gameNames' => $casinoNames
  ];

  return $result;
}

function get_usesetting_membercount($name, $membertype)
{
  $sql = "SELECT COUNT(account) AS account_count FROM root_member WHERE favorablerule = '".$name."' AND therole = '".$membertype."';";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  $count = $result[1]->account_count;

  return $count;
}

function preferential_gametype_value_setting($favorablerate, $casino_value, $game_flatform_value)
{
  if (array_key_exists($casino_value,$favorablerate)) {

    // 存在的話判斷定義檔 $game_flatform_list 內的值是否存在 json 第二層
    if (array_key_exists($game_flatform_value,$favorablerate[$casino_value])) {

      $game_type_value = $favorablerate[$casino_value][$game_flatform_value];
    } else {
      $game_type_value = 0;
    }

  } else {
    $game_type_value = 0;
  }

  return $game_type_value;
}

function combination_preferential_setting_html($preferential, $casino_list, $game_flatform_list, $casinoNames)
{
  global $tr;

  $html = '';

  /*
  根據 DB table : casino_list 設定
  動態生成反水比列表

  比對娛樂城與各遊戲類別與反水比設定
  不存在表示該設定是新增的
  */

  // 娛樂城的分類翻譯
  $tr_casino_list['live']    = $tr['live'];
  $tr_casino_list['game']    = $tr['game'];
  $tr_casino_list['html5']   = $tr['html5'];
  $tr_casino_list['fish']    = $tr['fish'];
  $tr_casino_list['lotto']   = $tr['lotto'];
  $tr_casino_list['lottery'] = $tr['lottery'];
  $tr_casino_list['sports']   = $tr['sports'];
  $tr_casino_list['card']    = $tr['card'];

  foreach ($casino_list as $casino_key => $casino_value) {

    $gametype_html = '';
    $gametype_value_html = '';

    foreach ($game_flatform_list[$casino_value] as $game_flatform_key => $game_flatform_value) {

      $game_type_value = preferential_gametype_value_setting($preferential, $casino_value, $game_flatform_value);

      // 翻譯後的值, 存在的話使用翻譯的數值. 不存在使用預設
      if(isset($tr_casino_list[$game_flatform_value])){
        $tr_game_flatform_value = $tr_casino_list[$game_flatform_value];
      }else{
        $tr_game_flatform_value = $game_flatform_value;
      }

      $gametype_html .= '
      <td>'.$tr_game_flatform_value.'(%)</td>
      ';

      $gametype_value_html = $gametype_value_html.'
      <td>
        <input type="number" min="0" step="0.1" class="form-control favorablerate" placeholder="" id="'.strtolower($casino_value).'_'.$game_flatform_value.'" value="'.$game_type_value.'">
      </td>
      ';
    }
    // end loop

    $html .= '
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

  return $html;
}

function combination_note_html($notes)
{
  global $tr;

  $html = '
  <div class="row">
    <div class="col-12 col-md-2">
      <td><strong>' . $tr['note'] . '</strong></td>
    </div>
    <div class="col-12 col-md-6">
      <textarea class="form-control validate[maxSize[500]]" rows="5" id="notes" maxlength="500" placeholder="('.$tr['max'].'500'.$tr['word'].')">'.$notes.'</textarea>
    </div>
  </div>
  <br>
  ';

  return $html;
}

function combination_otherarea_html($member_count, $agent_count, $notes)
{
  global $id;
  global $tr;
  global $default_favorable;

  $member_count_text = ($member_count === false) ? '會員數量查詢錯誤' : $member_count.$tr['people'];
  $agent_count_text = ($agent_count === false) ? '代理數量查詢錯誤' : $agent_count.$tr['people'];

  $html = '
  <div class="row">
    <div class="col-12 col-md-2">
      <td><strong>' . $tr['number of members'] . '</strong></td>
    </div>
    <div class="col-12 col-md-5">
      '.$member_count_text.'
    </div>
  </div>
  <br>
  ';

  $html .= '
  <div class="row">
    <div class="col-12 col-md-2">
      <td><strong>' . $tr['number of agents'] . '</strong></td>
    </div>
    <div class="col-12 col-md-5">
      '.$agent_count_text.'
    </div>
  </div>
  <br>
  ';

  $html .= combination_note_html($notes);

  if ($id == $default_favorable->id){
    $html .= '
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>' . $tr['delete preferential setting'] . '</strong></td>
      </div>
      <div class="col-12 col-md-6">
        <div class="text-danger">此为预设反水设定，无法删除</div>
      </div>
    </div>
    <br><br>
    ';
  } else {
    if ($member_count > 0 || $agent_count > 0) {
      $del_html = '<div class="text-danger">已有会员及代理不可删除</div>';
    } elseif ($member_count === false || $agent_count === false) {
      $del_html = '<div class="text-danger">会员或代理查询错误不可删除</div>';
    } else {
      $del_html = '<button id="del_preferential_setting" class="btn btn-danger"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>';
    }
  
    $html .= '
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>' . $tr['delete preferential setting'] . '</strong></td>
      </div>
      <div class="col-12 col-md-6">
        '.$del_html.'
      </div>
    </div>
    <br><br>
    ';
  }

  return $html;
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // ---------------------
  // 取出目前反水设定比例资料
  // ---------------------
  $favorable_setting = get_favorable_setting_byid($id);

  $show_list_html = '';
  if ($favorable_setting['status']) {
    $name = $favorable_setting['result']->name;
    $group_name = $favorable_setting['result']->group_name;
    $status = $favorable_setting['result']->status;
    $wager = $favorable_setting['result']->wager;
    $upperlimit = $favorable_setting['result']->upperlimit;
    $audit = $favorable_setting['result']->audit;
    // 这个属于公司和一级代理商的反水设定
    $favorablerate = json_decode($favorable_setting['result']->favorablerate, true);
    $notes = $favorable_setting['result']->notes;

    //預設反水設定資料
    $default_favorable = default_favorable_setting();

    // ---------------------
    // 取出目前反水设定比例资料 end
    // ---------------------

    $action = 'edit_favorable_setting';

    $preview_area_color = 'info';
    $preview_area_text = '
    * '.$tr['bet amount and the upper limit of rebate just can be positive integer'].'<br>
    * '.$tr['The rebate ratio and audit multiple only can two decimal places. If it exceeds, it will be rounded to the second decimal place'].'<br>
    * '.$tr['The same rebate setting name cannot have the same bet amount'].'<br>
    * '.$tr['The rebate settings already used by members and agents cannot be deleted'].'<br>
    * '.$tr['Editing,cannot be left blank except for Note'].'<br>
    * '.$tr['Calculation formula'].'：<br>
    # '.$tr['The amount of rebates generated by each member = the amount of bets that each member exceeds the threshold for issuing rebate * (the sum of the rebate ratio of each casino) * the proportion of commissions of agents and members at each floor'].'<br>
    # '.$tr['The amount of rebates for each agent and member from general agent = the amount of rebates generated by each member (based on the proportion of commissions) * the total number of agents and members who contributed (Unreleased to the general agent)'].'<br>
    ';

    $iscopy = '0';
    $isdisabled = '';
    if (isset($_GET['a']) && $_GET['a'] == 'copy') {
      $action = 'add_favorable_setting';
      $iscopy = '1';
      $isdisabled = 'disabled';
      $preview_area_color = 'danger';
      $preview_area_text = '* '.$tr['copy setting have not save yet'].'<br>';
    }

    // 一般設定
    $show_common_html = '
    <div id="preview_area" class="alert alert-'.$preview_area_color.'" role="alert">
    '.$preview_area_text.'
    </div>';

    // 一般設定
    $show_common_html .= '
    <div class="row">
      <div class="col-12 col-md-12">
        <span class="label label-default">' . $tr['setting'] . '</span>
        <hr>
      </div>
    </div>
    ';

    // 反水名稱設定
    $show_common_html = $show_common_html.'
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>' . $tr['Name of preferential'] . '</strong></td>
      </div>
      <div class="col-12 col-md-3">
        <input type="text" class="form-control validate[required,maxSize[50]]" maxlength="50" id="name" placeholder="請輸入反水設定名稱('.$tr['max'].'50'.$tr['word'].')" value="'.$group_name.'" '.$isdisabled.'>
      </div>
    </div>
    <br>
    ';

    // 判斷等級狀態是否啟用
    if ($status == '1') {
      $status_checked = 'checked';
    } else {
      $status_checked = '';
    }

    if($id == $default_favorable->id && $iscopy == '0'){
      $show_common_html = $show_common_html.'
        <div class="row">
          <div class="col-12 col-md-2">
            <strong>' . $tr['Turn on setting'] . '</strong>
          </div>
          <div class="col-12 col-md-10">
            <p class="text-danger">此为预设反水设定，无法变更「啟用设定」</p>
            <div class="col-12 col-md-12 status-switch pull-left" style="display:none">
              <input id="status" name="status" class="ststus_checkbox_switch" type="checkbox" '.$status_checked.'/>
              <label for="status" class="label-success"></label>
            </div>
          </div>
        </div>
        <br>
      ';
    }else{
    $show_common_html = $show_common_html.'
    <div class="row">
      <div class="col-12 col-md-2">
        <strong>' . $tr['Turn on setting'] . '</strong>
      </div>
      <div class="col-12 col-md-10">
        <div class="col-12 col-md-12 status-switch pull-left">
          <input id="status" name="status" class="ststus_checkbox_switch" type="checkbox" '.$status_checked.'/>
          <label for="status" class="label-success"></label>
        </div>
      </div>
    </div>
    <br>
    ';
    }
    // 一般設定  end
    // -------------------

    // 反水设定
    $show_favorablerate_html = '
    <div class="row">
      <div class="col-12 col-md-12">
      <span class="label label-default">' . $tr['Preferential setting'] . '</span>
      <hr>
      </div>
    </div>
    <br>
    ';

    $casino_gametype_list = get_casinolist();

    if ($casino_gametype_list) {
      $preferential_content_html = combination_preferential_setting_html($favorablerate, $casino_gametype_list['casino'], $casino_gametype_list['game_flatform'], $casino_gametype_list['gameNames']);
      $preferential_table_html = '
      <table class="table table-striped">
        <thead>
          <th width="15%" class="text-center">娱乐城</th>
          <th width="75%" class="text-center">反水比</th>
        </thead>
        '.$preferential_content_html.'
      </table>
      ';
    } else {
      $preferential_table_html = '<div class="text-danger">(x) 娱乐城与游戏类别查询错误。</div>';
    }

    // -----------------------------------------
    // 投注量(以会员投注量来决定发放反水门槛)
    // -----------------------------------------s
    $favorablerate_tab_html = '

    <div class="row">
      <div class="col-12 col-md-2">
        <strong>' . $tr['betting amount'] . '<br>(会员发放反水门槛)</strong>
      </div>
      <div class="col-12 col-md-3">
        <input type="number" min="0" class="form-control integercheck" id="wager" placeholder="投注量(以会员投注量来决定发放反水门槛)" value="'.$wager.'">
      </div>
    </div>
    <br>

    '.$preferential_table_html.'

    <br>
    <div class="row">
      <div class="col-12 col-md-2">
        <strong>' . $tr['maximum of preferential'] . '</strong>
      </div>
      <div class="col-12 col-md-3">
        <input type="number" min="0" step="100" class="form-control integercheck" id="upperlimit" placeholder="請輸入反水上限" value="'.$upperlimit.'">
      </div>
    </div>
    <br>

    <div class="row">
      <div class="col-12 col-md-2">
        <strong>稽核倍数</strong>
      </div>
      <div class="col-12 col-md-3">
        <input type="number" min="0" step=".01" class="form-control" id="audit" placeholder="請輸入反水稽核倍數(设定0表示不稽核)" value="'.$audit.'">
      </div>
    </div>
    <br><br>
    ';

    // output html
    $show_favorablerate_html = $show_favorablerate_html.'
    <div class="row">
      <div class="col-12 col-md-12">
        '.$favorablerate_tab_html.'
      </div>
    </div>
    ';

    // ----------------------------------------------------------------------
    // 娱乐城分佣反水设定 -- 状态描述
    // ----------------------------------------------------------------------
    $show_favorablerate_status_html = '
    <div class="row">
      <div class="col-12 col-md-12">
      <span class="label label-default">' . $tr['other'] .'</span>
      </div>
    </div>
    <hr>
    ';

    $member_count = get_usesetting_membercount($name, 'M');
    // $member_count = ($member_count === false) ? '會員數量查詢錯誤' : $member_count.$tr['people'];

    $agent_count = get_usesetting_membercount($name, 'A');
    // $agent_count = ($agent_count === false) ? '代理數量查詢錯誤' : $agent_count.$tr['people'];

    $show_favorablerate_status_html .= (!isset($_GET['a']) || $_GET['a'] != 'copy') ? combination_otherarea_html($member_count, $agent_count, $notes) : combination_note_html($notes);

    // ----------------------------------------------------------------------
    // 娱乐城分佣反水设定 -- 状态描述 end
    // ----------------------------------------------------------------------

    $btn_html = '
    <p align="right">
      <button id="submit_preferential_setting" class="btn btn-success">' . $tr['Save'] . '</button>
      <button class="btn btn-danger" onclick="javascript:location.href=\'./preferential_calculation_config.php\'"> ' .$tr['Cancel'] . '</button>
    </p>';


/*
      // -----------------------------------------
      // 无限代反水设定佣金设定
      // 公式：每个会员的投注量符合资格后, 以上面的娱乐城反水比例累积分项算出总和后, 在以 100% 比例分佣给所有代理及会员。
      // -----------------------------------------
      $level_favorablerate_common_html = '
      <div class="row">
        <div class="col-12 col-md-2">
          <td><strong>个人反水比例(%)</strong></td>
        </div>
        <div class="col-12 col-md-4">
          <input type="number" step=".01" class="form-control" id="self_favorablerate" placeholder="反水到个人的比例" value="'.($favorable_rules['self_favorablerate']*100).'" disabled>
        </div>
      </div>
      <hr>
      <div class="row">
        <div class="col-12 col-md-2">
          <td><strong>一级代理商反水比例(%)</strong></td>
        </div>
        <div class="col-12 col-md-4">
          <input type="number" step=".01" class="form-control" id="first_agent_favorablerate" placeholder="一级代理商保留分佣比例(公司下的代理为唯一级代理商)" value="'.($favorable_rules['first_agent_favorablerate']*100).'" disabled>
        </div>
      </div>
      <hr>
      ';

      // 检查全部加总
      $sum_level_favorable = $favorable_rules['self_favorablerate']+$favorable_rules['first_agent_favorablerate'];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][0];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][1];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][2];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][3];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][4];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][5];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][6];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][7];
      $sum_level_favorable = $sum_level_favorable+$favorable_rules['level_favorablerate'][8];

      // 选的代理商分用方式
      // 由下往上计算反水 or 由上往下计算反水
      if( $favorable_rules['is_bottom_up'] == true) {
        $is_bottom_up_checked = 'checked="checked"';
        $is_top_down_checked = '';
      }else{
        $is_bottom_up_checked = '';
        $is_top_down_checked = 'checked="checked"';
      }


      $level_favorablerate_levelrate_html = '
        <div class="row">

          <div class="col-12 col-md-2">
            <td><strong>分层分用列表</strong></td>
          </div>

          <div class="col-12 col-md-4">

            <div class="radio">
              <label><input type="radio" name="is_bottom_up" placeholder="由下往上计算反水" '.$is_bottom_up_checked.' disabled>由下往上计算反水</label>
              <label><input type="radio" name="is_bottom_up" placeholder="由上往下计算反水" '.$is_top_down_checked.' disabled>由上往下计算反水</label>
            </div>

            <div class="table-responsive">
            <table class="table">
              <tr><td>往上代理商代数</td><td>分佣比例(%)</td></tr>
              <tr><td>1</td><td>'.($favorable_rules['level_favorablerate'][0]*100).'%</td></tr>
              <tr><td>2</td><td>'.($favorable_rules['level_favorablerate'][1]*100).'%</td></tr>
              <tr><td>3</td><td>'.($favorable_rules['level_favorablerate'][2]*100).'%</td></tr>
              <tr><td>4</td><td>'.($favorable_rules['level_favorablerate'][3]*100).'%</td></tr>
              <tr><td>5</td><td>'.($favorable_rules['level_favorablerate'][4]*100).'%</td></tr>
              <tr><td>6</td><td>'.($favorable_rules['level_favorablerate'][5]*100).'%</td></tr>
              <tr><td>7</td><td>'.($favorable_rules['level_favorablerate'][6]*100).'%</td></tr>
              <tr><td>8</td><td>'.($favorable_rules['level_favorablerate'][7]*100).'%</td></tr>
              <tr><td>9</td><td>'.($favorable_rules['level_favorablerate'][8]*100).'%</td></tr>
            </table>
            </div>
            <div><strong>总计分佣反水合计(%)  '.($sum_level_favorable*100).'% </string></div>
          </div>
        </div>
      ';

      // 一级代理下，代理商及会员反水设定佣金设定 - 组合 html
      $show_level_favorablerate_html = '
      <div class="row">
      <br><br><hr>
      <span class="label label-default">公司一级代理下代理商及会员反水设定佣金设定</span>
      <span><a href="#" title="公司一级代理下代理商及会员反水设定佣金设定目前为固定值,后续会将这功能分开让一级代理商可以变动弹性设定代数及比例"><span class="glyphicon glyphicon-info-sign"></span></span></a>
      </div>
      <hr>
      <div class="row">
        '.$level_favorablerate_common_html.'
      </div>
      <div class="row">
        '.$level_favorablerate_levelrate_html.'
      </div>

      ';
      // -----------------------------------------------------------------------
      // 无限代反水设定佣金设定 end
      // -----------------------------------------------------------------------

*/

//  無限代固定的設定, 暫時先不開發關閉。
//<div class="col-12 col-md-12">
//'.$show_level_favorablerate_html.'
//</div>

    // -----------------------------------------------------------------------
    // 主要排版
    // -----------------------------------------------------------------------
    $indexbody_content = $indexbody_content.'
    <form id="preferential_form">
      <div class="col-12 col-md-12">
      '.$show_common_html.'
      </div>

      <div class="col-12 col-md-12">
      '.$show_favorablerate_html.'
      </div>

      <div class="col-12 col-md-12">
      '.$show_favorablerate_status_html.'
      </div>

      <div class="col-12 col-md-12">
      <hr>
      '.$btn_html.'
      </div>

      <div class="col-12 col-md-12">
        <div id="preview_result"></div>
      </div>
    </form>
    ';


    // 將 checkbox 堆疊成 switch 的 css
    $extend_head = $extend_head. <<<HTML
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function () {
            $("#preferential_form").validationEngine();
        });
    </script>
HTML;    

    $extend_head = $extend_head. "
    <style>

    .status-switch > input[type=\"checkbox\"] {
        visibility:hidden;
    }

    .status-switch > label {
        cursor: pointer;
        height: 0px;
        position: relative;
        width: 40px;
    }

    .status-switch > label::before {
        background: rgb(0, 0, 0);
        box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
        border-radius: 8px;
        content: '';
        height: 16px;
        margin-top: -8px;
        margin-left: -30px;
        position:absolute;
        opacity: 0.3;
        transition: all 0.4s ease-in-out;
        width: 30px;
    }
    .status-switch > label::after {
        background: rgb(255, 255, 255);
        border-radius: 16px;
        box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
        content: '';
        height: 16px;
        left: -4px;
        margin-top: -8px;
        margin-left: -30px;
        position: absolute;
        top: 0px;
        transition: all 0.3s ease-in-out;
        width: 16px;
    }
    .status-switch > input[type=\"checkbox\"]:checked + label::before {
        background: inherit;
        opacity: 0.5;
    }
    .status-switch > input[type=\"checkbox\"]:checked + label::after {
        background: inherit;
        left: 20px;
    }

    </style>
    ";


    $extend_js = $extend_js."
    <script>
    $(document).ready(function() {

      //格式判斷
      var format_chk = /^[-]?[0-9]\d*|^[+]?[0-9]\d*/;
      $('input[type=number]').blur(function(){ 
        if ( $(this).val() >= 0  && format_chk.test($(this).val()) == true ){
          $(this).removeClass('alert alert-danger negative_number mb-0');
          $(this).removeClass('error_format');
        }else if ( $(this).val() < 0  && format_chk.test($(this).val()) == true ){            
          $(this).addClass('alert alert-danger negative_number mb-0');
          $(this).removeClass('error_format');
        }else if ( format_chk.test($(this).val()) == false ){
          $(this).removeClass('alert alert-danger negative_number mb-0');
          $(this).addClass('alert alert-danger error_format mb-0');
        }
      });
      //正整數判斷
      var integer = /^[0-9]\d*$/;
      $('.integercheck').blur(function(){ 
        if ( integer.test($(this).val()) == true){
          $(this).removeClass('alert alert-danger not_integer mb-0');
        }else if ( integer.test($(this).val()) == false && $(this).val() != '') {
          $(this).addClass('alert alert-danger not_integer mb-0');
        }             
      });

      //防止表單刷新頁面
      $('#preferential_form').submit(function(e){
        e.preventDefault();
      });
      
      $('#submit_preferential_setting').click(function(e) {
        e.preventDefault();
        var id = '".$id."';
        var iscopy = '".$iscopy."';
        var name = $('#name').val();

        if($('#status').prop('checked')) {
          var status = 1;
        } else {
          var status = 0;
        }

        var wager = $('#wager').val();
        var upperlimit = $('#upperlimit').val();
        var audit = $('#audit').val();
        var notes = $('#notes').val();

        var inputArray=$(\"input[class='form-control favorablerate']\");
        var m = new Map();
        favorablerate_arr = {};
        inputArray.each (
          function() {
            var input =$(this);
            var id = input.attr('id');
            var val = $('#'+input.attr('id')).val();
            m.set(id, val);
        });

        m.forEach((value, key) => {
          var keys = [key];
          var last = keys.pop();
          keys.reduce((r, a) => {}, favorablerate_arr)[last] = value;
        });

        var favorablerate_json = JSON.stringify(favorablerate_arr);
        if( $('input[type=number]').hasClass('error_format') == true ){
          alert('设定值格式错误，请重新设定');
        }else if( $('input[type=number]').hasClass('negative_number') == true ){
            alert('设定值不可为负数');
        }else if( $('input[type=number]').hasClass('not_integer') == true ){
            alert('打码量，反水上限只可为正整数');
        }else{
          $.post('preferential_calculation_config_deltail_action.php?a=".$action."',
          {
            id: id,
            iscopy: iscopy,
            name: name,
            status: status,
            wager: wager,
            upperlimit: upperlimit,
            audit: audit,
            notes: notes,
            favorablerate_json: favorablerate_json
          },
          function(result){
            $('#preview_result').html(result);
          });
        }
      });

      $('#del_preferential_setting').click(function(e) {
        e.preventDefault();
        var id = '".$id."';
        var name = $('#name').val();
        var wager = $('#wager').val();

        $.post('preferential_calculation_config_deltail_action.php?a=del_favorable_setting',
        {
          id: id,
          name: name,
          wager: wager
        },
        function(result){
          $('#preview_result').html(result);
        });
      });
    });
    </script>
    ";
  } else {
    $show_transaction_list_html  = '(x) 反水設定查詢錯誤或該設定已被刪除。';

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content = $indexbody_content.'
    <div class="row">
      <div class="col-12 col-md-12">
      '.$show_transaction_list_html.'
      </div>
    </div>
    <br>
    <div class="row">
      <div id="preview_result"></div>
    </div>
    ';
  }

} else {
  // 沒有登入的顯示提示俊息
  $show_transaction_list_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_transaction_list_html.'
	  </div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
}


// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");