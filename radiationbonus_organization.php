<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 直銷組織加盟金
// File Name:	radiationbonus_organization.php
// Author:    Barkley
// Related:
// DB table:  root_statisticsdailyreport 每日營收日結報表
// DB table:  root_statisticsbonusagent 放射線組織獎金計算-代理加盟金
// Log:
// 將營運日報的資料，整理成為會員獎金分紅的報表，並且輸出成為資料表存放。
// 將計算完成的資料存放在 root_statisticsbonusagent 表格內,使用者可以指定日期更新
// 查詢的時候，會自動 insert data 到 root_statisticsbonusagent 表格
// 資料如果完整後，可以選擇計算出統計最後結果的資料。
// ----------------------------------------------------------------------------
// 程式開發的邏輯：
// -------------------------------------------------------------------------
// 透過一個指定的時間區間, 依據會員資料 root_member 搜尋日結報表的資料
// 依據 root_member 的上下階層關係, 列出每個會員的每一個上一代, 一直到 root
// 每一個會員的投注資訊, 透過日結報表統計指定的時間區間取得完整資訊，以利列出符合條件的會員(代理商)
// 依據每個會員日結報表資料(日結報表資料需要完整, 會員不存在的處理), 統計出來加盟金及總投注額
// -------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once __DIR__ ."/lib_view.php";

// betlog 專用的 DB lib
require_once dirname(__FILE__) ."/config_betlog.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// -------------------------------------------------------------------------
// $_GET 取得日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// 查詢DB的會員端設定，確認該功能是否有開啟，如已關閉則導向會員端設訂畫面
$protalsetting_sql = <<<SQL
    SELECT *
    FROM root_protalsetting
    WHERE (name = 'radiationbonus_organization')
    LIMIT 1;
SQL;
$protalsetting_result = runSQLall( $protalsetting_sql );
if( $protalsetting_result[0]==1 ){
    if( $protalsetting_result[1]->value=='off' ){
        echo <<<HTML
            <script>
                alert('该功能已被关闭，如需使用请先开启！');
                location.replace('protal_setting_deltail.php?sn=default');
            </script>
        HTML;
        exit();
    }
}

//var_dump($_GET);
// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker'])) {
  // 判斷格式資料是否正確, 不正確以今天的美東時間為主
  $current_datepicker = validateDate($_GET['current_datepicker'], 'Y-m-d');
  //var_dump($current_datepicker);
  if($current_datepicker) {
    $current_datepicker = $_GET['current_datepicker'];
  }else{
    // 轉換為美東的時間 date
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
  }
}else{
  // 轉換為美東的時間 date
  $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
}
//var_dump($current_datepicker);
//echo date('Y-m-d H:i:sP');
// 統計的期間時間 $rule['stats_commission_days'] 參考次變數
$current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker - 30 day"));
$current_datepicker_end = date( "Y-m-d");

$radiation_bonus_rule = nl2br(
'每代保证 4 层收益：每个会员业绩(营业额)达成，保证收入下 4 层分红。
  每周分红一次， 一年 52 次分红。
  未达成业绩者利润放入彩池，合并于股利分红时发放。

  收入来源：
  ----------------
  1. 代理加盟金：
  * 只赚第一次，加盟公司审查通过后，并通过审阅期 '.$rule['income_commission_reviewperiod_days'].' 天后，计算并发放分红加盟金。(人工管制)
  * 需要加盟成为代理商后，才可以推荐会员加入。开始招募会员后，审阅期就则立即结束。(人工管制)
  * 系统每日计算通过审阅期的加盟金，并分红为系统现金派彩。
  * 如果有加盟金发生，直接发予上四代会员，不受营业额达成限制。

  Q&A
  ------------
  每个会员的 4 层加盟的分红计算：上面加盟金、营业奖金、营利奖金 3 个收入来源，都以此方式分红。
  上游第1层 '.$rule['commission_1_rate'].'%
  上游第2层 '.$rule['commission_2_rate'].'%
  上游第3层 '.$rule['commission_3_rate'].'%
  上游第4层 '.$rule['commission_4_rate'].'%
  公司成本'.$rule['commission_root_rate'].' % 四層分紅累加百分比加總後需為 100%
  如果上游以无上一代，将分红归类到公司帐号。
');

$radiation_bonus_rule_html = <<<HTML
<!-- Button trigger modal -->

<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal">
  {$tr['Radiation Organization - Reward Dividends']}
</button>
<button class="btn" data-toggle="modal" data-target="#franchise-update-modal">{$tr['Personal Dividend Commission Update']}</button>

<!-- rule Modal -->
<div class="modal fade bs-example-modal-lg" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="myModalLabel">{$tr['Radiation Organization - Reward Dividends']}</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        $radiation_bonus_rule
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
body.modal-open .ui-datepicker {
    z-index: 1200 !important;
}
</style>

<!-- franchise-update Modal -->
<div class="modal fade bs-example-modal-lg" id="franchise-update-modal" tabindex="-1" role="dialog" aria-labelledby="franchiseUpdateLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="franchiseUpdateLabel">{$tr['Personal Dividend Commission Update']}</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form class="form-inline" method="get">
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-addon">{$tr['Specified date']}</div>
              <input type="text" class="form-control" placeholder="{$tr['Starting time']}" id="franchise_update_time" value="$current_datepicker_end">
            </div>
          </div>
          <button class="btn btn-primary ml-2 js-franchise-update">{$tr['update']}</button>
        </form>
      </div>
      <!-- <div class="modal-footer"> -->
        <!-- <button type="button" class="btn btn-default" data-dismiss="modal">Close</button> -->
      <!-- </div> -->
    </div>
  </div>
</div>


HTML;


// 初始化變數
// 功能標題，放在標題列及meta  $tr['Radiation tissue bonus calculation'] = '放射線組織獎金計算';  $tr['Agent Franchise Fee'] = '代理加盟金';
$function_title     = $tr['Radiation tissue bonus calculation'].'-'.$tr['Agent Franchise Fee'];

// render view
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;

return render(
  __DIR__ . '/radiationbonus_organization.view.php',
  compact(
    'function_title',
    'current_datepicker',
    'current_datepicker_start',
    'current_datepicker_end',
    'radiation_bonus_rule_html'
  )
);
