<?php
// ----------------------------------------------------------------------------
// Features:	反水轉帳動作的處理
// File Name:	preferential_calculation_action.php
// Author:		Barkley
// Related:   對應 preferential_calculation.php
// Log:
// 2017.5.29 配合反水計算與發放功能處理的動作
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_proccessing.php";

function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}


function all_favorablerate_amount_detail_json_to_html($detail_json) {
  $detail_data = json_decode($detail_json);
  $self_preferential = $detail_data->self_favorable;

  $list_html =<<<HTML
  <ul>
    <li> 自身反水: $self_preferential</li>
  </ul>
HTML;

  $table_html = '<table>';

  $table_html .= '
  <tr>
		<th>会员帐号</th>
    <th>反水</th>
	</tr>
  ';

  foreach ($detail_data->level_distribute as $list) {
    $table_html .= '
    <tr>
  		<th>' . $list->from_account . '</th>
      <th>' . $list->base_favorable . ' X ' . $list->from_favorable_rate .  ' = ' . $list->from_favorable . ' </th>
  	</tr>
    ';
  }

  $table_html .= '</table>';


  return $list_html . $table_html;
}



if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的测试');
}

if(isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 取得 today date get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker']) && validateDate($_GET['current_datepicker'], 'Y-m-d')) {
  // 格式正確
  $current_datepicker = $_GET['current_datepicker'];
}else{
  // php 格式的 2017-02-24
  // 轉換為美東的時間 date
  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  date_timezone_set($date, timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d');
}
//var_dump($current_datepicker);
//echo date('Y-m-d H:i:sP');
// ---------------------------------------------------------------

$filter_empty = false;
if(isset($_GET['filter_empty']) && ($_GET['filter_empty'] == 'true' || $_GET['filter_empty'] == '1')) {
  $filter_empty = true;
}

// var_dump($_SESSION);
//var_dump($_POST);
// var_dump($_GET);

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



// ----------------------------------
// 動作為會員 action
// ----------------------------------
if($action == 'member_prefer_paid' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// 寫入反水發放欄位 , 紀錄反水這個會員已經付款
// ----------------------------------------------------------------------------
  // var_dump($_GET);die();
// ----------------------------------------------------------------------------
	// 先一張圖 Loading !!

	$page_html = '<div style="
	height: 30vh;
	display: flex;
	justify-content: center;
	align-items: center;
	overflow: hidden;
	">
	<img src="./ui/loading_gears.gif" alt="Loading">
	</div>
	';
	echo $page_html;

// ----------------------------------------------------------------------------


  //var_dump($_GET);
  $member_id = $_GET['id'];
  $member_account = $_GET['acc'];
  // 檢查欄位
  $check_id_sql = "SELECT * FROM root_statisticsdailypreferential WHERE id = '$member_id';";
  // echo($check_id_sql);die();
  $check_id_result = runSQLall($check_id_sql);
  // var_dump($check_id_result);die();
  if($check_id_result[0] == 1) {
    // 判斷反水餘額是否已經更新
    if($check_id_result[1]->all_favorablerate_beensent_amount != $check_id_result[1]->all_favorablerate_amount) {
      // 判斷帳號是否一樣
      if($check_id_result[1]->member_account == $member_account) {
        // 將全部會員的總反水金額寫入已發放欄位, 把差異的值清空為 0
        $update_sql = "UPDATE root_statisticsdailypreferential SET all_favorablerate_beensent_amount = (SELECT all_favorablerate_amount FROM root_statisticsdailypreferential WHERE id = '$member_id') WHERE id = '$member_id';";
        $update_sql = $update_sql."UPDATE root_statisticsdailypreferential SET all_favorablerate_difference_amount = '0'  WHERE id = '$member_id';";
        //echo $update_sql;
        // 交易保證上面SQL更新要成功。
        $update_result = runSQLtransactions($update_sql);
        //var_dump($update_result);
        if($update_result == 1) {
          $logger = 'Success 完成將反水金額 '.$config['currency_sign'].$check_id_result[1]->all_favorablerate_amount.'寫入帳號 '.$member_account.' 已付款欄位, 成功。';
        }else{
          $logger = 'False 將全部反水金額寫入已發放欄位失敗,ID='.$member_id.'及帳號 ='.$member_account;
        }
      }else{
        $logger = 'False 更新的反水ID='.$member_id.'及帳號 ='.$member_account.'不正確';
      }
    }else{
      $logger = 'False 帳號 '.$check_id_result[1]->member_account.' 總反水金額 '.$config['currency_sign'].$check_id_result[1]->all_favorablerate_amount.' 已經發放 '.$config['currency_sign'].$check_id_result[1]->all_favorablerate_beensent_amount.'無須在更新。';
    }
  }else{
    $logger = 'False 反水紀錄ID='.$member_id.'不正確';
  }
  // echo $logger;
  echo '<hr><br><br><p align="center">'.$logger.'</p>';
  echo '<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';

  // 寫入memberlog
  $msg         = $logger; //客服
  $msg_log     = $msg; //RD
  $sub_service = 'preferential_calculation';
  memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $member_account??$member_id.'不正確', "$msg_log", 'b', $sub_service);

// ----------------------------------------------------------------------------
}elseif($action == 'notes_common_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
// ----------------------------------------------------------------------------
// 更新會員備註
// ----------------------------------------------------------------------------

	// var_dump($_POST);

  $pk = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);

  $update_note_sql = "UPDATE root_member SET notes = '".$notes."' WHERE id = '".$pk."';";
 	// var_dump($review_sql);
  $update_note_sql_result = runSQLtransactions($update_note_sql);
 	// var_dump($update_note_sql_result);

  if($update_note_sql_result == 1){
    // 更新 notes
    $logger = "更新处理资讯内容文章";
  }else{
    // 系统错误
    $logger = "更新未成功错误，請聯絡維護人員處理。";
  }
  echo '<script>alert("'.$logger.'");location.reload();</script>';die();

// ----------------------------------------------------------------------------
}elseif($action == 'reload_preferentiallist' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------
  // 算 root_member 人數
  $userlist_sql_tmp = "SELECT * FROM root_statisticsdailypreferential WHERE dailydate = '".$current_datepicker."' ";

  if($filter_empty) {
    $userlist_sql_tmp .= "AND (all_favorablerate_amount != '0' OR all_bets_amount != '0')";
  }

  $userlist_sql = $userlist_sql_tmp.';';
  // var_dump($userlist_sql);
  $userlist_count = runSQL($userlist_sql);

  // -----------------------------------------------------------------------
  // 分頁處理機制
  // -----------------------------------------------------------------------
  // 所有紀錄數量
  $page['all_records']     = $userlist_count;
  // 每頁顯示多少
  $page['per_size']        = $current_per_size;
  // 目前所在頁數
  $page['no']              = $current_page_no;
  // var_dump($page);

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
    }else{ $sql_order_dir = 'DESC';}
    if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY member_id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY member_parent_id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY member_therole '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY member_account '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY favorablerate_level '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY dailydate '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY all_bets_amount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 7){ $sql_order = 'ORDER BY all_favorablerate_amount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 12){ $sql_order = 'ORDER BY all_favorablerate_beensent_amount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 13){ $sql_order = 'ORDER BY all_favorablerate_difference_amount '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY member_id ASC';}
  }else{ $sql_order = 'ORDER BY member_id ASC';}
  // 取出 root_member 資料
  $userlist_sql   = $userlist_sql_tmp." ".$sql_order." OFFSET ".$page['no']." LIMIT ".$page['per_size']." ;";
  // var_dump($userlist_sql);
  $userlist = runSQLall($userlist_sql);

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist[0]; $i++){
      // 顯示資料 , 將資料填入 $b array , 可以方便轉 CSV 及顯示 html table
      // --------------------------------------------------------------------------
      //var_dump($check_pref_result);
      $b['id']                    = $userlist[$i]->id;
      // -------------------------------------------
      // 會員的資料資訊 -- 來自 每日營收日結報表
      // -------------------------------------------
      $b['member_id']              = $userlist[$i]->member_id;
      $b['member_account']         = $userlist[$i]->member_account;
      $b['member_parent_id']       = $userlist[$i]->member_parent_id;
      $b['member_therole']         = $userlist[$i]->member_therole;
      $b['dailydate']              = $userlist[$i]->dailydate;
      // 會員等級
      $b['favorablerate_level']     = $userlist[$i]->favorablerate_level;
      // MG 電子投注量
      $b['mg_totalwager']           = $userlist[$i]->mg_totalwager;
      // MG 損益
      // $b['mg_profitlost']           = $userlist[$i]->mg_profitlost;
      // MG 電子 會員反水比例
      $b['mg_favorable_rate']       = $userlist[$i]->mg_favorable_rate;
      // MG 電子 會員反水量 , 四捨五入取到小數點第二位。
      $b['mg_favorablerate_amount'] = $userlist[$i]->mg_favorablerate_amount;
      // 總投注量
      $b['all_bets_amount']         = $userlist[$i]->all_bets_amount;
      //$b['all_profitlost']          = $userlist[$i]->all_profitlost;
      // 總反水量 = MG + PT + ....
      $b['all_favorablerate_amount']= $userlist[$i]->all_favorablerate_amount;
      // 上限 - 同一個 name 需要一樣
      $b['favorable_limit']         = $userlist[$i]->favorable_limit;
      // 稽核倍數 - 同一個 name 需要一樣
      $b['favorable_audit']         = $userlist[$i]->favorable_audit;
      // 本日已經發送的反水金額
      $b['all_favorablerate_beensent_amount'] = $userlist[$i]->all_favorablerate_beensent_amount;
      // 本日需要需要補發的差額。 總反水 - 以發送  = 還需要補送的差額。
      $b['all_favorablerate_difference_amount'] = $userlist[$i]->all_favorablerate_difference_amount;


      $self_preferential = 0;
      $detail_data = json_decode($userlist[$i]->all_favorablerate_amount_detail, true);
      if(!empty($detail_data) && isset($detail_data['self_favorable'])) {
        $self_preferential = $detail_data['self_favorable'];
      }
      $b['self_favorable'] = $self_preferential;


      // $all_favorablerate_amount_detail = $userlist[$i]->all_favorablerate_amount_detail;

      // --------------------------------------------------------------------------

      // 處理反水的動作 , 本次友資料才顯示 button  , 沒有資料就不顯示。
      if(isset($b['id'])) {
        // 如果反水小於 等於 0 , 就不要發送反水
        if($b['all_favorablerate_difference_amount'] > 0) {
          // 1. 轉帳到會員代幣帳戶,帶有稽核倍數
          $all_favorablerate_beensent_amount_html = '';
          $all_favorablerate_beensent_amount_html = $all_favorablerate_beensent_amount_html.'<a href="member_depositgtoken.php?a='.$b['member_id'].'&gcash='.$b['all_favorablerate_difference_amount'].'&notes=发放游戏币反水,帐号'.$b['member_account'].',金額'.$b['all_favorablerate_difference_amount'].',日期'.$current_datepicker.'的反水" class="btn btn-default btn-xs"  target="_BLANK"  title="转帐到会游戏币帐户并带有稽核倍数'.$b['favorable_audit'].'倍=('.$config['currency_sign'].')'.round($b['all_favorablerate_difference_amount']*$b['favorable_audit'],2).'">转游戏币</a>&nbsp;&nbsp;';
          // 2. 轉帳到會員現金帳戶,沒有稽核
          $all_favorablerate_beensent_amount_html = $all_favorablerate_beensent_amount_html.'<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.$b['all_favorablerate_difference_amount'].'&notes=发放现金反水,帐号'.$b['member_account'].',金額'.$b['all_favorablerate_difference_amount'].',日期'.$current_datepicker.'的反水" class="btn btn-default btn-xs"  target="_BLANK"  title="转帐到会员现金帐户,金额'.round($b['all_favorablerate_difference_amount'],2).'沒有稽核">转现金</a>&nbsp;&nbsp;';
          // 3. 轉帳成功, 寫入反水發放欄位。
          $all_favorablerate_beensent_amount_html = $all_favorablerate_beensent_amount_html.'<a href="preferential_calculation_action.php?a=member_prefer_paid&id='.$userlist[$i]->id.'&acc='.$b['member_account'].'"  class="btn btn-default btn-xs" onclick="return confirm(\'此动作会将反水金额'.round($b['all_favorablerate_amount'],2).'写入以发放栏位，请确认已经汇款完成了,再来更新此栏位.\')"  target="_BLANK" title="转帐完成后,请点选这里手工确认已经发放反水。">更新纪录</a>';
        }else{
          // 沒有反水可以處理
          $all_favorablerate_beensent_amount_html = '<a href="#" title="" class="btn btn-default btn-xs" >没有反水</a>&nbsp;&nbsp;';
        }
      }else{
        $all_favorablerate_beensent_amount_html = '';
      }

      if($b['all_favorablerate_amount'] == 0) {

        $all_favorablerate_amount_detail_html = '<button type="button" class="btn btn-default btn-xs">没有反水</button>';

      } else {

        $all_favorablerate_amount_detail_html =<<<HTML
        <a
          class="btn btn-primary btn-xs"
          href="preferential_calculation_detail.php?member_account={$b['member_account']}&dailydate={$b['dailydate']}"
          target="_blank"
        >
          反水明細
        </a>
HTML;

      }

      // 顯示的表格資料內容
      $show_listrow_array[] = [
        'id' => $b['member_id'],
        'parent' => $b['member_parent_id'],
        'therole' => $b['member_therole'],
        'account' => $b['member_account'],
        'favorablerate_level' => $b['favorablerate_level'],
        'dailydate' => $b['dailydate'],
        'mg_totalwager' => $b['mg_totalwager'],
        'all_bets_amount' => '$' . $b['all_bets_amount'],
        'all_favorablerate_amount' => '$' . $b['all_favorablerate_amount'],
        'all_favorablerate_beensent_amount_html' => $all_favorablerate_beensent_amount_html,
        'all_favorablerate_beensent_amount' => '$' . $b['all_favorablerate_beensent_amount'],
        'all_favorablerate_difference_amount' => '$' . $b['all_favorablerate_difference_amount'],
        'all_favorablerate_amount_detail_html' => $all_favorablerate_amount_detail_html,
        'self_favorable' => '$' . $b['self_favorable'],
        'agent_favorable' => '$' . ($b['all_favorablerate_amount'] - $b['self_favorable']),
      ];
    }
    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($userlist_count),
      "data" => $show_listrow_array
    );
    // --------------------------------------------------------------------
    // 表格資料 row list , end for loop
    // --------------------------------------------------------------------
  }else{
    // NO member
    $output = array(
      "sEcho" => 0,
      "iTotalRecords" => 0,
      "iTotalDisplayRecords" => 0,
      "data" => ''
    );
  }
  // end member sql
  echo json_encode($output);
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------
}elseif($action == 'prefer_update' AND isset($_GET['prefer_update_date']) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  if(validateDate($_GET['prefer_update_date'], 'Y-m-d')) {
    $dailydate = $_GET['prefer_update_date'];
    $file_key = sha1('preferential'.$dailydate);
    $logfile_name = dirname(__FILE__) .'/tmp_dl/prefer_'.$file_key.'.tmp';
    if(file_exists($logfile_name)) {
      // 寫入memberlog
      $msg         = $_SESSION['agent']->account . '更新娱乐城反水计算：錯誤。日期：' . $dailydate . '。错误讯息：重复操作。'; //客服
      $msg_log     = $_SESSION['agent']->account . '更新娱乐城反水计算：錯誤。日期：' . $dailydate . '。版号：' . $file_key . '。已存在相同檔案：' . $logfile_name; //RD
      $sub_service = 'preferential_calculation';
      memberlogtodb($_SESSION['agent']->account, 'marketing', 'error', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

      die('更新中...請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' preferential_calculation_cmd.php run '.$dailydate.' web > '.$logfile_name.' &';
      //echo nl2br($command);

      // dispatch command and show loading view
      dispatch_proccessing(
        $command,
        '更新中...',
        $_SERVER['PHP_SELF'].'?a=prefer_update_reload&k='.$file_key,
        $logfile_name
      );

      // 寫入memberlog
      $msg         = $_SESSION['agent']->account . '按下更新娱乐城反水计算。日期：' . $dailydate . '。版号：' . $file_key.'。'; //客服
      $msg_log     = $msg; //RD
      $sub_service = 'preferential_calculation';
      memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

    }
  }else{
    $output_html  = '日期格式有问题，请确定有且格式正确，需要为 YYYY-MM-DD 的格式';
    echo '<hr><br><br><p align="center">'.$output_html.'</p>';
    echo '<br><br><p align="center"><button type="button" onclick="window.close();">关闭视窗</button></p>';

    // 寫入memberlog
    $msg         = $_SESSION['agent']->account . '更新娱乐城反水计算：错误。讯息：' . $output_html . '。'; //客服
    $msg_log     = $msg; //RD
    $sub_service = 'preferential_calculation';
    memberlogtodb($_SESSION['agent']->account, 'marketing', 'error', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

  }
}elseif($action == 'prefer_payout_update' AND isset($_GET['prefer_payout_date']) AND isset($_GET['s'])  AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  if(validateDate($_GET['prefer_payout_date'], 'Y-m-d') AND isset($_GET['s']) AND isset($_GET['s1']) AND isset($_GET['s2']) AND isset($_GET['s3']) ){
    // 取得獎金的各設定並生成token傳給 cmd 執行
    $bonus_status = filter_var($_GET['s'],FILTER_VALIDATE_INT);
    $bonus_type = filter_var($_GET['s1'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $audit_type = filter_var($_GET['s2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $audit_amount = filter_var($_GET['s3'],FILTER_VALIDATE_INT);
    $audit_ratio = filter_var($_GET['s4'],FILTER_VALIDATE_FLOAT);
    $audit_calculate_type = filter_var($_GET['s5'],FILTER_SANITIZE_STRING);

    $bonusstatus_array = [
      'bonus_status' => $bonus_status,
      'bonus_type' => $bonus_type,
      'audit_type' => $audit_type,
      'audit_amount' => $audit_amount,
      'audit_ratio' => $audit_ratio,
      'audit_calculate_type' => $audit_calculate_type,
    ];
    //var_dump($bonusstatus_array);
    // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
    $bonus_token = jwtenc('preferentialpayout', $bonusstatus_array);

    $dailydate = $_GET['prefer_payout_date'];
    $file_key = sha1('preferentialpayout'.$dailydate);
    $logfile_name = dirname(__FILE__) .'/tmp_dl/prefer_'.$file_key.'.tmp';
    if(file_exists($logfile_name)) {
      die('請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' preferential_payout_cmd.php run '.$dailydate.' '.$bonus_token.' '.$_SESSION['agent']->account.' web > '.$logfile_name.' &';
      // echo nl2br($command);

      // dispatch command and show loading view
      dispatch_proccessing(
        $command,
        '更新中...',
        $_SERVER['PHP_SELF'].'?a=prefer_update_reload&k='.$file_key,
        $logfile_name
      );

      // 寫入memberlog
      $msg         = $_SESSION['agent']->account . '娱乐城反水计算->派彩批次发送。日期：' . $dailydate . '。版号：' . $file_key . '。'; //客服
      $msg_log     = $msg; //RD
      $sub_service = 'payout';
      memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

    }
  }else{
    $output_html  = '日期格式或状态设定有问题，请确定有日期及状态设定且格式正确，日期格式需要为 YYYY-MM-DD 的格式';
    echo '<hr><br><br><p align="center">'.$output_html.'</p>';
    echo '<br><br><p align="center"><button type="button" onclick="window.close();">关闭视窗</button></p>';

    // 寫入memberlog
    $msg         = $_SESSION['agent']->account . '娱乐城反水计算->派彩批次发送：错误。讯息：' . $output_html . '。'; //客服
    $msg_log     = $msg; //RD
    $sub_service = 'payout';
    memberlogtodb($_SESSION['agent']->account, 'marketing', 'error', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

  }
}elseif($action == 'prefer_update_reload' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/prefer_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'prefer_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/prefer_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      unlink($reload_file);

      // 寫入memberlog
      $msg         = $_SESSION['agent']->account . '娱乐城反水计算，執行：成功。版号：' . $logfile_sha . '。'; //客服
      $msg_log     = $msg; //RD
      $sub_service = 'preferential_calculation';
      memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    var_dump($_POST);

}elseif(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
  echo json_encode($output);
}else{
  $logger = '(x) 只有管理員或有權限的會員才可以使用。';
  echo $logger;
}

?>
