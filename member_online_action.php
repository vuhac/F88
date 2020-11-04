<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 前台會員線上人數
// File Name:   member_online_action.php
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

require_once dirname(__FILE__) ."/online_lib.php";

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

// 會員ID
if(isset($_POST['member_id'])){
	$id = filter_var($_POST['member_id'],FILTER_SANITIZE_NUMBER_INT);
}

// 強制登出 前台會員帳號
if(isset($_POST['f_account_name'])){
		$force_logout = filter_var($_POST['f_account_name'],FILTER_SANITIZE_STRING);
}

// 開關
// if(isset($_REQUEST['account_switch'])){
// 	$the_account_switch = filter_var($_REQUEST['account_switch'],FILTER_SANITIZE_NUMBER_INT);
// }

// session key
if(isset($_POST['f_session_id'])){
	$session_id = filter_var($_POST['f_session_id'],FILTER_SANITIZE_STRING);

}

// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
    $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
    $current_per_size = $page_config['datatables_pagelength'];
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

//	--------------------------------------------------------------------


// 強制登出
// 可以再自行登入
if($action == 'f_force_logout'){

	$front_stage_logout= front_force_logout($force_logout,$session_id);

	if($front_stage_logout == 1){
		echo 'success';
	}else{
		die();
	}

}

// 帳號停用
// 停了帳號後，該會員在這頁會消失，會員無法再登入，除非自行聯繫客服把帳號開啟
// phpredis也得做同步登出
if($action == 'edit_account_status'){

	$to_freeze_account = freeze_m_account($id); // 改狀態
	$front_stage_logout= front_force_logout($force_logout,$session_id); // 登出

	if($front_stage_logout == 1){
		echo 'success';
	}else{
		die();
	}
	
}

// datatable
if($action == 'member_detail'){

	// 主機 PHP Session、前台、後台的 DB 的 REDISDB 編號, 此三個變數提供目前控管前後台的 DB 編號。
	$redisdb['db_session'] = 0;
	// $redisdb['db_front'] = 2;
	// $redisdb['db_back']  = 1;
  
	// 從 redisdb db 2 ($redisdb['db_front']) 取得 所有的 session
	$usersession = Agent_runRedisgetkeyvalue('*', $redisdb['db_front']);
	  
	// 分頁
	// 所有紀錄
	$page['all_records'] = $usersession;
	// 每頁顯示多少
	$page['per_size'] = $current_per_size;
	// 目前所在頁數
	$page['no'] = $current_page_no;
  
	$list = array();
	$list_key = array();
	$show_listrow_html = '';
		
	$link_member_data = '';
	$switch_account = '';
	$account_admin = '';

	// 帳號連結到會員詳細資料，取得會員ID
	$get_member_data = get_member_data();

	// 拆解 session 的分隔字元, 有資料才拆
	if($usersession[0] >= 2) {
		$i=$j= 1;
		// 把資料填入到表格內。
		for($i=1;$i<$usersession[0];$i++) {
			
			// 前台登入者在root_memberlog的資料
			$list[$i] = explode(',', $usersession[$i]['value']);
			$list_key[$i] = $usersession[$i]['key'];

			// ex: kt1_front_testagent 站台代碼_前台_帳號
			$account_value = explode('_', $list[$i][0] );		

			if(!isset($account_value[2])){

			}else{
				
				if (preg_match("/(iPod|iPad|iPhone)/", $list[$i][6]) OR preg_match("/android/i", $list[$i][6])){
					// 手機
					$device_info = '<a href="#" title="'.$list[$i][5].'"><span class="glyphicon glyphicon-phone" aria-hidden="true"></span></a>';
				} else{
					// 桌機
					$device_info = '<a href="#" title="'.$list[$i][5].$list[$i][6].'"><span class="glyphicon glyphicon-blackboard" aria-hidden="true"></span></a>';
				}

				$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";
			
				if(isset($list[$i][2])){
					$login_page = $list[$i][2]; // 瀏覽位置
					$login_time = date('Y-m-d H:i:s',	$list[$i][1]); // 最後時間
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
					$show_ip = ''; 
				}
			
				// 如果用無痕，指紋顯示'-'
				if(isset($list[$i][4]) AND $list[$i][4] == 'No HTTP_REFERER' OR $list[$i][4] == 'NOFINGER' OR $list[$i][4] == 'error'){
					$show = '-';
				}elseif(isset($list[$i][4]) AND strstr($list[$i][4],$protocol)){
					$show = '-';
				}else{
					$show = $list[$i][4];
				}
			
				$accmpid = $get_member_data[$account_value[2]]['id']??$account_value[2];
				// 狀態
				// $accmpstatus = $get_member_data[$account_value[2]]['status'] ?? '0';

				// 帳號連結
				$link_member_data =<<<HTML
				<a href="" title="{$account_value[0]},{$account_value[1]}" class="text-right link_detail" data-account="{$accmpid}">{$account_value[2]}</a>
HTML;
				// 管理
				// 帳號關閉 = 被登出而且不能再登入了
				// 強制登出 = 被登出但可以再自行登入
				$account_admin =<<<HTML
					<button class="btn btn-danger btn-xs edit_status"  data-logout ="{$account_value[2]}" data-session = "{$list_key[$i]}" data-id ="{$accmpid}" id="edit_{$account_value[2]}"  title="{$tr['Close account']}" ><i class="fas fa-key"></i></button>
					<button class="btn btn-warning btn-xs force_logout" data-logout ="{$account_value[2]}" id="force_logout_{$account_value[2]}"  data-session = "{$list_key[$i]}" title="{$tr['force']} {$account_value[2]} {$tr['Logout']}" ><i class="fas fa-power-off"></i></button>
					
					<form target="_blank" id ="link_member_log" action="member_log.php" method="post">
						<button class="btn btn-info btn-xs check_status" id="check_{$account_value[2]}" title="{$tr['check member login status']}"><i class="fas fa-link"></i></button>
						<input type="hidden" id="account" name="account_query" value="{$account_value[2]}">
						<input type="hidden" id="ip_source" name="ip_query" value="{$list[$i][3]}">
						<input type="hidden" id="fingerprint" name="fp_query" value="{$show}">
					</form>
HTML;
			}
			if($account_value[0] == $config['projectid']){
				$list_arr[] = array(
					'no'            => $j, // 序號
					'account'       => $link_member_data, // 帳號
					'last_time'     => $login_time, // 最後時間
					'browser_page'  => $login_page, // 瀏覽位置
					'source_ip'     => $show_ip.'('.$ip_location.')', // 來源IP
					'fp'            => $show, 
					'device'        => $device_info, // 裝置
					'admin'         => $account_admin // 管理
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