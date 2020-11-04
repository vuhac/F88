<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 每日營收日結報表(即時統計)
// File Name:	statistics_daily_immediately.php
// Author:		Barkley
// DB table:  root_statisticsdailyreport  每日營收日結報表
// Related:   每日營收報表, 搭配的程式功能說明
// statistics_daily_immediately.php    後台 - 即時統計 - 每日營收日結報表, 要修改下面的程式增加項目的時候，需要先使用這只程式即時測試函式並驗證。
// statistics_daily_report.php         後台 - 每日營收日結報表(讀取已生成資料庫頁面), 透過 php system 功能呼叫 statistics_daily_output_cmd.php 執行, 主要都從這個程式開始呼叫。
// statistics_daily_report_lib.php     後台 - 每日營收日結報表 - 專用函式庫(計算資料使用函式, 每個統計項目的公式都放這裡)
// statistics_daily_report_action.php  後台 - 每日營收日結報表動作程式 - 透過此程式呼叫 php system command 功能, 及其他後續擴充功能.
// statistics_daily_output_cmd.php     後台 - 每日營收日結報表(命令列模式, 主要用來排程生成日報表)
// command example: /usr/bin/php70 /home/testgpk2demo/web/begpk2/statistics_daily_report_output_cmd.php run 2017-02-26
// Log:
// 2017.2.27 改寫,原本的即時計算移除.以資料庫為主,排程定時統計。
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
// 日結報表函式庫
require_once dirname(__FILE__) ."/statistics_daily_report_lib.php";

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
$function_title 		= '每日營收日結報表(即時統計)';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li><a href="#">營收與行銷</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

  // --------------------------------------------------------------------------
  // 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
  // --------------------------------------------------------------------------
  if(isset($_GET['current_datepicker'])) {
    // 判斷格式資料是否正確
    if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
      $current_datepicker = $_GET['current_datepicker'];
    }else{
      $current_datepicker = date('Y-m-d');
    }
  }else{
    // php 格式的 2017-02-24
    $current_datepicker = date('Y-m-d');
  }
  // var_dump($current_datepicker);

  // 每次的處理量
  if(isset($_GET['current_per_size']) AND $_GET['current_per_size'] != NULL ) {
    $current_per_size = $_GET['current_per_size'];
  }else{
    $current_per_size = 10000;
  }

  // 起始頁面
  if(isset($_GET['current_page_no']) AND $_GET['current_page_no'] != NULL ) {
    $current_page_no = $_GET['current_page_no'];
  }else{
    $current_page_no = 0;
  }
  // --------------------------------------------------------------------------
  // 共有三個 get 變數取得
  // --------------------------------------------------------------------------


  // -------------------------------------
  // 紀錄分頁功能 -- 每日紀錄檔每天的數量應該和 member 數量一樣多, 和 member_wallets 一樣多
  // -------------------------------------
  // 計算會員數量
  // $userlist_sql     = "SELECT * FROM root_member WHERE root_member.status = '1' ORDER BY root_member.id DESC ;";
  $userlist_sql     = "SELECT * FROM root_member ORDER BY root_member.id DESC ;";
  $userlist_count  = runSQL($userlist_sql);
  // var_dump($userlist_count);

  // 所有紀錄數量
  $page['all_records']     = $userlist_count;
  // 每頁顯示多少
  $page['per_size']        = $current_per_size;
  // 可以分成多少頁
  $page['number_max']      = ceil($page['all_records']/$page['per_size']);
  // 目前所在頁數
  $page['no']              = $current_page_no;
  // 換算後的開始紀錄為多少？
  $page['start_records']   = $page['no']*$page['per_size'];
  // var_dump($page);

  // -------------------------------------
  // 搜尋「會員帳號」、「會員雙錢包」資訊, 搭配統計計算出每個人的營運貢獻狀況。 並且製作分頁功能
  // root_member.status = '1' 不用限制，因為會有問題。額外判斷這個狀態，餘額再獎金內扣除。
  // -------------------------------------
  $member_list_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id
  ORDER BY root_member.id ASC
  OFFSET ".$page['start_records']." LIMIT ".$page['per_size'].";
  ";

  // echo $member_list_sql;
  // var_dump($member_list_sql);
  $member_list_result = runSQLall($member_list_sql);
  // var_dump($member_list_result[1]->changetime_tz);

  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($member_list_result[0] >= 1){

    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $member_list_result[0] ; $i++){

      // ----------------------------------------------------
      // 底下 function 共用於 statistics_report_lib.php 檔案內。
      // 會員加盟金 -- 取得 agent_review() 函式，帶入「會員帳號」、「今日時間」
      // ----------------------------------------------------
      $agent_review_result = agent_review($member_list_result[$i]->account, $current_datepicker);
      if($agent_review_result['code'] == 1) {
        $agent_review_reult_html = ''.$agent_review_result['amount'].'';
      }else{
        $agent_review_reult_html = 0;
      }

      // ----------------------------------------------------
      // CASINO 投注的狀況 -- 取得 bettingrecords_mg() 函式，帶入「會員帳號」
      // ----------------------------------------------------
      // ※計算  Mg投注量(TotalWager)、Mg派彩量(TotalPayout)、Mg損益量(ProfitLost)
      // ※方式  Mg損益量(ProfitLost) = Mg投注量(TotalWager) - Mg派彩量(TotalPayout)
      $mg_result = bettingrecords_mg($member_list_result[$i]->mg_account, $current_datepicker);
      //var_dump($mg_result);
      if($mg_result['code'] == 1 AND $mg_result['accountnumber_count'] != NULL){
        $bettingrecords_mg_totalwager_html  = $mg_result['TotalWager'] / 100;
        $bettingrecords_mg_totalpayout_html = $mg_result['TotalPayout'] / 100;
        $bettingrecords_mg_profitlost_html  = $mg_result['ProfitLost'] / 100;
        // 注單量
        $bettingrecords_mg_count_html  = $mg_result['accountnumber_count'];
      }else{
        $bettingrecords_mg_totalwager_html  = 0;
        $bettingrecords_mg_totalpayout_html = 0;
        $bettingrecords_mg_profitlost_html  = 0;
        $bettingrecords_mg_count_html = 0;
      }

      // 判斷顏色 「紅色為 -1」，「綠色為 +1」、「黑色為 0」
      if($bettingrecords_mg_profitlost_html < 0){
        $bettingrecords_mg_profitlost_html = '<span style="color:red;">'.$bettingrecords_mg_profitlost_html.'</span>';
      }else if($bettingrecords_mg_profitlost_html == 0){
        $bettingrecords_mg_profitlost_html = $bettingrecords_mg_profitlost_html;
      }else{
        $bettingrecords_mg_profitlost_html = '<span style="color:green;">'.$bettingrecords_mg_profitlost_html.'</span>';
      }


      // ----------------------------------------------------
      // CASINO 的統計
      // ----------------------------------------------------
      // Member CASINO 總有效投注量 , MG + PT + ...
      $casino_all_bets_html = $bettingrecords_mg_totalwager_html;
      // Member CASINO 總贏的注量 , MG + PT + ...
      $casino_all_wins_html = $bettingrecords_mg_totalpayout_html;
      // Member CASINO 總損益 , MG + PT + ...
      $casino_all_profitlost_html = $bettingrecords_mg_profitlost_html;
      // Member 所有的注單量, MG + PT + more
      $casino_all_count_html = $bettingrecords_mg_count_html;



      // ----------------------------------------------------
      // cashdeposit 現金存款量
      // ----------------------------------------------------
      $cash_result = gcashpassbook_cashdeposit($member_list_result[$i]->account, $current_datepicker);
      if($cash_result['code'] == 1){
        $gcash_cashdeposit_html = $cash_result['balance'];
      }else{
        $gcash_cashdeposit_html = 0;
      }


      // ----------------------------------------------------
      // payonlinedeposit 電子支付存款
      // ----------------------------------------------------
      $cash_result = gcashpassbook_payonlinedeposit($member_list_result[$i]->account, $current_datepicker);
      if($cash_result['code'] == 1){
        $gcash_payonlinedeposit_html = $cash_result['balance'];
      }else{
        $gcash_payonlinedeposit_html = 0;
      }


      // ----------------------------------------------------
      // cashtransfer 現金轉帳
      // ----------------------------------------------------
      $cash_result = gcashpassbook_cashtransfer($member_list_result[$i]->account, $current_datepicker);
      if($cash_result['code'] == 1){
        $gcash_cashtransfer_html = $cash_result['balance'];
      }else{
        $gcash_cashtransfer_html = 0;
      }


      // ----------------------------------------------------
      // cashwithdrawal 現金提款
      // ----------------------------------------------------
      $cash_result = gcashpassbook_cashwithdrawal($member_list_result[$i]->account, $current_datepicker);
      if($cash_result['code'] == 1){
        $gcash_cashwithdrawal_html = $cash_result['balance'];
      }else{
        $gcash_cashwithdrawal_html = 0;
      }


      // ----------------------------------------------------
      // cashgtoken 現金轉代幣, 只看提款.withdrawal
      // ----------------------------------------------------
      $cash_result = gcashpassbook_cashgtoken($member_list_result[$i]->account, $current_datepicker);
      if($cash_result['code'] == 1){
        $gcash_cashgtoken_html = $cash_result['balance'];
      }else{
        $gcash_cashgtoken_html = 0;
      }

      // ----------------------------------------------------




      // ----------------------------------------------------
      // 代幣存款 1
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokendeposit($member_list_result[$i]->account, $current_datepicker);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtoken_tokendeposit_html = $gtoken_result['balance'];
      }else{
        $gtoken_tokendeposit_html = 0;
      }


      // ----------------------------------------------------
      // 代幣優惠 2
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokenfavorable($member_list_result[$i]->account, $current_datepicker);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtoken_tokenfavorable_html = $gtoken_result['balance'];
      }else{
        $gtoken_tokenfavorable_html = 0;
      }


      // ----------------------------------------------------
      // 代幣反水 3
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokenpreferential($member_list_result[$i]->account, $current_datepicker);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtoken_tokenpreferential_html = $gtoken_result['balance'];
      }else{
        $gtoken_tokenpreferential_html = 0;
      }


      // ----------------------------------------------------
      // 代幣派彩 4 -- 和其他的算法不一樣，需要存款-提出 , 可以和 bet log 對帳
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokenpay($member_list_result[$i]->account, $current_datepicker);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtoken_tokenpay_html = $gtoken_result['balance'];
      }else{
        $gtoken_tokenpay_html = 0;
      }

      // ----------------------------------------------------
      // 代幣轉現金 5 -- 會員提款代幣withdrawal會預先扣款，但是可能審核不通過退款。會把會退款紀錄在 deposit
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokengcash($member_list_result[$i]->account, $current_datepicker);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtoken_tokengcash_html = $gtoken_result['balance'];
      }else{
        $gtoken_tokengcash_html = 0;
      }

      // ----------------------------------------------------
      // 代幣回收 6
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokenrecycling($member_list_result[$i]->account, $current_datepicker);
      // var_dump($anti_result);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtoken_tokenrecycling_html = $gtoken_result['balance'];
      }else{
        $gtoken_tokenrecycling_html = 0;
      }


      // ----------------------------------------------------
      // 會員提領現金 7，手續費 n% , 紀錄在 root_withdrawgcash_review ,但是這裡計算成本以存簿  root_member_gcashpassbook的資訊為主。
	    //$transaction_category['cashadministrationfees'] 		= '現金提款行政費';
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_cashadministrationfees($member_list_result[$i]->account, $current_datepicker);
      //var_dump($gtoken_result);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtokenpassbook_cashadministrationfees_html = $gtoken_result['balance'];
      }else{
        $gtokenpassbook_cashadministrationfees_html = 0;
      }


      // ----------------------------------------------------
      // 會員提領代幣 8，提領轉現金的手續費用。 紀錄在 root_withdraw_review ，但是這裡計算成本以存簿 root_member_gtokenpassbook 的資訊為主。
  	  //$transaction_category['tokenadministrationfees'] 				= '代幣提款行政費';
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokenadministrationfees($member_list_result[$i]->account, $current_datepicker);
      //var_dump($gtoken_result);
      if($gtoken_result['code'] == 1 AND $gtoken_result['balance'] != NULL){
        $gtokenpassbook_tokenadministrationfees_html = $gtoken_result['balance'];
      }else{
        $gtokenpassbook_tokenadministrationfees_html = 0;
      }


      // ----------------------------------------------------
      // 代幣提款為現金時，行政稽核不通過的行政費用 9，此紀錄紀錄於後台的代幣提款申請審查紀錄表  root_withdraw_review 內，存簿紀錄分類同代幣領取行政費用。
      // ----------------------------------------------------
      $gtoken_result = gtokenpassbook_tokenadministration($member_list_result[$i]->account, $current_datepicker);
      //var_dump($gtoken_result);
      if($gtoken_result['code'] == 1 AND $gtoken_result['administrative_amount'] != NULL){
        $gtokenpassbook_tokenadministration_html = $gtoken_result['administrative_amount'];
      }else{
        $gtokenpassbook_tokenadministration_html = 0;
      }



      // ----------------------------------------------------
      // 整理資料完成
      // ----------------------------------------------------


      // ----------------------------------------------------
      // 會員帳號基本資訊, 無論是否有資料都會呈現。
      // ----------------------------------------------------
      // 會員ID
      $member_id_html = '<a href="member_account.php?a='.$member_list_result[$i]->id.'" target="_BLANK" title="檢查會員的詳細資料">'.$member_list_result[$i]->id.'</a>';
      // 上一代的資訊
      $member_parent_html = '<a href="member_account.php?a='.$member_list_result[$i]->parent_id.'" target="_BLANK"  title="會員上一代資訊">'.$member_list_result[$i]->parent_id.'</a>';
      // 會員身份
      $member_therole_html = '<a href="#" title="會員身份 R=管理員 A=代理商 M=會員">'.$member_list_result[$i]->therole.'</a>';
      // 會員帳號
      $member_account_html = '<a href="member_treemap.php?id='.$member_list_result[$i]->id.'" target="_BLANK" title="會員的組織結構狀態">'.$member_list_result[$i]->account.'</a>';
      // ----------------------------------------------------


      // ----------------------------------------------------
      // 表格 row -- tables DATA list
      // ----------------------------------------------------
      $show_listrow_html = $show_listrow_html.'
      <tr>
        <td>'.$member_id_html.'</td>
        <td>'.$member_therole_html.'</td>
        <td>'.$member_account_html.'</td>
        <td>'.$agent_review_reult_html.'</td>
        <td>'.$bettingrecords_mg_totalwager_html.'</td>
        <td>'.$bettingrecords_mg_totalpayout_html.'</td>
        <td>'.$bettingrecords_mg_profitlost_html.'</td>
        <td>'.$casino_all_bets_html.'</td>
        <td>'.$casino_all_wins_html.'</td>
        <td>'.$casino_all_profitlost_html.'</td>
        <td>'.$casino_all_count_html.'</td>
        <td>'.$gcash_cashdeposit_html.'</td>
        <td>'.$gcash_payonlinedeposit_html.'</td>
        <td>'.$gcash_cashtransfer_html.'</td>
        <td>'.$gcash_cashwithdrawal_html.'</td>
        <td>'.$gcash_cashgtoken_html.'</td>
        <td>'.$gtoken_tokendeposit_html.'</td>
        <td>'.$gtoken_tokenfavorable_html.'</td>
        <td>'.$gtoken_tokenpreferential_html.'</td>
        <td>'.$gtoken_tokenpay_html.'</td>
        <td>'.$gtoken_tokengcash_html.'</td>
        <td>'.$gtoken_tokenrecycling_html.'</td>
        <td>'.$gtokenpassbook_cashadministrationfees_html.'</td>
        <td>'.$gtokenpassbook_tokenadministrationfees_html.'</td>
        <td>'.$gtokenpassbook_tokenadministration_html.'</td>
      </tr>
      ';

    }
  }else{
    $show_listrow_html = $show_listrow_html.'
    <tr><td>目前無交易資料</td>
      <td></td>  <td></td>   <td></td>  <td></td>
      <td></td>  <td></td>   <td></td>  <td></td>
      <td></td>  <td></td>   <td></td>  <td></td>
      <td></td>  <td></td>   <td></td>  <td></td>
      <td></td>  <td></td>   <td></td>  <td></td>
      <td></td>  <td></td>   <td></td>  <td></td>
    </tr>
    ';
  }
  // ---------------------------------- END table data get
  // 表格欄位名稱
  $table_colname_html = '
  <tr>
    <th><a href="#" title="">會員ID</a></th>
    <th><a href="#" title="">會員身份</a></th>
    <th><a href="#" title="">會員帳號</a></th>
    <th><a href="#" title="">加盟金</a></th>
    <th><a href="#" title="本日使用者">[收入]MG投注量</a></th>
    <th><a href="#" title="本日使用者">[支出]MG派彩量</a></th>
    <th><a href="#" title="本日使用者MG損益量加總">[損益]MG損益量</a></th>
    <th><a href="#" title="本日使用者所有娛樂城投注量的總和">[收入]總投注量</a></th>
    <th><a href="#" title="本日使用者所有娛樂城派彩量的總和">[支出]總派彩量</a></th>
    <th><a href="#" title="本日使用者所有娛樂城的派彩量扣除投注量的總和，正值代表，負值代表。">[損益]總損益量</a></th>
    <th><a href="#" title="本日使用者投注單的筆數">[會員]總注單量</a></th>
    <th><a href="#" title="使用者透過支付方式存入系統使用者帳號的存款總和，正值代表收到現金存款，負值代表轉出款項。">[會員]現金存款</a></th>
    <th><a href="#" title="使用者透過電子支付方式存入系統使用者帳號的存款總和，此數值只有正值。">[會員]電子支付存款</a></th>
    <th><a href="#" title="代理商或是同代理商下的代理商轉帳現金給使用者帳號的總和，正值代表使用者收到的現金總和，負值代表代理商支出的現金量總和。">[會員]現金轉帳</a></th>
    <th><a href="#" title="將系統現金提領取款的分類，正值代表個人本日收到現金總和，負值代表個人本日提出現金總和。">[會員]現金取款</a></th>
    <th><a href="#" title="進入遊戲時，自動從使用者現金帳戶轉帳到代幣帳戶的本日累計總和，負值代表個人本日轉出多少現金總和。。">[會員]現金轉代幣</a></th>
    <th><a href="#" title="本日管理員代幣存款到使用者的總和，帶有自訂稽核值，可從存簿檢查，會員數值只有正值，負值為系統的出納帳號。">[會員]代幣存款</a></th>
    <th><a href="#" title="本日發放的代幣優惠到該使用者的總和，帶有自訂稽核值，可從存簿檢查，會員數值只有正值，負值為系統的出納帳號。">[會員]代幣優惠</a></th>
    <th><a href="#" title="本日發放的代幣反水到該使用者的總和，帶有自訂稽核值，可從存簿檢查，會員數值只有正值，負值為系統的出納帳號。">[會員]代幣反水</a></th>
    <th><a href="#" title="本日發放的代幣派彩到該使用者的總和，無須稽核，可從存簿檢查，會員數值只有正值，負值為系統的出納帳號。">[會員]代幣派彩</a></th>
    <th><a href="#" title="將代幣提領成為現金（取款）的分類，正值代表個人本日收到現金總和，負值代表個人本日提出現金總和。">[會員]代幣取款(現金)</a></th>
    <th><a href="#" title="設計為回收錯誤的代幣發放，包含代幣存款、優惠、反水及派彩等項目，本日該使用者回收的總和，數值只有正值。">[會員]代幣回收</a></th>
    <th><a href="#" title="提領為現金過程中的取款手續費，這裡的負值代表對於站方的支出，正值代表對於站方的收入。此數值來源為取款申請成功扣除取款申請拒絕的加總結果。">現金取款手續費</a></th>
    <th><a href="#" title="代筆提領為現金過程中的稽核不過行政費及取款手續費，這裡的負值代表對於站方的支出，正值代表對於站方的收入。此數值來源為取款申請成功扣除取款申請拒絕的加總結果。">代幣取款行政手續費</a></th>
    <th><a href="#" title="代幣取款為現金時，行政稽核不通過的行政費用，此紀錄紀錄於後台的代幣取款申請審查紀錄表內，存簿紀錄分類同代幣領取行政費用。">行政稽核不通過費用</th>
  </tr>
  ';

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

  // 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
  $extend_head = $extend_head.'
  <!-- x-editable (bootstrap version) -->
  <link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
  <script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
  ';

  // -------------------------------------
  // 計算每日日期的資料，已經生成再資料表 statisticsdailyreport 中的數量
  $dailystats_sql      = "SELECT * FROM root_statisticsdailyreport  WHERE dailydate = '$current_datepicker' ORDER BY member_id DESC ;";
  // var_dump($dailystats_sql);
  $dailystats_count     = runSQL($dailystats_sql);
  // -------------------------------------


  // -------------------------------------------------------------------------
  $show_tips_html = '<div class="alert alert-success">
  <p>* 目前查詢的日期為 '.$current_datepicker.' 的營收日報表，為美東時間(UTC -04)，每日結算時間範圍為 '.$current_datepicker.' 00:00:00 -04 ~ '.$current_datepicker.' 23:59:59 -04 </p>
  <p>* 對應的中原時間(UTC +08)範圍為：'.date( "Y-m-d", strtotime( "$current_datepicker -1 day")).' 13:00:00+08 ~ '.$current_datepicker.' 12:59:59+08</p>
  <p>* 此報表可以即時查驗統計資料，但是統計速度較慢，可以使用底下按鍵即時產生「每日營收日結報表資料」，以利快速查詢統計。</p>
  <p>* 目前系統有效會員有'.$userlist_count.'筆，系統統計「每日營收日結報表」資料庫，日期 '.$current_datepicker.' 有 '.$dailystats_count.' 筆。</p>
  </div>';

  // 日報表 -- 選擇日期
  $date_selector_html = '
  <a href="statistics_daily_immediately.php" title="目前顯示頁面" class="btn btn-success" onclick="blockscreengotoindex();">目前為每日營收日結報表(即時統計)</a>
  <a href="statistics_daily_report.php" title="切換到每日營收日結報表(已生成的資料庫版本)" class="btn btn-default" onclick="blockscreengotoindex();">每日營收日結報表(已生成的資料庫版本)</a>
  <hr>
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">日期(美東時間)</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="daily_statistics_report_date" placeholder="ex:2017-01-20" value="'.$current_datepicker.'"></div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_page_no" input type="number"  min="0" max="100" step="1" value="" placeholder="起點紀錄頁ex:'.$current_page_no.'='.$current_page_no*$current_per_size.'"></div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_per_size" input type="number" min="10" max="10000" step="10" value="" placeholder="每頁資料量ex:'.$current_per_size.'"></div>
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
        minDate: "-1w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';


  // 選擇日期 html
  $show_dateselector_html = $date_selector_html.$date_selector_js;
  // -------------------------------------------------------------------------

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
// include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");
?>
