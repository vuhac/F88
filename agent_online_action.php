<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 後台管端線上人數
// File Name:   agent_online_action.php
// Author:		Mavis
// Related:
// Log:
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

if(isset($_GET['a']) AND isset($_SESSION['agent'])){
    $action = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
    
}else{
    header('Location:home.php');
    die();
}


// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
    $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
    $current_per_size = $page_config['datatables_pagelength'];
    //$current_per_size = 10;
}
  
// 起始頁面, 搭配 current_per_size 決定起始點位置
if(isset($_GET['start']) AND $_GET['start'] != NULL ) {
    $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
}else{
    $current_page_no = 0;
}
// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if(isset($_GET['_'])){
    $secho = $_GET['_'];
}else{
    $secho = '1';
}


// datatable
if($action == 'agent_detail'){

    // Session、前台、後台的 DB 的 DB 位址
    $redisdb['db_session'] = 0;
    // $redisdb['db_front'] = 2;
    // $redisdb['db_back']  = 1;

	// 從 redisdb db 2 ($redisdb['db_front']) 取得 所有的 session
	$usersession = Agent_runRedisgetkeyvalue('*', $redisdb['db']);

    // 分頁
	// 所有紀錄
	$page['all_records'] = $usersession;
	// 每頁顯示多少
	$page['per_size'] = $current_per_size;
	// 目前所在頁數
    $page['no'] = $current_page_no;
    
    $list = array();

    $out = array();
	$show_listrow_html = '';
	// 拆解 session 的分隔字元, 有資料才拆
	if($usersession[0] >= 1) {
        $i=$j=1;
		// 把資料填入到表格內。
		for($i=1;$i<$usersession[0];$i++) {

            // 所有資料
            $list[$i] = explode(',', $usersession[$i]['value']);
   
            // ex: kt1_front_testagent 站台代碼_前台_帳號
            $account_value = explode('_', $list[$i][0] );

            if(isset($list[$i][6])) {
                $device_info = '<div title="'.$list[$i][5]. ' '.$list[$i][6].'"><span class="glyphicon glyphicon-blackboard" aria-hidden="true"></span></div>';
            }else{
                $device_info = '';
            }
                
            if(isset($list[$i][2])){
                $login_page = $list[$i][2]; // 瀏覽位置
                $login_time = date('Y-m-d H:i:s',	$list[$i][1]); // 最後時間
                $show_fp = $list[$i][4]; //fingerprint

                $show_ip = $list[$i][3]; // ip
     
                // ip位置
                $curl_ip_data = curl_ip_region($show_ip);
                foreach($curl_ip_data as $v){
                    if(isset($v['country_en']) AND $v['country_en'] != ''){
                        $ip_location = $v['country_en']." ".$v['city_en'];
                    }else{
                        $ip_location = '暂无地区资料';
                    }
                }

            }else{
                $login_page = '';
                $login_time = '';
                $show_fp = '';
                $show_ip = ''; 
            }
                
            if(!isset($account_value[2])){
                $account_name = 'guest'; // 帳號
                $admin_info = '';

            }else{
                $account_name = $account_value[2];

                // 管理
                $admin_info =<<<HTML
                <form target="_blank" id ="link_member_log" action="member_log.php" method="post">
                    <button class="btn btn-info btn-xs check_status" id="check_{$account_value[2]}" title="{$tr['check login status']}" ><i class="fas fa-link"></i></button>
                    <input type="hidden" id="account" name="account_query" value="{$account_value[2]}">
                    <input type="hidden" id="ip_source" name="ip_query" value="{$show_ip}">
                    <input type="hidden" id="fingerprint" name="fp_query" value="{$show_fp}">
                </form>
HTML;
            }
            if($account_value[0] == $config['projectid']){
                $list_arr[] = array(
                    'no'            => $j, // 序號
                    'account'       => $account_name, // 帳號
                    'last_time'     => $login_time, // 最後時間
                    'browser_page'  => $login_page, // 瀏覽位置
                    'source_ip'     => $show_ip.' ('.$ip_location.')', // 來源IP
                    'fp'            => $show_fp, 
                    'device'        => $device_info, // 裝置
                    'admin'         => $admin_info // 管理
                );
                $j++;
            } 
           
        }
        if(isset($list_arr)){
            $output = array(
                "sEcho" 												=> intval($secho),
                "iTotalRecords" 				        => intval($page['per_size']),
                "iTotalDisplayRecords" 	        => intval($page['all_records']),
                "data" 					=> $list_arr
            );
        }else{
            $output = array(
                "sEcho" 									=> 0,
                "iTotalRecords" 					=> 0,
                "iTotalDisplayRecords" 		=> 0,
                "data" 										=> ''
            );
        }
       
      } else{
            $output = array(
                "sEcho" 									=> 0,
                "iTotalRecords" 					=> 0,
                "iTotalDisplayRecords" 		=> 0,
                "data" 										=> ''
            );
        }
    echo json_encode($output);
    
}

?>