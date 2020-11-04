<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 代理商審查
// File Name:    agent_review.php
// Author:        Barkley
// Related:        對應前台 register_agent.php register_agent_action.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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
//加盟聯營股東申請審查
$function_title         = $tr['agent_review title'];
// 擴充 head 內的 css or js
$extend_head                = '';
// 放在結尾的 js
$extend_js                    = '';
// body 內的主要內容
$indexbody_content    = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁'; $tr['Members and Agents'] = '會員與加盟聯營股東';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Members and Agents'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// 預設可查區間限制
$current_date = gmdate('Y-m-d',time() + -4*3600);
$default_min_date = gmdate('Y-m-d',strtotime('- 2 month'));
$week = gmdate('Y-m-d',strtotime('- 7 days')); // 7天
$min_date = ' 00:00';
$max_date = ' 23:59';
// ----------------------------------------------------------------------------
function queryStr($query_sql_array)
{
    Global $tr;
    $query_top= 0;
    $show_member_log_sql = '';
    if(isset($query_sql_array['query_date_start_datepicker']) or isset($query_sql_array['query_date_end_datepicker'])){
        if (isset($query_sql_array['query_date_start_datepicker']) and $query_sql_array['query_date_start_datepicker'] != null) {
            if ($query_top == 1) {
                $show_member_log_sql = $show_member_log_sql . ' AND ';
                }
            $show_member_log_sql = $show_member_log_sql . 'applicationtime >= \'' . $query_sql_array['query_date_start_datepicker_gmt'] . '\'';
            $query_top = 1;
            }

        if (isset($query_sql_array['query_date_end_datepicker']) and $query_sql_array['query_date_end_datepicker'] != null) {
            if ($query_top == 1) {
                $show_member_log_sql = $show_member_log_sql . ' AND ';
                }
            $show_member_log_sql = $show_member_log_sql . 'applicationtime <= \'' . $query_sql_array['query_date_end_datepicker_gmt'] . '\'';
            $query_top           = 1;
            }

        if($query_top == 1 AND !isset($logger)){
          $return_sql = 'AND '.$show_member_log_sql;
        }elseif(isset($logger)){
          $return_sql['logger'] = $logger;
        }else{
          $return_sql = '';
        }
    }else{
            switch ($query_sql_array['t']) {
                case '1':
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '24 hours')) ";
                    break;
                case '7':
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '7 days')) ";
                    break;
                case '30':
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '30 days')) ";
                    break;
                case '90':
                    $return_sql     = " AND  (applicationtime >= (current_timestamp - interval '90 days')) ";
                    break;
                default:
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '24 hours')) ";
                    break;
            }
           }
    return $return_sql;
}

// -------------------------------------------------------------------------------

function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}


// -------------------------------------------------------------------------------
  // 開始時間
  $sd='';
  if (isset($_POST['sdate']) and $_POST['sdate'] != null) {
      // 判斷格式資料是否正確
    //   if (validateDate($_POST['sdate'], 'Y-m-d')) {
      if (validateDate($_POST['sdate'], 'Y-m-d H:i')) {

          $query_sql_array['query_date_start_datepicker']     = $_POST['sdate'];//.' 00:00:00';
          $sd=$_POST['sdate'];
          $query_sql_array['query_date_start_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u', strtotime($query_sql_array['query_date_start_datepicker'] . '-04') + 8 * 3600) . '+08:00';
        }
    }

  // 結束時間
  $ed='';
  if (isset($_POST['edate']) and $_POST['edate'] != null) {
      // 判斷格式資料是否正確
    //   if (validateDate($_POST['edate'], 'Y-m-d')) {
      if (validateDate($_POST['edate'], 'Y-m-d H:i')) {

          $query_sql_array['query_date_end_datepicker']     = $_POST['edate'];//.' 23:59:59';
          $ed=$_POST['edate'];
          $query_sql_array['query_date_end_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u', strtotime($query_sql_array['query_date_end_datepicker'] . '-04') + 8 * 3600) . '+08:00';
        }
    }

  // 快速查詢
  if (isset($_GET['t']) and $_GET['t'] != null) {
      $query_sql_array['t'] = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
  }

  // 審核未審核filter
  if (isset($_GET['status_filter']) and $_GET['status_filter'] != null) {
    switch ($_GET['status_filter'])
    {
    case 'cancel':
        $get_status_filter = 'cancel';
        $status_filter_query = " AND (root_agent_review.status = NULL OR root_agent_review.status = 0 OR root_agent_review.status = 4) ";
      break;
    case 'unreviewed':
        $get_status_filter = 'unreviewed';
        $status_filter_query = " AND (root_agent_review.status = 2 OR root_agent_review.status = 3) ";
      break;
    case 'audited':
        $get_status_filter = 'audited';
        $status_filter_query = " AND (root_agent_review.status = 1) ";
      break;
    default:
        $get_status_filter = 'unreviewed';
        $status_filter_query = " AND (root_agent_review.status = 2 OR root_agent_review.status = 3) ";
    }
  }else{
    $get_status_filter = 'unreviewed';
    $status_filter_query = " AND (root_agent_review.status = 2 OR root_agent_review.status = 3) ";
  }

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

    // 判斷代理商自動審查的開關, 目前的狀態
    if($protalsetting['agent_review_switch'] == 'manual') {
    $agent_review_switch_html = '<div class="btn-group" role="group" aria-label="Basic example" align="right">
    <a class="btn btn-success btn-sm" href="#" title="'.$tr['Currently reviewing agent qualification manually'].'" role="button">'.$tr['Manual'].'</a>
    <a class="btn btn-default btn-sm" href="protal_setting_deltail.php?sn=default#profile-tab_tab" target="_BLANK" title="'.$tr['Need to go to the Member set management to change the settings'].'" role="button">'.$tr['auto'].'</a>
    </div>';
    }else{
    $agent_review_switch_html = '<div class="btn-group" role="group" aria-label="Basic example">
    <a class="btn btn-default btn-sm" href="protal_setting_deltail.php?sn=default#profile-tab_tab" target="_BLANK" title="'.$tr['Need to go to the Member set management to change the settings'].'" role="button">'.$tr['Manual'].'</a>
    <a class="btn btn-success btn-sm" href="#" title="'.$tr['Currently reviewing agent qualification automatically'].'" role="button">'.$tr['auto'].'</a>
    </div>';
    }


    // $query_sql_array=[];
    // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
        $tz = $_SESSION['agent']->timezone;
    }else{
        $tz = '+08';
    }
    // 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
    $tzone = runSQLALL($tzsql);
    // var_dump($tzone);
    if($tzone[0]==1){
        $tzonename = $tzone[1]->name;
    }else{
        $tzonename = 'posix/Etc/GMT-8';
    }
    // to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz

    //搜尋交易紀錄的條件-yaoyuan
    // ref: https://www.postgresql.org/docs/9.1/static/functions-datetime.html

    $review_agent_status[NULL]         = $tr['state delete'];                //已刪除
    $review_agent_status[0]         = $tr['state cancel'];                //使用者取消
    $review_agent_status[1]         = $tr['state agree'];                    //同意成為代理商
    $review_agent_status[2]         = $tr['state apply'];                    //申請代理提交中
    $review_agent_status[3]         = $tr['state processing'];        //管理端處理中
    $review_agent_status[4]         = $tr['state reject'];                //管理端退回

    $query_str='';
    // 取得查詢條件

  // var_dump($query_sql_array);
  // 2-2去query_str($query_sql_array)函數，產生查詢條件
  if (isset($query_sql_array) and $query_sql_array != null){
    $query_str = queryStr($query_sql_array);
  }else{
    // 沒有資料的話, default 24 hr
    $query_str = " AND (applicationtime >= (current_timestamp - interval '7 days')) ";
  }

  //var_dump($query_str);
  // 列出系統中所有的待審查 agent 帳號及通過的帳號列表
  // -------------------------------------
  // $list_sql = 'SLECT *,'." to_char((applicationtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz ".' FROM "root_agent_review" WHERE "status" IS NOT NULL AND "status" != 1 '.$sql_load_limit.';';
  $list_sql = "
    SELECT *, to_char((applicationtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz,
     to_char((processingtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as processingtime_tz
  , root_member.id as root_member_id, root_agent_review.id as root_agent_review_id, root_agent_review.status as root_agent_review_status, root_agent_review.notes as root_agent_review_notes
  FROM root_member, root_agent_review
  WHERE root_member.account = root_agent_review.account  $query_str
  $status_filter_query ORDER BY root_agent_review_id DESC;
  ";

    $list = runSQLall($list_sql);
    // 資料數量


    /* 先找出所有代理商的資訊 */
    $agentlist = runSQlall("SELECT id, account FROM root_member WHERE therole = 'A'");
    $agentinfo_list = [];
    for ($i = 1; $i <= $agentlist[0]; $i++) {
        $agentinfo_list[$agentlist[$i]->id] = $agentlist[$i]->account;
    };


    $show_listrow_html = '';

    // var_dump($list);
    if($list[0] >= 1) {

        // 列出資料每一行 for loop
        for($i=1;$i<=$list[0];$i++) {

            // 會員帳號查驗連結
            $member_check_html = '<a href="member_account.php?a='.$list[$i]->root_member_id.'" target="_BLANK" title="檢查會員的詳細資料">'.$list[$i]->account.'</a>';
            if ($review_agent_status[$list[$i]->status] == $tr['state apply']) { //單號審核中
                // echo '<pre>', var_dump($list[$i]), '</pre>'; exit();
                $confirm_status_html = <<<HTML
                    <a href="#" class="btn btn-success btn-xs" onclick="confirmAgentRegiste('{$list[$i]->id}', '{$list[$i]->notes}');">{$tr['agree']}</a>&nbsp;
                    <a href="#" class="btn btn-danger btn-xs" onclick="refuseAgentRegiste('{$list[$i]->id}', '{$list[$i]->notes}');">{$tr['disagree']}</a>&nbsp;
                    <a href="agent_review_info.php?id={$list[$i]->id}" class="btn btn-primary btn-xs" target="_blank">{$tr['view']}</a>
                    <script>
                        function confirmAgentRegiste(review_id, review_notes) {
                            if ( confirm('{$tr["confirm register apply"]}') ) {
                                $.post('agent_review_action.php?a=agent_review_submit',
                                    {
                                        agent_review_id: review_id,
                                        agent_notes: review_notes
                                    }, function(result) {
                                        $("body").append(result);
                                    }
                                );
                            }
                        }
                        function refuseAgentRegiste(review_id, review_notes) {
                            if ( confirm('{$tr["refuse register apply"]}') ) {
                                $.post('agent_review_action.php?a=agent_review_cancel',
                                    {
                                        agent_review_id: review_id,
                                        agent_notes: review_notes
                                    }, function(result){
                                        $("body").append(result);
                                    }
                                )
                            }
                        }
                    </script>
                HTML;
            } else if ($review_agent_status[$list[$i]->status] == $tr['state agree']) { //已審核通過
                $confirm_status_html = <<<HTML
                    <a href="./agent_review_info.php?id={$list[$i]->id}" class="btn btn-warning btn-xs">{$tr['seq examination passed']}</a>
                HTML;
            } else { //審核退回
                $confirm_status_html = <<<HTML
                    <a href="./agent_review_info.php?id={$list[$i]->id}" class="btn btn-danger btn-xs">{$tr['application reject']}</a>
                HTML;
            }

            if ($list[$i]->root_agent_review_notes == 'agent_review_automatic') {
                $root_agent_review_notes = '自动审查';
            } else if ($list[$i]->root_agent_review_notes == '協助開戶代理帳號') {
                $root_agent_review_notes = '协助开户代理帐号';
            } else {
                $root_agent_review_notes = $list[$i]->root_agent_review_notes;
            }

            // 審查的選項 -- notes 管理人的備註
            $root_agent_review_notes_edit_html = '<span class="label label-default">'.nl2br($root_agent_review_notes).'</span>';

            // 處理人員帳號
            if($list[$i]->processingaccount == '') {
                $processingaccount_html = $agentinfo_list[$list[$i]->parent_id];
            }else{
                $processingaccount_html = '系統';
            }
            // 處理時間
            if($list[$i]->processingaccount == '') {
                $processingtime_html = gmdate('Y-m-d H:i:s', strtotime($list[$i]->applicationtime_tz)+-4 * 3600);
            }else{
                $processingtime_html = gmdate('Y-m-d H:i:s', strtotime($list[$i]->processingtime_tz)+-4 * 3600);
            }


            // 身份用途是來展示
            // 管理員
            $theroleicon['R'] = '<a href="#" title="'.$tr['Identity Management'].'"><span class="glyphicon glyphicon-king" aria-hidden="true">'.$tr['Identity Management'].'</a>';//代理商
            // 代理商
            $theroleicon['A'] = '<a href="#" title="'.$tr['Identity Agent'].'"><span class="glyphicon glyphicon-knight" aria-hidden="true">'.$tr['Identity Agent'].'</a>';//會員
            // 會員
            $theroleicon['M'] = '<a href="#" title="'.$tr['Identity Member'].'"><span class="glyphicon glyphicon-user" aria-hidden="true">'.$tr['Identity Member'].'</a>';//試用帳號
            // 試用會員
            $theroleicon['T'] = '<a href="#" title="'.$tr['Identity Trial Account'].'"><span class="glyphicon glyphicon-sunglasses" aria-hidden="true">'.$tr['Identity Trial Account'].'</a>';

            // 表格 row
            $show_listrow_html = $show_listrow_html.'
            <tr>
                <td>'.$list[$i]->root_agent_review_id.'</td>
                <td>'.$member_check_html.'</td>
                <td>'.$theroleicon[$list[$i]->therole].'</td>
                <td>'.$list[$i]->realname.'</td>
                <td>'.$list[$i]->mobilenumber.'</td>
                <td>'.$confirm_status_html.'</td>
                <td>'.$list[$i]->applicationip.'</td>
                <td>'.gmdate('Y-m-d H:i:s', strtotime($list[$i]->applicationtime_tz)+-4 * 3600).'</td>
                <td>'.$processingaccount_html.', '.$processingtime_html.'<br>'.$root_agent_review_notes_edit_html.'</td>
            </tr>
            ';
            // 申請時間
            // <td>'.$list[$i]->applicationtime_tz.'</td>
        }
        // 列出資料每一行 for loop -- end
    }

    if(isset($query_sql_array['query_date_start_datepicker'])){
        $sdate = $query_sql_array['query_date_start_datepicker'];
    } else if(isset($query_sql_array['t'])) {
        switch ($query_sql_array['t']) {
            case '1':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 1 days'));
                break;
            case '7':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 7 days'));
                break;
            case '30':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 30 days'));
                break;
            case '90':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 90 days'));
                break;
            default:
                $sdate = $current_date.$min_date;
                break;
        }
    } else {
        $sdate = $current_date.$min_date;
    }

    if(isset($query_sql_array['query_date_end_datepicker'])){
        $edate = $query_sql_array['query_date_end_datepicker'];
    } else {
        $edate = $current_date.$max_date;
    }

    $show_transaction_condition_html = '';
    // 自訂搜尋 + 快速查詢
    //快速查詢 1天內 7天內 30天內 90天內 開始日 結束日 查詢
    $show_transaction_condition_html = '
    <p>
        <form class="form" action="agent_review.php?status_filter='.$get_status_filter.'" method="POST">
            <div class="form-inline">
            <div class="btn-group mr-1 my-1">
                <a href="?t=1&status_filter='.$get_status_filter.'" class="btn btn-default" role="button">'.$tr['within 1 days'].'</a>
                <a href="?t=7&status_filter='.$get_status_filter.'" class="btn btn-default" role="button">'.$tr['within 7 days'].'</a>
                <a href="?t=30&status_filter='.$get_status_filter.'" class="btn btn-default" role="button">'.$tr['within 30 days'] .'</a>
                <a href="?t=90&status_filter='.$get_status_filter.'" class="btn btn-default" role="button">'.$tr['within 90 days'].'</a>
            </div>
                &nbsp
            <div class="input-group mr-1 my-1">
                <div class="input-group-prepend">
                    <span class="input-group-text" id="basic-addon1">'.$tr['start search date'].'</span>
                </div>
                <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" value="'.$sdate.'" placeholder="ex:2018-01-20">
                <div class="input-group-prepend">
                    <span class="input-group-text" id="basic-addon1">~</span>
                  </div>
                  <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" value="'.$edate.'" placeholder="ex:2018-01-20">
             </div>
                <button class="btn btn-primary mr-1 my-1" type="submit" role="button">'.$tr['search'].'</button>
            </div>
        </form>
    </p><hr>
    ';

    //審核狀態篩選
    $review_status = ['cancel'=>$tr['Be Cancel'],'audited'=>$tr['Qualified'],'unreviewed'=>$tr['Unreviewed']];
    $time_query = (isset($query_sql_array['t']))? '&t='.$query_sql_array['t']:'';
    $status_html = '';
    foreach ($review_status as $status_key => $status_value){
        $active_status_filter_btn = ($status_key==$get_status_filter)? 'success':'default';
        $status_html.=<<<HTML
            <a class="btn btn-{$active_status_filter_btn} btn-sm" href="?status_filter={$status_key}{$time_query}"role="button">{$status_value}</a>
HTML;
    }
    $status_filter_html=<<<HTML
    <div class="btn-group mr-3" role="group" align="right">
      {$status_html}
    </div>
HTML;

    $extend_head = $extend_head.'
    <!-- jquery datetimepicker js+css -->
    <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
    <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

    // $extend_js = $extend_js.'
    // <script>
    //         $( "#query_date_start_datepicker" ).datepicker({
    //             showButtonPanel: true,
    //             dateFormat: "yy-mm-dd",
    //             changeMonth: true,
    //             changeYear: true

    //         });
    //         $( "#query_date_end_datepicker" ).datepicker({
    //             showButtonPanel: true,
    //             dateFormat: "yy-mm-dd",
    //             changeMonth: true,
    //             changeYear: true
    //         });
    // </script>';

    // 增加時分秒
    $extend_js = <<<HTML
    <script>
    $("#query_date_start_datepicker").datetimepicker({
        showButtonPanel: true,
        changeMonth: true,
        changeYear: true,
        // timepicker: true,
        defaultTime: '00:00',
        format: "Y-m-d H:i",
        step:1
    });
    $("#query_date_end_datepicker").datetimepicker({
        showButtonPanel: true,
        changeMonth: true,
        changeYear: true,
        // timepicker: true,
        defaultTime: '23:59',
        format: "Y-m-d H:i",
        step:1
    });
    </script>
HTML;

    // 表格欄位名稱
    //申請單號、會員帳號、目前身份、姓名、電話、審核、申請時的IP、申請的時間、處理資訊
    $table_colname_html = '
    <tr>
        <th>'.$tr['seq'].'</th>
        <th>'.$tr['Account'].'</th>
        <th>'.$tr['identity'].'</th>
        <th>'.$tr['agent review name'].'</th>
        <th>'.$tr['Cell Phone'].'</th>
        <th>'.$tr['agent review review'].'</th>
        <th>'.$tr['IP'].'</th>
        <th>'.$tr['apply_time'].'</th>
        <th>'.$tr['agent review process info'].'</th>
    </tr>
    ';

    // enable sort table
    $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
    // $sorttablecss = ' class="table table-striped" ';


// var_dump($protalsetting);

    // 提示管理人員如何進行操作。
    // 申請成為代理商的費用
    $become_agent_payment_cash = $protalsetting['agency_registration_gcash'];
    // 猶豫期(天)
    $become_agent_hesitation_period = $protalsetting['income_commission_reviewperiod_days'];
    $become_agent_payment_gcash_html = money_format('%i', $become_agent_payment_cash);

    //1. 會員申請成為代理商，系統會先扣除  元後，再來進行資料審查。
    //2. 如果資料填寫都正確的話，就會給予通過成為代理商。並有  天的猶豫期來決定是否繼續。
    //3. 當代理商成立後，且立即招收了會員則會員的猶豫期就立即失效，代理商可以立即招收會員。
    //4. 如果猶豫期內需要申請退出，需要聯絡客服人員。成立後系統取消資格並將金額退回會員 GCASH 帳戶。
    $show_tips_html = '';
    // $show_tips_html = '<div class="alert alert-success">
    // '.$tr['agent review tips 1'].$become_agent_payment_gcash_html.$tr['agent review tips 1.5'].'<br>
    // '.$tr['agent review tips 2'].$become_agent_hesitation_period.$tr['agent review tips 2.5'].'<br>
    // '.$tr['agent review tips 3'].'<br>
    // '.$tr['agent review tips 4'].'<br>
    // </div>';

    // 列出 DATA 資料, 主表格架構
    $show_list_html = '
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
                    "searching" : false,
                    "paging":   true,
                    "ordering": true,
                    "info":     true,
          order: [[ 0, "desc" ]]
            } );
        } )
    </script>
    ';


    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content = $indexbody_content.'
    <div class="row">
    <div class="col-12 col-md-12">
    '.$show_transaction_condition_html.'
      </div>
    <div class="col-12 col-md-12">
    '.$show_tips_html.'
      </div>
    <div class="col-12 text-right">
    '.$status_filter_html.$agent_review_switch_html.'
      </div>
        <div class="col-12 col-md-12">
        '.$show_list_html.'
        </div>
    </div>
    <br>
    <div class="row">
        <div id="preview_result"></div>
    </div>
    ';

}else{
    // 沒有登入的顯示提示俊息
    //(x) 只有管理員或有權限的會員才可以登入觀看。
    $show_transaction_list_html  = $tr['only management and login mamber'];

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
$tmpl['html_meta_description']         = $tr['host_descript'];
$tmpl['html_meta_author']                     = $tr['host_author'];
$tmpl['html_meta_title']                     = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']                                = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']                            = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']             = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title.'<p id="agent_review_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>';
// 主要內容 -- content
$tmpl['panelbody_content']                = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/beadmin_fluid.tmpl.php";


?>
