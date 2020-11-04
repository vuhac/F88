<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 系統彩金發放管理 -- 動作
// File Name:    receivemoney_management_action.php
// Author:        Barkley Fix by Ian
// Related:   對應 receivemoney_management.php、receivemoney_management_detail.php
//            DB root_receivemoney
// Log:
// 2017.7.21 update
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

require_once dirname(__FILE__) . "/receivemoney_management_lib.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ----------------------------------------------------------------------------


// 取得現在的時間
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));//原timezone_open('EDT'))
date_timezone_set($date, timezone_open('America/St_Thomas'));//原timezone_open('EDT'))
$current_datepicker = date_format($date, 'Y-m-d');
$current_datetimepicker = date_format($date, 'Y-m-d H:i:s');
$current_datetimepicker_gmt = gmdate('Y-m-d H:i:s.u', strtotime($current_datetimepicker) + 8 * 3600) . '+08:00';

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
// 取得頁面傳來的操作指令 $tr['Illegal test'] = '(x)不合法的測試。';

// ----------
// 20200512
$datepicker = gmdate('Y-m-d H:i:s',time() + -4*3600); // 今天
//$default_date = gmdate('Y-m-d H:i:s',strtotime('- 7 days')); // 7天
$default_min_date = gmdate('Y-m-d H:i:s.u',strtotime('- 2 months') + 8 * 3600) . '+08:00'; // 最大可搜尋時間//----------------
//-----------

if (isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die($tr['Illegal test']);
}

if (isset($_GET['a2'])) {
    $action2 = filter_var($_GET['a2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}

// log file key
if (isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 彩金ID
if (isset($_GET['d']) and filter_var($_GET['d'], FILTER_VALIDATE_INT)) {
    $bonus_id = filter_var($_GET['d'], FILTER_VALIDATE_INT);
}
// 彩金ID array
if (isset($_POST['id']) and filter_var_array($_POST['id'], FILTER_VALIDATE_INT)) {
    $bonus_id_arr = filter_var_array($_POST['id'], FILTER_VALIDATE_INT);
}

// 彩金類別
if (isset($_GET['c'])) {
    // $prizecategories_get = preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '', urldecode($_GET['c']));
    $prizecategories_get =  filter_var($_GET['c'], FILTER_SANITIZE_STRING);
    // echo $prizecategories_get;
}

if (isset($_GET['status']) and filter_var($_GET['status'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
    $bonus_status_name = filter_var($_GET['status'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    if ($bonus_status_name == 'access') {
        $bonus_status = 1;
    } elseif ($bonus_status_name == 'deny') {
        $bonus_status = 0;
    } elseif ($bonus_status_name == 'cancel') {
        $bonus_status = 2;
    }
}


$bonus_status_name2ch=['1'=>$tr['Can receive'],'0'=>$tr['Cancel'],'2'=>$tr['time out']];

// 彩金領取期限
if (isset($_GET['t']) and validateDate($_GET['t'], 'Y-m-d H:i:s')) {
    $datetime = date('Y-m-d H:i:s', strtotime($_GET['t']) + 12 * 3600);
    $deadlinetime_ast = date('Y-m-d H:i:s', strtotime($_GET['t']));
}

// 取得查詢用的資料
$query_str_arr = [];
$query_str_arr['current_datetimepicker'] = gmdate('Y-m-d H:i:s.u', strtotime($current_datetimepicker . ' -04') + 8 * 3600) . '+08:00';

// 獎金分類名稱
if (isset($_GET['bonus_type']) and $_GET['bonus_type'] != '') {
    $query_str_arr['bonus_type'] = filter_var($_GET['bonus_type'], FILTER_SANITIZE_STRING);
    // $query_str_arr['bonus_type'] = preg_replace('/([^A-Za-z0-9\p{Han}\s-_@.])/ui', '', urldecode($_GET['bonus_type']));
} else {
    $query_str_arr['bonus_type'] = '';
}

// 獎金發放時間
if (isset($_GET['bons_givemoneytime']) and validateDate($_GET['bons_givemoneytime'], 'Y-m-d H:i:s')) {
    $query_str_arr['bons_givemoneytime'] = gmdate('Y-m-d H:i:s', strtotime($_GET['bons_givemoneytime'] . ' -04') + 8 * 3600) . '+08:00';
    $query_str_arr['bons_givemoneytime_ast'] = $_GET['bons_givemoneytime'];

    // db的givemoneytime
    // 存入db的時間，因有些資料存入的時間有毫秒，因此無法顯示
    $db_time = filter_var($_GET['db_time'], FILTER_SANITIZE_STRING,FILTER_VALIDATE_INT);
    $query_str_arr['db_time'] = substr($db_time,0,-3);

    if($query_str_arr['bons_givemoneytime_ast'] == $query_str_arr['db_time']){
        $query_str_arr['bons_givemoneytime_astime'] = $_GET['bons_givemoneytime'];
    }else{
        $query_str_arr['bons_givemoneytime_astime'] = $query_str_arr['db_time'];
    }

}else{
    $query_str_arr['bons_givemoneytime'] = '';
    $query_str_arr['bons_givemoneytime_ast'] = '';

    $query_str_arr['db_time'] = '';
}

// 獎金狀態
if (isset($_GET['bonus_status']) and $_GET['bonus_status'] != '') {
    $query_str_arr['bonus_status'] = (string)filter_var($_GET['bonus_status'], FILTER_VALIDATE_INT);
} else {
    $query_str_arr['bonus_status'] = '';
}
// 帳號
if (isset($_GET['member_account']) and $_GET['member_account'] != '') {
    $query_str_arr['member_account'] = filter_var($_GET['member_account'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    $query_str_arr['member_account'] = '';
}
// 獎金有效時間開始
// if (isset($_GET['bonus_validdatepicker_start']) and validateDate($_GET['bonus_validdatepicker_start'], 'Y-m-d')) {
//     $query_str_arr['bonus_validdatepicker_start'] = gmdate('Y-m-d H:i:s.u', strtotime($_GET['bonus_validdatepicker_start'] . '00:00:00 -04') + 8 * 3600) . '+08:00';
// } else{
//     $query_str_arr['bonus_validdatepicker_start'] = '';
// }
// // 獎金有效時間結束
// if (isset($_GET['bonus_validdatepicker_end']) and validateDate($_GET['bonus_validdatepicker_end'], 'Y-m-d')) {
//     $query_str_arr['bonus_validdatepicker_end'] = gmdate('Y-m-d H:i:s.u', strtotime($_GET['bonus_validdatepicker_end'] . '23:59:59 -04') + 8 * 3600) . '+08:00';
// } else {
//     $query_str_arr['bonus_validdatepicker_end'] = '';
// }

// -----------------------------------------------
// 20200513
// 獎金有效時間開始
if (isset($_GET['bonus_validdatepicker_start']) and validateDate($_GET['bonus_validdatepicker_start'], 'Y-m-d H:i')) {

    $query_str_arr['bonus_validdatepicker_start'] = gmdate('Y-m-d H:i:s.u', strtotime($_GET['bonus_validdatepicker_start'] . '-04') + 8 * 3600) . '+08:00';

} else{
    // 2020 03 12
    $query_str_arr['bonus_validdatepicker_start'] = $default_min_date;
}

// 獎金有效時間結束
if (isset($_GET['bonus_validdatepicker_end']) AND validateDate($_GET['bonus_validdatepicker_end'], 'Y-m-d H:i')) {

    $query_str_arr['bonus_validdatepicker_end'] = gmdate('Y-m-d H:i:s.u', strtotime($_GET['bonus_validdatepicker_end'] . '-04') + 8 * 3600) . '+08:00';

} else {
    // 2020 03 12
    $query_str_arr['bonus_validdatepicker_end'] = '';
}
// ------------------------------------------------

// 生成彩金分類表，所需要之參數
// if (isset($_GET['lottotype']) and $_GET['lottotype'] != '') {
//     $query_str_arr['lottotype'] = filter_var($_GET['lottotype'], FILTER_SANITIZE_STRING);
// } else {
//     $query_str_arr['lottotype'] = '';
// }


// --------------------------------
// 2020/03/09
if(isset($_GET['excel']) AND $_GET['excel'] != null){
	$excel_sql_array = (array)jwtdec('receivemoney',$_GET['excel']);
	$excelfilename = sha1($_GET['excel']);
}
// 彩票和非彩票
if(isset($_GET['tablet']) AND $_GET['tablet'] != null){
    $tablet = filter_var($_GET['tablet'], FILTER_SANITIZE_STRING);
}else{
    $tablet = '';
}
// --------------------------------


// 是否按下查詢鍵
if (isset($_POST['is_querybutton']) and $_POST['is_querybutton'] != '') {
    $is_querybutton= filter_var($_POST['is_querybutton'], FILTER_SANITIZE_STRING);
} else {
    $is_querybutton= '0';
}

// 選單頁，可領取暫停取消已領取彩票，型態過濾。查詢總表是否為彩票 islotto,nonlotto
if (isset($_POST['tab_type']) and $_POST['tab_type'] != '') {
    $tab_type= filter_var($_POST['tab_type'], FILTER_SANITIZE_STRING);
} else {
    $tab_type= '';
}

// 選單頁， pageSize
if (isset($_POST['pageSize']) and $_POST['pageSize'] != '') {
    $tab_pageSize= filter_var($_POST['pageSize'], FILTER_VALIDATE_INT);
} else {
    $tab_pageSize= 10;
}

// 選單頁， pageNumber
if (isset($_POST['pageNumber']) and $_POST['pageNumber'] != '') {
    $tab_pageNumber= filter_var($_POST['pageNumber'], FILTER_VALIDATE_INT);
} else {
    $tab_pageNumber= 1;
}


// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if (isset($_GET['length']) and $_GET['length'] != null) {
    $current_per_size = filter_var($_GET['length'], FILTER_VALIDATE_INT);
} else {
    $current_per_size = $page_config['datatables_pagelength'];
    //$current_per_size = 10;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if (isset($_GET['start']) and $_GET['start'] != null) {
    $current_page_no = filter_var($_GET['start'], FILTER_VALIDATE_INT);
} else {
    $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if (isset($_GET['_'])) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}


// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------
// var_dump($_GET);
// var_dump($_POST);
// die();

// ----------------------------------
// 動作為會員 action
// ----------------------------------
if ($action == 'query_receivemoney' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
// ----------------------------------------------------------------------------
    // 寫入反水發放欄位 , 紀錄反水這個會員已經付款
    // ----------------------------------------------------------------------------
    // 取得查詢條件
    $sql_str = sqlquery_str($query_str_arr);
    // var_dump($query_str_arr);die();
    // 處理 datatables 傳來的排序需求
    if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
        $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
        if($_GET['columns'][$_GET['order'][0]['column']]['data'] == 'status'){
            $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir.', receivedeadlinetime DESC';
        }else{
            $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
        }
      }else{ $sql_order = 'ORDER BY id ASC';}

    // 每頁顯示多少
    $page['per_size'] = $current_per_size;
    // 目前所在頁數
    $page['no'] = $current_page_no;


    // 檢查欄位
    $check_id_sql_tmp = create_sql(). $sql_str . ' ';
    // echo($check_id_sql_tmp);die();
    $userlist_count = runSQL($check_id_sql_tmp);
    // var_dump($userlist_count);die();

    $check_id_sql = $check_id_sql_tmp . $sql_order . ' OFFSET ' . $page['no'] . ' LIMIT ' . $page['per_size'] . ';';
    // echo $check_id_sql . "\n";die();

    $check_id_result = runSQLall($check_id_sql);
    // var_dump($check_id_result);die();
    // 所有紀錄數量
    $page['all_records'] = $userlist_count;


    // -----------------------------------------------
    // 組成下載xls加密字串
    if($userlist_count != 0){
        $dl_xls_querystr['current_datetimepicker']              = $query_str_arr['current_datetimepicker'];
        $dl_xls_querystr['bonus_type']                          = $query_str_arr['bonus_type'];
        $dl_xls_querystr['bons_givemoneytime_ast']              = $query_str_arr['bons_givemoneytime_ast'];
        $dl_xls_querystr['bonus_status']                        = $query_str_arr['bonus_status'];
        $dl_xls_querystr['member_account']                      = $query_str_arr['member_account'];
        $dl_xls_querystr['bonus_validdatepicker_start']         = $query_str_arr['bonus_validdatepicker_start'];
        $dl_xls_querystr['bonus_validdatepicker_end']           = $query_str_arr['bonus_validdatepicker_end'];
        $dl_xls_querystr['bons_givemoneytime']                  = $query_str_arr['bons_givemoneytime'];
        if(isset($query_str_arr['bons_givemoneytime_astime']) AND $query_str_arr['bons_givemoneytime_astime'] != ''){
            $dl_xls_querystr['bons_givemoneytime_astime']           = $query_str_arr['bons_givemoneytime_astime'];
        }
        $dl_csv_code = jwtenc('receivemoney', $dl_xls_querystr);
    }
    //------------------------------------------------

    if ($userlist_count >= 1) {
        for ($i = 1; $i <= $check_id_result[0]; $i++) {

            // 更多的詳細資訊
            $more_detail = $check_id_result[$i]->reconciliation_reference . ',' . $check_id_result[$i]->status_description . ',' . $check_id_result[$i]->member_ip . ',' . $check_id_result[$i]->member_fingerprinting;

            // 顯示的表格資料內容 ref: https://datatables.net/examples/ajax/objects.html
            $show_listrow_array[] = array(
                'id' => $check_id_result[$i]->id,
                'member_account' => $check_id_result[$i]->member_account,
                'gcash_balance' => $check_id_result[$i]->gcash_balance,
                'gtoken_balance' => $check_id_result[$i]->gtoken_balance,
                'givemoneytime' => $check_id_result[$i]->givemoneytime_fix,
                'receivedeadlinetime' => $check_id_result[$i]->receivedeadlinetime_fix,
                'summary' => preg_replace('/([\\\\])/ui', '', $check_id_result[$i]->summary),
                'receivetime' => $check_id_result[$i]->receivetime_fix,
                'prizecategories' => $check_id_result[$i]->prizecategories,
                'givemoney_member_account' => $check_id_result[$i]->givemoney_member_account,
                'last_modify_member_account' => $check_id_result[$i]->last_modify_member_account,
                'status' => status_helper($check_id_result[$i]),
                'moredetail' => $more_detail,
            );

            $output = array(
                "sEcho" => intval($secho),
                "iTotalRecords" => intval($page['per_size']),
                "iTotalDisplayRecords" => intval($userlist_count),
                "data" => $show_listrow_array,

                "download_url" => 'receivemoney_management_action.php?a=to_xls&excel='. $dl_csv_code
            );

        }

    } else {
        // NO member
        $output = array(
            "sEcho" => 0,
            "iTotalRecords" => 0,
            "iTotalDisplayRecords" => 0,
            "data" => '',
        );
    }
    echo json_encode($output);

} elseif ($action == 'query_summary' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
// -------------------------------------------------------------------------------------------
    $sql_str = sqlquery_str($query_str_arr);
    // var_dump($query_str_arr);die();
    $status_mapi18n=status_mapi18n();
    $summarytable_html =$modals= $logger='';

    if($is_querybutton=='0'){
        $status_ary=['0'=>'cancel','1'=>'can_receive','2'=>'timeout','3'=>'received','4'=>'expired'];
        $status_lotto_ary=['0'=>'lottosum_cancel','1'=>'lottosum_canreceive','2'=>'lottosum_timeout','3'=>'lottosum_received','4'=>'lottosum_expired'];

        if($tab_type=='nonlotto'){
            $menu_fun=$status_ary[$query_str_arr['bonus_status']];
            $ynlotto_sql=ynlotto_sql('0');
//             $query_namegivedate=<<<SQL
//             AND prizecategories like '%{$query_str_arr["bonus_type"]}%' AND givemoneytime = '{$query_str_arr["bons_givemoneytime"]}'
// SQL;
            $query_namegivedate=<<<SQL
                AND prizecategories like '%{$query_str_arr["bonus_type"]}%' AND givemoneytime = '{$query_str_arr["bons_givemoneytime_astime"]}'
SQL;
        }elseif($tab_type=='islotto'){
            $menu_fun=$status_lotto_ary[$query_str_arr['bonus_status']];
            $ynlotto_sql=ynlotto_sql('1');
            $query_namegivedate=<<<SQL
                AND prizecategories like '%{$query_str_arr["bonus_type"]}%'
            SQL;
        }else{
            $logger= '<script>alert("(x)不合法的測試，error code:202001091050");</script>';die();
        }

        $where=where_sql_switch($menu_fun,$query_str_arr);
        $sql_count = $ynlotto_sql['select'].$where['string'].$query_namegivedate.$ynlotto_sql['group'];
    }else{
        // 按下查詢鍵
        if($tab_type=='islotto'){
            //彩票彩金sql
            $sql_count = is_lotto($sql_str);
        }else{
            //非彩票彩金sql
            $sql_count = not_lotto($sql_str);
        }

    }

    $sql_count_result = runSQL($sql_count);
    if ($sql_count_result < 1) {
        $logger = $tr['No match was found'] . '！';
    }

    $tab_offset=($tab_pageSize * $tab_pageNumber)-$tab_pageSize;
    // var_dump($tab_pageNumber);//die();

    $sql = $sql_count.' OFFSET '.$tab_offset.' LIMIT '.$tab_pageSize;
    // echo($sql);die();
    // var_dump($query_str_arr['bonus_status']);
    // var_dump($where['status']);
    // var_dump($sql_result[$i]->status);
    //     die();
    $sql_result = runSQLall($sql);

    if($tab_type=='islotto'){
        for ($i = 1; $i <= $sql_result[0]; $i++) {
            $show_satatus=$bat_status='';
            if($query_str_arr['bonus_status'] == '4'){
                $show_satatus=$status_mapi18n[$query_str_arr['bonus_status']];
                $bat_status=$query_str_arr['bonus_status'];
            }elseif(isset($where['status'])){
                $show_satatus=$status_mapi18n[$where['status']];
                $bat_status=$where['status'];
            }else{
                $show_satatus=$status_mapi18n[$sql_result[$i]->status];
                $bat_status=$sql_result[$i]->status;
            }
            if($bat_status=='3'){
                $batch_processing='';
            }else{
                $batch_processing=<<<HTML
                     <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#category-modalR-{$i}">
                        {$tr['Batch processing']}
                    </button>
                HTML;
						}
            //table td 狀態顏色標示純UI 彩票類別
						if( $show_satatus == $tr['Can receive'] ){
							// 可領取
							$summarytable_html .= <<<HTML
									<tr class="status-success">
											<td class="text-left">{$sql_result[$i]->prizecategories}</td>
											<td class="text-center">{$sql_result[$i]->gcash_total}</td>
											<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
											<td class="text-center">{$sql_result[$i]->member_count}</td>
											<td class="text-center"><span class="status-info-sp"></span>{$show_satatus}</td>
											<td class="text-center">
											{$batch_processing}
											</td>
									</tr>
							HTML;
					}else if( $show_satatus == $tr['received'] ) {
							$summarytable_html .= <<<HTML
							<tr class="status-muted">
									<td class="text-left">{$sql_result[$i]->prizecategories}</td>
									<td class="text-center">{$sql_result[$i]->gcash_total}</td>
									<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
									<td class="text-center">{$sql_result[$i]->member_count}</td>
									<td class="text-center"><span class="status-muted-sp"></span>{$show_satatus}</td>
									<td class="text-center">
									{$batch_processing}
									</td>
							</tr>
							HTML;
					}else if( $show_satatus == $tr['expired'] ) {
							//已过期
							$summarytable_html .= <<<HTML
							<tr class="status-orange">
									<td class="text-left">{$sql_result[$i]->prizecategories}</td>
									<td class="text-center">{$sql_result[$i]->gcash_total}</td>
									<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
									<td class="text-center">{$sql_result[$i]->member_count}</td>
									<td class="text-center"><span class="status-orange-sp"></span>{$show_satatus}</td>
									<td class="text-center">
									{$batch_processing}
									</td>
							</tr>
							HTML;
					}else if( $show_satatus == $tr['time out'] ) {
							//暫停
							$summarytable_html .= <<<HTML
							<tr class="status-warning">
									<td class="text-left">{$sql_result[$i]->prizecategories}</td>
									<td class="text-center">{$sql_result[$i]->gcash_total}</td>
									<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
									<td class="text-center">{$sql_result[$i]->member_count}</td>
									<td class="text-center"><span class="status-warning-sp"></span>{$show_satatus}</td>
									<td class="text-center">
									{$batch_processing}
									</td>
							</tr>
							HTML;
					}else if( $show_satatus == $tr['Cancel'] ) {
							$summarytable_html .= <<<HTML
							<tr  class="status-pink">
									<td class="text-left">{$sql_result[$i]->prizecategories}</td>
									<td class="text-center">{$sql_result[$i]->gcash_total}</td>
									<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
									<td class="text-center">{$sql_result[$i]->member_count}</td>
									<td class="text-center"><span class="status-pink-sp"></span>{$show_satatus}</td>
									<td class="text-center">
									{$batch_processing}
									</td>
							</tr>
							HTML;
					}else{
					//沒有狀態
					$summarytable_html .= <<<HTML
					<tr>
						<td class="text-left">{$sql_result[$i]->prizecategories}</td>
						<td class="text-center">{$sql_result[$i]->gcash_total}</td>
						<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
						<td class="text-center">{$sql_result[$i]->member_count}</td>
						<td class="text-center">{$show_satatus}</td>
						<td class="text-center">
						{$batch_processing}
						</td>
					</tr>
					HTML;
					}

            $modals .= <<<HTML
                <div class="modal fade" id="category-modalR-{$i}" tabindex="-1" role="dialog" aria-labelledby="category-modalR-{$i}-label" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="category-modalR-{$i}-label">{$tr['Batch processing']}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <button class="btn btn-success btn-xs" onclick="bonus_batchedit({action:'allenable',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}'});">{$tr['All set to']}"{$tr['Can receive']}"</button>
                                <button class="btn btn-warning btn-xs" onclick="bonus_batchedit({action:'allstop',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}'});">{$tr['All set to']}"{$tr['time out']}"</button>
                                <button class="btn btn-danger btn-xs" onclick="bonus_batchedit({action:'allcancel',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}'});">{$tr['All set to']}"{$tr['Cancel']}"</button>
                                <button class="btn btn-info btn-xs" onclick="bonus_batchedit({action:'allstop2en',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}'});">{$tr['all']} "{$tr['time out']}" {$tr['set to']} "{$tr['Can receive']}"</button>
                                <div class="input-group mt-3">
                                    <input type="text" id="id_rdltR_{$i}" name="set_deadline" class="form-control set_deadline" placeholder="{$tr['Please set the date']}" value="">
                                    <div class="input-group-append">
                                        <span class="btn btn-primary btn-sm" onclick="bonus_batchedit_d({action:'update_receivedeadlinetime',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}',dateinput:'id_rdltR_{$i}'});">{$tr['Set bonus deadline']}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
HTML;
        }
    }elseif($tab_type=='nonlotto'){
        for ($i = 1; $i <= $sql_result[0]; $i++) {
            $show_satatus='';
            if($query_str_arr['bonus_status'] == '4'){
                $show_satatus=$status_mapi18n[$query_str_arr['bonus_status']];
                $bat_status=$query_str_arr['bonus_status'];
            }elseif(isset($where['status'])){
                $show_satatus=$status_mapi18n[$where['status']];
                $bat_status=$where['status'];
            }else{
                $show_satatus=$status_mapi18n[$sql_result[$i]->status];
                $bat_status=$sql_result[$i]->status;
            }
            if($bat_status=='3'){
                $batch_processing='';
            }else{
                $batch_processing=<<<HTML
                     <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#category-modal-{$i}">
                        {$tr['Batch processing']}
                    </button>
                HTML;
						}
						
            //table td 狀態顏色標示純UI 非彩票類別
            if( $show_satatus == $tr['Can receive'] ){
							$summarytable_html .= <<<HTML
									<tr class="status-success">
											<td class="text-left">{$sql_result[$i]->prizecategories}</td>
											<td class="text-center">{$sql_result[$i]->givemoneytime}</td>
											<td class="text-center">{$sql_result[$i]->gcash_total}</td>
											<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
											<td class="text-center">{$sql_result[$i]->member_count}</td>
											<td class="text-center"><span class="status-info-sp"></span>{$show_satatus}</td>
											<td class="text-center">
												{$batch_processing}
											</td>
									</tr>
							HTML;
            }else if( $show_satatus == $tr['received'] ){
                //非彩票類別UI 已领取
								$summarytable_html .= <<<HTML
                <tr class="status-muted">
                    <td class="text-left">{$sql_result[$i]->prizecategories}</td>
                    <td class="text-center">{$sql_result[$i]->givemoneytime}</td>
                    <td class="text-center">{$sql_result[$i]->gcash_total}</td>
                    <td class="text-center">{$sql_result[$i]->gtoken_total}</td>
                    <td class="text-center">{$sql_result[$i]->member_count}</td>
                    <td class="text-center"><span class="status-muted-sp"></span>{$show_satatus}</td>
                    <td class="text-center">
                    {$batch_processing}
                    </td>
                </tr>
            HTML;
            }else if( $show_satatus == $tr['expired'] ){
							//非彩票類別UI 已过期
							$summarytable_html .= <<<HTML
                <tr class="status-orange">
                    <td class="text-left">{$sql_result[$i]->prizecategories}</td>
                    <td class="text-center">{$sql_result[$i]->givemoneytime}</td>
                    <td class="text-center">{$sql_result[$i]->gcash_total}</td>
                    <td class="text-center">{$sql_result[$i]->gtoken_total}</td>
                    <td class="text-center">{$sql_result[$i]->member_count}</td>
                    <td class="text-center"><span class="status-orange-sp"></span>{$show_satatus}</td>
                    <td class="text-center">
                    {$batch_processing}
                    </td>
                </tr>
            HTML;
						}else if( $show_satatus == $tr['time out'] ){
							//非彩票類別UI 暂停
							$summarytable_html .= <<<HTML
							<tr class="status-warning">
									<td class="text-left">{$sql_result[$i]->prizecategories}</td>
									<td class="text-center">{$sql_result[$i]->givemoneytime}</td>
									<td class="text-center">{$sql_result[$i]->gcash_total}</td>
									<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
									<td class="text-center">{$sql_result[$i]->member_count}</td>
									<td class="text-center"><span class="status-warning-sp"></span>{$show_satatus}</td>
									<td class="text-center">
									{$batch_processing}
									</td>
							</tr>
						HTML;
						}else if( $show_satatus == $tr['Cancel'] ){
							//非彩票類別UI 取消
							$summarytable_html .= <<<HTML
							<tr class="status-pink">
									<td class="text-left">{$sql_result[$i]->prizecategories}</td>
									<td class="text-center">{$sql_result[$i]->givemoneytime}</td>
									<td class="text-center">{$sql_result[$i]->gcash_total}</td>
									<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
									<td class="text-center">{$sql_result[$i]->member_count}</td>
									<td class="text-center"><span class="status-pink-sp"></span>{$show_satatus}</td>
									<td class="text-center">
									{$batch_processing}
									</td>
							</tr>
						HTML;
					}else{
						//非彩票類別UI 無顏色標籤
						$summarytable_html .= <<<HTML
						<tr>
								<td class="text-left">{$sql_result[$i]->prizecategories}</td>
								<td class="text-center">{$sql_result[$i]->givemoneytime}</td>
								<td class="text-center">{$sql_result[$i]->gcash_total}</td>
								<td class="text-center">{$sql_result[$i]->gtoken_total}</td>
								<td class="text-center">{$sql_result[$i]->member_count}</td>
								<td class="text-center">{$show_satatus}</td>
								<td class="text-center">
								{$batch_processing}
								</td>
						</tr>
						HTML;
					}

            $modals .= <<<HTML
                <div class="modal fade" id="category-modal-{$i}" tabindex="-1" role="dialog" aria-labelledby="category-modal-{$i}-label" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="category-modal-{$i}-label">{$tr['Batch processing']}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <button class="btn btn-success btn-xs" onclick="bonus_batchedit({action:'allenable',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}',givemoneytime:'{$sql_result[$i]->givemoneytime}'});">{$tr['All set to']}"{$tr['Can receive']}"</button>
                                <button class="btn btn-warning btn-xs" onclick="bonus_batchedit({action:'allstop',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}',givemoneytime:'{$sql_result[$i]->givemoneytime}'});">{$tr['All set to']}"{$tr['time out']}"</button>
                                <button class="btn btn-danger btn-xs" onclick="bonus_batchedit({action:'allcancel',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}',givemoneytime:'{$sql_result[$i]->givemoneytime}'});">{$tr['All set to']}"{$tr['Cancel']}"</button>
                                <button class="btn btn-info btn-xs" onclick="bonus_batchedit({action:'allstop2en',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}',givemoneytime:'{$sql_result[$i]->givemoneytime}'});">{$tr['all']} "{$tr['time out']}" {$tr['set to']} "{$tr['Can receive']}"</button>
                                <div class="input-group mt-3">
                                    <input type="text" id="id_rdlt_{$i}" name="set_deadline" class="form-control set_deadline" placeholder="{$tr['Please set the date']}" value="">
                                    <div class="input-group-append">
                                        <span class="btn btn-primary btn-sm" onclick="bonus_batchedit_d({action:'update_receivedeadlinetime',bonus_name:'{$sql_result[$i]->prizecategories}',querybuton:'{$is_querybutton}',tab_type:'{$tab_type}',bat_status:'{$bat_status}',givemoneytime:'{$sql_result[$i]->givemoneytime}',dateinput:'id_rdlt_{$i}'})">{$tr['Set bonus deadline']}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
HTML;
        }
}
    // -------------------------------------------
    // 2020-03-04
    // 組成下載xls加密字串
    $dl_xls_querystr['current_datetimepicker']              = $query_str_arr['current_datetimepicker'];
    $dl_xls_querystr['bonus_type']                          = $query_str_arr['bonus_type'];
    $dl_xls_querystr['bons_givemoneytime_ast']              = $query_str_arr['bons_givemoneytime_ast'];
    $dl_xls_querystr['bonus_status']                        = $query_str_arr['bonus_status'];
    $dl_xls_querystr['member_account']                      = $query_str_arr['member_account'];
    $dl_xls_querystr['bonus_validdatepicker_start']         = $query_str_arr['bonus_validdatepicker_start'];
    $dl_xls_querystr['bonus_validdatepicker_end']           = $query_str_arr['bonus_validdatepicker_end'];
    $dl_xls_querystr['bons_givemoneytime']                  = $query_str_arr['bons_givemoneytime'];
    if(isset($query_str_arr['bons_givemoneytime_astime']) AND $query_str_arr['bons_givemoneytime_astime'] != ''){
        $dl_xls_querystr['bons_givemoneytime_astime']           = $query_str_arr['bons_givemoneytime_astime'];
    }

    $dl_csv_code = jwtenc('receivemoney', $dl_xls_querystr);
    // -------------------------------------------

    $current_date_time = gmdate('Y-m-d H:i:s', time()+-4 * 3600+24*3600);
    $show_setdeadline_datepick=<<<HTML
        <script>
            $("input[name='set_deadline']").datetimepicker({
                showButtonPanel: true,
                formatTime: "H:i",
                format: "Y-m-d H:i:s",
                changeMonth: true,
                changeYear: true,
                minDate:"{$current_date_time}",
                step:3600
            });
        </script>
HTML;

    // 開始準備生成分頁工具
    $total_page = ceil($sql_count_result / $tab_pageSize);

    $page_tool=create_page($total_page,$tab_pageSize,$tab_pageNumber,$tab_type,$is_querybutton);

    $output = array('source' => $summarytable_html,
                'errorlog' => $logger,
                'modals' => $modals.$show_setdeadline_datepick,
                "page_tool" => $page_tool,

                "download_url" => 'receivemoney_management_action.php?a=to_xls&excel='.$dl_csv_code.'&tablet='.$tab_type.'&query='.$is_querybutton,
            );

    header('Content-Type: application/json');
    echo json_encode($output);
    die();

// -------------------------------------------------------------------------------------------
} elseif ($action == 'bonus_batchedit' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // -----------------------------------------------------------------------
    // 彩金領取條件批次設定
    // -----------------------------------------------------------------------
    // 批次領取彩金 islotto,nonlotto
    if (isset($_GET['tab_type']) and $_GET['tab_type'] != '') {
        $tab_type= filter_var($_GET['tab_type'], FILTER_SANITIZE_STRING);
    } else {
        $tab_type= '';
    }

    if (isset($prizecategories_get)) {
        // 檢查傳入的類別值是否存在
        $chk_sql = 'SELECT prizecategories FROM root_receivemoney GROUP BY prizecategories;';
        $chk_result = runSQLall($chk_sql);
        if ($chk_result[0] >= 1) {
            for ($i = 1; $i <= $chk_result[0]; $i++) {
                if (strval($prizecategories_get) == strval($chk_result[$i]->prizecategories)) {
                    $prizecategories = $chk_result[$i]->prizecategories;
                }
            }
        }
        // confirm select bonus is
            $select_bonusid=<<<SQL
                SELECT id
                        -- ,member_account,
                        -- prizecategories,
                        -- to_char((givemoneytime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS' ) as givemoneytime_fix,
                        -- to_char((receivetime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivetime_fix,
                        -- to_char((receivedeadlinetime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivedeadlinetime_fix
                FROM root_receivemoney
                WHERE prizecategories ='{$prizecategories}'
                AND
SQL;

            if($tab_type=='nonlotto'){
                $select_bonusid.=<<<SQL
                    givemoneytime='{$query_str_arr["bons_givemoneytime"]}' AND
                SQL;
            }

            if ($query_str_arr['bonus_status'] == '3') {
                $select_bonusid .= 'receivetime IS NOT NULL';
            } elseif ($query_str_arr['bonus_status'] == '4') {
                $select_bonusid .= '(receivedeadlinetime < now() AND receivetime IS NULL)';
            } else {
                $select_bonusid .= '(status = \'' . $query_str_arr['bonus_status'] . '\' AND receivetime IS NULL)';
                // $select_bonusid .= '(status = \'' . $query_str_arr['bonus_status'] . '\' AND receivedeadlinetime >= now() AND receivetime IS NULL)';
            }
            $select_bonusid_result = runSQLall($select_bonusid);

            $sql_id_ary=[];
            $sql_id_in='';
            if($select_bonusid_result[0]>0){
                for ($i = 1; $i <= $select_bonusid_result[0]; $i++) {
                    $sql_id_ary[]=$select_bonusid_result[$i]->id;
                }
                $sql_id_in=' WHERE ID IN (\'' . implode("','", $sql_id_ary) . '\')';
            }else{
                echo ($tr['No such information'].',error:202001161658.');
                die();
            }
    }


    // var_dump($prizecategories);die();
    if (isset($prizecategories)) {
        if ($action2 == 'allenable') {
            $update_sql = 'UPDATE root_receivemoney SET updatetime = now(), status = \'1\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\''.$sql_id_in;

            $msg= $_SESSION['agent']->account . ' 点选批次处理，全部设为："' . $bonus_status_name2ch['1'] . '"。彩金类别：' . $prizecategories. '。发放日期：'.($query_str_arr['bons_givemoneytime_ast']==''? '未设定':$query_str_arr['bons_givemoneytime_ast']).'。';//客服

        } elseif ($action2 == 'allstop') {
            $update_sql = 'UPDATE root_receivemoney SET updatetime = now(), status = \'2\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\''.$sql_id_in;

            $msg = $_SESSION['agent']->account . ' 点选批次处理，全部设为："' . $bonus_status_name2ch['2'] . '"。彩金类别：' . $prizecategories. '。发放日期：' . ($query_str_arr['bons_givemoneytime_ast'] ==''? '未设定':$query_str_arr['bons_givemoneytime_ast']).'。'; //客服

        } elseif ($action2 == 'allcancel') {
            $update_sql = 'UPDATE root_receivemoney SET updatetime = now(), status = \'0\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\''.$sql_id_in;

            $msg = $_SESSION['agent']->account . ' 点选批次处理，全部设为："' . $bonus_status_name2ch['0'] . '"。彩金类别：' . $prizecategories. '。发放日期：' . ($query_str_arr['bons_givemoneytime_ast'] ==''? '未设定':$query_str_arr['bons_givemoneytime_ast']).'。'; //客服

        } elseif ($action2 == 'allstop2en') {
            $update_sql = 'UPDATE root_receivemoney SET updatetime = now(), status = \'1\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\''.$sql_id_in.' AND status = \'2\'';
            $msg = $_SESSION['agent']->account . ' 点选批次处理，全部"暂停"设为："' . $bonus_status_name2ch['1'] . '"。彩金类别：' . $prizecategories. '。发放日期：' . ($query_str_arr['bons_givemoneytime_ast'] ==''? '未设定':$query_str_arr['bons_givemoneytime_ast']).'。'; //客服

        } elseif ($action2 == 'update_receivedeadlinetime' and isset($datetime)) {
            $update_sql = 'UPDATE root_receivemoney SET updatetime = now(), receivedeadlinetime = \'' . $datetime . '\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\' '.$sql_id_in.';';

            $msg = $_SESSION['agent']->account . ' 点选批次处理，设定奖金截止日期："' . $deadlinetime_ast. '"。彩金类别：' . $prizecategories. '。发放日期：' . ($query_str_arr['bons_givemoneytime_ast'] ==''? '未设定':$query_str_arr['bons_givemoneytime_ast']).'。'; //客服
        }

        // 依照彩金類別或[發放日期]是否存在，寫入memberlog
        $msg_log     = $msg; //RD
        $sub_service = "payout";
        memberlogtodb($_SESSION['agent']->account, "marketing", "notice", $msg, "彩金类别：" . $prizecategories . "。", "$msg_log", "b", $sub_service);

    } elseif ($action2 == 'checked' and isset($bonus_status) and isset($bonus_id_arr)) {
        $id_arr = implode('\',\'', filter_var_array($bonus_id_arr, FILTER_VALIDATE_INT));
        $update_sql = 'UPDATE root_receivemoney SET updatetime = now(), status = \'' . $bonus_status . '\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\'  WHERE receivetime is null AND id IN (\'' . $id_arr . '\');';

        // 点选设定彩金状态，需写入memberlog
        $id_arr_sq = implode('"，"', filter_var_array($bonus_id_arr, FILTER_VALIDATE_INT));
        $msg         = $_SESSION['agent']->account . ' 点选奖金编号：("'.$id_arr_sq.'")，设定状态：'.$bonus_status_name2ch[$bonus_status] . '。'; //客服
        $msg_log     = $msg; //RD
        $sub_service = "payout";
        memberlogtodb($_SESSION['agent']->account, "marketing", "notice", $msg, "彩金编号：(\"".$id_arr_sq."\")。", "$msg_log", "b", $sub_service);

    } else {
        $logger = '(401)' . $tr['Data error'] . '！'; //$tr['Data error'] = '資料錯誤';
    }

    if (isset($update_sql)) {
        // echo $update_sql;die();
        $update_result = runSQLall($update_sql);
        // var_dump($update_result);die();
        if ($update_result[0] >= 1) { //$tr['Data has been successfully updated'] = '資料已成功更新';
            $logger = $tr['Data has been successfully updated'] . '！';
        } else { //$tr['Data update failed'] = '資料更新失敗';
            $logger = $tr['Data update failed'] . '！';
        }
    }

    echo $logger;
} elseif ($action == 'bonus_edit' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R' and isset($bonus_id)) {
    // -----------------------------------------------------------------------
    // 個別彩金詳細設定 - 更新
    // -----------------------------------------------------------------------

    // 驗證將更新的資料
    if (isset($_POST['status']) and $_POST['status'] <= 2) {
        $bonus['status'] = filter_var($_POST['status'], FILTER_VALIDATE_INT);
        if (!isset($bonus['status'])) { //$tr['Data error'] = '資料錯誤';
            $logger = '(8011)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(801)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['summary'])) {
        // $bonus['summary'] = escape_specialcharator($_POST['summary']);
        $bonus['summary'] = $_POST['summary'];
    } else { //$tr['Data error'] = '資料錯誤';
        $logger = '(802)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['gcash'])) {
        $bonus['gcash'] = filter_var($_POST['gcash'], FILTER_VALIDATE_FLOAT);
        if (!isset($bonus['gcash'])) { //$tr['Data error'] = '資料錯誤';
            $logger = '(8031)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(803)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['gtoken'])) {
        $bonus['gtoken'] = filter_var($_POST['gtoken'], FILTER_VALIDATE_FLOAT);
        if (!isset($bonus['gtoken'])) {
            $logger = '(8041)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(804)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['receivedeadline']) and validateDate($_POST['receivedeadline'], 'Y-m-d H:i:s')) {
        $bonus['receivedeadline'] = date('Y-m-d H:i:s', strtotime($_POST['receivedeadline']) + 12 * 3600);
    } else {
        $logger = '(805)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['auditmode']) and filter_var($_POST['auditmode'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
        $bonus['auditmode'] = filter_var($_POST['auditmode'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    } else {
        $logger = '(806)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['auditmodeamount'])) {
        $bonus['auditmodeamount'] = filter_var($_POST['auditmodeamount'], FILTER_VALIDATE_FLOAT);
        if (!isset($bonus['auditmodeamount'])) {
            $logger = '(8071)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(807)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['note'])) {
        //$bonus['note'] = filter_var(urldecode($_POST['note']), FILTER_SANITIZE_ENCODED, FILTER_FLAG_ENCODE_HIGH);
        $bonus['note'] = escape_specialcharator($_POST['note']);
    } else {
        $logger = '(808)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }

    //var_dump($bonus);

    // 檢查是否有該筆資料
    $chk_sql = 'SELECT * FROM root_receivemoney WHERE id=\'' . $bonus_id . '\';';
    $chk_result = runSQLall($chk_sql);

    if ($chk_result[0] == 1 and $chk_result[1]->receivetime == null) {

        // 更新用SQL
        $update_sql = 'UPDATE root_receivemoney SET status = \'' . $bonus['status'] . '\',
                                                gcash_balance = \'' . $bonus['gcash'] . '\',
                                                gtoken_balance = \'' . $bonus['gtoken'] . '\',
                                                receivedeadlinetime = \'' . $bonus['receivedeadline'] . '\',
                                                auditmode = \'' . $bonus['auditmode'] . '\',
                                                auditmodeamount = \'' . $bonus['auditmodeamount'] . '\',
                                                summary = \'' . $bonus['summary'] . '\',
                                                system_note = \'' . $bonus['note'] . '\',
                                                last_modify_member_account = \'' . $_SESSION['agent']->account . '\',
                                                updatetime = now() WHERE id=\'' . $bonus_id . '\';';
        //echo $update_sql;
        //$update_result[0] = 1;
        $update_result = runSQLall($update_sql);
        if ($update_result[0] == 1) { //$tr['Data has been successfully updated'] = '資料已成功更新';
            $logger = '' . $tr['Data has been successfully updated'] . '！';
        } else { //$tr['Data update failed'] = '資料更新失敗';
            $logger = '' . $tr['Data update failed'] . '！';
        }
    } elseif ($chk_result[0] == 1 and $chk_result[1]->receivetime != null) {

        // 更新用SQL
        $update_sql = 'UPDATE root_receivemoney SET system_note = \'' . $bonus['note'] . '\', last_modify_member_account = \'' . $_SESSION['agent']->account . '\' WHERE id=\'' . $bonus_id . '\';';
        //echo $update_sql;
        //$update_result[0] = 1;
        $update_result = runSQLall($update_sql);
        if ($update_result[0] == 1) { //$tr['Description of the source of the prize money'] = '彩金來源說明'; $tr['Data has been successfully updated'] = '資料已成功更新';
            $logger = $tr['Description of the source of the prize money'] . '  ' . $tr['Data has been successfully updated'] . '！';
        } else { //$tr['Data update failed'] = '資料更新失敗';
            $logger = $tr['Description of the source of the prize money'] . ' ' . $tr['Data update failed'] . '！';
        } //$tr['This bonus has been collected'] = '此筆彩金已被領取';
        $logger = $tr['This bonus has been collected'] . '！' . $logger;
    } else { //$tr['No such information'] = '無此資料';
        $logger = '(800)' . $tr['No such information'] . '！';
    }

    echo $logger;
} elseif ($action == 'bonus_add' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // -----------------------------------------------------------------------
    // 個別彩金詳細設定 - 新增
    // -----------------------------------------------------------------------

    // var_dump($_POST);var_dump($_GET);die();
    // 驗證將更新的資料
    if (isset($_POST['status']) and $_POST['status'] <= 2) {
        $bonus['status'] = filter_var($_POST['status'], FILTER_VALIDATE_INT);
        if (!isset($bonus['status'])) { //$tr['Data error'] = '資料錯誤';
            $logger = '(8011)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(801)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['summary'])) {
        //$bonus['summary'] = addslashes(urldecode($_POST['summary']));
        // $bonus['summary'] = escape_specialcharator($_POST['summary']);
        $bonus['summary'] = $_POST['summary'];
    } else {
        $logger = '(802)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['gcash'])) {
        $bonus['gcash'] = filter_var($_POST['gcash'], FILTER_VALIDATE_FLOAT);
        if (!isset($bonus['gcash'])) {
            $logger = '(8031)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(803)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['gtoken'])) {
        $bonus['gtoken'] = filter_var($_POST['gtoken'], FILTER_VALIDATE_FLOAT);
        if (!isset($bonus['gtoken'])) {
            $logger = '(8041)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(804)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['receivedeadline']) and validateDate($_POST['receivedeadline'], 'Y-m-d H:i:s')) {
        $bonus['receivedeadline'] = date('Y-m-d H:i:s', strtotime($_POST['receivedeadline']) + 12 * 3600);
    } else {
        $logger = '(805)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['auditmode']) and filter_var($_POST['auditmode'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
        $bonus['auditmode'] = filter_var($_POST['auditmode'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    } else {
        $logger = '(806)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['auditmodeamount'])) {
        $bonus['auditmodeamount'] = filter_var($_POST['auditmodeamount'], FILTER_VALIDATE_FLOAT);
        if (!isset($bonus['auditmodeamount'])) {
            $logger = '(8071)' . $tr['Data error'] . '！';
            echo $logger;
            die($logger);
        }
    } else {
        $logger = '(807)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['note'])) {
        //$bonus['note'] = filter_var(urldecode($_POST['note']), FILTER_SANITIZE_ENCODED, FILTER_FLAG_ENCODE_HIGH);
        $bonus['note'] = escape_specialcharator($_POST['note']);
    } else {
        $logger = '(808)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['account']) and filter_var($_POST['account'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
        $bonus['account'] = filter_var($_POST['account'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    } else {
        $logger = '(809)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['givemoneytime']) and validateDate($_POST['givemoneytime'], 'Y-m-d H:i:s')) {
        $bonus['givemoneytime'] = date('Y-m-d H:i:s', strtotime($_POST['givemoneytime']) + 12 * 3600);
    } else {
        $logger = '(810)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }
    if (isset($_POST['prizecategories'])) {
        // $bonus['prizecategories'] = preg_replace('/([^A-Za-z0-9\p{Han}\s-_@.])/ui', '', urldecode($_POST['prizecategories']));20191217改成下列判斷，否則會出錯
        $bonus['prizecategories'] = preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '', urldecode($_POST['prizecategories']));
    } else {
        $logger = '(811)' . $tr['Data error'] . '！';
        echo $logger;
        die($logger);
    }

    //var_dump($bonus);

    // 檢查是否有該筆資料
    $chk_sql = 'SELECT id FROM root_member WHERE account=\'' . $bonus['account'] . '\';';
    $chk_result = runSQLall($chk_sql);
    //var_dump($chk_result);
    // 檢查是否有該筆資料
    $chkreceivemoney_sql = 'SELECT id FROM root_receivemoney WHERE status = \'' . $bonus['status'] . '\' AND
                                              gcash_balance = \'' . $bonus['gcash'] . '\' AND
                                              gtoken_balance = \'' . $bonus['gtoken'] . '\' AND
                                              receivedeadlinetime = \'' . $bonus['receivedeadline'] . '\' AND
                                              auditmode = \'' . $bonus['auditmode'] . '\' AND
                                              auditmodeamount = \'' . $bonus['auditmodeamount'] . '\' AND
                                              summary = \'' . $bonus['summary'] . '\' AND
                                              system_note = \'' . $bonus['note'] . '\' AND
                                              member_account = \'' . $bonus['account'] . '\' AND
                                              prizecategories = \'' . $bonus['prizecategories'] . '\' AND
                                              givemoneytime = \'' . $bonus['givemoneytime'] . '\';';
    //echo $chkreceivemoney_sql;
    $chkreceivemoney_result = runSQL($chkreceivemoney_sql);

    if ($chk_result[0] == 1 and $chkreceivemoney_result == 0) {
        $bonus['id'] = $chk_result[1]->id;

        // 新增用SQL $tr['Reconciliation information'] = '對帳資訊';
        $insert_sql = "
    INSERT INTO root_receivemoney (status,gcash_balance,gtoken_balance,receivedeadlinetime,auditmode,auditmodeamount,summary,system_note,member_account,prizecategories,givemoneytime,member_id,updatetime,transaction_category,givemoney_member_account,reconciliation_reference,last_modify_member_account)
      VALUES ('{$bonus['status']}', '{$bonus['gcash']}', '{$bonus['gtoken']}', '{$bonus['receivedeadline']}', '{$bonus['auditmode']}', '{$bonus['auditmodeamount']}',
       '{$bonus['summary']}', '{$bonus['note']}', '{$bonus['account']}', '{$bonus['prizecategories']}', '{$bonus['givemoneytime']}', '{$bonus['id']}', now(),'tokenfavorable',
       '{$_SESSION['agent']->account}', '{$tr['Reconciliation information']}對帳資訊', '{$_SESSION['agent']->account}');
    ";
        //echo $insert_sql;
        //$update_result[0] = 1;
        $insert_result = runSQLall($insert_sql);

        // 檢查是否有該筆資料
        $chkinsert_sql = 'SELECT id FROM root_receivemoney WHERE status = \'' . $bonus['status'] . '\' AND
                                                gcash_balance = \'' . $bonus['gcash'] . '\' AND
                                                gtoken_balance = \'' . $bonus['gtoken'] . '\' AND
                                                receivedeadlinetime = \'' . $bonus['receivedeadline'] . '\' AND
                                                auditmode = \'' . $bonus['auditmode'] . '\' AND
                                                auditmodeamount = \'' . $bonus['auditmodeamount'] . '\' AND
                                                summary = \'' . $bonus['summary'] . '\' AND
                                                system_note = \'' . $bonus['note'] . '\' AND
                                                member_account = \'' . $bonus['account'] . '\' AND
                                                prizecategories = \'' . $bonus['prizecategories'] . '\' AND
                                                givemoneytime = \'' . $bonus['givemoneytime'] . '\' AND
                                                member_id = \'' . $bonus['id'] . '\';';
        $chkinsert_result = runSQLall($chkinsert_sql);
        if ($insert_result[0] == 1 and $chkinsert_result[0] == 1) {
            $logger = $tr['The bonus information has been successfully added']; //$tr['The bonus information has been successfully added'] = '彩金資料已成功新增';
            $newid = $chkinsert_result[1]->id;
        } else {
            $logger = '' . $tr['New bonus information add failed'] . '！'; //$tr['New bonus information add failed'] = '彩金資料新增失敗';
            $newid = '';
        }
    } elseif ($chk_result[0] == 0) {
        $logger = '(800)' . $tr['No account'] . '！'; //$tr['No account'] = '無此帳號';
        $newid = '';
    } elseif ($chkreceivemoney_result == 1) {
        $logger = '(888)' . $tr['This bonus information has been available'] . '！'; //$tr['This bonus information has been available'] = '已有此筆彩金資料';
        $newid = '';
    }

    $return_arr = array('logger' => $logger, 'id' => $newid);
    echo json_encode($return_arr);

} elseif ($action == 'batched_receive' and isset($_GET['prizecategories']) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    if (isset($_GET['prizecategories']) and $_GET['prizecategories'] != '') {
        $prizecategories = filter_var($_GET['prizecategories'], FILTER_SANITIZE_STRING);
    }else{
        die($tr['bonus'].$tr['Data error'].'，error:202001141415。');
    }
    // 批次領取彩金 islotto,nonlotto
    if (isset($_GET['tab_type']) and $_GET['tab_type'] != '') {
        $tab_type= filter_var($_GET['tab_type'], FILTER_SANITIZE_STRING);
    } else {
        $tab_type= '';
    }

    // var_dump($tab_type=='nonlotto')
    $file_key = sha1('receivemoney_batched' . $prizecategories);
    $logfile_name = dirname(__FILE__) . '/tmp_dl/receivemoney_batched_' . $file_key . '.tmp';

    if (file_exists($logfile_name)) {
        die('批次领取中...請勿重覆操作');
    } else {
        if($tab_type=='nonlotto'){
            $search_array = [
                'prizecategories'            => $prizecategories,
                'last_modify_member_account' => $_SESSION['agent']->account,
                'bons_givemoneytime'         => $query_str_arr['bons_givemoneytime'],
                'tab_type'                   => $tab_type,
            ];
        }else{
            $search_array = [
                'prizecategories'            => $prizecategories,
                'last_modify_member_account' => $_SESSION['agent']->account,
                'tab_type'                   => $tab_type,
            ];
        }

        // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
        $search_token = jwtenc('receivemoney', $search_array);
        // var_dump($search_array);die();
        $command = $config['PHPCLI'].' receivemoney_cmd.php run ' . $search_token . ' web > ' . $logfile_name . ' &';
        // echo nl2br($command);die();

        // dispatch command and show loading view
        dispatch_proccessing(
            $command,
            '批次领取中...',
            $_SERVER['PHP_SELF'] . '?a=batched_receive_reload&k=' . $file_key,
            $logfile_name
        );
    }

} elseif ($action == 'batched_receive_reload' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/receivemoney_batched_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        echo file_get_contents($reload_file);
    } else {
        die('(x)不合法的測試');
    }
} elseif ($action == 'batched_receive_del' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/receivemoney_batched_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        unlink($reload_file);

        // memberlogtodb 批次領取彩金成功，按關閉視窗時寫入log
        $msg         = $_SESSION['agent']->account.'按批次领取，发放时间:'.$query_str_arr['bons_givemoneytime_ast'].'，名称：'.$query_str_arr['bonus_type'].'，之彩金。';//客服
        $msg_log     = $msg;//RD
        $sub_service = 'payout';
        memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', "$msg", $query_str_arr['bonus_type'].','.$query_str_arr['bons_givemoneytime_ast'], "$msg_log", 'b', $sub_service);

    } else {
        die('(x)不合法的測試');
    }
} elseif ($action == 'tab_prmt' and $tab_type!=''  and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // 分頁
    // var_dump($action);die();
    $tab_offset=($tab_pageSize * $tab_pageNumber)-$tab_pageSize;
    // var_dump($tab_offset,$tab_pageSize,$tab_pageNumber);die();

    $data=execute_menu_sql($tab_offset,$tab_pageSize,$tab_type,$query_str_arr);
    // var_dump($data);die();

    // 開始準備生成分頁工具
    $total_page = ceil($data["count"] / $tab_pageSize);



    $page_tool=create_page($total_page,$tab_pageSize,$tab_pageNumber,$tab_type);

    $return_data = array(
                "page_tool" => $page_tool,
                "source" => $data["content"],
                "tab_type"=>$tab_type
            );


    echo json_encode($return_data);
    die();


}elseif($action == 'to_xls' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
    // 匯出excel

    // 上方彩金

    // 每頁顯示多少
    $page['per_size'] = $current_per_size;
    // 目前所在頁數
    $page['no'] = $current_page_no;

    // 處理 datatables 傳來的排序需求
    if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
        $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
        $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY id ASC';}

    // 查詢條件
    $sql_str = sqlquery_str($excel_sql_array);
    // var_dump($excel_sql_array);
    // die();

    $check_id_sql_tmp = create_sql(). $sql_str . ' ';
    // 算彩金明細資料量
    $userlist_count = runSQL($check_id_sql_tmp);
    // var_dump($userlist_count);die();

    $check_id_sql = $check_id_sql_tmp .$sql_order;
    $result = runSQLall($check_id_sql);
    // var_dump($check_id_sql);die();

    if($result[0] >= 1){

        $j = $v = 1;
        // 彩金欄位名稱
        $column_title_xls[0][$v++] = '奖金编号';
        $column_title_xls[0][$v++] = '领取者帐号';
        $column_title_xls[0][$v++] = '现金';

        $column_title_xls[0][$v++] = '游戏币';
        $column_title_xls[0][$v++] = '奖金发放时间';
        $column_title_xls[0][$v++] = '奖金失效时间';

        $column_title_xls[0][$v++] = '奖金领取时间';
        $column_title_xls[0][$v++] = '奖金摘要';
        $column_title_xls[0][$v++] = '奖金类别';

        $column_title_xls[0][$v++] = '目前记录状态';
        $column_title_xls[0][$v++] = '管理者';
        $column_title_xls[0][$v++] = '最后操作者';

        for($i =1 ; $i <= $result[0]; $i++){
            $v = 1;
            $column_title_xls[$i][$v++] = $result[$i]->id;
            $column_title_xls[$i][$v++] = $result[$i]->member_account;
            $column_title_xls[$i][$v++] = $result[$i]->gcash_balance;

            $column_title_xls[$i][$v++] = $result[$i]->gtoken_balance;
            $column_title_xls[$i][$v++] = $result[$i]->givemoneytime_fix;
            $column_title_xls[$i][$v++] = $result[$i]->receivedeadlinetime;

            $column_title_xls[$i][$v++] = $result[$i]->receivetime_fix;
            $column_title_xls[$i][$v++] = $result[$i]->prizecategories;
            $column_title_xls[$i][$v++] = $result[$i]->summary;

            $column_title_xls[$i][$v++] = status_helper($result[$i]);
            $column_title_xls[$i][$v++] = $result[$i]->givemoney_member_account;
            $column_title_xls[$i][$v++] = $result[$i]->last_modify_member_account;

            $j++;
        }
    }else{
        $column_title_xls[] = '无明細资料';
    };

   // 彩票和非彩票
   $status_mapi18n=status_mapi18n();

   $status_ary=['0'=>'cancel','1'=>'can_receive','2'=>'timeout','3'=>'received','4'=>'expired'];
   $status_lotto_ary=['0'=>'lottosum_cancel','1'=>'lottosum_canreceive','2'=>'lottosum_timeout','3'=>'lottosum_received','4'=>'lottosum_expired'];

   if(array_key_exists($excel_sql_array['bonus_status'],$status_ary) OR array_key_exists($excel_sql_array['bonus_status'],$status_lotto_ary)){

        if($is_querybutton == '0'){
            //非彩票彩金sql
            if($tablet == 'nonlotto'){

                $menu_fun=$status_ary[$excel_sql_array['bonus_status']];
                $ynlotto_sql=ynlotto_sql('0');

                if($excel_sql_array['bonus_type'] != ''){
                    // $query_namegivedate=<<<SQL
                    //     AND prizecategories like '%{$excel_sql_array["bonus_type"]}%' AND givemoneytime = '{$excel_sql_array["bons_givemoneytime"]}'
                    // SQL;
                    $query_namegivedate=<<<SQL
                        AND prizecategories like '%{$excel_sql_array["bonus_type"]}%' AND givemoneytime = '{$excel_sql_array["bons_givemoneytime_astime"]}'
                    SQL;

                    $where = where_sql_switch($menu_fun,$excel_sql_array);
                    $nonlotto_sql_count = $ynlotto_sql['select'].$where['string'].$query_namegivedate.$ynlotto_sql['group'];

                }else{
                    $nonlotto_sql_count = not_lotto($sql_str);
                }

            }elseif($tablet == 'islotto'){

                $menu_fun=$status_lotto_ary[$excel_sql_array['bonus_status']];
                $ynlotto_sql=ynlotto_sql('1');
                if($excel_sql_array['bonus_type'] != ''){

                    $query_namegivedate=<<<SQL
                        AND prizecategories like '%{$excel_sql_array["bonus_type"]}%'
                    SQL;

                    $where = where_sql_switch($menu_fun,$excel_sql_array);
                    $lotto_sql_count = $ynlotto_sql['select'].$where['string'].$query_namegivedate.$ynlotto_sql['group'];

                }else{
                    $lotto_sql_count = is_lotto($sql_str);
                }

            }else{
                // $logger= '<script>alert("(x)不合法的測試，error code:202001091050");</script>';die();
                $logger= '<script>alert("(x)不合法的測試");</script>';die();
            }

        }else{
            // 彩票
            $lotto_sql_count = is_lotto($sql_str);
            //非彩票
            $nonlotto_sql_count = not_lotto($sql_str);
        }
    }else{
        // 彩票
        $lotto_sql_count = is_lotto($sql_str);
        //非彩票
        $nonlotto_sql_count = not_lotto($sql_str);
    }
    // var_dump($nonlotto_result);die();
    $islotto_result = runSQL($lotto_sql_count);
    $nonlotto_result = runSQL($nonlotto_sql_count);

    // 非彩票
    if($nonlotto_result >= 1){
        $lotto_result = runSQLall($nonlotto_sql_count);

        $j = $v = 1;
        // 欄位名稱
        $column_title_not_lotto[0][$v++] = '奖金类别';
        $column_title_not_lotto[0][$v++] = '奖金发放时间';
        $column_title_not_lotto[0][$v++] = '现金总和';

        $column_title_not_lotto[0][$v++] = '游戏币总和';
        $column_title_not_lotto[0][$v++] = '人數';
        $column_title_not_lotto[0][$v++] = '彩金状态';

        for($i =1 ; $i <= $lotto_result[0]; $i++){
            $v= 1;

            $show_satatus='';
            if($excel_sql_array['bonus_status'] == '4'){
                $show_satatus=$status_mapi18n[$excel_sql_array['bonus_status']];
                $bat_status=$excel_sql_array['bonus_status'];
            }elseif(isset($where['status'])){
                $show_satatus=$status_mapi18n[$where['status']];
                $bat_status=$where['status'];
            }else{
                $show_satatus=$status_mapi18n[$lotto_result[$i]->status];
                $bat_status=$lotto_result[$i]->status;
            }
            $column_title_not_lotto[$i][$v++] = $lotto_result[$i]->prizecategories;
            $column_title_not_lotto[$i][$v++] = $lotto_result[$i]->givemoneytime;

            $column_title_not_lotto[$i][$v++] = $lotto_result[$i]->gcash_total;
            $column_title_not_lotto[$i][$v++] = $lotto_result[$i]->gtoken_total;
            $column_title_not_lotto[$i][$v++] = $lotto_result[$i]->member_count;

            $column_title_not_lotto[$i][$v++] = $show_satatus;

            $j++;
        }
    }
    if($islotto_result >= 1){
        $lotto_result = runSQLall($lotto_sql_count);
        // 彩票
        $r = $s = 1;
        // 欄位名稱
        $column_title_lotto[0][$s++] = '奖金类别';
        $column_title_lotto[0][$s++] = '现金总和';
        $column_title_lotto[0][$s++] = '游戏币总和';
        $column_title_lotto[0][$s++] = '人数';
        $column_title_lotto[0][$s++] = '彩金状态';

        for($i =1 ; $i <= $lotto_result[0]; $i++){
            $s= 1;
            $show_satatus=$bat_status='';
            if($excel_sql_array['bonus_status'] == '4'){
                $show_satatus=$status_mapi18n[$excel_sql_array['bonus_status']];
                $bat_status=$excel_sql_array['bonus_status'];
            }elseif(isset($where['status'])){
                $show_satatus=$status_mapi18n[$where['status']];
                $bat_status=$where['status'];
            }else{
                $show_satatus=$status_mapi18n[$lotto_result[$i]->status];
                $bat_status=$lotto_result[$i]->status;
            }

            $column_title_lotto[$i][$s++] = $lotto_result[$i]->prizecategories;
            $column_title_lotto[$i][$s++] = $lotto_result[$i]->gcash_total;
            $column_title_lotto[$i][$s++] = $lotto_result[$i]->gtoken_total;
            $column_title_lotto[$i][$s++] = $lotto_result[$i]->member_count;

            $column_title_lotto[$i][$s++] = $show_satatus;

            $r++;
        }
    }
      

    $spredsheet = new Spreadsheet();

    $myworksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spredsheet, '彩金明细');

    // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
    $spredsheet->addSheet($myworksheet, 0);

    // 總表索引標籤開始寫入資料
    $sheet = $spredsheet->setActiveSheetIndex(0);
    // 寫入資料陣列
    // 彩金明細
    $sheet->fromArray($column_title_xls,NULL,'A1');

    // if($tablet == 'nonlotto'){
    if(isset($column_title_not_lotto)){
    
        //建立新的工作表
        $spredsheet->createSheet();
        $worksheet =$spredsheet->setActiveSheetIndex(1);//現編輯頁
        $spredsheet->getActiveSheet()->setTitle("非彩票");  //設定標題
        // 寫入資料陣列
        $spredsheet->getActiveSheet()->fromArray($column_title_not_lotto,NULL,'A1');
    };
    // if($tablet == 'islotto'){
    if(isset($column_title_lotto)){
        //建立新的工作表
        $spredsheet->createSheet();
        $worksheet =$spredsheet->setActiveSheetIndex(2);//現編輯頁
        $spredsheet->getActiveSheet()->setTitle("彩票");  //設定標題
        // 寫入資料陣列
        $spredsheet->getActiveSheet()->fromArray($column_title_lotto,NULL,'A1');
    };

    // 自動欄寬
    $worksheet = $spredsheet->getActiveSheet();
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spredsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    };
    // unset($column_title_xls);

    // 檔案名稱
    $filename = "receivemoney_management_".date('ymd_His', time());
    // $absfilename = "./tmp_dl/".$filename.".xlsx";

    // 直接匯出，不存於disk
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spredsheet, 'Xlsx');
    // 清除快取防亂碼
    ob_end_clean();
    $writer->save('php://output');
    // $writer->save($absfilename);
    die();

} else {
    echo '(x)不合法的測試，error code:201912171434';
}