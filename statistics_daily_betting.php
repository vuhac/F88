<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 透過程式定時每 10 分鐘分析投注單內的資訊
// File Name:	statistics_daily_betting.php
// Author:		Barkley
// DB table:  MG
// Related:
// Log:
//
// -------------------------------------------------------------------------- --

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) ."/config_betlog.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= '透過程式定時分析投注單內的資訊';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';



  // -------------------------------------------------------------------------
  // 取得日期 - 決定開始用份的範圍日期
  // -------------------------------------------------------------------------
  // get example: ?current_datepicker=2017-02-03
  // ref: http://php.net/manual/en/function.checkdate.php
  function validateDate($date, $format = 'Y-m-d H:i:s')
  {
      $d = DateTime::createFromFormat($format, $date);
      return $d && $d->format($format) == $date;
  }


  // -----------------------------------------------------------------------------
  // 傳入指定日期, 輸出查詢到的指定統計時間
  // -----------------------------------------------------------------------------
  function stat_betting($current_datepicker, $datetime, $datepicker, $istart, $iend) {

    // MG 娛樂城的投注紀錄統計處理 , 10 分鐘內, MG games 有多少人投注count_accountnumber, 投注量sum_betting多少, 投注額sum_totalwager多少 , 損益sum_profit多少？
    $stat_sql = "SELECT count(count_accountnumber) as count_accountnumber,sum(count_accountnumber) as sum_betting, sum(sum_totalwager) as sum_totalwager , sum(sum_totalwager - sum_totalpayout) as sum_profit
    FROM ".'
    (SELECT count("AccountNumber") as count_accountnumber, sum("TotalWager") as sum_totalwager, sum("TotalPayout") as sum_totalpayout
    FROM test_mg_bettingrecords
    WHERE gamereceivetime >= \''.$datepicker[$istart].'-04\' AND gamereceivetime < \''.$datepicker[$iend].'-04\'
    GROUP BY "AccountNumber") as groupaccountnumber;';
    // var_dump($stat_sql);
    //print_r($stat_sql);

    $stat_result = runSQLall_betlogmg($stat_sql);
    //var_dump($stat_result);
    if($stat_result[0] == 1) {
      // 當日
      $s['dairy_date']          = $current_datepicker;
      // 美東 -- 時間區間開始
      $s['dailytime_start']     = $datetime[$istart];
      // 美東 -- 時間區間結束
      $s['dailytime_end']       = $datetime[$iend];
      // 有多少少人投注
      $s['count_accountnumber'] = $stat_result[1]->count_accountnumber;
      // 投注量有多少
      $s['sum_betting']         = round($stat_result[1]->sum_betting);
      // 投注額有多少
      $s['sum_totalwager']      = round($stat_result[1]->sum_totalwager);
      // 損益量
      $s['sum_profit']          = round($stat_result[1]->sum_profit);
      // 娛樂城分類 ,MG
      $s['casino_classification'] = 'MG';
      // 資料備註
      $s['notes']               = '';
      //var_dump($s);
      $s['projname']             = 'GPK';

    }else{
      print_r($stat_sql);
      $logger = 'False, 娛樂城的投注紀錄統計處理'.$datepicker[$istart].' ~ '.$datepicker[$iend].' , 資料數量為'.$stat_result[1]->count_accountnumber;
      var_dump($logger);
      $s = false;
    }


    return($s);
  }
  // -----------------------------------------------------------------------------




  // -------------------------------------------------------------------------
  // END function lib
  // -------------------------------------------------------------------------

  // --------------------------------------------------------------------------
  // 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
  // --------------------------------------------------------------------------
  if(isset($_GET['current_datepicker'])) {
    // 判斷格式資料是否正確
    if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
      $current_datepicker = $_GET['current_datepicker'];
      $current_timepicker = '00:00:00';
    }else{
      $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
      $current_timepicker = gmdate('H:i:s',time() + -4*3600);
    }
  }else{
    // php 格式的 2017-02-24
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
    $current_timepicker = gmdate('H:i:s',time() + -4*3600);
  }
  var_dump($current_datepicker);
  var_dump($current_timepicker);

  // 如果時間大於現在日期, stop
  if(strtotime($current_datepicker) > strtotime(gmdate('Y-m-d',time() + -4*3600)) ) {
    $logger = '指定的時間'.$current_datepicker.'不合法';
    die($logger);
  }
  // --------------------------------------------------------------------------
  //
  // --------------------------------------------------------------------------


  // $current_datepicker = '2017-04-22';
  $chkeckdate_sql = "SELECT dailydate, count(dailydate) as count_dailydate FROM root_statisticsbetting WHERE dailydate = '$current_datepicker' GROUP BY dailydate;";
  //var_dump($chkeckdate_sql);
  $chkeckdate_result = runSQLall($chkeckdate_sql);
  var_dump($chkeckdate_result);

  // 不要進入更新模式 == true --> 不更新,只顯示
  //$noforce_update_action = true;
  // 不要更新 = false --> 強置更新本日資料
  //$noforce_update_action = false;
  //var_dump($noforce_update_action);

  // 如果有資料, 跳過這個日期.除非選擇了強制更新
  if($chkeckdate_result[0] == 1 AND $chkeckdate_result[1]->count_dailydate == 24) {
    // 資料存在, 跳過, view only
    echo '日期'.$current_datepicker.'資料存在';
    $listdata_sql = "SELECT * FROM root_statisticsbetting WHERE dailydate = '".$current_datepicker."' LIMIT 2 ;";
    $listdata_result = runSQLall($listdata_sql);
    var_dump($listdata_result);

  }else{
    // 資料不存在, insert 更新
    echo '日期'.$current_datepicker.'資料不存在或是資料有缺少';

    // 生成日期範圍
    // 生成間隔 , 一小時
    $step_time = 60;
    // 資料
    $datepicker = array();
    // 日期分鐘數
    $dayminutes = (60*24)/$step_time;

    // 產生 1 天 24hr 的每隔 10 min 間隔
    for($i=0;$i<=$dayminutes;$i++) {
      $intval_time = $i*$step_time;
      $datetime[$i]   = date("H:i:s", strtotime("+$intval_time minutes", strtotime($current_datepicker)));
      $datepicker[$i] = date("Y-m-d H:i:s", strtotime("+$intval_time minutes", strtotime($current_datepicker)));
      //var_dump(strtotime($datetime[$i]));
      //var_dump(strtotime($current_timepicker));
    }
    // var_dump($datepicker);
    // print_r($datepicker);
    // 每天約有 24 筆資料, 檢查 $current_datepicker 每個時段的資料是否存在.

    // 已經生成的資訊, 除非設定為 1 才會強置更新.
    $forece_update = 0;

    $insert_count = 0;
    $update_count = 0;
    // 會每次跑 24 次 , 從最接近的時間 $i_min 開始跑
    for($i=0;$i<$dayminutes;$i++) {
      // 開始時間的索引
      $istart  = $i;
      // 結束時間的索引
      $iend    = $i+1;

      // 在目前時間範圍外,不更新, 只更新時間範圍內的.  OR 日期時間不等於今日的話可以執行
      if(strtotime($datetime[$i]) < strtotime($current_timepicker)) {

        // 查詢資料是否已經存在資料庫內, 存在 update , 不存在則 insert
        $check_sql = "SELECT * FROM root_statisticsbetting WHERE dailydate = '".$current_datepicker."' AND dailytime_start = '".$datetime[$istart]."' AND  dailytime_end = '".$datetime[$iend]."';";
        //var_dump($check_sql);
        $check_result  = runSQLall($check_sql);
        //var_dump($check_result);

        // 查詢資料是否已經存在資料庫內, 存在 update , 不存在則 insert
        if($check_result[0] == 1 ){
          // 存在 update , 強至 update 一整天, 直到 $s = false 跳出
          if($forece_update == 1) {
            $s = stat_betting($current_datepicker, $datetime, $datepicker, $istart, $iend);
            //var_dump($s);
            if($s != false){
              // 將資料塞入到資料庫 root_statisticsbetting 中
              $update_sql = "UPDATE root_statisticsbetting SET
              dailydate = '".$s['dairy_date']."',
              dailytime_start = '".$s['dailytime_start']."',
              dailytime_end = '".$s['dailytime_end']."',
              count_accountnumber = '".$s['count_accountnumber']."',
              sum_betting = '".$s['sum_betting']."',
              sum_totalwager = '".$s['sum_totalwager']."',
              sum_profit = '".$s['sum_profit']."',
              casino_classification = '".$s['casino_classification']."',
              notes = '".$s['notes']."',
              updatetime = now(),
              projname = '".$s['projname']."'
              WHERE id = '".$check_result[1]->id."';
              ";
              //var_dump($update_sql);
              $update_result = runSQLall($update_sql);
              if($update_result[0] == 1){
                $logger = 'Success, Update統計的資料'.$s['dairy_date'].'_'.$s['dailytime_start'].'_'.$s['dailytime_end'];
              }else{
                $logger = 'False, Update統計的資料'.$s['dairy_date'].'_'.$s['dailytime_start'].'_'.$s['dailytime_end'];
              }
              //var_dump($logger);
              echo '<p>update '.$current_datepicker.'_'.$datetime[$istart].'_'.$datetime[$iend].'</p>';
            }else{
              $logger = 'Update 沒有資料,  會跳出就表示有問題了. break it!!';
              break;
            }
          }else{
            echo '<p>不強制 update '.$current_datepicker.'_'.$datetime[$istart].'_'.$datetime[$iend].'</p>';
          }

        }else{
          // 不存在則 insert
          // 取得指定日期的投注統計資料
          $s = stat_betting($current_datepicker, $datetime, $datepicker, $istart, $iend);
          //var_dump($s);
          if($s != false){
            // 將資料塞入到資料庫 root_statisticsbetting 中
            $insert_sql = 'INSERT INTO "root_statisticsbetting" ("dailydate", "dailytime_start", "dailytime_end", "count_accountnumber", "sum_betting", "sum_totalwager", "sum_profit", "casino_classification", "notes", "updatetime")'.
            "VALUES ('".$s['dairy_date']."', '".$s['dailytime_start']."', '".$s['dailytime_end']."', '".$s['count_accountnumber']."', '".$s['sum_betting']."', '".$s['sum_totalwager']."', '".$s['sum_profit']."', '".$s['casino_classification']."', '".$s['notes']."', now()  );";
            // var_dump($insert_sql);
            $insert_result = runSQLall($insert_sql);
            if($insert_result[0] == 1){
              $logger = 'Success, 插入統計的資料'.$s['dairy_date'].'_'.$s['dailytime_start'].'_'.$s['dailytime_end'];
            }else{
              $logger = 'False, 插入統計的資料'.$s['dairy_date'].'_'.$s['dailytime_start'].'_'.$s['dailytime_end'];
            }
            var_dump($logger);
          }else{
            $logger = 'Update 沒有資料,  會跳出就表示有問題了. break it!!';
          }

          echo '<p>insert '.$current_datepicker.'_'.$datetime[$istart].'_'.$datetime[$iend].'</p>';
        }
          // end if - else
      }else{
        $logger = $datetime[$i].'本日的時間範圍外,不更新.';
        break;
      }
      // end if
    }
    // end for loop
    var_dump($logger);
  }
  // end if

  // ----------------------------------------------------------------------------
  //
  // ----------------------------------------------------------------------------

  // ----------------------------------------------------------------------------
  //
  // ----------------------------------------------------------------------------


// 一次生成一天的統計資料, 當天資料可以指定更新或是顯示 only





  // 表格標題
  $table_colname_html = '';
  // 表格內容
  $show_listrow_html  = '';

  // ----------------------------------------------------
  // 表格 row -- tables DATA list
  // ----------------------------------------------------
  $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
  // $sorttablecss = ' class="table table-striped" ';
  // 列出資料, 主表格架構
  $show_list_html = '';
  // 列表
  $show_list_html = $show_list_html.'
  <table '.$sorttablecss.'>
  <thead>
  '.$table_colname_html.'
  </thead>
  <tfoot>
  '.$table_colname_html.'
  </tfoot>
  <tbody>
  '.$show_listrow_html.'
  </tbody>
  </table>
  ';

  // 參考使用 datatables 顯示
  // https://datatables.net/examples/styling/bootstrap.html
  $extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  ';


  // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
  $extend_head = $extend_head.'
  <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
      $("#show_list").DataTable( {
          "paging":   true,
          "ordering": true,
          "info":     true,
          "order": [[ 0, "desc" ]],
					"pageLength": 100
      } );
    } )
  </script>
  ';


  // -------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-success">
  <p>* 目前查詢的日期為 '.$current_datepicker.' 的營收日報表，為美東時間(UTC -04)，每日結算時間範圍為 '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04 </p>
  <p>* 對應的中原時間(UTC +08)範圍為：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  </div>';

  // 日報表 -- 選擇日期
  $date_selector_html = '
  <hr>
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">日期(美東時間)</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="daily_statistics_report_date" placeholder="ex:2017-01-20" value="'.$current_datepicker.'"></div>
        <input type="hidden" name="check_key" value="'.sha1('123456').'">
      </div>
    </div>
    <button type="submit" class="btn btn-primary" id="daily_statistics_report_date_query" onclick="blockscreengotoindex();">即時查詢</button>
  </form>
  <hr>';

  // default date
  $dateyearrange_start 	= date("Y");
  $dateyearrange_end 		= date("Y");
  $dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
  // ref: http://api.jqueryui.com/datepicker/#entry-examples
  $date_selector_js = '
  <script>
    $( function() {
      $( "#daily_statistics_report_date" ).datepicker({
        yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
        maxDate: "+0d",
        minDate: "-13w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';
  // 選擇日期 html + JS
  $show_dateselector_html = $date_selector_html.$date_selector_js;
  // -------------------------------------------------------------------------

$show_list_html = '';

  // -------------------------------------------------------------------------
  // 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">

    <div class="col-12 col-md-12">
    '.$show_tips_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$show_dateselector_html.'
    </div>

    <hr>
		<div class="col-12 col-md-12">
    '.$show_list_html.'
		</div>

	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= '<h2><strong>'.$function_title.'</strong></h2><hr>';
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
// include("template/beadmin_fluid.tmpl.php");
?>
