<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 優惠碼詳細列表action
// File Name:	activity_detail_init_action.php
// Author:    Mavis
// Related:
// DB Table: root_promotion_code,root_promotion_activity
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 文字檔
require_once dirname(__FILE__) ."/activity_management_editor_lib.php";
// csv
require_once dirname(__FILE__) . "/lib_file.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(isset($_GET['a']) && $_SESSION['agent']->therole == 'R'){
	$action = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
} else {
	die($tr['Illegal test']);
}

if(isset($_GET['s']) && $_GET['s'] != NULL){
	$id = filter_var($_GET['s'],FILTER_SANITIZE_NUMBER_INT);
	
}

if(isset($_GET['csv'])){
	$CSVquery_sql_array = jwtdec('promotioncode_csv',$_GET['csv']);
	$csvfilename = sha1($_GET['csv']);

}

// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) && $_GET['length'] != NULL ) {
  $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
  $current_per_size = $page_config['datatables_pagelength'];
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if(isset($_GET['start']) && $_GET['start'] != NULL ) {
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


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";

function csv_query(){
	global $id;
	$csv_data=<<<SQL
		SELECT 
			act.activity_name,
			act.activity_id,
			act.bouns_amount,
			act.bouns_classification,
			act.bonus_auditclass,
			act.bonus_audit,
			act.effecttime,
			act.endtime,
			act.activity_domain,
			act.activity_subdomain,
			code.promo_id,
			code.member_account,
			code.member_ip,
			code.member_fingerprint,
			code.status,
			code.receivetime
		FROM root_promotion_activity AS act
		JOIN root_promotion_code AS code
		ON code.activity_id = act.activity_id
		WHERE act.id = '{$id}'
SQL;

	return $csv_data;
}

// -----------------------------------------------------------------------
// datatable 初始化
// -----------------------------------------------------------------------
if($action == 'init' && $_SESSION['agent']->therole == 'R') {

	$tzonename = 'posix/Etc/GMT+4';

	$sql=<<<SQL
		SELECT * ,
			to_char((receivetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS receivetime
		FROM root_promotion_activity  AS act
		JOIN root_promotion_code AS code
		ON code.activity_id = act.activity_id
		WHERE act.id = '{$id}'
SQL;

	 // 處理 datatables 傳來的排序需求
  	if(isset($_GET['order'][0]) && $_GET['order'][0]['column'] != ''){
	    if($_GET['order'][0]['dir'] == 'asc'){ 
	      $sql_order_dir = 'ASC';
	    }else{ 
	      $sql_order_dir = 'DESC';
	    }
	    if($_GET['order'][0]['column'] == 0){ 
	      $sql_order = 'ORDER BY code.id '.$sql_order_dir;
	    }elseif($_GET['order'][0]['column'] == 1){ 
	      $sql_order = 'ORDER BY code.member_account '.$sql_order_dir;
	    }elseif($_GET['order'][0]['column'] == 2){ 
	      $sql_order = 'ORDER BY code.status '.$sql_order_dir;
	    }else{ 
	      $sql_order = 'ORDER BY code.id ASC';
	    }
	}else{ 
	    $sql_order = 'ORDER BY code.id ASC';
	}

    // 算資料數
    $count_sql = $sql.";";
    $count_list = runSQL($count_sql);

    // 分頁
    // 所有紀錄
    $page['all_records'] = $count_list;
    // 每頁顯示多少
    $page['per_size'] = $current_per_size;
    // 目前所在頁數
    $page['no'] = $current_page_no;

    // 取出資料
    $list_sql = <<<SQL
    	{$sql} {$sql_order} OFFSET {$page['no']} LIMIT {$page['per_size']}
SQL;
    $result = runSQLall($list_sql);
    
  	if($result[0] >= 1) {

	    for($i=1;$i<=$result[0];$i++){

	      	// 活動狀態
	      	if($result[$i]->activity_status == '1'){
	        	$isshow_status = 'checked';
	      	} elseif($result[$i]->activity_status == '0') {
	        	$isshow_status = '';
	      	}

	      	// 優惠碼狀態
	      	if($result[$i]->status == '1' && $result[$i]->receivetime != ''){
	       		$promo_status =<<<HTML
	        	<span class="label label-success">{$tr['received']}</span>
HTML;
	      	} elseif($result[$i]->status == '0'){
	        	$promo_status =<<<HTML
	         	<span class="label label-danger">{$tr['non received']}</span>
HTML;
			};

			$receive_time = ($result[$i]->receivetime != null) ? date('Y-m-d H:i:s', strtotime($result[$i]->receivetime) + 12 * 3600) : '';
	        // 活動
	        $promo['id'] = $id;

	        $promo['activity_id'] = $result[$i]->activity_id; // 活動代碼
	        $promo['activity_name'] = $result[$i]->activity_name; // 活動名稱
	        $promo['activity_status'] = $isshow_status; // 活動狀態
	        $promo['activity_desc'] = htmlspecialchars_decode($result[$i]->activity_desc); // 活動說明
	        $promo['effecttime'] = $result[$i]->effecttime; // 開始時間
	        $promo['endtime'] = $result[$i]->endtime; // 結束時間
	        $promo['activity_domain'] = $result[$i]->activity_domain; //顯示在哪個domain
	        $promo['bouns_number'] = $result[$i]->bouns_number; // 優惠碼數量(產生多少組優惠碼)
	        $promo['amount'] = $result[$i]->bouns_amount; // 金額
	        $promo['classification'] = $result[$i]->bouns_classification; //獎金類別(遊戲幣，現金)
	        $promo['bonus_auditclass'] = $result[$i]->bonus_auditclass; // 稽核類別
	        $promo['bonus_audit'] = $result[$i]->bonus_audit; //獎金稽核倍數(遊戲幣)
	        $promo['note'] = htmlspecialchars_decode($result[$i]->note); // 備註

	        // 優惠碼
	        $promo['promocoed_id'] = $result[$i]->id;
	        $promo['promo_id']= $result[$i]->promo_id; // 優惠碼
	        $promo['member_account'] = $result[$i]->member_account; // 領取的會員帳號
	        $promo['member_ip'] = $result[$i]->member_ip; // 會員ip
	        $promo['member_fingerprint'] = $result[$i]->member_fingerprint; // 會員fingerprint
	        $promo['status'] = $promo_status; // 彩金領取狀態
			$promo['receivetime'] =  $receive_time; // 領取時間
					// gmdate('Y/m/d H:i:s',strtotime($result[$i]->receivetime)+ -4*3600),

	    	$show_list_array[] = array(
	    	 	'id' 										=>  $promo['promocoed_id'],
	    	 	'promotion_id' 							=>  $promo['promo_id'],
	    	 	'the_member_account' 				=> $promo['member_account'],
	    	 	'bouns_classification'	=> $coin_classification[$promo['classification']],
	    	 	'bouns_amount' 					=> $promo['amount'],
	    	 	'promotion_status' 								=> $promo['status'],
	    	 	'promotion_receivetime' 					=> $promo['receivetime']
	    	);
	    }
    	$output = array(
          "sEcho" 								=> intval($secho),
          "iTotalRecords" 				=> intval($page['per_size']),
          "iTotalDisplayRecords" 	=> intval($page['all_records']),
          "data" 									=> $show_list_array
        );

	} else{
		$output = array(
	    "sEcho" 								=> 0,
	    "iTotalRecords" 				=> 0,
	    "iTotalDisplayRecords" 	=> 0,
	    "data" 									=> ''
      );
	}
	echo json_encode($output);
	
}elseif($action == 'summary' && $_SESSION['agent']->therole == 'R') {
	// 統計資料

	if(isset($query_str['logger'])){
		$output = array('logger' => $query_str['logger']);
		echo json_encode($output);
  	}else{
	
		$promo_summary_sql=<<<SQL
			SELECT *
			FROM root_promotion_activity AS act
			WHERE id = '{$id}'
SQL;
			$show_promoction = runSQLall($promo_summary_sql); // csv
			
			$root_promotion_code_sql=<<<SQL
				SELECT  id
				FROM root_promotion_code
				WHERE activity_id='{$show_promoction[1]->activity_id}'
SQL;
			$root_promotion_code_sql_result = runSQL($root_promotion_code_sql,0);// 計算優惠碼數量

			// 已領
			$get_promo_summary_sql=<<<SQL
				SELECT status AS code_status 
				FROM root_promotion_code AS code 
				WHERE activity_id='{$show_promoction[1]->activity_id}' 
				AND code.status = '1'
SQL;
			$show_get_promo = runSQL($get_promo_summary_sql,0);

			// 未領
			$un_get_sql=<<<SQL
				SELECT status AS code_status 
				FROM root_promotion_code AS code 
				WHERE activity_id='{$show_promoction[1]->activity_id}' 
				AND code.status = '0'
SQL;
			$show_unget_promoction_code = runSQL($un_get_sql,0);

			// 最後更新
			$max_time=<<<SQL
				SELECT to_char((MAX(receivetime) AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS lastupdate_time 
				FROM root_promotion_code AS code 
				WHERE activity_id = '{$show_promoction[1]->activity_id}'
SQL;
			$show_lastest_update = runSQLall($max_time,0);
			$this_act_total = $show_promoction[1]->bouns_number * $show_promoction[1]->bouns_amount; // 總額
			$latest = $show_lastest_update[1]->lastupdate_time ? $show_lastest_update[1]->lastupdate_time : $tr['no']; // 最後更新時間
	
			// csv檔名
			$filename = "promotioncode_csv_".date("Y-m-d_His").'.csv';

			$dl_csv_code = jwtenc('promotioncode_csv',$show_promoction); // csv

			$show_list_array = [
				"all_promoction_code"		=> $root_promotion_code_sql_result,
				"get_promoction_code"		=> $show_get_promo,
				"unget_promoction_code"	=> $show_unget_promoction_code,
				"amount_promoction_code"=> '$'.$this_act_total,
				"latest_update"					=> $latest,
				"download_url"					=> "activity_detail_init_action.php?s={$id}&a=dl_csv&csv=".$dl_csv_code,
				"csv_filename"					=> $filename
			];

			echo json_encode($show_list_array);
	}
}elseif($action == 'dl_csv' && $_SESSION['agent']->therole == 'R'){

	// csv
	$csv_sql = csv_query($CSVquery_sql_array);
	if(isset($query_str['logger'])){
    	$output = array('logger' => $query_str['logger']);
		echo json_encode($output);
		return;
	}
		// 寫入 CSV 檔案前, 先產生一組 key 來處理
		$csv_key = 'promotioncode_csv';
		$csv_key_sha1 = sha1($csv_key);

		// csv檔案標題
		$csv_file_title[$csv_key_sha1] = [$tr['activity name'],$tr['promotion code'],$tr['promo code'],$tr['each promotion amount'],$tr['bonus category'],$tr['payout status'],$tr['Account'],'ip','fingerprint',$tr['Receive time'],$tr['information']];
		$front_urlpath = 'promotion_activity.php?a=';
		// 活動資訊
		// 計算筆數
		$promo_result_count = runSQLall($csv_sql,0);
		$promo_dec = json_decode(json_encode($promo_result_count),true);
		
		$promoctioncode_paginator = new Paginator($csv_sql, 3000);
		
		if($promoctioncode_paginator->total < 1){
			echo '(405) No Data!!';
			die();
		}

		// -------------------------------------------
		// 將內容輸出到 檔案 , csv format
		// -------------------------------------------
		$filename = "promotioncode_csv_".date("Y-m-d_His").'.csv';
		$file_path = dirname(__FILE__) .'/tmp_dl/'.$filename;

		$csv_stream = new CSVWriter($file_path); 
		// $csv_stream = new CSVStream($filename);
		$csv_stream->begin();

		// 將資料輸出到檔案 title
		foreach($csv_file_title as $wline){
			$csv_stream->writeRow($wline);
		}

		// 在分頁回圈內，將資料寫出
		for(
			$promoction_code_result = $promoctioncode_paginator->getCurrentPage()->data;
			count($promoction_code_result) > 0;
			$promoction_code_result = $promoctioncode_paginator->getNextPage()->data
			){
				foreach($promoction_code_result as $code){
				$subdomain = $code->activity_subdomain; // 子網域
				$find_sub = str_replace('/',' ',$subdomain); // 移除 '/'
				$desktop_sub = explode(" ",$find_sub);

				$csv_stream->writeRow([
					$code->activity_name,
					$code->activity_id,
					$code->promo_id,
					$code->bouns_amount,
					$code->bouns_classification,
					$code->status,
					$code->member_account,
					$code->member_ip,
					$code->member_fingerprint,
					$code->receivetime,
					$tr['url'].':'.$protocol.$desktop_sub[0].'.'.$code->activity_domain.'/'.$front_urlpath.''.$code->activity_id.'，'.$tr['promo code'].':'.$code->promo_id.'，'.$tr['bonus'].':'.$code->bouns_amount.'，'.$tr['Starting time'].':'.$code->effecttime.'，'.$tr['End time'].':'.$code->endtime
					// 	'活动网址:'.$protocol.$desktop_sub[0].'.'.$code->activity_domain.'/'.$front_urlpath.''.$code->activity_id.'，优惠码:'.$code->promo_id.'，奖金:'.$code->bouns_amount.'，活动开始时间:'.$code->effecttime.'，活动结束时间:'.$code->endtime
				]);
			}
		}
		// 將資料輸出到檔案
		foreach ($csv_file_title as $wline) {
			$csv_stream->writeRow($wline);	
		}
		
		$csv_stream->end();

		$excel_stream = new csvtoexcel($filename,$file_path);
        $excel_stream->begin();

}elseif($action == 'test') {
  var_dump($_POST);
  echo 'ERROR';
}

?>