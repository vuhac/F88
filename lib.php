<?php
// ----------------------------------------
// Features:    後台專用 GPK 專用 PHP lib 函式庫
// File Name:    lib.php
// Author:        Barkley
// Editor:      Damocles
// Related:
// Log:
// 2016.8.11 update
// 2019.9.27 update by Damocles
// -----------------------------------------------------------------------------

@session_start();
/*
// 函式索引說明：
// -----------------------------------------------------------------------------
0  ip_limits()  IP 白名單, 兩種方式為 OR 運算, 其中之一符合則為 true , 如不存在就是 false
1  agent_permission() 權限設定功能，放在頁面的最前端 session() 後面如果不符合就禁制進入程式。沒有登入的使用者，也會被拒絕。
2  agent_menu 管理員登入後的選單 for 後台使用
3  agent_menu_member() 切換代理商、會員、及系統管理員身份
4  代理商登入後的畫面 time 及登入人數顯示
5  建立一個 redis client 連線，設定一個 key and value
6  建立一個 redis client in db $db 連線，刪除一個 key
7  如果使用者有登入, session_start 則 redis set 就要寫入資料並延長一次時間.
8  計算 redis server session 線上使用者人數
9  建立一個 redis client 連線，刪除除了當下的 session 以外，刪除所有同使用者的 key
10 redis client 檢查系統中的使用者數量，是否有其他的登入者
11 建立一個 redis client in db $db 連線， 取得指定的 key and value
12 頁腳顯示 and google analytic
13 樣版共用JS及CSS header ： assets_include($conf=NULL)
14 除錯模式：各別頁面有各自的訪問限制，各頁面須配合加上$lib_debug判斷是否開啟或關閉限制，並且預設各頁面所需參數。
   注意事項：merge前須註解該部分程式碼
   * 開啟除錯模式：http://your domain and dir/lib.php?debug_mode=true (例：https://damocles.jutainet.com/begpk2dev/lib.php?debug_mode=true)
   * 關閉除錯模式：http://your domain and dir/lib.php?debug_mode=false (例：https://damocles.jutainet.com/begpk2dev/lib.php?debug_mode=false)
*/



// ----------------------------------------------------------------------------
// (0)
// IP 白名單, 兩種方式為 OR 運算, 其中之一符合則為 true , 如不存在就是 false
// 使用方式, 填入 NULL 代表不使用
// ip = ip_limits( 需要比對的 IP , 白名單IP , IP區間參數)
// $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
// $ipaddress_range_json = '{"upper":"114.33.201.242","lower":"114.33.201.242"}';
// $allowip_json = '["114.33.201.242","114.33.201.241","140.117.72.20","59.127.186.209"]';
// ----------------------------------------------------------------------------
function ip_limits($client_ip = NULL , $allowip_json = NULL, $ipaddress_range_json = NULL){
    // var_dump($ipaddress_range_json);
    //var_dump($allowip_json);

    // IP 上下限判斷
    // ------------------------------------------------------
    if($ipaddress_range_json != NULL) {
        $ipaddress_range = json_decode($ipaddress_range_json);
        //var_dump($ipaddress_range);
        // IP 範圍的下限 IP
        $min    = ip2long($ipaddress_range->lower);
        // IP 範圍的上限 IP
        $max    = ip2long($ipaddress_range->upper);
        // 客戶端比對的 IP
        $needle = ip2long($client_ip);
        // 如果在範圍內 is true , 不然則否
        if(($needle >= $min) AND ($needle <= $max)) {
            $range_result = true;
        }else{
            $range_result = false;
        }
    }else{
        $range_result = false;
    }
    // ------------------------------------------------------


    // IP 白名單判斷
    // ------------------------------------------------------
    if($allowip_json != NULL) {
        $allowip_array = json_decode($allowip_json);
        //var_dump($allowip_array);
        if(in_array($client_ip, $allowip_array))
        {
            $allowip_result = true;
        }else{
          $allowip_result = false;
        }
    }else{
        $allowip_result = false;
    }

    $iplimits_result = ($allowip_result) OR ($range_result);

    return($iplimits_result);
}
// ------------------------------------------------------

// ----------------------------------------------------------------------------
// (1)
// 權限設定功能，放在頁面的最前端 session() 後面如果不符合就禁制進入程式。沒有登入的使用者，也會被拒絕。
// 由兩個地方控制：
// 資料庫的 $_SESSION['agent']->permission
// 這個 lib 的白名單 $white_rule = array("home.php");
// usage:
// ----------------------------------------------------------------------------
function agent_permission() {
    // 前台的IP權限, 有列入的 IP ,就允許. 當為 NULL 時，表示全部允許。
    $member_permission['fornt_ip'] = array("114.33.201.242", "192.168.1.100");
    // 控制前台的頁面權限, 有列入的檔案就是允許的, 當為 NULL 時，表示全部允許。
    // $member_permission['front_member'] = array("home.php", "stationmail.php", "member.php", "wallets.php");
    $member_permission['front_member'] = NULL;
    // 控制後台代理商IP權限, 有列入的 IP ,就允許. 當為 NULL 時，表示全部允許。
    $member_permission['back_ip'] = array("114.33.201.242", "192.168.1.100");
    // 後台代理商頁面權限, 有列入的檔案就是允許的, 當為 NULL 時，表示全部允許。
    // $member_permission['back_agent'] = array("home.php", "member_permission.php", "member.php", "member_account.php", "member_treemap.php");
    $member_permission['back_agent'] = NULL;
    // var_dump($member_permission);
    // 寫入 DB 的格式
    //var_dump(json_encode($member_permission));

    // ----------------------------------------------------------------------------
    // 因為同網域使用的關係, 如果發現 $_SESSION['member'] 存在 session 內, 提示使用者清除後再登入 by mtchang 2017.9.3
    if(isset($_SESSION['member'])) {
        $login_error = '前台、後台同時在同網域有登入<br>請全部登出後在重新登入，避免系統異常。';
        $login_error_js = '<script>document.location.href="agent_login.php";</script>';
        echo $login_error.$login_error_js;
        die();
    }
    // ----------------------------------------------------------------------------

    // ----------------------------------------------------------------------------
    // 權限設定功能
    // ----------------------------------------------------------------------------
    // 如果沒有登入，不可以進入這個頁面。如果權限不對,提示使用者點擊後回到上一頁。
    if(!isset($_SESSION['agent'])) {
        $logger = 'You are not logged in or session timeout. Loading ...';

        $msg=$logger;
        $msg_log=$logger;
        $sub_service='login';
        memberlogtodb('guest','member','warning',"$msg",'guest',"$msg_log",'b',$sub_service);
        // memberlog2db('guest', 'agent login', 'notice', "$logger");

        // 顯示一個不是很醜的 loading 畫面, 然後隔 n 秒後跳轉視窗。
        $show_loading_html = '
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico">
        <title>Loading ...</title>
        <meta content="1; url=index.php" http-equiv="refresh">
        </head>
        <body>
        <div style="height: 80vh;display: flex;justify-content: center;align-items: center;overflow: hidden;cursor: pointer;" onmouseover="">
            <p>'.$logger.'</p>
            <img src="./ui/loading.gif" width="50px" title="'.$logger.'"/>
        </div>
        </body>
        </html>
        ';
        echo $show_loading_html;
        die();
    }else{
        // 陣列存在的頁面檔案，才可以進入系統。權限設定時就是把檔名寫入系統資料庫內，然後取出時就可以使用。

        if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
            // by pass 通過


        }else{

            // 個人管制名單 in db by agent or person
            // $person_allow_rule = json_decode($_SESSION['agent']->permission);
            $person_allow_rule = $member_permission;
            //var_dump($person_allow_rule);
            if(is_null($person_allow_rule)) {
                // 預設允許清單, 只要能登入的 agent 都可以連上的頁面
                $person_allow_rule_agent = array("home.php","agent_login.php");
            }else{
                // 後台的允許檔案清單
                $person_allow_rule_agent = $person_allow_rule['back_agent'];
            }
            //var_dump($person_allow_rule_agent);

            // $thispage = str_replace('/begpk2/',"",$_SERVER['PHP_SELF']);
            // 把這頁的檔名過濾出來 , example: 'PHP_SELF' => string '/begpk2/test.php' 取出 test.php
            $this_selfpage =  preg_replace("/^\/.*\//", '', $_SERVER['PHP_SELF']);
            // var_dump($this_selfpage);
            //OR in_array($thispage, $white_rule)
            // 符合條件的話, 才允許進入系統
            if (in_array($this_selfpage, $person_allow_rule_agent) ) {
                $logger = "allow in $this_selfpage";
                //var_dump($logger);
                // 允許通過
            }else{
                // $logger = "Permission denied in $thispage";
                $logger = "你沒有這個頁面 $this_selfpage 的存取權限";
                //var_dump($logger);

                // 顯示一個不是很醜的 你沒有權限頁面，然後讓使用者可以選擇跳到上一頁，
                $show_permissiondeny_html = '
                <html lang="en">
                <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="shortcut icon" href="favicon.ico">
                <title>'.$logger.'</title>
                </head>
                <body>
                <div style="height: 50vh;display: flex;justify-content: center;align-items: center;overflow: hidden;cursor: pointer;" onmouseover="">
                    <p><img src="ui/emblem-unblock-denied.png" height="90%" onclick="history.back()" alt="'.$logger.'" /></p>
                </div>
                <div style="height: 20vh;display: flex;justify-content: center;align-items: center;overflow: hidden;cursor: pointer;" onmouseover="">
                    <p><button onclick="history.back()" type="submit"><h2>Back to the previous page</h2></button>
                    &nbsp;&nbsp;
                    <button onclick="location.href=\'home.php\'" type="submit"><h2>Back to Home</h2></button>
                    <button onclick="location.href=\'agent_login_action.php?a=logout\'" type="submit"><h2>Logout</h2></button>
                    </p>
                </div>
                </body>
                </html>
                ';
                echo $show_permissiondeny_html;
                die();
            }
            // 每個頁面的管制 END
        }
        // if root


    }
    // ----------------------------------------------------------------------------
    // 權限設定功能 END
    // ----------------------------------------------------------------------------
    return(1);

	// 前台的IP權限, 有列入的 IP ,就允許. 當為 NULL 時，表示全部允許。
	$member_permission['fornt_ip'] = array("114.33.201.242", "192.168.1.100");
	// 控制前台的頁面權限, 有列入的檔案就是允許的, 當為 NULL 時，表示全部允許。
	// $member_permission['front_member'] = array("home.php", "stationmail.php", "member.php", "wallets.php");
	$member_permission['front_member'] = NULL;
	// 控制後台代理商IP權限, 有列入的 IP ,就允許. 當為 NULL 時，表示全部允許。
	$member_permission['back_ip'] = array("114.33.201.242", "192.168.1.100");
	// 後台代理商頁面權限, 有列入的檔案就是允許的, 當為 NULL 時，表示全部允許。
	// $member_permission['back_agent'] = array("home.php", "member_permission.php", "member.php", "member_account.php", "member_treemap.php");
	$member_permission['back_agent'] = NULL;
	// var_dump($member_permission);
	// 寫入 DB 的格式
	//var_dump(json_encode($member_permission));

	// ----------------------------------------------------------------------------
	// 因為同網域使用的關係, 如果發現 $_SESSION['member'] 存在 session 內, 提示使用者清除後再登入 by mtchang 2017.9.3
	if(isset($_SESSION['member'])) {
		$login_error = '前台、後台同時在同網域有登入<br>請全部登出後在重新登入，避免系統異常。';
		$login_error_js = '<script>document.location.href="agent_login.php";</script>';
		echo $login_error.$login_error_js;
		die();
	}
	// ----------------------------------------------------------------------------

	// ----------------------------------------------------------------------------
	// 權限設定功能
	// ----------------------------------------------------------------------------
	// 如果沒有登入，不可以進入這個頁面。如果權限不對,提示使用者點擊後回到上一頁。
	if(!isset($_SESSION['agent'])) {
		$logger = 'You are not logged in or session timeout. Loading ...';

		// memberlog2db('guest', 'agent login', 'notice', "$logger");
		// $logger = 'You are not logged in!! <script>document.location.href="index.php";</script>';

		// 寫入memberlog
        $msg         = $logger; //客服
        $msg_log     = $logger; //RD
        $sub_service = 'login';
        memberlogtodb('guest', 'member', 'warning', $msg, 'guest', "$msg_log", 'b', $sub_service);

		// 顯示一個不是很醜的 loading 畫面, 然後隔 n 秒後跳轉視窗。
		$show_loading_html = '
		<html lang="en">
		<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="shortcut icon" href="favicon.ico">
		<title>Loading ...</title>
		<meta content="1; url=index.php" http-equiv="refresh">
		</head>
		<body>
		<div style="height: 80vh;display: flex;justify-content: center;align-items: center;overflow: hidden;cursor: pointer;" onmouseover="">
			<p>'.$logger.'</p>
			<img src="./ui/loading.gif" width="50px" title="'.$logger.'"/>
		</div>
		</body>
		</html>
		';
		echo $show_loading_html;
		die();
	}else{
		// 陣列存在的頁面檔案，才可以進入系統。權限設定時就是把檔名寫入系統資料庫內，然後取出時就可以使用。

		if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
			// by pass 通過


		}else{

			// 個人管制名單 in db by agent or person
			// $person_allow_rule = json_decode($_SESSION['agent']->permission);
			$person_allow_rule = $member_permission;
			//var_dump($person_allow_rule);
			if(is_null($person_allow_rule)) {
				// 預設允許清單, 只要能登入的 agent 都可以連上的頁面
				$person_allow_rule_agent = array("home.php","agent_login.php");
			}else{
				// 後台的允許檔案清單
				$person_allow_rule_agent = $person_allow_rule['back_agent'];
			}
			//var_dump($person_allow_rule_agent);

			// $thispage = str_replace('/begpk2/',"",$_SERVER['PHP_SELF']);
			// 把這頁的檔名過濾出來 , example: 'PHP_SELF' => string '/begpk2/test.php' 取出 test.php
			$this_selfpage =  preg_replace("/^\/.*\//", '', $_SERVER['PHP_SELF']);
			// var_dump($this_selfpage);
			//OR in_array($thispage, $white_rule)
			// 符合條件的話, 才允許進入系統
			if (in_array($this_selfpage, $person_allow_rule_agent) ) {
				$logger = "allow in $this_selfpage";
				//var_dump($logger);
				// 允許通過
			}else{
				// $logger = "Permission denied in $thispage";
				$logger = "你沒有這個頁面 $this_selfpage 的存取權限";
				//var_dump($logger);

				// 顯示一個不是很醜的 你沒有權限頁面，然後讓使用者可以選擇跳到上一頁，
				$show_permissiondeny_html = '
				<html lang="en">
				<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<link rel="shortcut icon" href="favicon.ico">
				<title>'.$logger.'</title>
				</head>
				<body>
				<div style="height: 50vh;display: flex;justify-content: center;align-items: center;overflow: hidden;cursor: pointer;" onmouseover="">
					<p><img src="ui/emblem-unblock-denied.png" height="90%" onclick="history.back()" alt="'.$logger.'" /></p>
				</div>
				<div style="height: 20vh;display: flex;justify-content: center;align-items: center;overflow: hidden;cursor: pointer;" onmouseover="">
					<p><button onclick="history.back()" type="submit"><h2>Back to the previous page</h2></button>
					&nbsp;&nbsp;
					<button onclick="location.href=\'home.php\'" type="submit"><h2>Back to Home</h2></button>
					<button onclick="location.href=\'agent_login_action.php?a=logout\'" type="submit"><h2>Logout</h2></button>
					</p>
				</div>
				</body>
				</html>
				';
				echo $show_permissiondeny_html;
				die();
			}
			// 每個頁面的管制 END
		}
		// if root

	}
	// ----------------------------------------------------------------------------
	// 權限設定功能 END
	// ----------------------------------------------------------------------------
	return(1);
}

// 針對runSQLall出來的資料移除掉時間戳等資訊(可自訂欲移除的資料表欄位名稱)，避免不必要的資訊傳到前端
// $data 可傳入array or object，會回傳整理過後的 $data
// Last Updated At 2020/02/05 By Damocles
function unset_date_log($data){
    $date_columns = ['deleted_at', 'updated_at', 'created_at'];
    foreach($data as $key_outer=>$val_outer){ // 遍歷資料內容
        foreach($date_columns as $val_inner){ // 逐一檢查是否有指定欄位值
            if($key_outer == $val_inner){
                if( is_array($data) ){
                    unset($data[$key_outer]);
                }
                else if( is_object($data) ){
                    unset($data->$key_outer);
                }
            }
        } // end inner foreach
    } // end outer foreach
    return $data;
} // end unset_date_log --------------------------------------------------------------------

// 引用config.php取得$su給子帳號管理的function使用
// Last Updated At 2020/02/05 By Damocles
include_once('config.php');


// 子帳號管理->頁面權限判斷
// 如果帳號無頁面訪問權限，會直接被中斷訪問並提示錯誤訊息
// Last Updated At 2020/02/05 By Damocles
page_premission($su); // 頁面權限判斷，直接使用
function page_premission($su)
{
    $path_parts = pathinfo($_SERVER['PHP_SELF']);
    $now_page = $path_parts["basename"]; // 現在訪問的頁面
    $su['superuser'] = ( (isset($su['superuser'])) ? $su['superuser'] : [] );

    // 角色是否為維運
    if ( isset($_SESSION['agent']) && ($_SESSION['agent']->therole=='R') ) {
        // 在superuser名單內，可任意訪問
        if ( !in_array($_SESSION['agent']->account, $su['superuser']) ) {
            // 判斷帳號狀態，帳號如果停用或凍結，就導回登入頁
            if ( !account_status($_SESSION['agent']->account) ) {
                die(<<<HTML
                    <script>
                        alert('This account you logined is disabled');
                        location.replace('agent_login_action.php?a=logout');
                    </script>
                HTML);
            }

            // 判斷頁面狀態
            $now_page_status = page_status($now_page);

            // 沒有頁面資料
            if ( is_bool($now_page_status) && ($now_page_status == false) ) {
                $exception_pages = ['agent_login.php', 'agent_login_action.php', 'index.php', 'home.php'];
                if ( !in_array($now_page, $exception_pages) ) {

                    // 判斷未定義的頁面權限群組是否存在
                    if ( !isExsitFunction('unsetted_pages') ) { // 不存在
                        // 產生未定義頁面的群組
                        $generate_unsetted_function = insert_function([
                            'function_name' => 'unsetted_pages',
                            'group_name' => 'system_management',
                            'function_title' => '未定義頁面',
                            'function_description' => '存放未定義的頁面',
                            'function_public' => 'f',
                            'function_status' => 't',
                            'function_maintain_status' => 'f'
                        ]);

                        // 產生未定義頁面的群組失敗，彈跳錯誤訊息
                        if (!$generate_unsetted_function) {
                            die(<<<HTML
                                <script>
                                    alert('Undefined Unsetted Function，Please contract the web keeper.');
                                    history.go(-1);
                                </script>
                            HTML);
                        }
                    }

                    // 將此頁面資料寫入DB
                    $generate_undefined_page = insert_site_page([
                        'page_name' => $now_page,
                        'function_name' => 'unsetted_pages',
                        'page_description' => '未定義的頁面'
                    ]);

                    // 判斷未定義頁面資料寫入狀態
                    if (!$generate_undefined_page) {
                        die(<<<HTML
                            <script>
                                alert('Generate Undefined Page Data Failed，Please contract the web keeper.');
                                history.go(-1);
                            </script>
                        HTML);
                    } else {
                        // 新頁面預設是沒有權限進入
                        die(<<<HTML
                            <script>
                                alert("Sorry, you have no premission to access this page.");
                                history.go(-1);
                            </script>
                        HTML);
                    }
                }
            }
            // 頁面停用
            else if($now_page_status == 'disabled'){
                die(<<<HTML
                    <script>
                        alert('This page({$now_page}) has been disabled，Please contract the web keeper.');
                        history.go(-1);
                    </script>
                HTML);
            }
            // 頁面維護中
            else if($now_page_status == 'maintained'){
                die(<<<HTML
                    <script>
                        alert('This page({$now_page}) is maintaining，Come again later.');
                        history.go(-1);
                    </script>
                HTML);
            }
            else if($now_page_status == 'private'){
                // 判斷權限狀態
                if( !private_page_premission($_SESSION['agent']->account, $now_page, $su) ){
                    die(<<<HTML
                        <script>
                            alert("Sorry, you have no premission to access this page.");
                            history.go(-1);
                        </script>
                    HTML);
                }
            }
        }
    }
} // end page_premission --------------------------------------------------------------------

// 子帳號管理->帳號狀態判斷
// 回傳boolen表示帳號是否可以使用
// Last Updated At 2020/02/05 By Damocles
function account_status($account){
    // 有效帳號狀態查詢
    $query = <<<SQL
        SELECT *
        FROM root_member
        WHERE (account = '{$account}') AND
              (status = '1');
    SQL;

    try{
        $result = runSQLall($query);
    }
    catch(Execption $e){
        return false;
    }
    return ( ($result[0] == 1) ? true : false );
} // end account_status --------------------------------------------------------------------

// 子帳號管理->頁面(功能)開放狀態
// 注意：多個功能可能共用同一個頁面，訪問該頁面需要所有功能的權限
// 回傳 false、disabled、maintained、public、private
// Last Updated At 2020/02/05 By Damocles
function page_status($page_name){
    // 查詢頁面所屬的function資料
    $query = <<<SQL
        SELECT site_functions.function_name,
               site_functions.function_status,
               site_functions.function_maintain_status,
               site_functions.function_public,
               site_page.page_name
        FROM site_functions
        JOIN site_page
            ON (site_functions.function_name = site_page.function_name)
        WHERE (site_page.page_name = '{$page_name}')
    SQL;
    $result = runSQLall($query, 0);
    // 資料表中無該頁面的資料
    if($result[0] == 0){
        return false;
    }
    // 該頁面相對應於一個功能
    else if($result[0] == 1){
        if( !$result[1]->function_status ){
            return 'disabled';
        }
        else if( $result[1]->function_maintain_status ){
            return 'maintained';
        }
        else if( !$result[1]->function_public ){
            return 'private';
        }
        else{
            return 'public';
        }
    }
    // 該頁面相對應於多個功能
    else{
        unset($result[0]);
        $is_disabled = false;
        $is_maintained = false;
        $private = false;
        foreach($result as $val){
            if( !$val->function_status ){
                $is_disabled = true;
            }
            else if( $val->function_maintain_status ){
                $is_maintained = true;
            }
            else if( !$val->function_public ){
                $private = true;
            }
        } // end foreach
        if($is_disabled){
            return 'disabled';
        }
        else if($is_maintained){
            return 'maintained';
        }
        else if($private){
            return 'private';
        }
        else{
            return 'public';
        }
    }
} // end page_status --------------------------------------------------------------------

// 子帳號管理->管制頁面(功能)權限
// 注意：多個功能可能共用同一個頁面，訪問該頁面需要所有功能的權限
// 注意：該function會被其他多個function呼叫，故傳入$su做superuser判斷
// 回傳 boolen
// Last Updated At 2020/02/05 By Damocles
function private_page_premission($account, $page_name, $su){
    $su['superuser'] = ( (isset($su['superuser'])) ? $su['superuser'] : [] );
    // 判斷是否為superuser，superuser不受限制
    if( in_array($account, $su['superuser']) ){
        return true;
    }
    // 比對是否有權限
    else{
        // 查詢有使用該page的所有function，如果沒有資料則回傳false
        $query = <<<SQL
            SELECT site_functions.function_name
            FROM site_functions
            JOIN site_page
                ON (site_functions.function_name = site_page.function_name)
            WHERE (site_page.page_name = '{$page_name}')
        SQL;
        $result = runSQLall($query);
        if($result[0] == 0){
            return false;
        }
        unset($result[0]);
        $relate_functions = $result;

        // 查詢該帳號所有的private function資料
        $query = <<<SQL
            SELECT function_name,
                    premission_status
            FROM root_member_account_unpublic_function_access_premission
            WHERE (account = '{$account}')
        SQL;
        $result = runSQLall($query);
        if($result[0] == 0){
            return false;
        }
        unset($result[0]);
        $account_premission = $result;

        // 比對該page的所有function，是否全部都有權限，只要有一個沒有，該頁面就沒有權限
        $has_premission = true;
        foreach($relate_functions as $val_outer){ // 遍歷跟該page相關的function
            $has_premission_data = false; // 該帳號是否有該function的資料
            foreach($account_premission as $val_inner){ // 遍歷帳號所擁有的權限資料
                if($val_outer->function_name == $val_inner->function_name){
                    $has_premission_data = true;
                    if(!$val_inner->premission_status){
                        $has_premission = false;
                        break 2;
                    }
                }
            } // end inner foreach

            // 如果帳號的權限資料沒有該function的紀錄，那就等同於沒有權限
            if(!$has_premission_data){
                $has_premission = false;
                break;
            }
        } // end outer foreach

        return $has_premission;
    }
} // end private_page_premission --------------------------------------------------------------------


// 子帳號管理->帳號上下級權限判斷
// 角色有:維運(權限最大，正常情況只有1人)->站長(權限第2大，正常情況只有1人)->客服長->客服
// 可以判斷客服A與是否有權限管理客服B(客服A是否有被下放權限管理同屬客服長的客服B)
// 回傳boolen來判斷所傳入帳號是否有權限
// Last Updated At 2020/02/06 By Damocles
function heighter_premission($login_account, $operating_account, $su){
    $su['ops'] = ( isset($su['ops']) ? $su['ops'] : [] );
    $su['master'] = ( isset($su['master']) ? $su['master'] : [] );

    // 以登入帳號不是維運執行判斷(維運擁有最大的權限)
    if( !in_array($login_account, $su['ops']) ){
        // 登入帳號是站長
        if( in_array($login_account, $su['master'] ) ){
            // 站長得操作對象不能是維運
            if( in_array($operating_account, $su['ops']) ){
                return false;
            }
        }
        // 登入帳號是客服長、客服
        else{
            // 查詢登入帳號的id、parent_id
            $query = <<<SQL
                SELECT id,
                       parent_id
                FROM root_member
                WHERE (account = '{$login_account}')
                LIMIT 1;
            SQL;
            $result = runSQL($query, 0);
            $login_account_id = ( ($result[0] == 1) ? $result[1]->id : 0 );
            $login_account_parent_id = ( ($result[0] == 1) ? $result[1]->parent_id : 0 );

            // 查詢被操作帳號的id、parent_id
            $query = <<<SQL
                SELECT id,
                       parent_id
                FROM root_member
                WHERE (account = '{$operating_account}')
                LIMIT 1;
            SQL;
            $result = runSQL($query, 0);
            $operating_account_id = ( ($result[0] == 1) ? $result[1]->id : 0 );
            $operating_account_parent_id = ( ($result[0] == 1) ? $result[1]->parent_id : 0 );

            // 登入帳號是客服長
            if($login_account_parent_id == '1'){
                // 判斷操作帳號是不是自己底下的人
                if($login_account_id != $operating_account_parent_id){
                    return false;
                }
            }
            // 登入帳號是客服
            else{
                // 判斷操作帳號跟登入帳號是否屬於同一個客服長，再判斷登入帳號是否有被下放權限
                if($login_account_parent_id == $operating_account_parent_id){
                    // 判斷登入帳號是否有被下放權限
                    $query = <<<SQL
                        SELECT *
                        FROM root_member_account_unpublic_function_access_premission
                        WHERE (account = '{$login_account}') AND
                              (function_name = 'customer_service_management_authority') AND
                              (premission_status = 't')
                        LIMIT 1;
                    SQL;
                    $result = runSQL($query, 0);
                    if($result[0] != 1){
                        return false;
                    }
                }
                // 2個帳號屬於不同客服長，不能相互編輯
                else{
                    return false;
                }
            }
        }
    }
    return true;
} // end heighter_premission --------------------------------------------------------------------


// 子帳號管理->查詢指定的帳號的指定資料，輸入欲搜尋的欄位類型(account/id)與參數 (已測試)
// 回傳runSQL結果(包含key 0)
// Last Updated At 2020/02/06 By Damocles
function query_account_data($where_column, $val){
    $query = <<<SQL
        SELECT id,
               account,
               realname,
               mobilenumber,
               email,
               status,
               notes
        FROM root_member
    SQL;

    // 加入搜尋條件
    if($where_column == 'account'){
        $query .= <<<SQL
            WHERE (account = '{$val}')
        SQL;
    }
    else if($where_column == 'id'){
        $query .= <<<SQL
            WHERE (id = '{$val}')
        SQL;
    }

    $query .= <<<SQL
        LIMIT 1;
    SQL;

    return runSQLall($query, 0);
} // end query_account_data --------------------------------------------------------------------

// 子帳號管理->更新指定的帳號資料 (已測試)
// 回傳boolen
// Last Updated At 2020/02/14 By Damocles
function update_account_data($account, $data=[]){
    // 傳送過來的密碼要已經先編碼過
    // $().crypt({method:'sha1', source:$('#password_input').val()});
    $update_val_stmt = '';
    $data_length = count($data);
    $columns = [
        'realname',
        'passwd',
        'mobilenumber',
        'email',
        'status',
        'notes'
    ];
    // 組合update stmt
    foreach( $columns as $val ){
        // 判斷是否最後一圈
        if( $data_length == 1 ){
            if( isset($data[$val]) ){
                $update_val_stmt .= $val.'='."'".$data[$val]."'";
            }
        }
        else{
            if( isset($data[$val]) ){
                $data_length--;
                $update_val_stmt .= $val.'='."'".$data[$val]."', ";
            }
        }
    } // end foreach

    $update_sql = <<<SQL
        UPDATE root_member
        SET {$update_val_stmt}
        WHERE (account='{$account}')
    SQL;
    try{
        return ( (runSQLall($update_sql, 0)[0]==1) ? true : false );
    }
    catch(Exception $e){
        return false;
    }
} // end update_account_data --------------------------------------------------------------------

// 子帳號管理->新增帳號資料 (已測試)
// 回傳boolen
// Last Updated At 2020/02/14 By Damocles
function insert_account_data($data=[]){
    $insert_key_stmt = 'therole';
    $insert_val_stmt = "'R'";
    $columns = [
        'account',
        'realname',
        'passwd',
        'mobilenumber',
        'email',
        'status',
        'parent_id',
        'notes',
        'enrollmentdate'
    ];

    foreach( $columns as $val ){
        if( isset($data[$val]) ){
            $insert_key_stmt .= ', '.$val;
            $insert_val_stmt .= ", '".$data[$val]."'";
        }
    } // end foreach

    $insert_stmt = <<<SQL
        INSERT INTO root_member ({$insert_key_stmt}) VALUES ($insert_val_stmt);
    SQL;
    try{
        return ( (runSQLall($insert_stmt, 0)[0]==1) ? true : false );
    }
    catch(Exception $e){
        return false;
    }
} // end insert_account_data --------------------------------------------------------------------


// 子帳號管理->查詢指定的帳號的帳號設定值，輸入欲搜尋的欄位類型(account/id)與參數 (已測試)
// 回傳runSQL結果(包含key 0)
// Last Updated At 2020/02/06 By Damocles
// echo '<pre>', var_dump( query_account_setting('account', 'damocles001') ), '</pre>'; exit();
function query_account_setting($where_column, $val){
    $query = <<<SQL
        SELECT *
        FROM root_member_account_setting
    SQL;

    // 加入搜尋條件
    if($where_column == 'account'){
        $query .= <<<SQL
            WHERE (account = '{$val}')
        SQL;
    }
    else if($where_column == 'id'){
        $query .= <<<SQL
            WHERE (id = '{$val}')
        SQL;
    }

    $query .= <<<SQL
        LIMIT 1;
    SQL;

    return runSQLall($query, 0);
} // end query_account_setting --------------------------------------------------------------------

// 子帳號管理->更新指定帳號的帳號設定值 (已測試)
// 回傳boolen
// Last Updated At 2020/02/07 By Damocles
/* echo '<pre>', var_dump( update_account_setting('damocles001', [
    'gcash_input_min' => '100',
    'gcash_input_max' => '200',
    'gtoken_input_min' => '300',
    'gtoken_input_max' => '400'
]) ), '</pre>'; exit(); */
function update_account_setting($account, $account_setting_data=[]){
    $update_val_stmt = '';
    $is_first = true;
    $columns = [
        'gcash_input_max',
        'gcash_input_daily_max',
        'gtoken_input_max',
        'gtoken_input_daily_max'
    ];
    // 組合update stmt
    foreach( $account_setting_data as $key_outer=>$val_outer ){ // 遍歷傳來的參數
        foreach( $columns as $key_inner=>$val_inner ){
            if( $val_inner == $key_outer ){
                if( $is_first ){
                    $is_first = false;
                    $update_val_stmt .= $val_inner.'='."'".$val_outer."'";
                }
                else{
                    $update_val_stmt .= ', '.$val_inner.'='."'".$val_outer."'";
                }
            }
        } // end inner foreach
    } // end outer foreach

    $update_sql = <<<SQL
        UPDATE root_member_account_setting
        SET {$update_val_stmt}
        WHERE (account='{$account}')
    SQL;
    try{
        return ( (runSQLall($update_sql, 0)[0]==1) ? true : false );
    }
    catch(Exception $e){
        return false;
    }
} // end update_account_setting --------------------------------------------------------------------

// 子帳號管理->新增指定帳號的帳號設定值 (已測試)
// 回傳boolen
// Last Updated At 2020/02/07 By Damocles
// echo '<pre>', var_dump( insert_account_setting([
//     'account' => 'damocles001'
// ]) ), '</pre>'; exit();
function insert_account_setting($account_setting_data=[]){
    // 依照傳入的值產生陳述式的key & value
    $isset_column = ''; // insert欄位
    $isset_column_val = ''; // insert值

    // 非必填資訊
    $normal_column = [
        'gcash_input_max',
        'gcash_input_daily_max',
        'gtoken_input_max',
        'gtoken_input_daily_max'
    ];
    foreach($normal_column as $val){
        if( isset($account_setting_data[$val]) ){
            // 組合欄位
            if($isset_column == ''){
                $isset_column = $val;
            }
            else{
                $isset_column .= ' ,'.$val;
            }

            // 組合值
            if($isset_column_val == ''){
                $isset_column_val = "'".$account_setting_data[$val]."'";
            }
            else{
                $isset_column_val .= " ,'".$account_setting_data[$val]."'";
            }
        }
    } // end foreach

    // 判斷必填資訊是否都有
    $required_column = ['account']; // 必填欄位(可隨意增加，不影響結構)
    foreach($required_column as $val){
        if( !isset($account_setting_data[$val]) ){
            return false;
        }
        else{
            // 組合欄位
            if($isset_column == ''){
                $isset_column = $val;
            }
            else{
                $isset_column .= ' ,'.$val;
            }

            // 組合值
            if($isset_column_val == ''){
                $isset_column_val = "'".$account_setting_data[$val]."'";
            }
            else{
                $isset_column_val .= " ,'".$account_setting_data[$val]."'";
            }
        }
    } // end foreach

    // 判斷帳號是否存在
    $isset_account = ( (query_account_data('account', $account_setting_data['account'])[0] == 1) ? true : false );

    // 帳號設定值資料是否存在
    $isset_account_setting = ( (query_account_setting('account', $account_setting_data['account'])[0] == 1) ? true : false );

    // 因為root_member_account_setting有跟root_member做外鍵綁定，故要判斷
    // 帳號不存在或帳號設定值已存在，皆不需要新增
    if( !$isset_account || $isset_account_setting ){
        return false;
    }

    // 執行寫入帳號的帳號設定值
    $insert_stmt = <<<SQL
        INSERT INTO root_member_account_setting (
            {$isset_column}
        ) VALUES (
            {$isset_column_val}
        )
    SQL;
    try{
        return ( (runSQL($insert_stmt, 0) == 1) ? true : false );
    }
    catch(Exception $e){
        return false;
    }
} // end insert_account_setting --------------------------------------------------------------------


// 判斷function是否存在
// 回傳boolen
// Last Updated At 2020/02/17 By Damocles
function isExsitFunction($function_name) {
    $stmt = <<<SQL
        SELECT *
        FROM "site_functions"
        WHERE ("function_name" = '{$function_name}')
        LIMIT 1;
    SQL;
    $result = runSQLall($stmt, 0);
    return ( ($result[0] == 1) ? true : false );
} // end isset_function --------------------------------------------------------------------

// 查詢已啟用的function
// 回傳runSQL結果(包含key 0)
// Last Updated At 2020/02/17 By Damocles
function enable_functions(){
    $query_stmt = <<<SQL
        SELECT function_name,
               group_name,
               function_title,
               function_description,
               function_public,
               function_status,
               function_maintain_status
        FROM site_functions
        WHERE (function_status = 't')
        ORDER BY function_public ASC, group_name ASC;
    SQL;
    $result = runSQLall($query_stmt, 0);
    return $result;
} // end enable_functions --------------------------------------------------------------------

// 查詢帳號對unpublic function訪問權限
// 回傳runSQL結果(包含key 0)
// Last Updated At 2020/02/17 By Damocles
function query_unpublic_function_premission($account){
    $query_stmt = <<<SQL
        SELECT function_name
        FROM root_member_account_unpublic_function_access_premission
        WHERE ( account = '{$account}' ) AND
              (premission_status = 't');
    SQL;
    $result = runSQLall($query_stmt, 0);
    return $result;
} // end query_unpublic_function_premission --------------------------------------------------------------------

// 比對帳號對function的訪問權限 (返還該帳號對已啟用function的權限-boolen)
// Last Updated At 2020/02/17 By Damocles
// echo '<pre>', var_dump( function_premission('damocles1223') ), '</pre>'; exit();
function function_premission($account=''){
    // 找出已啟用的functions
    $_enable_functions = enable_functions();
    if( $_enable_functions[0] > 0 ){
        unset($_enable_functions[0]);

        // 判斷是否有傳入$account，比對資料判斷該帳號是否有unpublic_function的權限
        if( isset($account) && !empty($account) ){
            $unpublic_function_premission = query_unpublic_function_premission($account);

            // 對unpublic_function有權限 or 沒有任何權限
            if( $unpublic_function_premission[0] > 0 ){
                unset($unpublic_function_premission[0]);
                $unpublic_function_has_premission = true;
            }
            else{
                $unpublic_function_has_premission = false;
            }

            foreach( $_enable_functions as $key_outer=>$val_outer ){ // 遍歷已啟用的function
                // 判斷function為不公開，在資料中加上has_premission屬性
                if( !$val_outer->function_public ){
                    $_enable_functions[$key_outer]->has_premission = false; // 先預設沒有權限，給後面去判斷
                    if( $unpublic_function_has_premission ){
                        foreach( $unpublic_function_premission as $key_inner=>$val_inner ){
                            if( $val_outer->function_name == $val_inner->function_name ){ // 比對到有權限，更新權限資料
                                $_enable_functions[$key_outer]->has_premission = true;
                            }
                        } // end inner foreach
                    }
                }
            } // end outer foreach
            return $_enable_functions;
        }
        // 回傳原始的enable functions資料
        else{
            return $_enable_functions;
        }
    }
    else{
        return [];
    }
} // end function_premission --------------------------------------------------------------------

// 查詢function group資料
// 回傳runSQL結果(包含key 0)
// Last Updated At 2020/02/17 By Damocles
function query_function_group($group_name=''){
    $query_stmt = <<<SQL
        SELECT group_name,
               group_description,
               group_status
        FROM site_page_group
    SQL;

	if( $group_name != '' ){
        $query_stmt .= <<<SQL
            WHERE (group_name = '{$group_name}')
            LIMIT 1
        SQL;
    }
    return runSQLall( $query_stmt, 0 );
} // end query_function_group --------------------------------------------------------------------


// 查詢unpublic function資料
// 回傳runSQL結果(包含key 0)
// Last Updated At 2020/02/19 By Damocles
function query_unpublic_function_data($account, $function_name=''){
    $query = <<<SQL
        SELECT account,
               function_name,
               premission_status
        FROM root_member_account_unpublic_function_access_premission
        WHERE (account = '{$account}')
    SQL;

    if($function_name != ''){
        $query .= <<<SQL
            AND (function_name = '{$function_name}')
        SQL;
    }
    return runSQLall($query, 0);
} // end query_unpublic_function_data --------------------------------------------------------------------

// 更新unpublic function資料
// 回傳boolen
// Last Updated At 2020/02/19 By Damocles
function update_unpublic_function_data($account, $function_name, $premission_status){
    if( is_bool($premission_status) ){
        $premission_status = ( ($premission_status) ? 't' : 'f' );
    }
    $stmt = <<<SQL
        UPDATE root_member_account_unpublic_function_access_premission
        SET premission_status = '{$premission_status}'
        WHERE (account = '{$account}') AND
              (function_name = '{$function_name}')
    SQL;
    return ( (runSQL($stmt, 0) == 1) ? true : false );
} // end update_unpublic_function_data --------------------------------------------------------------------

// 新增unpublic function資料
// 回傳boolen
// Last Updated At 2020/02/19 By Damocles
function insert_unpublic_function_data($account, $function_name, $premission_status){
    if( is_bool($premission_status) ){
        $premission_status = ( ($premission_status) ? 't' : 'f' );
    }
    $stmt = <<<SQL
        INSERT INTO root_member_account_unpublic_function_access_premission (
            account,
            function_name,
            premission_status
        ) VALUES (
            '{$account}',
            '{$function_name}',
            '{$premission_status}'
        );
    SQL;
    return ( (runSQL($stmt, 0) == 1) ? true : false );
} // end insert_unpublic_function_data --------------------------------------------------------------------


// 子帳號管理--權限管理，是否有權限操作權限管理
// 回傳boolen
// Last Updated At 2020/02/24 By Damocles
function update_premission($account, $su){
    // 判斷是否角色R
    $query = <<<SQL
        SELECT *
        FROM root_member
        WHERE (account = '{$account}') AND
              (status = '1') AND
              (therole = 'R')
        LIMIT 1;
    SQL;
    $result_account_premission = runSQLall($query, 0);
    if( $result_account_premission[0] == 1 ){
        // 維運、站長、客服長
        if( in_array( $account, $su['superuser'] ) || ($result_account_premission[1]->parent_id == '1') ){
            return true;
        }
        //客服有被下放權限者
        else{
            $query_sql = <<<SQL
                SELECT *
                FROM root_member_account_unpublic_function_access_premission
                WHERE (account = '{$account}') AND
                      (function_name = 'customer_service_management_authority') AND
                      (premission_status = 't')
                LIMIT 1;
            SQL;
            $result_query = runSQLall( $query_sql, 0 );
            return ( ($result_query[0] == 1) ? true : false );
        }
    }
    else{
        return false;
    }
} // end update_premission

// 查詢function資料，如果未指定function name，則回傳總計數量+所有function的資料&page的array(不判斷function status)。
// actor_management_editor.php
function queryFunction($function_name='')
{
    $stmt = <<<SQL
        SELECT "site_functions"."function_name",
               "site_functions"."group_name",
               "site_page_group"."group_description",
               "site_functions"."function_title",
               "site_functions"."function_description",
               "site_functions"."function_public",
               "site_functions"."function_status",
               "site_functions"."function_maintain_status"
        FROM "site_functions"
        JOIN "site_page_group"
        ON("site_functions"."group_name" = "site_page_group"."group_name")
    SQL;

    if ($function_name != '') {
        $stmt .= <<<SQL
            WHERE ("site_functions"."function_name" = '{$function_name}')
            LIMIT 1;
        SQL;
    } else {
        $stmt .= <<<SQL
            ORDER BY "updated_at" DESC;
        SQL;
    }

    return runSQLall($stmt, 0);
}

// 以function name查詢所屬page
function queryPagesByFunctionName($function_name)
{
    $stmt = <<<SQL
        SELECT "page_name"
        FROM "site_page"
        WHERE ("function_name"='{$function_name}')
        AND ("page_status"='open')
        ORDER BY "updated_at" DESC;
    SQL;
    return runSQLall($stmt, 0);
}

// 更新function資料，成功回傳true，失敗回傳false，功能相依於function queryFunctions。
// actor_management_operate_action.php
function update_function($function_name, $data){

    // 功能相依於function queryFunctions
    $result_query_function_setting = queryFunction($function_name);

    if( $result_query_function_setting[0] > 0 ){
        // 如果設定資料一致的話就不用再更新，回傳true。
        if(
            ($result_query_function_setting[1]->function_title == $data['function_title']) &&
            ($result_query_function_setting[1]->function_public == $data['function_public']) &&
            ($result_query_function_setting[1]->function_status == $data['function_status']) &&
            ($result_query_function_setting[1]->function_maintain_status == $data['function_maintain_status']) &&
            ($result_query_function_setting[1]->function_description == $data['function_description'])
        ){
            return true;
        }
        // 需要更新資料，更新成功回傳true，失敗回傳false
        else{
            $data["function_public"] = ( ($data["function_public"]) ? 't' : 'f' );
            $data["function_status"] = ( ($data["function_status"]) ? 't' : 'f' );
            $data["function_maintain_status"] = ( ($data["function_maintain_status"]) ? 't' : 'f' );

            $update_function_setting = <<<SQL
                UPDATE site_functions
                SET function_title = '{$data["function_title"]}',
                    function_description = '{$data["function_description"]}',
                    function_public = '{$data["function_public"]}',
                    function_status = '{$data["function_status"]}',
                    function_maintain_status = '{$data["function_maintain_status"]}'
                WHERE (function_name = '{$function_name}');
            SQL;
            $result_update_function_setting = runSQLall($update_function_setting, 0);
            return ( ($result_update_function_setting[0] == 1) ? true : false );
        }
    }
    // 查無該function
    else{
        return false;
    }
} // end update_function

// 建立function資料，成功回傳true，失敗回傳false
// 回傳boolen
// Created At 2020/04/08 By Damocles
function insert_function($function_data)
{
    $insert_data = [];
    $allow_columns = [
        'function_name',
        'group_name',
        'function_title',
        'function_description',
        'function_public',
        'function_status',
        'function_maintain_status'
    ];
    // 過濾傳入參數是否合法
    foreach ($allow_columns as $val) {
        if ( isset($function_data[$val]) && !empty($function_data[$val]) ) {
            $insert_data[$val] = (string)$function_data[$val];
        } else {
            $insert_data[$val] = NULL;
        }
    }

    $stmt = <<<SQL
        INSERT INTO "site_functions" (
            "function_name",
            "group_name",
            "function_title",
            "function_description",
            "function_public",
            "function_status",
            "function_maintain_status",
            "updated_at",
            "created_at"
        ) VALUES (
            '{$insert_data["function_name"]}',
            '{$insert_data["group_name"]}',
            '{$insert_data["function_title"]}',
            '{$insert_data["function_description"]}',
            '{$insert_data["function_public"]}',
            '{$insert_data["function_status"]}',
            '{$insert_data["function_maintain_status"]}',
            now(),
            now()
        );
    SQL;
    return ( (runSQLall($stmt)[0] == '1') ? true : false );
}

// 建立site_page資料，成功回傳true，失敗回傳false
// 回傳boolen
// Created At 2020/04/08 By Damocles
function insert_site_page($site_page_data)
{
    $insert_data = [];
    $allow_columns = [
        'page_name',
        'function_name',
        'page_description'
    ];

    // 過濾傳入參數是否合法
    foreach ($allow_columns as $val) {
        if ( isset($site_page_data[$val]) && !empty($site_page_data[$val]) ) {
            $insert_data[$val] = (string)$site_page_data[$val];
        } else {
            $insert_data[$val] = NULL;
        }
    }

    $stmt = <<<SQL
        INSERT INTO "site_page" (
            "page_name",
            "function_name",
            "page_description",
            "page_status",
            "updated_at",
            "created_at"
        ) VALUES (
            '{$insert_data["page_name"]}',
            '{$insert_data["function_name"]}',
            '{$insert_data["page_description"]}',
            'open',
            now(),
            now()
        );
    SQL;

    return ( (runSQLall($stmt)[0] == 1) ? true : false );
}

// 將數值轉換成帶有貨幣符號的數值格式(需引用config.php)
// transCurrencySign($amount, $config['default_locate'], $config['currency_sign']);
function transCurrencySign($amount, $default_locate=null, $currency_sign=null)
{
    if ( ($default_locate == null) || ($currency_sign == null) ) {
        global $config;
        $default_locate = $config['default_locate'];
        $currency_sign = $config['currency_sign'];
    }
    $fmt = new NumberFormatter($default_locate, NumberFormatter::CURRENCY);
    return $fmt->formatCurrency($amount, $currency_sign);
}

// --------------------------------------
// (2)
// 管理員登入後的選單 for 後台使用
// use: echo agent_menu();
// --------------------------------------
/*
* 權限等級
// 維運客服及站長帳號設定，用來做權限區分用
** System   -- 系統管理員,平台維護商 , 指定使者,且為 R 等級 therole
// $su['ops'] 維運客服帳號
// $su['master'] 維運客服帳號
// $su['superuser'] 所有特權帳號
** R -- 站長及客服人員
** A -- 代理商
** M -- 會員
*/
function agent_menu(){
    global $tr;
    global $su;
    global $config;

    // 最高管理員選單
    $root_menu_item_html = '';
    // 管理員選單
    $cs_menu_item_html = '';
    // 代理商選單
    $agent_menu_item_html = '';


    // 只有商城模式的时候, 才出现这个选单
    if($config['website_type'] == 'ecshop') {
        $radiation_organization_menu_html = '
        <li><a href="bonus_commission_agent.php" onclick="blockscreengotoindex();" >'.$tr['radiation organization bonus'].'</a></li>
        <li><a href="bonus_commission_sale.php" onclick="blockscreengotoindex();" >'.$tr['radiation organization operating bonus'].'</a></li>
        <li><a href="bonus_commission_profit.php" onclick="blockscreengotoindex();" >'.$tr['radiation organization profit bonus'].'</a></li>
        <li><a href="bonus_commission_dividendreference.php" onclick="blockscreengotoindex();">'.$tr['radiation organization dividends'].'</a></li>
        ';
    }
    else{
        $radiation_organization_menu_html = '';
    }



    // ---------------------------------------
    // 代理商權限的選單
    // ---------------------------------------
    //會員查詢 新增會員 代理申請審核 試玩帳號管理
    $agent_menu_item_html = $agent_menu_item_html.'
    <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-knight" aria-hidden="true"></span>
        <p>'.$tr['Members and Agents'].'<span class="caret"></span></p>
        </a>
        <ul class="dropdown-menu">
            <li class="dropdown-header">'.$tr['Members and Agents'].'</li>
            <li role="separator" class="divider"></li>
            <li><a href="member.php">'.$tr['Member inquiry'].'</a></li>
            <li><a href="member_create.php">'.$tr['New Member'].'</a></li>
            <li><a href="agent_create.php">'.$tr['Add affiliate associates'].'</a></li>
        </ul>
    </li>';
    // 帳務管理
    //公司入款審核 線上支付看板 現金取款申請審核 加盟金取款申請審核 錢包交易紀錄查詢
    $agent_menu_item_html = $agent_menu_item_html.'
    <li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-piggy-bank" aria-hidden="true"></span>
        <p>'.$tr['Account Management'].'<span class="caret"></span></p>
      </a>
      <ul class="dropdown-menu">
            <li class="dropdown-header">'.$tr['Account Management'].'</li>
            <li role="separator" class="divider"></li>
        <li><a href="transaction_query.php">'.$tr['Transaction history query'].'</a></li>
      </ul>
    </li>';


    // 營收與行銷
    // <li><a href="preferential_calculation_group.php" onclick="blockscreengotoindex();" >團體'.$tr['Preferential calculation'].'</a></li>
    //每日營收日結報表 反水計算 放射線組織加盟獎金 放射線組織營運獎金 放射線組織營利獎金 放射線組織股利發放 行銷優惠編輯器
    $agent_menu_item_html = $agent_menu_item_html.'
    <li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-yen" aria-hidden="true"></span>
        <p>'.$tr['profit and promotion'].'<span class="caret"></span></p>
      </a>
      <ul class="dropdown-menu">
          <li class="dropdown-header">'.$tr['profit and promotion'].'</li>
          <li role="separator" class="divider"></li>
            <li><a href="preferential_calculation.php" onclick="blockscreengotoindex();" >'.$tr['Casino Preferential calculation'].'</a></li>
            <li><a href="agent_profitloss_calculation.php" onclick="blockscreengotoindex();">'.$tr['Casino Agent profitloss calculation'].'</a></li>
      </ul>
    </li>';

    // 各式報表
    // 查詢統計報表 投注紀錄查詢 登入紀錄查詢 娛樂城轉帳紀錄查詢
    $agent_menu_item_html = $agent_menu_item_html.'<li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-print" aria-hidden="true"></span>
        <p>'.$tr['Various reports'].'<span class="caret"></span></p>
        </a>
      <ul class="dropdown-menu">
          <li class="dropdown-header">'.$tr['Various reports'].'</li>
            <li role="separator" class="divider"></li>
          <li><a href="statistics_report.php">'.$tr['search Statistics report'].'</a></li>
        <li><a href="member_betlog.php">'.$tr['Betting record search'].'</a></li>
      </ul>
    </li>';




    // ---------------------------------------
    // 最高管理員權限 root AND R (目前不分權限)
    // ---------------------------------------
    //會員查詢 新增會員 代理申請審核 試玩帳號管理
    $root_menu_item_html = $root_menu_item_html.'
    <li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
      <span class="glyphicon glyphicon-knight" aria-hidden="true"></span>
      <p>'.$tr['Members and Agents'].'<span class="caret"></span></p>
      </a>
      <ul class="dropdown-menu">
          <li class="dropdown-header">'.$tr['Members and Agents'].'</li>
          <li role="separator" class="divider"></li>
        <li><a href="member.php" onclick="blockscreengotoindex();">'.$tr['Member inquiry'].'</a></li>
        <li><a href="member_create.php" onclick="blockscreengotoindex();">'.$tr['New Member'].'</a></li>
            <li><a href="agent_create.php" onclick="blockscreengotoindex();">'.$tr['Add affiliate associates'].'</a></li>
        <li><a href="agent_review.php" onclick="blockscreengotoindex();">'.$tr['Agent application for review'].'</a></li>
        <li><a href="member_register_review.php">'.$tr['member_register_review_title'].'</a></li>
      </ul>
    </li>';
    // 先暫時移除試玩功能
    // <li><a href="trial_admin.php" onclick="blockscreengotoindex();">'.$tr['Demo account management'].'</a></li>

    // 帳務管理
    //公司入款審核 線上支付看板 現金取款申請審核 加盟金取款申請審核 錢包交易紀錄查詢
    $root_menu_item_html = $root_menu_item_html.'
    <li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-piggy-bank" aria-hidden="true"></span>
        <p>'.$tr['Account Management'].'<span class="caret"></span></p>
      </a>
      <ul class="dropdown-menu">
            <li class="dropdown-header">'.$tr['Account Management'].'</li>
            <li role="separator" class="divider"></li>
        <li><a href="depositing_company_audit.php" onclick="blockscreengotoindex();">'.$tr['Depositing audit company'].'</a></li>
            <li><a href="depositing_siteapi_audit.php" onclick="blockscreengotoindex();">'.$tr['Online payment dashboard'].'</a></li>
        <li><a href="withdrawalgtoken_company_audit.php" onclick="blockscreengotoindex();">'.$tr['GTOKEN Application for Withdrawal'].'</a></li>
            <li><a href="withdrawalgcash_company_audit.php" onclick="blockscreengotoindex();">'.$tr['GCASH Application for Withdrawal'].'</a></li>
        <li><a href="transaction_query.php" onclick="blockscreengotoindex();">'.$tr['Transaction history query'].'</a></li>
            <li><a href="transaction_statistics.php" onclick="blockscreengotoindex();">'.$tr['Transaction Statistics report'].'</a></li>
      </ul>
    </li>';
    // 擬移除原本的線上支付看板
    // <li><a href="depositing_onlinepay_audit.php" onclick="blockscreengotoindex();">'.$tr['Online payment dashboard'].'</a></li>

    // 營收與行銷 --> 營銷管理
    // <li><a href="preferential_calculation_group.php" onclick="blockscreengotoindex();" >團體'.$tr['Preferential calculation'].'</a></li>
    //每日營收日結報表 反水計算 放射線組織加盟獎金 放射線組織營運獎金 放射線組織營利獎金 放射線組織股利發放 行銷優惠編輯器
    // <li><a href="message.php" onclick="blockscreengotoindex();">站内讯息</a></li>

    $menu_item_html = '';
     // 取會員端資料，用來顯示/不顯示 menu
     $hide_menu_html = [
        'depositbet_calculation' => [
            'href' => 'agent_depositbet_calculation.php',
            'tr' => 'Deposit betting commission calculation'
        ]
    ];
    // 存款投注傭金
    $protal_setting_sql = <<<SQL
        SELECT * FROM root_protalsetting
            WHERE name = 'depositbet_calculation'
    SQL;
    $result = runSQLall($protal_setting_sql);
    unset($result[0]);

    foreach($result as $k => $v){
        if($v->value == 'on'){
            $menu_item_html =<<<HTML
                <li>
                    <a href="{$hide_menu_html[$v->name]['href']}" onclick="blockscreengotoindex();">{$tr[$hide_menu_html[$v->name]['tr']]}</a>
                </li>
        HTML;
        }
    };

    // 營收與行銷 --> 營銷管理
    $root_menu_item_html .= <<<HTML
    <li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-yen" aria-hidden="true"></span>
        <p>{$tr['profit and promotion']}<span class="caret"></span></p>
        </a>
      <ul class="dropdown-menu">
        <li class="dropdown-header">{$tr['profit and promotion']}</li>
        <li role="separator" class="divider"></li>
        <li><a href="receivemoney_management.php" onclick="blockscreengotoindex();" >{$tr['Pay out management']}</a></li>
        <li><a href="statistics_daily_report.php" onclick="blockscreengotoindex();" >{$tr['Daily Revenue Statement']}</a></li>
        <li><a href="preferential_calculation.php" onclick="blockscreengotoindex();" >{$tr['Casino Preferential calculation']}</a></li>
        <li><a href="realtime_reward.php" onclick="blockscreengotoindex();" >{$tr['Realtime Rebate inquiry'] }</a></li>
        <li><a href="agent_profitloss_calculation.php" onclick="blockscreengotoindex();">{$tr['Casino Agent profitloss calculation']}</a></li>
        <!-- <li><a href="agent_depositbet_calculation.php" onclick="blockscreengotoindex();">{$tr['Deposit betting commission calculation']}</a></li> -->
        {$menu_item_html}
        <li><a href="offer_management.php" onclick="blockscreengotoindex();">{$tr['promotion Offer Editor']}</a></li>
        <li><a href="mail.php" onclick="blockscreengotoindex();">{$tr['letters management']}</a></li>
        <li><a href="announcement_admin.php" onclick="blockscreengotoindex();">{$tr['billboard managemnt'] }</a></li>
        {$radiation_organization_menu_html}
        <li><a href="activity_management.php" title="開發中" onclick="blockscreengotoindex();">{$tr['prmotional code']}</a></li>
      </ul>
    </li>
    HTML;

    // 系統管理
    //會員等級管理 公司入款帳戶管理 線上付商戶管理 會員端設定 反水設定 佣金設定 娛樂城管理 管理端站內信 公佈欄管理 站台設定資訊
    $root_menu_item_html = $root_menu_item_html.'<li class="dropdown">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        <span class="glyphicon glyphicon-wrench" aria-hidden="true"></span>
        <p>'.$tr['System Management'].'<span class="caret"></span></p>
      </a>
      <ul class="dropdown-menu">
          <li class="dropdown-header">'.$tr['System Management'].'</li>
          <li role="separator" class="divider"></li>
        <li><a href="member_grade_config.php" onclick="blockscreengotoindex();">'.$tr['Member level management'].'</a></li>
        <li><a href="deposit_company_config.php" onclick="blockscreengotoindex();">'.$tr['Company Account Management'].'</a></li>
        <li><a href="site_api_config.php" onclick="blockscreengotoindex();">'.$tr['Third-party payment business management'].'</a></li>
        <li><a href="protal_setting_deltail.php?sn=default" onclick="blockscreengotoindex();">'.$tr['Members client settings'].'</a></li>
        <li><a href="preferential_calculation_config.php" onclick="blockscreengotoindex();">'.$tr['Preferential setting'].'</a></li>
        <li><a href="commission_setting.php" onclick="blockscreengotoindex();">'.$tr['Commission setting'].'</a></li>
            <li><a href="casino_switch_process.php" onclick="blockscreengotoindex();">'.$tr['Casino Management'].'</a></li>
      </ul>
    </li>';


// 擬移除原本的線上付款商戶管理
	// <li><a href="deposit_onlinepayment_config.php" onclick="blockscreengotoindex();">'.$tr['Third-party payment business management'].'</a></li>

	// 各式報表
	// 查詢統計報表 投注紀錄查詢 登入紀錄查詢 娛樂城轉帳紀錄查詢
	$root_menu_item_html = $root_menu_item_html.'<li class="dropdown">
	  <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
		<span class="glyphicon glyphicon-print" aria-hidden="true"></span>
		<p>'.$tr['Various reports'].'<span class="caret"></span></p>
		</a>
	  <ul class="dropdown-menu">
		  <li class="dropdown-header">'.$tr['Various reports'].'</li>
			<li role="separator" class="divider"></li>
		  <li><a href="statistics_report.php" onclick="blockscreengotoindex();">'.$tr['search Statistics report'].'</a></li>
	    <li><a href="member_betlog.php" onclick="blockscreengotoindex();">'.$tr['Betting record search'].'</a></li>
	    <li><a href="member_log.php" title="開發中" onclick="blockscreengotoindex();">'.$tr['Login log query'].'</a></li>
        <li><a href="member_casinotransferlog.php" onclick="blockscreengotoindex();">'.$tr['Casino transfer record inquiry'].'</a></li>
        <li><a href="first_store_report.php" onclick="blockscreengotoindex();">'.$tr['menu item title'].'</a></li>
	  </ul>
	</li>';


	// 維運客服及站長帳號設定，用來做權限區分用
	// $su['ops'] 系統維運客服帳號
	// $su['master'] 站長客服帳號
	// $su['superuser'] 所有特權帳號(系統維運+站長)

	// 開發中的功能,限定只有特定帳號 $su['superuser'] 才可以使用
	$ops_menu_html = '';
	$master_menu_html = '';
	// 系統維運客服帳號
	$ops_menu_html .= <<<HTML
		<li class="dropdown-item disabled">{$tr['webmaster function']}</li>
		<li><a href="systemconfig_ann.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['e-business platform']}</a></li>
		<li><a href="cash_management.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['system point management']}</a></li>
		<li role="separator" class="divider"></li>
		<li class="dropdown-item disabled">{$tr['maintenance function']}</li>
		<li><a href="actor_management.php" title="開發中" onclick="blockscreengotoindex();">{$tr['role managment']}</a></li>
		<li><a href="subdomain_management.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['subdomain management']}</a></li>
		<li><a href="systemconfig_information.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['website setting info']}</a></li>
		<li><a href="login_attempt_ip_management.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['login error log management']}</a></li>
		<li role="separator" class="divider"></li>
	HTML;

    // 取得會員端設定資料，用來顯示(不顯示)選項
    $menu_items = [
        'bonus_commision_divdendreference' => [
            'href' => 'bonus_commission_dividendreference.php',
            'tr' => 'radiation organization dividends'
        ],
        'bonus_commision_profit' => [
            'href' => 'bonus_commission_profit.php',
            'tr' => 'radiation organization profit bonus'
        ]/* ,
        'radiationbonus_organization' => [
            'href' => 'radiationbonus_organization.php',
            'tr' => 'Agent Franchise Fee'
        ] */
    ];
    $where_query = ' WHERE (name IN (';
    $round_count = 0; // 代替$key
    foreach( $menu_items as $key=>$val ){
        if( $round_count == 0 ){ // 第一圈
            $where_query .= "'" . $key . "'";
        }
        else{
            $where_query .= ", '" . $key . "'";
        }
        $round_count++;
    } // end foreach
    $where_query .= ') )';
	$protalsetting_sql = <<<SQL
        SELECT *
        FROM root_protalsetting
        {$where_query}
    SQL;
    $protalsetting_result = runSQLall( $protalsetting_sql );
    unset( $protalsetting_result[0] ); // 把總計數量移除掉

    foreach( $protalsetting_result as $key=>$val ){
        if( $val->value=='on' ){
            $ops_menu_html .= <<<HTML
                <li>
                    <a href="{$menu_items[$val->name]['href']}" onclick="blockscreengotoindex();">{$tr[ $menu_items[$val->name]['tr'] ]}</a>
                </li>
            HTML;
        }
    } // end foreach


	// 站長客服帳號選單
	$master_menu_html .= <<<HTML
        <li><a href="systemconfig_announce_read.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['system platform']}</a></li>
        <li><a href="uisetting_management.php" title="開放測試中" onclick="blockscreengotoindex();">{$tr['front sub domain management']}</a></li>
    HTML;

    // 取得會員端設定資料，用來顯示(不顯示)選項
    $menu_items = [
        'radiationbonus_organization' => [
            'href' => 'radiationbonus_organization.php',
            'tr' => 'Agent Franchise Fee'
        ]
    ];
    $where_query = ' WHERE (name IN (';
    $round_count = 0; // 代替$key
    foreach( $menu_items as $key=>$val ){
        if( $round_count == 0 ){ // 第一圈
            $where_query .= "'" . $key . "'";
        }
        else{
            $where_query .= ", '" . $key . "'";
        }
        $round_count++;
    } // end foreach
    $where_query .= ') )';
    $protalsetting_sql = <<<SQL
        SELECT *
        FROM root_protalsetting
        {$where_query}
    SQL;
    $protalsetting_result = runSQLall( $protalsetting_sql );
    unset( $protalsetting_result[0] ); // 把總計數量移除掉

    foreach( $protalsetting_result as $key=>$val ){
        if( $val->value=='on' ){
            $master_menu_html .= <<<HTML
                <li>
                    <a href="{$menu_items[$val->name]['href']}" onclick="blockscreengotoindex();">{$tr[ $menu_items[$val->name]['tr'] ]}</a>
                </li>
            HTML;
        }
    } // end foreach

    $master_menu_html .= <<<HTML
        <li><a href="cash_management.php" title="開發中" onclick="blockscreengotoindex();">{$tr['system point management']}</a></li>
        <li><a href="admin_management.php" title="開放使用中" onclick="blockscreengotoindex();">{$tr['sub-account management']}</a></li>
        <li><a href="member_authentication.php" title="開放使用中" onclick="blockscreengotoindex();">{$tr['User authentication management']}</a></li>
        <li><a href="member_import.php" title="開放使用中" onclick="blockscreengotoindex();">{$tr['Member import management']}</a></li>
    HTML;

	// 站長客服帳號
	if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['master'])) {
		$root_menu_item_html = $root_menu_item_html.'<li class="dropdown">
		  <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
			<span class="glyphicon glyphicon-th" aria-hidden="true"></span>
			<p>'.$tr['webmaster'].'<span class="caret"></span></p>
		 </a>
		  <ul class="dropdown-menu">
			  <li class="dropdown-header">'.$tr['webmaster'].'</li>
				<li role="separator" class="divider"></li>
				'.$master_menu_html.'
		  </ul>
		</li>';
	}

	// 系統維運客服帳號 , 系統維運也要可以看到站長的選單。
	if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['ops'])) {

		$root_menu_item_html = $root_menu_item_html.'<li class="dropdown">
		  <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
			<span class="glyphicon glyphicon-th" aria-hidden="true"></span>
			<p>'.$tr['webmaster'].'<span class="caret"></span></p>
		  </a>
		  <ul class="dropdown-menu">
			  <li class="dropdown-header">'.$tr['webmaster'].'</li>
				<li role="separator" class="divider"></li>
				'.$master_menu_html.'
		  </ul>
		</li>';

		$root_menu_item_html = $root_menu_item_html.'<li class="dropdown">
		  <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
			<span class="glyphicon glyphicon-th" aria-hidden="true"></span>
			<p>'.$tr['maintenance management'].'<span class="caret"></span></p>
		  </a>
		  <ul class="dropdown-menu">
			  <li class="dropdown-header">'.$tr['maintenance management'].'</li>
				<li role="separator" class="divider"></li>
				'.$ops_menu_html.'
				<li class="d-none"><a href="pool.php" onclick="blockscreengotoindex();" class="text-danger">彩池貢獻金(開發中)</a></li>
		  </ul>
		</li>';
  }



    // 依据使用者身份, 给予不同的选单
    if($_SESSION['agent']->therole == 'R' AND $_SESSION['agent']->account == 'root') {
        $menu_html = '
        <ul class="nav navbar-nav">
            '.$root_menu_item_html.'
        </ul>
        ';
    }elseif($_SESSION['agent']->therole == 'R') {
        $menu_html = '
        <ul class="nav navbar-nav">
            '.$root_menu_item_html.'
        </ul>
        ';
    }else{
        $menu_html = '
        <ul class="nav navbar-nav">
            '.$agent_menu_item_html.'
        </ul>
        ';
    }




    return($menu_html);
} // end agent_menu



// --------------------------------------
// (3)
// 切換代理商、會員、及系統管理員身份
// 代理商會員選單 -- 使用者
// --------------------------------------
function agent_menu_member() {
    global $tr;
    global $su;


    // 問候
    $welcome_text = $tr['Hello'];
    if( (date("H") >= 4 ) && (date("H") < 11 )){
        $welcome_text = $tr['Good morning'];
    }elseif( (date("H") >= 11 ) && (date("H") < 17 )){
        $welcome_text = $tr['Good afternoon']     ;
    }elseif( (date("H") >= 17 ) && (date("H") <= 23 )){
        $welcome_text = $tr['Good night'] ;
    }else{
        $welcome_text = $tr['Hello'];
    }

    // 身份切換使用 -- 沒有做
    /*
    $agent_rolechange_html = '
        <li class="dropdown">
          <a href="#" title="'.$tr['Identity change'].'" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
            <span class="glyphicon glyphicon-retweet" aria-hidden="true"><span class="caret"></span></a>
          <ul class="dropdown-menu">
            <li class="dropdown-header">'.$tr['Identity change'].'</li>
            <li role="separator" class="divider"></li>
            <li><a href="#">'.$tr['Explorer'].'</a></li>
            <li class="disabled"><a href="#">'.$tr['Customer service'].'</a></li>
            <li class="disabled"><a href="#">'.$tr['Cashier'].'</a></li>
          </ul>
        </li>
    ';
    */

    // 後台站長廣播訊息紀錄生成
    $ann_sql = <<<SQL
    (SELECT id FROM site_announcement WHERE showinmessage = '1' AND status = '1' AND effecttime <= current_timestamp AND endtime >= current_timestamp)
    EXCEPT
    (SELECT ann_id::BIGINT as id FROM site_announcement_status WHERE account = '{$_SESSION['agent']->account}' AND watchingstatus = '1');
SQL;
  //var_dump($ann_sql);
    $ann_sql_result = runSQLall($ann_sql);
    // var_dump($sysop_bullhorn);
    $sysop_bullhorn_count = $ann_sql_result['0'];
    if($sysop_bullhorn_count >= 1) {
        $sysop_bullhorn_text = '你有'.$sysop_bullhorn_count.'则系统公告，尚未读取。';
        $sysop_bullhorn_show = '<a href="systemconfig_announce_read.php" class="sysop_bullhorn" title="'.$sysop_bullhorn_text.'"><span class="badge">'.$sysop_bullhorn_count.'</span></a>';
    }else{
        $sysop_bullhorn_show = '';
    }

    // 判斷登入者身分並顯示
    $accountstr='';
    if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['master'])){
        $accountstr=$tr['Webmaster'].'，'.$tr['Hello'].'';
    }else if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['ops'])){
        $accountstr= $tr['Maintenance'].'，'.$tr['Hello'].'';
    }else if($_SESSION['agent']->therole == 'R'){
        $accountstr=$tr['Customer service'].'，'.$tr['Hello'].'';
    }else{
        $accountstr=$tr['Hello'];
    }

    // 登入的使用者帳號, 用 class 控制顏色
    $login_user_show = '<span class="login_user_show">'.$_SESSION['agent']->account.'</span>';

    // 登出，修改密碼的選單
    $agent_menu_member_html = '';
    $agent_menu_member_html = $agent_menu_member_html.'
    <ul class="nav navbar-nav navbar-right">
    <li>'.$sysop_bullhorn_show.'</li>
        <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        '.$login_user_show.'
        <span class="caret"></span>
        </a>
          <ul class="dropdown-menu">
              <li class="dropdown-item disabled">'.$accountstr.'</li>
              <li role="separator" class="divider"></li>
            <li><a href="admin_edit.php?i='.$_SESSION['agent']->id.'"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span>'.$tr['Change Password'].'</a></li>
            <li><a href="member_security_setting.php?i='.$_SESSION['agent']->id.'"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span>'.$tr['security setting'].'</a></li>
            <li role="separator" class="divider"></li>
            <li><a href="agent_login_action.php?a=logout"><span class="glyphicon glyphicon-log-out" aria-hidden="true"></span>'.$tr['Logout'].'</a></li>
          </ul>
        </li>
    </ul>
    ';

    return($agent_menu_member_html);
}


// --------------------------------------
// (3 - 1.)
// 提是目前有站內訊息 ( 鈴鐺 + 紅色訊息數量提示)
// --------------------------------------
function agent_menumessage_member() {
    global $tr;
    global $su;
    global $stationmail;
    // 公告
    $announement = [];
    // 站內信
    $mail = [];
    //通知訊息
    $message = [];
    // 公司入款
    $company = [];
    // 線上支付
    $onlinepay = [];
    // 遊戲幣
    $withdrawalgtoken = [];
    // 現金
    $withdrawalgcash = [];
    // 取款
    $withdrawal = [];
    // 入款
    $deposit = [];
    // 代理商
    $agent = [];
    // 會員註冊審查
    $registerreview = [];
    // 會員通知
    $member = [];

    // ----------------------------------------------
    // 顯示線上支付未審核
    $site_api_key = $GLOBALS['config']['gpk2_pay']['apiKey'] ?? '';
    $show_onlinepay_sql=<<<SQL
    SELECT * FROM
    (SELECT a.*, age(now(),request_time) as intervaltime,
    to_char((request_time AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz ,

    b.id as account_id,
    b.parent_id as p_id
    FROM root_site_api_deposit as a

    left join root_member as b
    on a.account=b.account
    )
    as tt
    WHERE intervaltime < interval '90 days'
    AND status = 0
    AND site_account_name='$site_api_key'
    ORDER BY transfertime_tz DESC
SQL;
    $onlinepay_result = runSQLall($show_onlinepay_sql);

    $sysop_onlinepay_count = $onlinepay_result['0'];
    if($sysop_onlinepay_count >= 1) {
        $onlinepaylists = '';
    }else{
        $onlinepaylists = '<li class="nodata">'.$tr['no onlinepay unaudit'].'</li>';
    }
    unset($onlinepay_result[0]);
    $onlinepay_decode = json_decode(json_encode($onlinepay_result),true);
    $i = 0;
    foreach($onlinepay_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $onlinepay['id'] = $value['id'];
        $onlinepay['account'] = $value['account'];
        $onlinepay['amount'] = $value['amount'];
        $onlinepay['transfertime_tz'] = gmdate('Y-m-d H:i',strtotime($value['request_time']) + -4*3600);
        $onlinepaylist = '<li class="data"><a href="depositing_siteapi_audit.php">
                    <span class="title">'.$onlinepay['account'].'</span>
                    <span class="subtitle"><span>'.substr($onlinepay['transfertime_tz'],0,10).'</span>
                    <span>'.$tr['onlinepay'].'</span></span>
                </li>';

        array_push($deposit, [
            'id' => '',
            'account' => $onlinepay['account'],
            'applicationtime_tz' => $onlinepay['transfertime_tz'],
            'type' => $tr['onlinepay'],
            'link' => 'depositing_siteapi_audit.php'
        ]);

        $onlinepaylists = $onlinepaylists.$onlinepaylist;
    }

    $onlinepaylists = $onlinepaylists.'<li class="view-all"><a href="depositing_siteapi_audit.php">'.$tr['see all'].'(<span>'.$sysop_onlinepay_count.'</span>)</a></li>';

    // 顯示公司入款未審核
    $show_deposit_sql=<<<SQL
    SELECT * FROM
    (SELECT a.*, age(now(),a.changetime) as intervaltime,
    to_char((transfertime AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz ,
    to_char((a.changetime AT TIME ZONE 'posix/Etc/GMT+4'), 'YYYY-MM-DD HH24:MI:SS' ) as changetime_tz,
    b.id as account_id,
    b.parent_id as p_id
    FROM root_deposit_review as a

    left join root_member as b
    on a.account=b.account
    )
    as tt
    WHERE intervaltime < interval '90 days'
    AND status = 2
    ORDER BY changetime_tz DESC
SQL;
    $deposit_result = runSQLall($show_deposit_sql);
    $sysop_company_count = $deposit_result['0'];
    if($sysop_company_count >= 1) {
        $depositCompanylists = '';
    }else{
        $depositCompanylists = '<li class="nodata">'.$tr['no company deposits unaudit'].'</li>';
    }
    unset($deposit_result[0]);
    $deposit_decode = json_decode(json_encode($deposit_result),true);
    $i = 0;
    foreach($deposit_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $company['id'] = $value['id'];
        $company['account'] = $value['account'];
        $company['amount'] = $value['amount'];
        $company['transfertime_tz'] = gmdate('Y-m-d H:i',strtotime($value['changetime']) + -4*3600);  
        $depositCompanylist = '<li class="data"><a href="depositing_company_audit_review.php?id='.$company['id'].'">
                    <span class="title">'.$company['account'].'</span>
                    <span class="subtitle"><span>'.substr($company['transfertime_tz'],0,10).'</span>
                    <span>'.$tr['company deposits'].'</span></span>
                </li>';

        array_push($deposit, [
            'id' => $company['id'],
            'account' => $company['account'],
            'applicationtime_tz' => $company['transfertime_tz'],
            'type' => $tr['company deposits'],
            'link' => 'depositing_company_audit_review.php?id='
        ]);

        $depositCompanylists = $depositCompanylists.$depositCompanylist;
    }

    $depositCompanylists = $depositCompanylists.'<li class="view-all"><a href="depositing_company_audit.php?unaudit">'.$tr['see all'].'(<span>'.$sysop_company_count.'</span>)</a></li>';

    // 全部入款
    $deposit_count = '';
    $sysop_deposit_count = (int)$sysop_company_count+(int)$sysop_onlinepay_count;
    if ($sysop_deposit_count>0){
        $depositLists = '';
        $sysop_text = $tr['you have'].$sysop_deposit_count.$tr['deposit unaudit'];
        $deposit_count = $deposit_count.'<span class="badge">'.$sysop_deposit_count.'</span>';
    } else {
        $depositLists = '<li class="nodata">'.$tr['no deposit unaudit'].'</li>';
        $sysop_text = '';
        $deposit_count = $deposit_count.'<span class="badge nonebadge">'.$sysop_deposit_count.'</span>';
    }

    $ctime_str = array();
    foreach($deposit as $key=>$v){
        $deposit[$key]['ctime_str'] = strtotime($v['applicationtime_tz']);
        $ctime_str[] = $deposit[$key]['ctime_str'];
    }
    array_multisort($ctime_str,SORT_DESC,$deposit);

    foreach($ctime_str as $key => $value){

        $withdrawalList = '<li class="data"><a href="'.$deposit[$key]['link'].$deposit[$key]['id'].'">
                                <span class="title">'.$deposit[$key]['account'].'</span>
                                <span class="subtitle"><span>'.substr($deposit[$key]['applicationtime_tz'],0,10).'</span>
                                <span>'.$deposit[$key]['type'].'</span></span></a>
                            </li>';
        $depositLists = $depositLists.$withdrawalList;
    }

    $agent_menudeposit_member_html = '';
        $agent_menudeposit_member_html = $agent_menudeposit_member_html.'
        <li class="dropdown">
            <a href="#" class="dropdown-toggle sysop_wallet announce" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" title="'.$sysop_text.'">
                '.$deposit_count.'
            <span class="caret"></span>
            </a>
            <ul class="dropdown-menu deposit messege" id="announce">
                <li class="dropdown-item disabled">'.$tr['depositing auditing'].'</li>
                <ul class="dropdown-item nav nav-tabs">
                    <li class="nav-item dropdown-submenu">
                        <a class="nav-link dropdown-item dropdown-toggle" href="#">全部</a>
                        <div class="dropdown-menu">
                            <a class="nav-link dropdown-item active" data-stopPropagation="true" id="pills-depositing-tab" data-toggle="pill" href="#pills-depositing" role="tab" aria-controls="pills-depositing" aria-selected="true">'.$tr['all'].'</a>
                            <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-depositing_company-tab" data-toggle="pill" href="#pills-depositing_company" role="tab" aria-controls="pills-depositing_company" aria-selected="true">'.$tr['company deposits'].'</a>
                            <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-depositing_onlinepay-tab" data-toggle="pill" href="#pills-depositing_onlinepay" role="tab" aria-controls="pills-depositing_onlinepay" aria-selected="true">'.$tr['onlinepay'].'</a>
                        </div>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pills-depositing" role="tabpanel" aria-labelledby="pills-depositing-tab">
                        '.$depositLists.'
                    </div>
                    <div class="tab-pane fade" id="pills-depositing_company" role="tabpanel" aria-labelledby="pills-depositing_company-tab">
                        '.$depositCompanylists.'
                    </div>
                    <div class="tab-pane fade" id="pills-depositing_onlinepay" role="tabpanel" aria-labelledby="pills-depositing_onlinepay-tab">
                        '.$onlinepaylists.'
                    </div>
                </div>
            </ul>
        </li>
        ';




    // -----------------------------------------------------

    // 遊戲幣取款未審核
    $show_withdrawalgtoken_sql=<<<SQL
    SELECT *,to_char((applicationtime AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) AS applicationtime_tz
    FROM root_withdraw_review AS withdraw_review
    WHERE withdraw_review.status = '2'
    ORDER BY id DESC --LIMIT 500
SQL;
    $withdrawalgtoken_result = runSQLall($show_withdrawalgtoken_sql);
    $sysop_withdrawalgtoken_count = $withdrawalgtoken_result['0'];

    if($sysop_withdrawalgtoken_count >= 1) {
        $withdrawalgtokenLists = '';
    }else{
        $withdrawalgtokenLists = '<li class="nodata">'.$tr['no gtoken unaudit'].'</li>';
    }
    unset($withdrawalgtoken_result[0]);
    $withdrawalgtoken_decode = json_decode(json_encode($withdrawalgtoken_result),true);
    $i = 0;
    foreach($withdrawalgtoken_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $withdrawalgtoken['account'] = $value['account'];
        $withdrawalgtoken['amount'] = $value['amount'];
        $withdrawalgtoken['administrative_amount'] = $value['administrative_amount'];
        $withdrawalgtoken['fee_amount'] = $value['fee_amount'];
        $withdrawalgtoken['applicationtime_tz'] = $value['applicationtime_tz'];
        $withdrawalgtoken['id'] = $value['id'];

        array_push($withdrawal, [
            'id' => $withdrawalgtoken['id'],
            'account' => $withdrawalgtoken['account'],
            'applicationtime_tz' => $withdrawalgtoken['applicationtime_tz'],
            'type' => $tr['Gtoken'],
            'link' => 'withdrawalgtoken_company_audit_review.php?id='
        ]);

        $withdrawalgtokenList = '<li class="data"><a href="withdrawalgtoken_company_audit_review.php?id='.$withdrawalgtoken['id'].'">
                                <span class="title">'.$withdrawalgtoken['account'].'</span>
                                <span class="subtitle"><span>'.substr($withdrawalgtoken['applicationtime_tz'],0,10).'</span>
                                <span>'.$tr['Gtoken'].'</span></span></a>
                            </li>';

        $withdrawalgtokenLists = $withdrawalgtokenLists.$withdrawalgtokenList;
    }
    $withdrawalgtokenLists = $withdrawalgtokenLists.'<li class="view-all"><a href="withdrawalgtoken_company_audit.php?unaudit">'.$tr['see all'].'(<span>'.$sysop_withdrawalgtoken_count.'</span>)</a></li>';
    // -----------------------------------------------------

    // 現金取款未審核
    $withdrawalgcash_sql=<<<SQL
    SELECT *,to_char((applicationtime AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) AS applicationtime_tz
    FROM root_withdrawgcash_review AS withdrawgcash
    WHERE withdrawgcash.status = '2'
    ORDER BY id DESC --LIMIT 500

SQL;
    $withdrawalgcash_result = runSQLall($withdrawalgcash_sql);
    $sysop_withdrawalgcash_count = $withdrawalgcash_result['0'];

    if($sysop_withdrawalgcash_count >= 1) {
        $withdrawalgcashLists = '';
    }else{
        $withdrawalgcashLists = '<li class="nodata">'.$tr['no gcash unaudit'].'</li>';
    }
    unset($withdrawalgcash_result[0]);
    $withdrawalgcash_decode = json_decode(json_encode($withdrawalgcash_result),true);
    $i = 0;
    foreach($withdrawalgcash_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $withdrawalgcash['account'] = $value['account'];
        $withdrawalgcash['amount'] = $value['amount'];
        $withdrawalgcash['fee_amount'] = $value['fee_amount'];
        $withdrawalgcash['applicationtime_tz'] = $value['applicationtime_tz'];
        $withdrawalgcash['id'] = $value['id'];

        array_push($withdrawal, [
            'id' => $withdrawalgcash['id'],
            'account' => $withdrawalgcash['account'],
            'applicationtime_tz' => $withdrawalgcash['applicationtime_tz'],
            'type' => $tr['Franchise'],
            'link' => 'withdrawalgcash_company_audit_review.php?id='
        ]);

        $withdrawalgcashList = '<li class="data"><a href="withdrawalgcash_company_audit_review.php?id='.$withdrawalgcash['id'].'">
                                <span class="title">'.$withdrawalgcash['account'].'</span>
                                <span class="subtitle"><span>'.substr($withdrawalgcash['applicationtime_tz'],0,10).'</span>
                                <span>'.$tr['Franchise'].'</span></span></a>
                            </li>';
        $withdrawalgcashLists = $withdrawalgcashLists.$withdrawalgcashList;
    }
    $withdrawalgcashLists = $withdrawalgcashLists.'<li class="view-all"><a href="withdrawalgcash_company_audit.php?unaudit">'.$tr['see all'].'(<span>'.$sysop_withdrawalgcash_count.'</span>)</a></li>';
    // 全部取款
    $withdrawal_count = '';
    $sysop_withdrawal_count = (int)$sysop_withdrawalgcash_count+(int)$sysop_withdrawalgtoken_count;
    if ($sysop_withdrawal_count>0){
        $withdrawalLists = '';
        $sysop_text = $tr['you have'].$sysop_withdrawal_count.$tr['withdrawal unaudit'];
        $withdrawal_count = $withdrawal_count.'<span class="badge">'.$sysop_withdrawal_count.'</span>';
    } else {
        $withdrawalLists = '<li class="nodata">'.$tr['no withdrawal unaudit'].'</li>';
        $sysop_text = '';
        $withdrawal_count = $withdrawal_count.'<span class="badge nonebadge">'.$sysop_withdrawal_count.'</span>';
    }

    $ctime_str = array();
    foreach($withdrawal as $key=>$v){
        $withdrawal[$key]['ctime_str'] = strtotime($v['applicationtime_tz']);
        $ctime_str[] = $withdrawal[$key]['ctime_str'];
    }
    array_multisort($ctime_str,SORT_DESC,$withdrawal);

    foreach($ctime_str as $key => $value){

        $withdrawalList = '<li class="data"><a href="'.$withdrawal[$key]['link'].$withdrawal[$key]['id'].'">
                                <span class="title">'.$withdrawal[$key]['account'].'</span>
                                <span class="subtitle"><span>'.substr($withdrawal[$key]['applicationtime_tz'],0,10).'</span>
                                <span>'.$withdrawal[$key]['type'].'</span></span></a>
                            </li>';
        $withdrawalLists = $withdrawalLists.$withdrawalList;
    }

    $agent_menuwithdrawal_member_html = '';
    $agent_menuwithdrawal_member_html = $agent_menuwithdrawal_member_html.'
    <li class="dropdown">
        <a href="#" class="dropdown-toggle sysop_coins announce" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" title="'.$sysop_text.'">
            '.$withdrawal_count.'
        <span class="caret"></span>
        </a>
        <ul class="dropdown-menu messege withdrawal" id="announce">
            <li class="dropdown-item disabled">'.$tr['withdrawal auditing'].'</li>
            <ul class="dropdown-item nav nav-tabs">
                <li class="nav-item dropdown-submenu">
                    <a class="nav-link dropdown-item dropdown-toggle" href="#">'.$tr['all'].'</a>
                    <div class="dropdown-menu">
                        <a class="nav-link dropdown-item active" data-stopPropagation="true" id="pills-withdrawalall-tab" data-toggle="pill" href="#pills-withdrawalall" role="tab" aria-controls="pills-withdrawalall" aria-selected="true">'.$tr['all'].'</a>
                        <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-withdrawalgcash-tab" data-toggle="pill" href="#pills-withdrawalgcash" role="tab" aria-controls="pills-withdrawalgcash" aria-selected="true">'.$tr['Franchise'].'</a>
                        <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-withdrawalgtoken-tab" data-toggle="pill" href="#pills-withdrawalgtoken" role="tab" aria-controls="pills-withdrawalgtoken" aria-selected="false">'.$tr['Gtoken'].'</a>
                    </div>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="pills-withdrawalall" role="tabpanel" aria-labelledby="pills-withdrawalall-tab">
                    '.$withdrawalLists.'
                </div>
                <div class="tab-pane fade" id="pills-withdrawalgcash" role="tabpanel" aria-labelledby="pills-withdrawalgcash-tab">
                    '.$withdrawalgcashLists.'
                </div>
                <div class="tab-pane fade" id="pills-withdrawalgtoken" role="tabpanel" aria-labelledby="pills-withdrawalgtoken-tab">
                    '.$withdrawalgtokenLists.'
                </div>
            </div>
        </ul>
    </li>
    ';
    // --------------------------------------------
    // 代理商審查未審核
    $agent_sql=<<<SQL
    SELECT * FROM root_agent_review
    WHERE applicationtime >= (current_timestamp - interval '90 days')
    AND status = 2
    ORDER BY applicationtime DESC
SQL;
    $agent_result = runSQLall($agent_sql);
    $sysop_agent_count = $agent_result['0'];

    if($sysop_agent_count >=1){
        $agentLists = '';
    }else{
        $agentLists = '<li class="nodata">'.$tr['no agent unaudit'].'</li>';
    }
    unset($agent_result[0]);
    $agent_decode = json_decode(json_encode($agent_result),true);
    $i = 0;
    foreach($agent_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $agent['account'] = $value['account'];
        $agent['applicationtime'] = $value['applicationtime'];
        $agent['id'] = $value['id'];

        $agentList = '<li class="data"><a href="agent_review_info.php?id='.$agent['id'].'">
                                <span class="title">'.$agent['account'].'</span>
                                <span class="subtitle"><span>'.substr($agent['applicationtime'],0,10).'</span>
                                <span>'.$tr['agentReview'].'</span></span>
                            </li>';

        array_push($member, [
            'id' => $agent['id'],
            'account' => $agent['account'],
            'applicationtime_tz' => $agent['applicationtime'],
            'type' => $tr['agentReview'],
            'link' => 'agent_review_info.php?id='
        ]);

        $agentLists = $agentLists.$agentList;
    }
    $agentLists = $agentLists.'<li class="view-all"><a href="agent_review.php?t=90">'.$tr['see all'].'(<span>'.$sysop_agent_count.'</span>)</a></li>';
    // -----------------------------------------------------
    // 會員註冊未審核
    $registerreview_sql=<<<SQL
    SELECT * FROM root_member_register_review
    WHERE applicationtime >= (current_timestamp - interval '90 days')
    AND status = '4'
    ORDER BY applicationtime DESC
SQL;
    $registerreview_result = runSQLall($registerreview_sql);
    $sysop_registerreview_count = $registerreview_result['0'];
    if($sysop_registerreview_count >= 1) {
        $registerLists = '';
    }else{
        $registerLists = '<li class="nodata">'.$tr['no register unaudit'].'</li>';
    }
    unset($registerreview_result[0]);
    $registerreview_decode = json_decode(json_encode($registerreview_result),true);
    $i = 0;
    foreach($registerreview_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $registerreview['account'] = $value['account'];
        $registerreview['applicationtime'] = $value['applicationtime'];
        $registerreview['id'] = '';

        array_push($member, [
            'id' => $registerreview['id'],
            'account' => $registerreview['account'],
            'applicationtime_tz' => $registerreview['applicationtime'],
            'type' => $tr['forestageRegister'],
            'link' => 'member_register_review.php?t=90'
        ]);

        $registerList = '<li class="data"><a href="member_register_review.php?t=90">
                        <span class="title">'.$registerreview['account'].'</span>
                        <span class="subtitle"><span>'.substr($registerreview['applicationtime'],0,10).'</span>
                        <span>'.$tr['forestageRegister'].'</span></span>
                    </li>';
        $registerLists = $registerLists.$registerList;
    }
    $registerLists = $registerLists.'<li class="view-all"><a href="member_register_review.php?t=90">'.$tr['see all'].'(<span>'.$sysop_registerreview_count.'</span>)</a></li>';

    // 全部會員
    $member_count = '';
    $sysop_member_count = (int)$sysop_agent_count+(int)$sysop_registerreview_count;
    if ($sysop_member_count>0){
        $memberLists = '';
        $member_count = $member_count.'<span class="badge">'.$sysop_member_count.'</span>';
        $sysop_text = $tr['you have'].$sysop_member_count.$tr['member auditing'];
    } else {
        $member_count = $member_count.'<span class="badge nonebadge">'.$sysop_member_count.'</span>';
        $sysop_text = '';
        $memberLists = '<li class="nodata">'.$tr['no data unaudit'].'</li>';
    }

    $ctime_str = array();
    foreach($member as $key=>$v){
        $member[$key]['ctime_str'] = strtotime($v['applicationtime_tz']);
        $ctime_str[] = $member[$key]['ctime_str'];
    }
    array_multisort($ctime_str,SORT_DESC,$member);

    foreach($ctime_str as $key => $value){
        $memberList = '<li class="data"><a href="'.$member[$key]['link'].$member[$key]['id'].'">
                                <span class="title">'.$member[$key]['account'].'</span>
                                <span class="subtitle"><span>'.substr($member[$key]['applicationtime_tz'],0,10).'</span>
                                <span>'.$member[$key]['type'].'</span></span></a>
                            </li>';
        $memberLists = $memberLists.$memberList;
    }

    $agent_menuagent_member_html = '';
    $agent_menuagent_member_html = $agent_menuagent_member_html.'
    <li class="dropdown">
        <a href="#" class="dropdown-toggle sysop_user announce" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" title="'.$sysop_text.'">
            '.$member_count.'
        <span class="caret"></span>
        </a>
        <ul class="dropdown-menu messege member" id="announce">
            <li class="dropdown-item disabled">'.$tr['member auditing'].'</li>
            <ul class="dropdown-item nav nav-tabs">
                <li class="nav-item dropdown-submenu">
                    <a class="nav-link dropdown-item dropdown-toggle" href="#">全部</a>
                    <div class="dropdown-menu">
                        <a class="nav-link dropdown-item active" data-stopPropagation="true" id="pills-member-tab" data-toggle="pill" href="#pills-member" role="tab" aria-controls="pills-member" aria-selected="true">'.$tr['all'].'</a>
                        <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-register-tab" data-toggle="pill" href="#pills-register" role="tab" aria-controls="pills-register" aria-selected="true">'.$tr['forestageRegister'].'</a>
                        <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-agent-tab" data-toggle="pill" href="#pills-agent" role="tab" aria-controls="pills-agent" aria-selected="true">'.$tr['agentReview'].'</a>
                    </div>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="pills-member" role="tabpanel" aria-labelledby="pills-member-tab">
                    '.$memberLists.'
                </div>
                <div class="tab-pane fade" id="pills-register" role="tabpanel" aria-labelledby="pills-register-tab">
                    '.$registerLists.'
                </div>
                <div class="tab-pane fade" id="pills-agent" role="tabpanel" aria-labelledby="pills-agent-tab">
                    '.$agentLists.'
                </div>
            </div>
        </ul>
    </li>
    ';


    // var_dump($agent);die();
    // -----------------------------------------
    // 後台站長廣播訊息紀錄生成
    $ann_sql = <<<SQL
    (SELECT id FROM site_announcement WHERE showinmessage = '1' AND status = '1' AND effecttime <= current_timestamp AND endtime >= current_timestamp)
    EXCEPT
    (SELECT ann_id::BIGINT as id FROM site_announcement_status WHERE account = '{$_SESSION['agent']->account}' AND watchingstatus = '1');
SQL;
  //var_dump($ann_sql);
    $ann_sql_result = runSQLall($ann_sql);
    // var_dump($sysop_bullhorn);
    $sysop_bullhorn_count = $ann_sql_result['0'];
    if($sysop_bullhorn_count >= 1) {
        $lists = '';
    }else{
        $lists = '<li class="nodata">'.$tr['no announce unread'].'</li>';
    }
    // 顯示公告預覽
    $show_announcement_preview_sql=<<<SQL
    SELECT * FROM site_announcement AS announcement
         WHERE id not IN
         (
             SELECT ann_id FROM site_announcement_status AS status
             WHERE status.account = '{$_SESSION['agent']->account}'
             AND status.watchingstatus = '1'
         )
        AND effecttime <= current_timestamp
        AND endtime >= current_timestamp
        And status = '1'
        ORDER BY id desc
SQL;
    $show_announcement_preview_result = runSQLall($show_announcement_preview_sql);
    unset($show_announcement_preview_result[0]);
    // var_dump($show_announcement_preview_result);die();
    $show_announcement_preview_decode = json_decode(json_encode($show_announcement_preview_result),true);

    $i = 0;
    foreach($show_announcement_preview_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }
        $announement['title'] = $value['title'];
        $announement['name'] = $value['name'];
        $announement['content'] = $value['content'];
        $announement['effecttime']= $value['effecttime'];

        $announement['operator']= $value['operator'];

        array_push($message, [
            'title' => $announement['title'],
            'time' => $announement['effecttime'],
            'type' => $tr['announce'],
            'link' => 'systemconfig_announce_read.php'
        ]);


        $list = '<li class="data"><a href="systemconfig_announce_read.php">
                    <span class="title">'.$announement['title'].'</span>
                    <span class="subtitle"><span>'.substr($announement['effecttime'],0,10).'</span>
                    <span>'.$tr['announce'].'</span></span></a>
                </li>';
        $lists = $lists.$list;
    }

    $lists = $lists.'<li class="view-all"><a href="systemconfig_announce_read.php">'.$tr['see all'].'(<span>'.$sysop_bullhorn_count.'</span>)</a></li>';

    // 站內信
    $mail_sql =<<<SQL
    SELECT * FROM root_stationmail
    WHERE cs_status = '1'
    AND cs_readtime IS NULL
    AND msgto = '{$stationmail['sendto_system_cs']}'
    ORDER BY id desc
SQL;
    $mail_result = runSQLall($mail_sql);
    $sysop_mail_count = $mail_result['0'];

    if($sysop_mail_count >= 1) {
        $mailLists = '';
    }else{
        $mailLists = '<li class="nodata">'.$tr['no mail unread'].'</li>';
    }

    unset($mail_result[0]);
    $mail_decode = json_decode(json_encode($mail_result),true);

    $i = 0;
    foreach($mail_decode as $key => $value){
        $i++;
        if ($i>5) {
            break;
        }

        $mail['msgfrom'] = $value['msgfrom'];
        $mail['msgto'] = $value['msgto'];
        $mail['subject'] = $value['subject'];
        $mail['message'] = $value['message'];
        $mail['sendtime'] = gmdate('Y-m-d H:i',strtotime($value['sendtime']) + -4*3600);

        array_push($message, [
            'title' => $mail['subject'],
            'time' => $mail['sendtime'],
            'type' => $tr['mail'],
            'link' => 'mail.php'
        ]);

        $mailList = '<li class="data"><a href="mail.php">
                                <span class="title">'.$mail['subject'].'</span>
                                <span class="subtitle"><span>'.substr($mail['sendtime'],0,10).'</span>
                                <span>'.$tr['mail'].'</span></span></a>
                            </li>';
        $mailLists = $mailLists.$mailList;
    }

    $mailLists = $mailLists.'<li class="view-all"><a href="mail.php">'.$tr['see all'].'(<span>'.$tr['unread'].$sysop_mail_count.'</span>)</a></li>';

    // 全部訊息
    $sysop_notify_count = (int)$sysop_mail_count+(int)$sysop_bullhorn_count;
    $notify_count = '';

    if ($sysop_notify_count>0){
        $notifyLists = '';
        $sysop_mail_text = $tr['you have'].$sysop_notify_count.$tr['notify unread'];
        $notify_count = $notify_count.'<span class="badge">'.$sysop_notify_count.'</span>';
    } else {
        $sysop_mail_text = '';
        $notify_count = $notify_count.'<span class="badge nonebadge">'.$sysop_notify_count.'</span>';
        $notifyLists = '<li class="nodata">'.$tr['no notify unread'].'</li>';
    }

    $ctime_str = array();
    foreach($message as $key=>$v){
        $message[$key]['ctime_str'] = strtotime($v['time']);
        $ctime_str[] = $message[$key]['ctime_str'];
    }
    array_multisort($ctime_str,SORT_DESC,$message);

    foreach($ctime_str as $key => $value){

        $notifyList = '<li class="data"><a href="'.$message[$key]['link'].'">
                                <span class="title">'.$message[$key]['title'].'</span>
                                <span class="subtitle"><span>'.substr($message[$key]['time'],0,10).'</span>
                                <span>'.$message[$key]['type'].'</span></span></a>
                            </li>';
        $notifyLists = $notifyLists.$notifyList;
    }

    $agent_notify_member_html = '';
    $agent_notify_member_html = $agent_notify_member_html.'
    <li class="dropdown">
        <a href="#" class="dropdown-toggle sysop_bullhorn announce" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" title="'.$sysop_mail_text.'">
            '.$notify_count.'
        <span class="caret"></span>
        </a>
        <ul class="dropdown-menu announce messege" id="announce">
            <li class="dropdown-item disabled">'.$tr['notify'].'</li>
            <ul class="dropdown-item nav nav-tabs">
                <li class="nav-item dropdown-submenu">
                    <a class="nav-link dropdown-item dropdown-toggle" href="#">'.$tr['all'].'</a>
                    <div class="dropdown-menu">
                        <a class="nav-link dropdown-item active" data-stopPropagation="true" id="pills-notify-tab" data-toggle="pill" href="#pills-notify" role="tab" aria-controls="pills-notify" aria-selected="true">'.$tr['all'].'</a>
                        <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-announce-tab" data-toggle="pill" href="#pills-announce" role="tab" aria-controls="pills-announce" aria-selected="true">'.$tr['announce'].'</a>
                        <a class="nav-link dropdown-item" data-stopPropagation="true" id="pills-mail-tab" data-toggle="pill" href="#pills-mail" role="tab" aria-controls="pills-mail" aria-selected="false">'.$tr['mail'].'</a>
                    </div>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="pills-notify" role="tabpanel" aria-labelledby="pills-notify-tab">
                    '.$notifyLists.'
                </div>
                <div class="tab-pane fade" id="pills-announce" role="tabpanel" aria-labelledby="pills-announce-tab">
                    '.$lists.'
                </div>
                <div class="tab-pane fade" id="pills-mail" role="tabpanel" aria-labelledby="pills-mail-tab">
                    '.$mailLists.'
                </div>
            </div>
        </ul>
    </li>
    ';

    //-----------------------------------------------

    return($agent_menuagent_member_html.$agent_notify_member_html.$agent_menudeposit_member_html.$agent_menuwithdrawal_member_html);
}


// --------------------------------------
// (3 - 2.)
// 切換代理商、會員、及系統管理員身份
// 代理商會員選單 -- 使用者
// --------------------------------------
function agent_menutop_member() {
    global $tr;
    global $su;

    // 後台站長廣播訊息紀錄生成
    $ann_sql = <<<SQL
    (SELECT id FROM site_announcement WHERE showinmessage = '1' AND status = '1' AND effecttime <= current_timestamp AND endtime >= current_timestamp)
    EXCEPT
    (SELECT ann_id::BIGINT as id FROM site_announcement_status WHERE account = '{$_SESSION['agent']->account}' AND watchingstatus = '1');
SQL;

    // 判斷登入者身分並顯示
    $accountstr='';
    if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['master'])){
        $accountstr=$tr['Webmaster'].'，'.$tr['Hello'].'';
    }else if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['ops'])){
        $accountstr=$tr['Maintenance'].'，'.$tr['Hello'].'';
    }else if($_SESSION['agent']->therole == 'R'){
        $accountstr=$tr['Customer service'].'，'.$tr['Hello'].'';
    }else{
        $accountstr=$tr['Hello'];
    }

    // 登入的使用者帳號, 用 class 控制顏色
    $login_user_show = '<span class="login_user_show">'.$_SESSION['agent']->account.'</span>';

    // 登出，修改密碼的選單
    $agent_menutop_member_html = '';
    $agent_menutop_member_html = $agent_menutop_member_html.'
        <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
        '.$login_user_show.'
        <span class="caret"></span>
        </a>
          <ul class="dropdown-menu">
              <li class="dropdown-item disabled">'.$accountstr.'</li>
              <li role="separator" class="divider"></li>
            <li><a href="admin_edit.php?i='.$_SESSION['agent']->id.'"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span>'.$tr['Change Password'].'</a></li>
            <li><a href="member_security_setting.php?i='.$_SESSION['agent']->id.'"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span>'.$tr['security setting'].'</a></li>
            <li role="separator" class="divider"></li>
            <li><a href="agent_login_action.php?a=logout"><span class="glyphicon glyphicon-log-out" aria-hidden="true"></span>'.$tr['Logout'].'</a></li>
          </ul>
        </li>
    ';
    return($agent_menutop_member_html);
}
// --------------------------------------
// (4)
// 代理商登入後的畫面 time 及登入人數顯示
// user: agent_menu_time()
// --------------------------------------
function agent_menu_time() {
    global $tr;

    // 即時計算線上 login 人數, agent in redisserver db1 , member in db2
    // ------------------------------------------------------------------------------
    // 從 redis server DB1 計算 session 紀錄數量
    $online_users_count = Agent_RegSession2Count(1);
    $online_users = '<a href="agent_online.php" title="'.$tr['Online admin at back stage'].'" class="btn btn-default btn-xs onlineusers">
    <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
    &nbsp;<span class="badge">'.$online_users_count.'</span>&nbsp;
    </a>
    ';
    // echo $online_users;

    $agent_menu_time_html = '';
    // ------------------------------------------------------------------------------
    // javascript 即時顯示美東時間
    // 需要搭配 http://momentjs.com/timezone/ 否則時區問題不好處理 https://github.com/moment/moment-timezone/
    // ------------------------------------------------------------------------------
    $timezone_area_text['zh-hk']     = '美東時間';
    $timezone_area_text['zh-cn']     = '美东时间';
    $timezone_area_text['en']         = 'Eastern Time';
    //$timezone_area_text = $tr['EDT(GMT -5)'];

    if(isset($_SESSION['lang']) AND $_SESSION['lang'] == 'zh-tw') {
        $locale_code = 'zh-cn';
    }elseif(isset($_SESSION['lang']) AND $_SESSION['lang'] == 'zh-cn') {
        $locale_code = 'zh-cn';
    }else{
        $locale_code = 'en';
    }
    //moment.locale('en');
    //moment.locale('zh-hk');
    //moment.locale('zh-cn');

    // https://momentjs.com/docs/#/displaying/
    // 統一設定為 美東時間(夏令時間, 相對於中原時間-12小時), 沒有日光節約的一小時。所以使用 GMT+4
    // 因為他媽的最大的那個公司應該沒有想到日光節約所以 -12 變成了業界不成文標準。
    $showtime_js = "
    <script>
        $( document ).ready(function() {
            ShowTime();
        });

        function ShowTime() {
            moment.locale('$locale_code');
            var d = new Date();
            var d_withtimezone = moment().tz('Etc/GMT+4').format('YYYY/MM/DD(dd) HH:mm:ss')
            document.getElementById('showtimebox').innerHTML = d_withtimezone;
            setTimeout('ShowTime()', 1000);
        }
    </script>
    ";

    // 預設以美東時間當顯示，因為很多遊戲都是以美東時間計算，如不顯示客戶容易誤解。
    // 美東時間 == America/St_Thomas
    $timezone_area = 'America/St_Thomas';
    // PHP 的時區計算
    date_default_timezone_set($timezone_area);
    $date_hour = date("Y/m/d h:m:s a");
    // var_dump($date_hour);

    $agent_menu_time_html ='
    <div title="'.$timezone_area_text[$locale_code].'" class="shhowtimebox_est">
    <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
    <span id="showtimebox" class="showtimebox">YYYY/MM/DD HH:mm:ss</span>
    </div>';

    // 加上 JS
    $agent_menu_time_html = $online_users.$agent_menu_time_html.$showtime_js;

return($agent_menu_time_html);
}

// --------------------------------------
// (4 - 1.)
// 代理商登入後的畫面 time
// user: agent_menutop_time()
// --------------------------------------
function agent_menutop_time() {
    global $tr;
    global $config;
    $agent_menutop_time_html = '';

    // monent.js使用語系以config.php內的設定值為主，而moment.js使用語系參數都是英文小寫，詳細支援清單請參照moment.js官方文件(下方網址)
    // https://www.ge.com/digital/documentation/predix-services/c_custom_locale_support.html
    $moment_locate = strtolower($config['default_locate']); // config.php

    // moment.js使用時區以config.php內的設定值為主
    $moment_timezone = $config['default_timezone'];

    $moment_js = <<<HTML
        <script>
            $(function(){
                ShowTime();
            });
            function ShowTime(){
                moment.locale('{$moment_locate}');
                var d = new Date();
                var d_withtimezone = moment().tz('{$moment_timezone}').format('YYYY/MM/DD(dd) HH:mm:ss');
                $('#showtimebox').html(d_withtimezone);
                setTimeout('ShowTime()',1000);
            }
        </script>
    HTML;

    $moment_html = <<<HTML
        <div title="{$moment_timezone}" class="shhowtimebox_est">
            <span id="showtimebox" class="showtimebox">YYYY/MM/DD HH:mm:ss</span>
        </div>
    HTML;

    $agent_menutop_time_html = $moment_js.$moment_html;

    return($agent_menutop_time_html);
}

// --------------------------------------
// (4 - 2.)
// 代理商登入後的畫面入人數顯示
// user: agent_menu_time()
// --------------------------------------
function agent_menutop_people() {
    global $tr;
    // 即時計算線上 login 人數, agent in redisserver db1 , member in db2
    // ------------------------------------------------------------------------------
    // 從 redis server DB1 計算 session 紀錄數量
    $online_users_count = Agent_RegSession2Count(1);
    $online_users = '<li class="dropdown number_visitors"><a href="agent_online.php" title="'.$tr['Online admin at back stage'].'" class="btn btn-default btn-xs onlineusers">
    <span class="glyphicon glyphicon-user" aria-hidden="true"></span>
    <span class="badge">'.$online_users_count.'</span>
    </a></li>
    ';

return($online_users);
}

// --------------------------------------
// (5)
// 建立一個 redis client 連線，設定一個 key and value
// 參考文件:https://github.com/phpredis/phpredis#connect-open
// use: Agent_runRedisSET($sid, $value, 1)
// 成功傳回 1 失敗傳回 0
// --------------------------------------
function Agent_runRedisSET($key, $value, $db, $expire=14400) {

    global $redisdb;
    // 預設 DB 定義在全域變數
    if(isset($redisdb['db'])) {
        $db = $redisdb['db'];
    }else{
        die('No select RedisDB');
    }

    $redis = new Redis();
    // 2 秒 timeout
    if($redis->pconnect($redisdb['host'], 6379, 1)) {
        // success
        if($redis->auth($redisdb['auth'])) {
            // echo 'Authentication Success';
        }else{
            return(0);
            die('Authentication failed');
        }
    }else{
        // error
        return(0);
        die('Connection Failed');
    }
    // 選擇 DB
    $redis->select($db);
    // echo "Server is running: ".$redis->ping();

    // 設定 timeout 值, 目前的時間往後加上去
    $server_time     = time();
    $expire            = 3600;    // 1 hr
    $expire_time    = $server_time + $expire;

    $r[1] = $redis->set($key, $value);
    $r[2] = $redis->expireAt($key, $expire_time);

    // var_dump($r);

    return(1);
	global $redisdb;
	// 預設 DB 定義在全域變數
	if(isset($redisdb['db'])) {
		$db = $redisdb['db'];
	}else{
		die('No select RedisDB');
	}

	$redis = new Redis();
	// 2 秒 timeout
	if($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		}else{
			echo "Authentication faild";
			return(0);
			// die('Authentication failed');
		}
	}else{
		// error
		echo "Connection Failed";
		return(0);
		// die('Connection Failed');
	}
	// 選擇 DB
	$redis->select($db);
	// echo "Server is running: ".$redis->ping();

	// 設定 timeout 值, 目前的時間往後加上去
	$server_time 	= time();
	$expire			= 3600;	// 1 hr
	$expire_time	= $server_time + $expire;

	$r[1] = $redis->set($key, $value);
	$r[2] = $redis->expireAt($key, $expire_time);

	// var_dump($r);

	return(1);
}


// --------------------------------------
// (6)
// 建立一個 redis client in db $db 連線，刪除一個 key
// 參考文件:https://github.com/phpredis/phpredis#connect-open
// use: Agent_runRedisDEL($key, $db=1)
// 成功傳回 1 失敗傳回 0
// --------------------------------------
function Agent_runRedisDEL($key, $db) {

	global $redisdb;
	if(!isset($db)) {
		die('No select RedisDB');
	}

	$redis = new Redis();
	// 2 秒 timeout
	if($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		}else{
			echo "Authentication failed";
			return(0);
			// die('Authentication failed');
		}
	}else{
		// error
		echo "Connection Failed";
		return(0);
		// die('Connection Failed');
	}
	// 選擇 DB 1
	$redis->select($db);
	// echo "Server is running: ".$redis->ping();

	$r = $redis->delete($key);

	return($r);
}


// ----------------------------------------------------------------------------
// 7
// 如果使用者有登入, session_start 則 redis set 就要寫入資料並延長一次時間.
// 取得儲存於 session 中的 name and id , 當使用者登入成功時. 紀錄這些 id. 於 redis server 上面. 統計有多少同 id 使用者在線上.
// user: Agent_RegSession2RedisDB()
// ----------------------------------------------------------------------------
function Agent_RegSession2RedisDB() {

	global $config;
	global $redisdb;
	// 預設 DB 定義在全域變數
	if(isset($redisdb['db'])) {
		$db = $redisdb['db'];
	}else{
		die('No select RedisDB');
	}

	// 判斷使用者的 session 是否存在
	if(isset($_SESSION['agent'])) {
		// 後台 + 專案站台 + 帳號
		$value = $config['projectid'].'_back_'.$_SESSION['agent']->account;
		// var_dump($value);die();
		// $value = $_SESSION['agent']->account;
		// 目前程式所在的 session , 需要加上 phpredis_session
		$session_id = session_id();
		// db 1 自己寫出來的 session, save member session data
		$sid = sha1($value).':'.$session_id;
		// db 0 系統的 php session
		$phpredis_sid = 'PHPREDIS_SESSION:'.$session_id;

		// var_dump($session_id);die();
		// var_dump($sid);
		// var_dump($phpredis_sid);

		// browser reload 則 redis session 又會跑回來，處理方式要 remove cookie
		// 當登入後，發現有兩個 redisdb in db1 session data ，就刪除自己這個，並且移除這個 login cookie
		$checkuser_result = Agent_CheckLoginUser($value);
		// var_dump($checkuser_result);die();

		// 只有1個 or 0 個(剛登入) session 所以紀錄這次的狀態，並更新。

		// 從哪裡點擊來的
		if (!empty($_SERVER['HTTP_REFERER'])) {
		    $http_referer = $_SERVER['HTTP_REFERER'];
		}else{
				$http_referer = "NO HTTP_REFERER";
		}
		// 如果有 fingerprint 的話,紀錄
		if(isset($_SESSION['fingertracker'])){
			$fingertracker = $_SESSION['fingertracker'];
		}else{
			$fingertracker = 'NOFINGER';
		}
		// var_dump($fingertracker);
		// $value = $value.','.time().','.$_SERVER["SCRIPT_NAME"].','.$_SERVER["REMOTE_ADDR"].','.$fingertracker.','.$http_referer.','.$_SERVER["HTTP_USER_AGENT"];
		$value = $value.','.time().','.$_SERVER["SCRIPT_NAME"].','.explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'].','.$fingertracker.','.$http_referer.','.$_SERVER["HTTP_USER_AGENT"];

		// var_dump($value);
		// 沒有資料的時候, 註冊這個使用者資訊
		if($checkuser_result['count'] == 0){
			// 會員 or 代理商註冊在 redis db 內
			$rrset = Agent_runRedisSET($sid, $value, $db);
			// var_dump($rrset);die();
		}

		// 如果兩個不相等的 session 的話
		if($checkuser_result['count'] == 1 AND $checkuser_result['key'] != $sid){
			// $logger = '有重复的使用者'.$checkuser_result['key'].'登入，你已经被登出系统。';
			$logger = '有重复的使用者'.$_SESSION['agent']->account.'登入，你已经被登出系统。';
			// 刪除 redisdb db1 sid
			$r[0] = Agent_runRedisDEL($phpredis_sid,0);
			// 從 $db 中刪除指定的 sid
			$r[1] = Agent_runRedisDEL($sid,$db);
			// cookie 設定為 timeout
			setcookie ("PHPSESSID", "", time() - 3600);
			// var_dump($r);die();
			//var_dump($_COOKIE);
			echo '<script>alert("'.$logger.'");document.location.href="agent_login_action.php?a=logout";</script>';
		}elseif(isset($checkuser_result['count']) AND $checkuser_result['count'] >= 2) {
			$logger = '有重复的使用者'.$checkuser_result['count'].'人次登入，你已强迫登出系统。';
			// 刪除 redisdb db1 sid
			$r[0] = Agent_runRedisDEL($phpredis_sid,0);
			$r[1] = Agent_runRedisDEL($sid,$db);
			// cookie 設定為 timeout
			setcookie ("PHPSESSID", "", time() - 3600);
			//var_dump($r);
			//var_dump($_COOKIE);
			echo '<script>alert("'.$logger.'");document.location.href="agent_login_action.php?a=logout";</script>';
		}

	}else{
		return(0);
	}
	return(1);
}


// ----------------------------------------------------------------------------
// 8
// 計算 redis server session 線上使用者人數
// agent 放在 db1
// member 放在 db2
// user: Agent_RegSession2Count($db=1)
// ----------------------------------------------------------------------------
function Agent_RegSession2Count($db) {

    global $redisdb;
    // 原版
    // 預設 DB 定義在全域變數
    // if(isset($redisdb['db'])) {
    //     $db = $redisdb['db'];
    // }else{
    //     die('No select RedisDB');
    // }

    // $redis = new Redis();
    // // 2 秒 timeout
    // if($redis->pconnect($redisdb['host'], 6379, 1)) {
    //     // success
    //     if($redis->auth($redisdb['auth'])) {
    //         // echo 'Authentication Success';
    //     }else{
    //         return(0);
    //         die('Authentication failed');
    //     }
    // }else{
    //     // error
    //     return(0);
    //     die('Connection Failed');
    // }
    // // 選擇 DB 0 or 1
    // $redis->select($db);
    // // echo "Server is running: ".$redis->ping();

    // $count = $redis->dbSize();
    // return($count);

    // ----------------------------------------
    // 過濾掉guest，顯示線上人數
    global $config;
    $all_data = Agent_runRedisgetkeyvalue('*', $redisdb['db']);
    $list = [];

    if($all_data[0] >= 1){
        $i = $j = 1;
        for($i = 1; $i < $all_data[0]; $i++){
            $list[$i] = explode(',',$all_data[$i]['value']);
            $account_value = explode('_', $list[$i][0]);

            if($account_value[0] == $config['projectid']){
                $number = $j++;
            }
        }
    }
    return($number);
    // ------------------------------------------

}

// --------------------------------------
// 9
// 建立一個 redis client 連線，刪除除了當下的 session 以外，刪除所有同使用者的 key
// 已確保所有的 session 只有一個登入者
// 參考文件: https://github.com/phpredis/phpredis#connect-open
// user: Agent_runRedisKeepOneUser()
// --------------------------------------
function Agent_runRedisKeepOneUser() {

	global $redisdb;
	// 預設 DB 定義在全域變數
	if(isset($redisdb['db'])) {
		$db = $redisdb['db'];
	}else{
		die('No select RedisDB');
	}

	$redis = new Redis();
	// 2 秒 timeout
	if($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		}else{
			echo "Authentication failed";
			return(0);
			// die('Authentication failed');
		}
	}else{
		// error
		echo "Connection Failed";
		return(0);
		// die('Connection Failed');
	}
	// 選擇 DB , 使用者自訂的 session 放在 db 1
	$redis->select($db);
	// echo "Server is running: ".$redis->ping();

	// 找出已經登入的使用者 key  from db 1
	if(isset($_SESSION['agent'])) {
		// 使用者帳號 sha1 後就是 session id
		$account_sid = sha1($_SESSION['agent']->account);
		// var_dump($account_sid);die();

		// 搜尋已經登入的 users key
		$userkey = $account_sid.'*';
		$alive_userkeys = $redis->keys("$userkey");
		// var_dump($alive_userkeys);die();

		// 確認目前當下 session 的 key
		$current_sessionid = $account_sid.':'.session_id();
		//var_dump($current_sessionid);

		// 轉換為要刪除的 php session id  , 保留這個 session 本身的 session id 不刪除
		$alive_userkeys_count = count($alive_userkeys);
		// var_dump($alive_userkeys_count);die();
		$phpsession_userkeys = NULL;
		$delalivekey		= NULL;
		$kk = 0;
		for($k=0;$k<$alive_userkeys_count;$k++) {
			if($current_sessionid != $alive_userkeys[$k] ) {
				$phpsession_userkeys[$kk] = str_replace($account_sid,'PHPREDIS_SESSION',$alive_userkeys[$k]);
				// 刪除所有位於 db 1 的除了自己以外的 key,
				//echo 'alive key deleted'.$alive_userkeys[$k];
				$delalivekey[$kk] = $redis->delete($alive_userkeys[$k]);
				$kk++;
			}
		}
		// var_dump($delalivekey);die();


		// 切換到 db 0 ，刪除除了自己以外的 phpsession 的 key
		$delsessionkey = NULL;
		//var_dump($phpsession_userkeys);
		// 系統 phpsession 放在 db 0
		$redis->select(0);
		$phpsession_userkeys_count = count($phpsession_userkeys);
		for($d=0;$d<$phpsession_userkeys_count;$d++) {
			echo 'phpsession key deleted'.$phpsession_userkeys[$d];
			$delsessionkey[$d] = $redis->delete($phpsession_userkeys[$d]);
		}
		//var_dump($delsessionkey);


	}else{
		echo '你沒有登入系統.';
		echo '<script>location.reload("true");</script>';
		return(0);
	}

	return(1);
}



// --------------------------------------
// 10
// 檢查系統中的使用者數量，是否有其他的登入者
// 參考文件: https://github.com/phpredis/phpredis#connect-open
// user: Agent_CheckLoginUser($check_agentaccount)
// --------------------------------------
function Agent_CheckLoginUser($check_agentaccount) {

  //$check_agentaccount = 'root';
	global $redisdb;
	// 預設 DB 定義在全域變數
	if(isset($redisdb['db'])) {
		$db = $redisdb['db'];
	}else{
		die('No select RedisDB');
	}

	$redis = new Redis();
	// 2 秒 timeout
	if($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		}else{
			echo 'Redisdb authentication failed';
			return(0);
			// die('Redisdb authentication failed');
		}
	}else{
		// error
		echo 'Redisdb Connection Failed';
		return(0);
		// die('Redisdb Connection Failed');
	}
	// 選擇 DB , agent 使用者自訂的 session 放在 $db
	$redis->select($db);
	// echo "Server is running: ".$redis->ping();
  // -----------------------------------------------

    // 找出已經登入的使用者 key  from db 1
    // 使用者帳號 sha1 後就是 session id
    $account_sid = sha1($check_agentaccount);
    // var_dump($account_sid);

    // 搜尋已經登入的 users key
    $userkey = $account_sid.'*';
    $alive_userkeys = $redis->keys("$userkey");
    // var_dump($alive_userkeys);die();
  // 同一個使用者，只能有一個登入.沒有登入使用者的時候，應該是 false
    $alive_userkeys_count = count($alive_userkeys);
    // var_dump($alive_userkeys_count);die();
  if($alive_userkeys_count == 1) {
        // 剛好有一個使用者
    $keyvalue['key'] = $alive_userkeys[0];
    $keyvalue['value'] = $redis->get($keyvalue['key']);
        $keyvalue['count'] = $alive_userkeys_count;
    $r = $keyvalue;
  }elseif($alive_userkeys_count == 0){
        // 沒有使用者
    $keyvalue['count'] = $alive_userkeys_count;
        $r = $keyvalue;
  }else{
        $keyvalue['count'] = $alive_userkeys_count;
        $r = $keyvalue;
        var_dump($r);
        // 三小，系統應該有漏洞，程式有問題，快點呼叫工程師。
        //die('System redisdb session error, please call service.');
    }

    return($r);
}




// --------------------------------------
// 11
// 建立一個 redis client in db $db 連線， 取得指定的 key and value
// 參考文件:https://github.com/phpredis/phpredis#connect-open
// use: Agent_runRedisDEL($key, $db=1)
// 成功傳回 1 失敗傳回 0
// --------------------------------------
function Agent_runRedisgetkeyvalue($key, $db) {

	global $redisdb;
	if(!isset($db)) {
			die('No select RedisDB');
	}

	$redis = new Redis();
	// 2 秒 timeout
	if($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		}else{
			echo "Authentication failed";
			return(0);
			// die('Authentication failed');
		}
	}else{
		// error
		echo "Connection Faild";
		return(0);
		// die('Connection Failed');
	}
	// 選擇 DB , 分前台或後台
	$redis->select($db);
	// echo "Server is running: ".$redis->ping();
	// -------------------------------------------------
	$online_session = $redis->keys("*");
	// var_dump($online_session);//die();

	// 把 session 值取出，並轉換成為 array 型態
	$usersession = array();
	$j=1;
	foreach ($online_session as $key) {
		$usersession[$j]['value'] 	= $redis->get($key);
		$usersession[$j]['key'] 		=	$key;
		$j++;
	}
	// 資料數量
	$usersession[0] = $j;
	// var_dump($usersession[0]);//die();

	return($usersession);
}


// ==============================================================================================
// 12
// 頁腳顯示 , 每個樣本檔案中都會使用到。
// 放置有 時間計算, 選單, 指紋偵測, analytic
// ==============================================================================================
function page_footer(){
	// 計算時間
	global $program_start_time;
	global $tr;
	global $config;

	// google analytic and piwiki 網站內容分析
	require_once dirname(__FILE__) ."/analytic.php";

	// ----------------------------------------------------------------------------
	// 帆布指紋偵測機制 , 可以識別訪客的瀏覽器唯一值。
	// ref: http://blog.jangmt.com/2017/03/canvas-fingerprinting.html
	// ----------------------------------------------------------------------------
	$fingerprintsession_html = '<iframe name="fingerprint" frameborder="0" src="fingerprintsession.php" height="0px" width="0%" scrolling="no">
	  <p>Your browser does not support iframes.</p>
	</iframe>';
	// 指紋偵測 iframe
	// ----------------------------------------------------------------------------

	// ----------------------------------------------------------------------------
	// 算累積花費時間, 另一個開始放在 config.php
	$program_spent_time = microtime(true) - $program_start_time;
	// $program_spent_time_html = "<script>console.log('".$program_spent_time."')</script>";
	$program_spent_time_html = "Generate time: $program_spent_time ";
	// ----------------------------------------------------------------------------

	//$host_footer_html = $tr['host_footer'];

	// ----------------------------------------------------------------------------
	// fingerprinting + 頁腳選單
	// ----------------------------------------------------------------------------
	$host_footer_html = '<span align="left">'.$config['hostname'].' AT '.$config['website_domainname'].'&nbsp;&nbsp;&nbsp;&nbsp;'.$config['footer'].'</span>';
	$host_footer_html = $host_footer_html.$fingerprintsession_html.'<div align="right">'.$program_spent_time_html.'</div>';

	// 顯示內容
	$footer_content = $host_footer_html;

	// ----------------------------------------------------------------------------
	// 紀錄網頁在哪裡
	// ----------------------------------------------------------------------------
	//$who 誰在那個頁面操作 -- 後台行為紀錄 notice
	if(isset($_SESSION['agent']->account)){
		$account = 	$_SESSION['agent']->account;
	}else{
		$account = 'admin';
	}
	// $service = 'behavior';
	// $message_level = 'notice';
	// 傳入想要寫入的訊息, 沒有的話就是空.
	// $logger = $_SERVER['HTTP_HOST'];
	// $r = memberlog2db("$account","$service","$message_level", "$logger");


	$msg         = $account.' clicks '.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']; //客服
	$msg_log     = 'The function name is the page_footer() in lib.php'; //RD
    $sub_service = 'information';
    // $r=memberlogtodb("$account", 'member', 'notice', $msg, "$account", "$msg_log", 'b', $sub_service);

    // 把後台 登入紀錄寫入memberlog的service改寫成administrator
	$r=memberlogtodb("$account", 'administrator', 'notice', $msg, "$account", "$msg_log", 'b', $sub_service);


	// ----------------------------------------------------------------------------


	return($footer_content);
}


/*
function page_footer(){
    global $system_config;
    // 計算時間
    global $program_start_time;
    global $tr;

    // 算累積花費時間, 另一個開始放在 config.php
    $program_spent_time = microtime(true) - $program_start_time;
    $program_spent_time_html = "<p>The page generate time: $program_spent_time </p>";
    //$program_spent_time_html = "<!-- The page generate time: $program_spent_time -->";

    $host_footer_html = $config['hostname'].'&nbsp;&nbsp;&nbsp;&nbsp;'.$config['footer'];

    $footer_content = '
    '.$host_footer_html.'
    '.$program_spent_time_html.'
    ';

    return($footer_content);
}
*/


























// ==============================================================================================
//
//
// gpk2 轉帳函式
//
//
// ==============================================================================================
// 使用者提供 11 個 變數後，就可以執行轉帳動作
// 轉帳操作人員，只能是管理員或是會員的上線使用者.
// $member_id                   = $_SESSION['agent']->id;
// 娛樂城代號
// $casino                      = 'gpk2';
// 來源轉帳帳號
// $source_transferaccount      = filter_var($_POST['source_transferaccount_input'], FILTER_SANITIZE_STRING);
// 目的轉帳帳號
// $destination_transferaccount = filter_var($_POST['destination_transferaccount_input'], FILTER_SANITIZE_STRING);
// 轉帳金額，需要依據會員等級限制每日可轉帳總額。
// $transaction_money           = filter_var($_POST['balance_input'], FILTER_SANITIZE_NUMBER_INT);
// 摘要資訊
// $summary                     = filter_var($_POST['summary_input'], FILTER_SANITIZE_STRING);
// 實際存提
// $realcash                    = filter_var($_POST['realcash_input'], FILTER_SANITIZE_NUMBER_INT);
// 稽核模式，三種：免稽核、存款稽核、優惠存款稽核
// $auditmode_select            = filter_var($_POST['auditmode_select_input'], FILTER_SANITIZE_STRING);
// 稽核金額
// $auditmode_amount            = filter_var($_POST['auditmode_input'], FILTER_SANITIZE_NUMBER_INT);
// 來源帳號的密碼驗證，驗證後才可以存款
// $password_verify_sha1        = filter_var($_POST['password_input'], FILTER_SANITIZE_STRING);
// 系統轉帳文字資訊
// $system_note_input           = filter_var($_POST['system_note_input'], FILTER_SANITIZE_STRING);
// ------------------------------------
function gpk2_memberdeposit_transfer($member_id, $source_transferaccount, $destination_transferaccount, $transaction_money
    , $summary, $realcash, $auditmode_select, $auditmode_amount, $password_verify_sha1, $system_note_input ) {
    // ----------------------
    // 轉帳邏輯
    // source_transferaccount_input 轉帳給 destination_transferaccount transaction_money 額度
    // ----------------------
    // 轉帳操作人員，只能是管理員或是會員的上線使用者.
    $d['member_id']                   = $member_id;
    // 娛樂城代號
    $d['casino']                      = 'gpk2';
    // 來源轉帳帳號
    $d['source_transferaccount']      = $source_transferaccount;
    // 目的轉帳帳號
    $d['destination_transferaccount'] = $destination_transferaccount;
    // 轉帳金額，需要依據會員等級限制每日可轉帳總額。
    $d['transaction_money']           = $transaction_money;
    // 摘要資訊
    $d['summary']                     = $summary;
    // 實際存提
    $d['realcash']                    = $realcash;
    // 稽核模式，三種：免稽核、存款稽核、優惠存款稽核
    $d['auditmode_select']            = $auditmode_select;
    // 稽核金額
    $d['auditmode_amount']            = $auditmode_amount;
    // 來源帳號的密碼驗證，驗證後才可以存款
    $d['password_verify_sha1']        = $password_verify_sha1;
    // 系統轉帳文字資訊
    $d['system_note_input']           = $system_note_input;

    $d['system_note_input'] = $d['source_transferaccount'].$d['summary'].'到'.$d['destination_transferaccount'].'金額'.$d['transaction_money'].','.$d['system_note_input'];

    // var_dump($d);

    // 0. 取得使用者完整的資料
    $destination_transferaccount_sql = "SELECT * FROM gpk.root_member WHERE account = '".$d['destination_transferaccount']."';";
    // var_dump($destination_transferaccount_sql);
    $destination_transferaccount_result = runSQLALL($destination_transferaccount_sql);
    if($destination_transferaccount_result[0] != 1){
      $logger = '目的端使用者資料有問題，結束。';
      echo $logger;
      die();
      return(7);
    }
    // var_dump($destination_transferaccount_result);
    // 驗證 來源端使用者資料
    $source_transferaccount_sql = "SELECT * FROM gpk.root_member WHERE account = '".$d['source_transferaccount']."';";
    // var_dump($source_transferaccount_sql);
    $source_transferaccount_result = runSQLALL($source_transferaccount_sql);
    if($source_transferaccount_result[0] != 1){
      $logger = '來源端使用者資料有問題，結束。';
      echo $logger;
      die();
      return(6);
    }
    //var_dump($source_transferaccount_result);

    // 驗證來源端使用者的密碼
    // check password to transaction_money
    if($source_transferaccount_result[1]->passwd == $d['password_verify_sha1']) {
      // 密碼對，才工作
      //var_dump($source_transferaccount_result[1]->passwd);
      //var_dump($d['password_verify_sha1']);
    }else{
      // 密碼錯，結束
      $logger = '來源端使用者驗證的密碼錯誤，結束';
      echo $logger;
      die('');
      return(5);
    }

    // 1. PHP 檢查帳戶 destination_transferaccount 是否存在，不存在建立一個 root_member_wallet 帳號
    $user_alive_sql = "SELECT gpk2_balance FROM gpk.root_member_wallet WHERE gpk2_account = '".$d['destination_transferaccount']."';";
      $user_alive_result = runSQLALL($user_alive_sql);
    // var_dump($user_alive_result);
    if($user_alive_result == 0) {
      // 不存在，建立 destination_transferaccount root_member_wallet
      $add_member_wallet_sql = "INSERT INTO gpk.root_member_wallet (id, changetime, gpk2_account, gpk2_balance) VALUES ('37', 'now()', 'dora', '0'); ";
      $add_member_wallet_result = runSQLALL($add_member_wallet_sql);
      if($add_member_wallet_result[0] == 1) {
        // 成功建立這個帳號錢包
      }else{
        // 建立帳號錢包失敗，結束
        $logger = '建立帳號錢包失敗，結束';
        echo $logger;
        die();
        return(4);
      }
      //var_dump($add_member_wallet_result);
    }


    // 2. PHP 檢查帳戶 source_transferaccount 是否有錢,且大於 transaction_money , 成立才工作,否則結束
    $check_source_transferaccount_balance_sql = "SELECT gpk.root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE gpk2_account = '".$source_transferaccount_result[1]->account."' and gpk2_balance >= ".$d['transaction_money']."::money;";
    //var_dump($check_source_transferaccount_balance_sql);
    $check_source_transferaccount_balance_result = runSQLALL($check_source_transferaccount_balance_sql);
    //var_dump($check_source_transferaccount_balance_result);

    // 錢夠，轉帳動作才工作,否則結束
    if($check_source_transferaccount_balance_result[0] == 1) {
      // sql 交易 begin
      $transaction_money_sql = 'BEGIN;';
      //$transaction_money_sql = '';
      // 3. PGSQL 將 source_transferaccount 帳戶 ${site}_balance 欄位扣除 transaction_money 的值 , 餘額($new_balabce )為 ${site}_balance - transaction_money 但是要 source_transferaccount 的 gpk2_balance  >= 2000::money 才作這件事

      $transaction_money_sql = $transaction_money_sql."UPDATE gpk.root_member_wallet SET changetime = now(), gpk2_balance =
      ((SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = ".$source_transferaccount_result[1]->id.") - ".$d['transaction_money']."::money)
      WHERE id = ".$source_transferaccount_result[1]->id." AND ((SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = ".$source_transferaccount_result[1]->id.") >= ".$d['transaction_money']."::money);";

      // 4. PGSQL 將 destination_transferaccount 帳號 ${site}_balance 欄位加上 transaction_money 的值
      $transaction_money_sql = $transaction_money_sql."UPDATE gpk.root_member_wallet SET changetime = now(), gpk2_balance =
      ((SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = ".$destination_transferaccount_result[1]->id.") + ".$d['transaction_money']."::money)
      WHERE id = ".$destination_transferaccount_result[1]->id." ;";

      // 操作：root_memberdepositpassbook
      // 5. PGSQL 新增 1 筆紀錄 帳號 source_transferaccount 轉帳到 destination_transferaccount , destination_transferaccount 存款 withdrawal 為 transaction_money
      $transaction_money_sql = $transaction_money_sql."INSERT INTO gpk.root_memberdepositpassbook
      (transaction_time, deposit, withdrawal, system_note, member_id, currency, summary, source_transferaccount, auditmode, auditmodeamount, casino, realcash, destination_transferaccount)
       VALUES ('now()', '".$d['transaction_money']."', '0', '".$d['system_note_input']."', '".$d['member_id']."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."', '".$d['auditmode_select']."', '".$d['auditmode_amount']."', '".$d['casino']."', '".$d['realcash']."', '".$d['destination_transferaccount']."');";

      // 6. PGSQL 新增 1 筆紀錄 帳號 destination_transferaccount 轉帳從 source_transferaccount , destination_transferaccount 提款 deposit 為 transaction_money
      $transaction_money_sql = $transaction_money_sql."INSERT INTO gpk.root_memberdepositpassbook
      (transaction_time, deposit, withdrawal, system_note, member_id, currency, summary, source_transferaccount, auditmode, auditmodeamount, casino, realcash, destination_transferaccount)
       VALUES ('now()', '0', '".$d['transaction_money']."', '".$d['system_note_input']."', '".$d['member_id']."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['destination_transferaccount']."', '".$d['auditmode_select']."', '".$d['auditmode_amount']."', '".$d['casino']."', '".$d['realcash']."', '".$d['source_transferaccount']."');";

       $transaction_money_sql = $transaction_money_sql.'COMMIT;';

       // echo '<p>'.$transaction_money_sql.'</p>';
       $transaction_money_result = runSQLtransactions($transaction_money_sql);
       //$transaction_money_result = 1;
       if($transaction_money_result) {
        $logger = $d['source_transferaccount'].$d['summary'].'到'.$d['destination_transferaccount'].'金額'.$d['transaction_money'].'成功';
        echo $logger;
       }else{
           $logger = $d['source_transferaccount'].$d['summary'].'到'.$d['destination_transferaccount'].'金額'.$d['transaction_money'].'失敗';
        echo $logger;
        return(3);
       }

    }else{
      $logger = $d['source_transferaccount'].', 存款不足，結束。';
      echo $logger;
      die();
      return(2);
    }

    return(1);


    /*
        -- 操作：root_member_wallet
        -- 1. PHP 檢查帳戶 dora 是否存在，不存在建立一個空帳號
        -- 新增帳號 dora 的空資料在錢包內，如果該帳號不存在的話。
        INSERT INTO gpk.root_member_wallet (id, changetime, gpk2_account, gpk2_balance)
        VALUES ('37', 'now()', 'dora', '0');

        -- 2. PHP 檢查帳戶 agent 是否有錢,且大於 $money , 成立才工作,否則結束
        SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = 2 and gpk2_balance >= 2000::money;

        -- 3. PGSQL 將 agent 帳戶 ${site}_balance 欄位扣除 $money 的值 , 餘額($new_balabce )為 ${site}_balance - $money
        -- 但是要 agent 的 gpk2_balance  >= 2000::money 才作這件事
        UPDATE root_member_wallet SET changetime = now(), gpk2_balance =
        ((SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = 2) - 2000::money)
        WHERE id = 2 AND ((SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = 2) >= 2000::money);

        -- 4. PGSQL 將 dora 帳號 ${site}_balance 欄位加上 $money 的值
        UPDATE root_member_wallet SET changetime = now(), gpk2_balance =
        ((SELECT root_member_wallet.gpk2_balance FROM  gpk.root_member_wallet WHERE id = 37) + 2000::money)
        WHERE id = 37  ;

        -- 操作：root_memberdepositpassbook
        -- 1. PGSQL 新增 1 筆紀錄 帳號 agent 轉帳到 dora , agent 提款 withdrawal 為 $money
        INSERT INTO "root_memberdepositpassbook"
        ("transaction_time", "deposit", "withdrawal", "balance", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "transferstat", "casino", "realcash", "destination_transferaccount")
        VALUES ('now()', '2000', '0', '', 'root 轉帳 dora', '1', $config['currency_sign'], '入款', 'root', 'FreeAudit', '0', '1', 'gpk2', '0', 'dora');

        -- 2. PGSQL 新增 1 筆紀錄 帳號 agent 轉帳到 dora , dora 存款 deposit 為 $money
        INSERT INTO "root_memberdepositpassbook"
        ("transaction_time", "deposit", "withdrawal", "balance", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "transferstat", "casino", "realcash", "destination_transferaccount")
        VALUES ('now()', '2000', '0', '', 'root 轉帳 dora', '1', $config['currency_sign'], '入款', 'root', 'FreeAudit', '0', '1', 'gpk2', '0', 'dora');
    */

}



// ==============================================================================================
//
// 專屬後台
// 取得指定 id and account 的帳號餘額，如果該 gpk2 帳戶不存在的話建立該帳戶。
//
//
// ==============================================================================================
// usage: $user_balance = get_gpk2_member_wallet_account_balance($user->id, $user->account);
// 回傳 gpk2 餘額(balance)
// ==============================================================================================
function get_gpk2_member_wallet_account_balance($userid, $useraccount) {
    // 抓 $user->id 餘額
    $user_balance_sql = "SELECT gpk2_balance FROM root_member_wallet WHERE id = $userid;";
    $user_balance_result = runSQLALL($user_balance_sql);
    if($user_balance_result[0] == 1) {
        // 存在，取出餘額
        $user_balance = $user_balance_result[1]->gpk2_balance;
    }else{
        // 沒有資料，建立初始資料。
        $member_wallet_addaccount_sql = "INSERT INTO gpk.root_member_wallet (id, changetime, gpk2_account, gpk2_balance)
        VALUES ('".$userid."', 'now()', '".$useraccount."', '0');";
        $rwallet = runSQL($member_wallet_addaccount_sql);
        if($rwallet == 1){
            $user_balance = '$0.00';
            $logger = "$userid, $useraccount".',Create root_member_wallet account success!! ';
            //echo $logger;
            memberlog2db($_SESSION['agent']->account,'member wallet','info', "$logger");
        }else {
            $logger = "$userid, $useraccount".',Create root_member_wallet account false!! ';
            // echo $logger;
            memberlog2db($_SESSION['agent']->account,'member wallet','error', "$logger");
            die();
        }
    }
    return($user_balance);
}
// 回傳 gpk2 餘額(balance)  end

// ----------------------------------------------------------------------------

// ----------------------------------------------------------.
/*
// use sample
$salt = '11223344';
// 需要傳遞的陣列
$codevalue_array = array(
  'Amt'             => '111',
  'MerchantOrderNo' => 'ertgyhujioiuytre'
);
// 產生
$send_code = jwtenc($salt,$codevalue_array);
var_dump($send_code);
// 解碼
$codevalue = jwtdec($salt,$send_code);
var_dump($codevalue);
*/
// ----------------------------------------------------------.
// jwtenc 傳送需要被回傳的資料, 包含驗證碼
// $salt 加密的密碼
// $codevalue_array 傳送的資料陣列
// ----------------------------------------------------------.
function jwtenc($salt, $codevalue_array, $debug =0) {
  // 將變數排序陣列
  $check_codevalue = ksort($codevalue_array);

  // 將變數使用 json + base64 encode
  $base64_codevalue =base64_encode(json_encode($codevalue_array));

  // 用 sha1 加密 , 產生檢核碼
  $checkvalue = sha1($salt . sha1($salt . $base64_codevalue));

  // 兩個碼合在一起當成變數傳遞
  $send_code = $checkvalue.'_'.$base64_codevalue;

  if($debug  == 1) {
    var_dump($check_codevalue);
    var_dump($base64_codevalue);
    var_dump($checkvalue);
    var_dump($send_code);
  }
  return($send_code);
}
// ----------------------------------------------------------.

// ----------------------------------------------------------.
// jwtdec 解開並驗證傳回的資料是否正確, 不正確為 false
// $salt 加密的密碼
// $send_code 接收到的 jwt data
// ----------------------------------------------------------.
function jwtdec($salt, $send_code, $debug =0) {
  // 將傳來的 code 拆開
  $send_code_value = explode('_', $send_code);

  // return
  $checkvalue = sha1($salt . sha1($salt . $send_code_value[1]));

  // 判斷資料是否有被竄改
  if($checkvalue == $send_code_value[0]){
    $codevalue =json_decode(base64_decode($send_code_value[1]));

  }else{
    // 資料被串改 false return
    $codevalue = false;
  }

  if($debug  == 1) {
    var_dump($send_code_value);
    var_dump($checkvalue);
    var_dump($codevalue);
  }
  return($codevalue);
}
// ----------------------------------------------------------.

/**
 * 判斷是否為 AJAX 請求
 *
 * @return boolean
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * 計算目前記憶體使用量
 * 在撈取大量資料(如注單)做計算時參考用
 *
 * e.g. 65000+筆 10分鐘注單統計約 80M
 *
 * @return string
 */
function memory_use_now(): string
{
    $level = array('Bytes', 'KB', 'MB', 'GB');
    $n = memory_get_usage();
    for ($i=0, $max=count($level); $i<$max; $i++)
    {
        if ($n < 1024)
        {
            $n = round($n, 2);
            return "{$n} {$level[$i]}";
        }
        $n /= 1024;
    }
}

/**
 * 樣版共用JS及CSS header
 */

function assets_include($conf=NULL)
{
     global $config;
     global $cdnfullurl;
     global $cdnfullurl_js;

     $langjs = (isset($_SESSION['lang'])) ? $_SESSION['lang'] : 'zh-cn';

     $head_asset = <<<HTML
     <script src="in/jquery/jquery.min.js?version_key=20200817"></script>

     <!-- bootstrap and jquery -->
     <link rel="stylesheet" href="in/bootstrap/css/bootstrap.min.css?version_key=20200817">
     <script src="in/bootstrap/js/bootstrap.min.js?version_key=20200817"></script>

     <!-- Parse, validate, manipulate, and display dates in JavaScript. -->
     <script src="in/moment-with-locales.js"></script>
     <script src="in/moment-timezone-with-data.js"></script>
     <!-- jquery.crypt.js -->
     <script src="in/jquery.crypt.js"></script>

     <!-- Custom styles for this template -->
     <link rel="stylesheet"  href="ui/style.css?version_key=20200817" >
     <!-- Jquery blockUI js  -->
     <script src="./in/jquery.blockUI.js"></script>

     <!-- JS Language File -->
     <script src='in/lang/{$langjs}.js'></script>
     <!-- mq component stomp.js -->
     <script src="./in/stomp.js"></script>
HTML;

     if(is_null($conf)){
         $head_asset .= <<<HTML
         <!-- icon -->
         <link rel="stylesheet"  href="in/fonticon/css/icons.css?version_key=20200817" >

         <!-- Jquery UI js+css  -->
         <script src="in/jquery-ui.js"></script>
         <link rel="stylesheet"  href="in/jquery-ui.css" >

         <!-- 轉換頁面時候的黑畫面 -->
         <script>
                 // 偵測是否有按下shift鍵，如果有就不執行blockui的遮罩
                 var shifted = false;
                 var ctrled = false;
                 $(document).on('keyup keydown', function(e){shifted = e.shiftKey} );
                 $(document).on('keyup keydown', function(e){ctrled = e.ctrlKey} );
                 function blockscreengotoindex() {
                     if(!shifted && !ctrled ){
                         $.blockUI({ message: '<img src="ui/loading_text.gif" />' });
                     }
                 };
         </script>
HTML;
     }
     return $head_asset;
}

/**
 * 樣版共用JS及CSS footer
 */

function footer_include($conf=NULL)
{
     global $config;
     global $tr;
     global $cdnfullurl;
     global $cdnfullurl_js;

     $langjs = (isset($_SESSION['lang'])) ? $_SESSION['lang'] : 'zh-cn';

    $footer_asset = <<<HTML
    <script type="module">

        var ws = new WebSocket("$config[rebbit_js_url]"); //連接websocket

        // 获得Stomp client对象
        var client = Stomp.over(ws);

        client.debug = null  //console是否顯示

        // 定义连接成功回调函数
        var on_connect = function(x) {
            //data.body是接收到的数据

            client.subscribe("/exchange/msg_notify", function(data) {
                const datas = JSON.parse(data.body)

                if (datas.from === 'SiteAnnouncement') {
                    // 公告
                    $("#announce.messege #pills-announce").prepend(`<li class="data"><a href="systemconfig_announce_read.php">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[announce]</span></span>
                                            </li>`);
                    $("#announce.messege #pills-announce .nodata").addClass('d-none');
                    $("#announce.announce #pills-notify").prepend(`<li class="data"><a href="systemconfig_announce_read.php">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[announce]</span></span>
                                            </li>`);
                    $("#announce.announce #pills-notify .nodata").addClass('d-none');
                    $('.sysop_bullhorn .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_bullhorn .badge').text());
                    $('.sysop_bullhorn .badge').text(nums+1);
                    let num = Number($('#announce.messege #pills-announce .view-all span').text());
                    $('#announce.messege #pills-announce .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#announce_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+time+' 有新公告，請刷新');
                } else if (datas.from === 'CompanyDeposit') {
                    // 公司入款
                    $("#announce.deposit #pills-depositing_company").prepend(`<li class="data"><a href="depositing_company_audit_review.php?id=`+datas.detail.data_id+`">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[companydeposits]</span></span>
                                            </li>`);
                    $("#announce.deposit #pills-depositing_company .nodata").addClass('d-none');
                    $("#announce.deposit #pills-depositing").prepend(`<li class="data"><a href="depositing_company_audit_review.php?id=`+datas.detail.data_id+`">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[companydeposits]</span></span>
                                            </li>`);
                    $("#announce.deposit #pills-depositing .nodata").addClass('d-none');
                    $('.sysop_wallet .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_wallet .badge').text());
                    $('.sysop_wallet .badge').text(nums+1);
                    let num = Number($('#announce.deposit #pills-depositing_company .view-all span').text());
                    $('#announce.deposit #pills-depositing_company .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#depositing_company_audit_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+datas.account+' '+time+' 已存款，請刷新');
                } else if (datas.from === 'OnlinePay') {
                    // 線上支付
                    $("#announce.deposit #pills-depositing_onlinepay").prepend(`<li class="data"><a href="depositing_siteapi_audit.php">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[onlinepay]</span></span>
                                            </li>`);
                    $("#announce.deposit #pills-depositing_onlinepay .nodata").addClass('d-none');
                    $("#announce.deposit #pills-depositing").prepend(`<li class="data"><a href="depositing_siteapi_audit.php">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[onlinepay]</span></span>
                                            </li>`);
                    $("#announce.deposit #pills-depositing .nodata").addClass('d-none');
                    $('.sysop_wallet .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_wallet .badge').text());
                    $('.sysop_wallet .badge').text(nums+1);
                    let num = Number($('#announce.deposit #pills-depositing_onlinepay .view-all span').text());
                    $('#announce.deposit #pills-depositing_onlinepay .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#onlinepay_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+datas.account+' '+time+' 已存款，請刷新');
                } else if (datas.from === 'TokenWithdrawal') {
                    // 遊戲幣取款
                    $("#announce.withdrawal #pills-withdrawalgtoken").prepend(`<li class="data"><a href="withdrawalgtoken_company_audit.php?unaudit">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[Gtoken]</span></span>
                                            </li>`);
                    $("#announce.withdrawal #pills-withdrawalgtoken .nodata").addClass('d-none');
                    $("#announce.withdrawal #pills-withdrawalall").prepend(`<li class="data"><a href="withdrawalgtoken_company_audit.php?unaudit">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[Gtoken]</span></span>
                                            </li>`);
                    $("#announce.withdrawal #pills-withdrawalall .nodata").addClass('d-none');
                    $('.sysop_coins .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_coins .badge').text());
                    $('.sysop_coins .badge').text(nums+1);
                    let num = Number($('#announce.withdrawal #pills-withdrawalgtoken .view-all span').text());
                    $('#announce.withdrawal #pills-withdrawalgtoken .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#tokenwithdrawal_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+datas.account+' '+time+' 已取款，請刷新');
                } else if (datas.from === 'CashWithdrawal') {
                    // 現金取款
                    $("#announce.withdrawal #pills-withdrawalgcash").prepend(`<li class="data"><a href="withdrawalgcash_company_audit.php?unaudit">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[Franchise]</span></span>
                                            </li>`);
                    $("#announce.withdrawal #pills-withdrawalgcash .nodata").addClass('d-none');
                    $("#announce.withdrawal #pills-withdrawalall").prepend(`<li class="data"><a href="withdrawalgcash_company_audit.php?unaudit">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[Franchise]</span></span>
                                            </li>`);
                    $("#announce.withdrawal #pills-withdrawalall .nodata").addClass('d-none');
                    $('.sysop_coins .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_coins .badge').text());
                    $('.sysop_coins .badge').text(nums+1);
                    let num = Number($('#announce.withdrawal #pills-withdrawalgcash .view-all span').text());
                    $('#announce.withdrawal #pills-withdrawalgcash .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#cashwithdrawal_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+datas.account+' '+time+' 已取款，請刷新');
                } else if (datas.from === 'AgentReview') {
                    // 代理商審核
                    $("#announce.member #pills-agent").prepend(`<li class="data"><a href="agent_review.php?t=90">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[agentReview]</span></span>
                                            </li>`);
                    $("#announce.member #pills-agent .nodata").addClass('d-none');
                    $("#announce.member #pills-member").prepend(`<li class="data"><a href="agent_review.php?t=90">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[agentReview]</span></span>
                                            </li>`);
                    $("#announce.member #pills-member .nodata").addClass('d-none');
                    $('.sysop_user .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_user .badge').text());
                    $('.sysop_user .badge').text(nums+1);
                    let num = Number($('#announce.member #pills-agent .view-all span').text());
                    $('#announce.member #pills-agent .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#agent_review_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+datas.account+' '+time+' 已申請代理商，請刷新');
                } else if (datas.from === 'MemberRegister') {
                    // 註冊審核
                    $("#announce.member #pills-register").prepend(`<li class="data"><a href="member_register_review.php?t=90">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[forestageRegister]</span></span>
                                            </li>`);
                    $("#announce.member #pills-register .nodata").addClass('d-none');
                    $("#announce.member #pills-member").prepend(`<li class="data"><a href="member_register_review.php?t=90">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[forestageRegister]</span></span>
                                            </li>`);
                    $("#announce.member #pills-member .nodata").addClass('d-none');
                    $('.sysop_user .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_user .badge').text());
                    $('.sysop_user .badge').text(nums+1);
                    let num = Number($('#announce.member #pills-register .view-all span').text());
                    $('#announce.member #pills-register .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#member_review_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+datas.account+' '+time+' 已申請會員，請刷新');
                } else if (datas.from === 'StationMail ') {
                    // 站內信
                    $("#announce.announce #pills-mail").prepend(`<li class="data"><a href="mail.php">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[mail]</span></span>
                                            </li>`);
                    $("#announce.announce #pills-mail .nodata").addClass('d-none');
                    $("#announce.announce #pills-notify").prepend(`<li class="data"><a href="mail.php">
                                                <span class="title">`+datas.account+`</span>
                                                <span class="subtitle"><span>`+datas.date.substr(0,10)+`</span>
                                                <span>$tr[mail]</span></span>
                                            </li>`);
                    $("#announce.announce #pills-notify .nodata").addClass('d-none');
                    $('.sysop_bullhorn .badge').removeClass('nonebadge')
                    let nums = Number($('.sysop_bullhorn .badge').text());
                    $('.sysop_bullhorn .badge').text(nums+1);
                    let num = Number($('#announce.announce #pills-mail .view-all span').text());
                    $('#announce.announce #pills-mail .view-all span').text(num+1);

                    let time = moment(datas.date).tz('Etc/GMT+4').format('YYYY-MM-DD HH:mm:ss')

                    $('#mail_mq').show().html('<i class="fas fa-exclamation-triangle mr-1"></i>'+time+' 有站內信，請刷新');
                } else {
                // alert('有新通知！')
                }
            });
        };

        var on_error = function(x) {}

        // 连接RabbitMQ
        client.connect('$config[rebbit_js_user]', '$config[rebbit_js_password]', on_connect, on_error, '$config[rebbit_js_vhost]');


        $(".messege .nav-link").on("click",function (e) {
        e.stopPropagation();
        })

        $('.messege.dropdown-menu a.dropdown-toggle').on('click', function(e) {

        var subMenu = $(this).next(".dropdown-menu");
        subMenu.toggleClass('show');

        return false;
        });
        $(".messege .dropdown-submenu .dropdown-menu .nav-link").on("click",function (e) {
        $(this).tab('show')
        $(this).siblings(".dropdown-item").removeClass('active');
        $(this).parent('.dropdown-menu').removeClass('show')

        let navName = $(this).text()
        $(this).parent('.dropdown-menu').siblings("a.nav-link").text(navName)
        })

        $(".messege .dropdown-submenu .dropdown-menu .nav-link.active").on("click",function (e) {
            e.preventDefault();
        })
    </script>
HTML;

        return $footer_asset;
}

/**
 * 顯示除錯模式訊息
 *
 * @param int $debug 除錯模式，1 為除錯模式
 * @param mixed $showVars 要顯示的變數
 */
function debugMode(int $debug, $showVars)
{
    if ($debug == 1) {
        var_dump($showVars);
    }
}

// ----------------------------------------------------------------------------
// (14)
if( isset($_GET['debug_mode']) && ($_GET['debug_mode']=="true") ){
    date_default_timezone_set("Asia/Taipei");
    if( !isset($_SESSION['debug_mode']) || (@$_SESSION['debug_mode']!=true) ){
        $_SESSION['debug_mode'] = true;
    }
    echo '=== 系統已開啟除錯模式 ==='.'<br>'.
         '開啟時間：'.date("Y/m/d H:i:s");
    exit();
}
else if( isset($_GET['debug_mode']) && ($_GET['debug_mode']=="false") ){
    date_default_timezone_set("Asia/Taipei");
    if( !isset($_SESSION['debug_mode']) || (@$_SESSION['debug_mode']!=false) ){
        $_SESSION['debug_mode'] = false;
    }
    echo '=== 系統已關閉除錯模式 ==='.'<br>'.
         '關閉時間：'.date("Y/m/d H:i:s");
    exit();
}
else if( isset($_GET['debug_mode']) && ( ($_GET['debug_mode']!="true") && ($_GET['debug_mode']!="false") ) ){
    if( isset($_SESSION['debug_mode']) ){
        $_SESSION['debug_mode'] = null;
    }
    echo '=== 資料異常 ====';
    exit();
}
// ----------------------------------------------------------------------------
?>
