<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 會員端設定管理詳細
// File Name:	systemconfig_information.php
// Author:    Ian, Barkley
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------
//
// 只允許 root 帳號進入觀看, 因為牽涉到系統安全問題. by barkley 2017.9.15
//
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
$function_title 		= $tr['website setting info'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['maintenance management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------



// ----------------------------------------------------------------------------
// 本頁面只允許 $su['ops'] 的帳號, 其餘一律沒有權限。
// 允許使用者的列表
$allow_user_html = '';
foreach ($su['ops'] as &$value) {
  $allow_user_html = $allow_user_html.', '.$value;
}
// ----------------------------------------------------------------------------



// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['ops'])) {
  $extend_head				= $extend_head.'<!-- Jquery UI js+css  -->
                          <script src="in/jquery-ui.js"></script>
                          <link rel="stylesheet"  href="in/jquery-ui.css" >
                          <!-- Jquery blockUI js  -->
                          <script src="./in/jquery.blockUI.js"></script>
                          <!-- Datatables js+css  -->
                          <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                          <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                          <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                          <script type="text/javascript" language="javascript" class="init">
                            $(document).ready(function() {
                              $("#systeminfo").DataTable( {
                                  "autoWidth": false,
                                  "paging":   false,
                                  "ordering": false,
                                  "info":     true
                              } );
                            } )
                          </script>
                          ';


  $table_colname_html = '
  <tr>
    <th>'.$tr['category'].'</th>
    <th>'.$tr['parameter name'].'</th>
    <th>'.$tr['parameter num'].'</th>
    <th>'.$tr['parameter description'].'</th>
  </tr>
  ';

  // 產生 HTML 格式的表格
  // security_mode = true;  安全模式, 預設不會顯示密碼
  $debug = false;
  //$debug = true;
  function make_table_html($cate_desc, $var_name, $var_value, $var_desc, $security_mode = false) {
    global $debug;

    // 如果變數是布林值的話
    if (is_bool($var_value) === true) {
      if($var_value == true) {
        $var_value_show = '[bool] true';
      }else{
        $var_value_show = '[bool] false';
      }
    }else{
      // 如果是安全模式的話, 密碼遮蔽
      if($security_mode == false) {
        $var_value_show = $var_value;
      }else{
        // 如果在 debug 模式的話, 還是顯示帳號資料
        if($debug == true) {
          $var_value_show = $var_value;
        }else{
          $var_value_show = '********';
        }

      }
    }
      $r_html =  '<tr><td>'.$cate_desc.'</td><td>'.$var_name.'</td><td>'.$var_value_show.'</td><td>'.$var_desc.'</td></tr>';
      return($r_html);
  }
  // --------------------------------------------------


  // 取得 systeminfo 區塊內容
  //執行模式
  $cate_desc = $tr['execution mode'];
  $system_info_content  = make_table_html($cate_desc, '$system_mode', $system_mode, $tr['execution mode setting']);
  $system_info_content .= make_table_html($cate_desc, 'system_config_path', dirname(__FILE__), $tr['current file path']);

  //特權帳號
  $cate_desc = $tr['privileged account'];
  $system_info_content  .= make_table_html($cate_desc, '$su[\'ops\']', json_encode($su['ops']), $tr['operations account']);
  $system_info_content  .= make_table_html($cate_desc, '$su[\'master\']', json_encode($su['master']), $tr['webmaster account']);
  $system_info_content  .= make_table_html($cate_desc, '$su[\'superuser\']', json_encode($su['superuser']), $tr['superuser']);


  //訊息記錄層級：
  $cate_desc = $tr['message record level:'];
  $system_info_content  .= make_table_html($cate_desc, '$config[\'log_level\']', $config['log_level'], $tr['member operation records in the platform']);
  $system_info_content  .= make_table_html($cate_desc, '$config[\'casino_transferlog_level\']', $config['casino_transferlog_level'], $tr['member operation records in the casino']);



  // 取得 資料庫設定資訊
  $cate_desc = $tr['database'];
  $system_info_content .= make_table_html($cate_desc, '$pdo[\'db\']', $pdo['db'], $tr['database type']);
  $system_info_content .= make_table_html($cate_desc, '$pdo[\'host\']', $pdo['host'], $tr['database server location']);
  $system_info_content .= make_table_html($cate_desc, '$pdo[\'host4write\']', $pdo['host4write'], $tr['write database server location']);
  $system_info_content .= make_table_html($cate_desc, '$pdo[\'dbname\']', $pdo['dbname'], $tr['database name']);
  $system_info_content .= make_table_html($cate_desc, '$pdo[\'user\']', $pdo['user'], $tr['database account']);
  $system_info_content .= make_table_html($cate_desc, '$pdo[\'password\']', $pdo['password'], $tr['database password'] , true);

  // Redis伺服器
  $cate_desc = $tr['Redis server'];
  $system_info_content .= make_table_html($cate_desc, '$redisdb[\'host\']', $redisdb['host'], $tr['Redis server location']);
  $system_info_content .= make_table_html($cate_desc, '$redisdb[\'auth\']', $redisdb['auth'], $tr['Redis server verify code'], true);
  $system_info_content .= make_table_html($cate_desc, '$redisdb[\'db\']', $redisdb['db'], $tr['Redis server verify id']);


  // 取得 其他伺服器設定資訊
  $cate_desc = $tr['other servers'];
  $system_info_content .= make_table_html($cate_desc, '$config[\'hostname\']', $config['hostname'], $tr['server name']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'footer\']', $config['footer'], $tr['site authorization description']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'website_domainname\']', $config['website_domainname'], $tr['website_domainname']);
  $system_info_content .= make_table_html($cate_desc, '$cdnfullurl', $cdnfullurl, $tr['CDN path']);
  $system_info_content .= make_table_html($cate_desc, '$cdnfullurl', $cdnrooturl, $tr['CDN root path']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'cdn_baseurl\']', $config['cdn_baseurl'], 'cdn_baseurl');
  $system_info_content .= make_table_html($cate_desc, '$config[\'website_project_type\']', $config['website_project_type'], 'website_project_type');
  $system_info_content .= make_table_html($cate_desc, '$config[\'website_type\']', $config['website_type'], $tr['website type']);
  $system_info_content .= make_table_html($cate_desc, '$webtheme[\'themepath\']', $webtheme['themepath'], $tr['THEME path setting of the website template']);
  $system_info_content .= make_table_html($cate_desc, '$webtheme[\'gameiconpath\']', $webtheme['gameiconpath'], $tr['game icon path for website']);
  $system_info_content .= make_table_html($cate_desc, '$cdn4gamesicon', $cdn4gamesicon, $tr['game icon path for website']);


  // mqtt config
  $cate_desc = $tr['MQTT servers'];
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_url\']', $config['mqtt_url'], $tr['MQTT url']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_host\']', $config['mqtt_host'], $tr['MQTT host']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_port\']', $config['mqtt_port'], 'MQTT PORT');
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_username\']', $config['mqtt_username'], $tr['MQTT account']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_password\']', $config['mqtt_password'], $tr['MQTT password'], true);
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_channel_hash\']', $config['mqtt_channel_hash'], $tr['MQTT channel_hash']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_message_reciever_host\']', $config['mqtt_message_reciever_host'], $tr['MQTT reciever_host']);
  $system_info_content .= make_table_html($cate_desc, '$config[\'mqtt_channel_hash_salt\']', $config['mqtt_channel_hash_salt'], 'MQTT SALT');




  // GAME 資訊
  $cate_desc = $tr['GAME Information'];
  $system_info_content .= make_table_html($cate_desc, '$MG_CONFIG[\'apiaccount\']', $MG_CONFIG['apiaccount'], 'MG API account');
  $system_info_content .= make_table_html($cate_desc, '$MG_CONFIG[\'apipassword\']', $MG_CONFIG['apipassword'], 'MG API password' , true);
  $system_info_content .= make_table_html($cate_desc, '$config[\'ig_hashCode\']', $config['ig_hashCode'], $tr['IG general agent hashcode, which is 1-1 relationship with site project code'] , true);
  $system_info_content .= make_table_html($cate_desc, '$config[\'casino_transfer_mode\']', $config['casino_transfer_mode'], $tr['Casino transfer settings 0: Test environment, no transfer 1: Formal environment, transfer will be performed normally'] );

  $system_info_content .= make_table_html($cate_desc, '$stats_config[\'mg_bettingrecords_tables\']', $stats_config['mg_bettingrecords_tables'], $tr['MG betting record table']);
  $system_info_content .= make_table_html($cate_desc, '$stats_config[\'pt_bettingrecords_tables\']', $stats_config['pt_bettingrecords_tables'], $tr['PT betting record table']);
  $system_info_content .= make_table_html($cate_desc, '$stats_config[\'mega_bettingrecords_tables\']', $stats_config['mega_bettingrecords_tables'], $tr['GPK (MEGA) betting record table']);
  $system_info_content .= make_table_html($cate_desc, '$stats_config[\'ec_bettingrecords_tables\']', $stats_config['ec_bettingrecords_tables'], $tr['EC betting record table']);
  $system_info_content .= make_table_html($cate_desc, '$stats_config[\'igsst_bettingrecords_tables\']', $stats_config['igsst_bettingrecords_tables'], $tr['IG SST betting record table']);
  $system_info_content .= make_table_html($cate_desc, '$stats_config[\'ighkt_bettingrecords_tables\']', $stats_config['ighkt_bettingrecords_tables'], $tr['IG HKT betting record table']);

  // 金流
  $cate_desc = $tr['cash flow Information'];
  $system_info_content .= make_table_html($cate_desc, '$config[\'payment_mode\']', $config['payment_mode'], $tr['test: test cash flow release: official cash flow'] );
  $system_info_content .= make_table_html($cate_desc, '$config[\'onlinepay_service\']', json_encode($config['onlinepay_service']), $tr['onlinepay_service']);


  //系統預設資訊
  $cate_desc = $tr['system defaut Information'];
  $system_info_content .= make_table_html($cate_desc, '$system_config[\'default_agent\']', $system_config['default_agent'], $tr['system default agent account']);
  $system_info_content .= make_table_html($cate_desc, '$system_config[\'withdrawal_default_password\']', $system_config['withdrawal_default_password'], $tr['system default agent password']);
  $system_info_content .= make_table_html($cate_desc, '$system_config[\'captcha_for_test\']', $system_config['captcha_for_test'], $tr['test unit login verification code']);
  $system_info_content .= make_table_html($cate_desc, '$stationmail[\'sendto_system_cs\']', $stationmail['sendto_system_cs'], $tr['system defaults account for sending system letters']);
  $system_info_content .= make_table_html($cate_desc, '$gcash_cashier_account', $gcash_cashier_account, $tr['cash account, transfer account proposed by GCASH.']);
  $system_info_content .= make_table_html($cate_desc, '$gtoken_cashier_account', $gtoken_cashier_account, $tr['GTOKEN account, transfer account proposed by GTOKEN.']);



  /*
  // 取得 會員相關設定-會員等級設定
  $system_info_content = $system_info_content.'
  <tr><th><p>會員相關設定-會員等級設定</p></th><th></th><th></th></tr>
  '.$table_colname_html.'
  <tr><td>$member_grade_config[\'depositlimits_upper\']</td><td>'.$member_grade_config['depositlimits_upper'].'</td><td>公司入款限額 - 上限</td></tr>
  <tr><td>$member_grade_config[\'depositlimits_lower\']</td><td>'.$member_grade_config['depositlimits_lower'].'</td><td>公司入款限額 - 下限</td></tr>
  <tr><td>$member_grade_config[\'deposit_allow\']</td><td>'.$member_grade_config['deposit_allow'].'</td><td>公司入款是否開啟 on/off ( 1 / 0 )</td></tr>
  ';
  */


  //會員相關設定
  $cate_desc = $tr['member settings'];
  $system_info_content .= make_table_html($cate_desc, '$member_register[\'registerip_member_numberoftimes\']', $member_register['registerip_member_numberoftimes'], $tr['member registration ip allowed times']);
  $system_info_content .= make_table_html($cate_desc, '$member_register[\'registerfingerprinting_member_numberoftimes\']', $member_register['registerfingerprinting_member_numberoftimes'], $tr['member registration fingerprint code allowed times']);
  $system_info_content .= make_table_html($cate_desc, '$member_register[\'registerip_agent_numberoftimes\']', $member_register['registerip_agent_numberoftimes'], $tr['proxy boot registration ip allowed times']);
  $system_info_content .= make_table_html($cate_desc, '$member_register[\'registerfingerprinting_agent_numberoftimes\']', $member_register['registerfingerprinting_agent_numberoftimes'], $tr['the number of times that the agent guides the registration fingerprint code']);

  //代理商相關設定
  $cate_desc = $tr['agent settings'];
  $system_info_content .= make_table_html($cate_desc, '$system_config[\'agency_registration_gcash\']', $system_config['agency_registration_gcash'], $tr['When a member wants to apply to become an agent, he needs a small amount before he can apply.']);

  $cate_desc = $tr['radiation tissue bonus'];
  $system_info_content .= make_table_html($cate_desc, '$rule[\'commission_1_rate\']', $rule['commission_1_rate'], $tr['Guaranteed four-tier dividend-upstream first tier (those who do not reach the performance will not be included in the dividend calculation, and the number of tiers will be retained until the company account) Unit%']);
  $system_info_content .= make_table_html($cate_desc, '$rule[\'commission_2_rate\']', $rule['commission_2_rate'], $tr['Guaranteed four-tier dividend-upstream tier 2 (those who have not reached the performance will not be included in the dividend calculation, and the number of tiers will be retained until the company account) Unit%']);
  $system_info_content .= make_table_html($cate_desc, '$rule[\'commission_3_rate\']', $rule['commission_3_rate'], $tr['Guaranteed four tiers of dividends-upstream third tier (those who have not reached the performance will not be included in the dividend calculation, and the number of tiers will be retained until the company account) Unit%']);
  $system_info_content .= make_table_html($cate_desc, '$rule[\'commission_4_rate\']', $rule['commission_4_rate'], $tr['Guaranteed four-tier dividend distribution-the fourth-upstream tier (those who have not reached the performance will not be included in the dividend calculation, and the number of tiers will be retained until the company account) Unit%']);
  $system_info_content .= make_table_html($cate_desc, '$rule[\'commission_root_rate\']', $rule['commission_root_rate'], $tr['Guarantee four-tier dividend-company cost, unit%']);




/*
  // 取得 放射線組織-加盟金
  $system_info_content = $system_info_content.'
  <tr><th><p>放射線組織-加盟金</p></th><th></th><th></th></tr>
  '.$table_colname_html.'
  <tr><td>$rule[\'income_commission_reviewperiod_days\']</td><td>'.$rule['income_commission_reviewperiod_days'].'</td><td>審閱期(單位：天)</td></tr>
  <tr><td>$rule[\'stats_commission_days\']</td><td>'.$rule['stats_commission_days'].'</td><td>結算獎金的週期, 每次 n 天算一次  n >=1 n<=7 , 目前系統預設為 1 日 , 設定超過 1 日時, 需要注意重疊時間的問題。(單位：天)</td></tr>
  ';

  // 取得 放射線組織-營業獎金
  $system_info_content = $system_info_content.'
  <tr><th><p>放射線組織-營業獎金</p></th><th></th><th></th></tr>
  '.$table_colname_html.'
  <tr><td>$rule[\'amountperformance\']</td><td>'.$rule['amountperformance'].'</td><td>每個營業點(代理商)需要達成的業績量，才可以參與營運分配。</td></tr>
  <tr><td>$rule[\'sale_bonus_rate\']</td><td>'.$rule['sale_bonus_rate'].'</td><td>營業獎金分紅比例 -- (此獎金分配和反水分配共用，需要注意避免總和超過利潤) , 單位 %</td></tr>
  <tr><td>$rule[\'stats_bonus_days\']</td><td>'.$rule['stats_bonus_days'].'</td><td>營業獎金統計週期 美東時間(日)</td></tr>
  <tr><td>$rule[\'stats_weekday\']</td><td>'.$rule['stats_weekday'].'</td><td>預設星期幾為預設 7 天的起始週期</td></tr>
  ';

  // 取得 放射線組織-公司的營業利潤獎金設定
  $system_info_content = $system_info_content.'
  <tr><th><p>放射線組織-公司的營業利潤獎金設定</p></th><th></th><th></th></tr>
  '.$table_colname_html.'
  <tr><td>$rule[\'stats_profit_day\']</td><td>'.$rule['stats_profit_day'].'</td><td>營利獎金結算週期，每月的幾號？ 美東時間 (固定週期為月)</td></tr>
  <tr><td>$rule[\'amountperformance_month\']</td><td>'.$rule['amountperformance_month'].'</td><td>營利獎金發放門檻 -- option 不一定要限制，看是否為正值</td></tr>
  <tr><td>$rule[\'platformcost_rate\']</td><td>'.$rule['platformcost_rate'].'</td><td>營業利潤計算時 平台佔營運成本的比例</td></tr>
  <tr><td>$rule[\'cashcost_rate\']</td><td>'.$rule['cashcost_rate'].'</td><td>金流成本比例 0.8 ~ 2%</td></tr>
  ';
*/

  //客服資訊
  $cate_desc = $tr['customer service information'];
  $system_info_content .= make_table_html($cate_desc, '$customer_service_cofnig[\'online_weblink\']', $customer_service_cofnig['online_weblink'], $tr['online customer url']);
  $system_info_content .= make_table_html($cate_desc, '$customer_service_cofnig[\'qq\']', $customer_service_cofnig['qq'], $tr['customer QQ']);
  $system_info_content .= make_table_html($cate_desc, '$customer_service_cofnig[\'email\']', $customer_service_cofnig['email'], $tr['customer E-mail']);
  $system_info_content .= make_table_html($cate_desc, '$customer_service_cofnig[\'mobile_tel\']', $customer_service_cofnig['mobile_tel'], $tr['customer tel']);
  $system_info_content .= make_table_html($cate_desc, '$customer_service_cofnig[\'online_weblink\']', '<img style="display:block; width:100px;height:100px;"  src="'.$customer_service_cofnig['wechat_qrcode'].'" />' , $tr['customer WeChat']);



  // -----------------------------------------------------------------------------



  // 輸出 systeminfo 區塊
  $indexbody_content = $indexbody_content.'
  <div class="row">
	  <div class="col-12 col-md-12">
      <div class="alert alert-info">
      '.$tr['This page only allows webmaster'].' '.$allow_user_html.' '.$tr['account access'].'
      </div>
    </div>
  </div>
  <div class="row">
	  <div class="col-12 col-md-12">
      <div id="system_info">
      <table id="systeminfo"  class="display" cellspacing="0" width="100%" >
      <thead>
      '.$table_colname_html.'
      </thead>
      <tbody>
      '.$system_info_content.'
      </tbody>
      </table>
      </div>
    </div>
  </div>
  ';

}else{




  // 沒有登入權限的處理
  $indexbody_content = $indexbody_content.'
  <br>
  <div class="row">
	  <div class="col-12 col-md-12">
      <div class="alert alert-danger">
      此頁面只允許特定帳號 '.$allow_user_html.' '.$tr['account access'].'
      </div>
    </div>
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

?>
