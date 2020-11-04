<?php
// ----------------------------------------------------------------------------
// Features:	優惠詳細的動作處理
// File Name:	offer_management_action.php
// Author:		
// Related:		
// DB Table:    
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_promotional_management.php";

//$tr['Illegal test'] = '(x)不合法的測試。';
if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = $_GET['a'];
} else {
  die($tr['Illegal test']);
}
//var_dump($_SESSION);
// var_dump($_POST);

if(isset($_GET['name']) AND $_GET['name'] != null){
  $query_array['name'] = filter_var($_GET['name'],FILTER_SANITIZE_STRING);
}

if(isset($_GET['s_date']) AND $_GET['s_date'] != null){
  if(validateDate($_GET['s_date'], 'Y-m-d H:i')){
    $query_array['start_date'] = filter_var($_GET['s_date'],FILTER_SANITIZE_STRING);
  }
}

if(isset($_GET['e_date']) AND $_GET['e_date'] != null){
  if(validateDate($_GET['e_date'],'Y-m-d H:i')){
    $query_array['end_date'] = filter_var($_GET['e_date'],FILTER_SANITIZE_STRING);
  }
}else{
  $default_min_date = gmdate('Y-m-d',strtotime('- 3 month')); // 7天
  $query_array['end_date'] = $default_min_date;
}

if(isset($_GET['status_query']) AND $_GET['status_query'] != null){
  $query_array['status'] = filter_var_array($_GET['status_query'],FILTER_SANITIZE_STRING);
}else{
  $query_array['status'] = '';
}
// 分類
// 單一分類
if(isset($_GET['cat_query']) AND $_GET['cat_query'] != 'undefined'){
  if(is_array($_GET['cat_query'])){
    $query_array['category'] = filter_var_array($_GET['cat_query'],FILTER_SANITIZE_STRING);
  }else{
    $query_array['category'] = filter_var($_GET['cat_query'],FILTER_SANITIZE_STRING);
  }
}
// 全選
if(isset($_GET['cat_all']) AND $_GET['cat_all'] != null){
  $query_array['cat_all'] = filter_var($_GET['cat_all'],FILTER_SANITIZE_STRING);
}
// 網域名稱
if(isset($_GET['domain_name']) AND $_GET['domain_name'] != null){
  $query_array['domain_name'] = filter_var($_GET['domain_name'],FILTER_SANITIZE_STRING);
}
if(isset($_GET['sub_name']) AND $_GET['sub_name'] != null){
  $query_array['sub_name'] = filter_var($_GET['sub_name'],FILTER_SANITIZE_STRING);
}
// 數字
if(isset($_GET['domain_id']) AND $_GET['domain_id'] != null){
  $domain_id = filter_var($_GET['domain_id'],FILTER_SANITIZE_STRING);
}
if(isset($_GET['sub_id']) AND $_GET['sub_id'] != null){
  $sub_id = filter_var($_GET['sub_id'],FILTER_SANITIZE_STRING);
}

// modal
// 編輯分類名稱、啟用/停用所有該分類的活動
// 分類
if(isset($_POST['origin_cat_name']) AND $_POST['origin_cat_name'] != null){
  $query_array['origin'] = filter_var($_POST['origin_cat_name'],FILTER_SANITIZE_STRING);
}
// 分類新名稱
if(isset($_POST['edit_cat_name']) AND $_POST['edit_cat_name'] != null){
  $query_array['new_category'] = filter_var($_POST['edit_cat_name'],FILTER_SANITIZE_STRING);
}

// 分類啟用停用狀態
if(isset($_POST['cat_switch']) AND $_POST['cat_switch'] != null){
  $query_array['cat_switch'] = filter_var($_POST['cat_switch'],FILTER_SANITIZE_NUMBER_INT);
}

if(isset($_POST['domain_name']) AND $_POST['domain_name'] != null){
  $query_array['modal_domain_name'] = filter_var($_POST['domain_name'],FILTER_SANITIZE_STRING);
}
if(isset($_POST['sub_name']) AND $_POST['sub_name'] != null){
  $query_array['modal_sub_name'] = filter_var($_POST['sub_name'],FILTER_SANITIZE_STRING);
}

// 新增分類
if(isset($_GET['add_cat_name']) AND $_GET['add_cat_name'] != null){
  $query_array['add_new_category'] = filter_var_array($_GET['add_cat_name'],FILTER_SANITIZE_STRING);
}
// var_dump($query_array['add_new_category']);
if(isset($_GET['time']) AND $_GET['time'] != null){
  if(validateDate($_GET['time'],'Y-m-d H:i')){
    $query_array['add_time'] = filter_var($_GET['time'],FILTER_SANITIZE_STRING);
  }
}

// tab filter
if (isset($_GET['status_filter']) and $_GET['status_filter'] != null) {
	switch ($_GET['status_filter']){
	case 'on':
		$get_status_filter = 'on';
    $status_filter_query = " AND classification_status = 1 ";
    $classification_query = " AND status = 1 ";
	  break;
	case 'off':
		$get_status_filter = 'off';
    $status_filter_query = " AND classification_status = 0 ";
    $classification_query = " AND status = 0 ";
	  break;

	default:
		$get_status_filter = 'on';
    $status_filter_query = " AND classification_status = 1 ";
    $classification_query = " AND status = 1 ";
	}
}else{
	$get_status_filter = 'on';
  $status_filter_query = " AND classification_status = 1 ";
  $classification_query = " AND status = 1 ";
}
// var_dump($_GET);die();

// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if ( isset($_GET['length']) && ($_GET['length'] != null) ) {
  $current_per_size = filter_var($_GET['length'], FILTER_VALIDATE_INT);
} else {
  $current_per_size = $page_config['datatables_pagelength'];
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if ( isset($_GET['start']) && ($_GET['start'] != null) ) {
  $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
} else {
  $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if ( isset($_GET['_']) ) {
  $secho = $_GET['_'];
} else {
  $secho = '1';
}
function validateDate($date, $format = 'Y-m-d H:i:s') {
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}

function combineUpdateClassificationSortSql($sort, $agent_acc, $desktop_domain, $mobile_domain)
{
  $sql = '';

  foreach ($sort as $k => $class) {
    $sql .= <<<SQL
    UPDATE root_promotions
    SET sort = '{$k}',
        processingaccount = '{$agent_acc}'
    WHERE classification = '{$class}'
    AND desktop_domain = '{$desktop_domain}'
    AND mobile_domain = '{$mobile_domain}';
SQL;
  }

  return $sql;
}

function classification_sort($base, $data)
{
  $all_tabs = json_decode($base, true);
  $tabs_sort = explode('&', str_replace('tab[]=','',$data));

  $sort = [];
  foreach ($tabs_sort as $k => $v) {
    $tab_sort = filter_var($v, FILTER_SANITIZE_STRING);

    if (!$tab_sort) {
      return false;
    }

    if (!isset($all_tabs[$v])) {
      return false;
    }

    $sort[$k+1] = $all_tabs[$v];
  }

  return $sort;
}

if($action == 'delete' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

  if ($id == '') {
    //$tr['Wrong attempt'] = '(x)錯誤的嘗試。';
    $logger = $tr['Wrong attempt'];
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  $promotional_data = get_designatedid_promotional($id);

  if (!$promotional_data['status']) {
    //$tr['Query error or the data has been deleted'] = '查詢錯誤或者該筆資料已被刪除。';
    $logger = $tr['Query error or the data has been deleted'];
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  }

  $delete_result = update_promotional_status($promotional_data['result']->id, 2);

  if ($delete_result) {
    //$tr['Delete successfully'] = '刪除成功。';
    $logger = $tr['Delete successfully'];
    echo '<script>alert("'.$logger.'");location.reload();</script>';
  } else {
    //$tr['delete failed'] = '刪除失敗。';
    $logger = $tr['delete failed'];
    echo '<script>alert("'.$logger.'");</script>';
    die();
  }

// ----------------------------------------------------------------------------
} elseif($action == 'edit_status') {

  $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
  $is_open = filter_var($_POST['is_open'], FILTER_SANITIZE_NUMBER_INT);

  if ($id == '' AND $is_open == '') {
    //$tr['Wrong attempt'] = '(x)錯誤的嘗試。';
    $logger = $tr['Wrong attempt'];
    echo '<script>alert("'.$logger.'");</script>';
    die();
  }

  $promotional_data = get_designatedid_promotional($id);

  if (!$promotional_data['status']) {
    //$tr['Query error or the data has been deleted'] = '查詢錯誤或者該筆資料已被刪除。';
    $logger = $tr['Query error or the data has been deleted'];
    echo '<script>alert("'.$logger.'");</script>';
    die();
  }

  $edit_result = update_promotional_status($promotional_data['result']->id, $is_open);

  if (!$edit_result) {
    //$tr['delete failed'] = '刪除失敗。';
    $logger = '启用状态更新失败';
    echo '<script>alert("'.$logger.'");</script>';
    die();
  }

// ----------------------------------------------------------------------------
} /*elseif($action == 'edit_sort' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  $sort = classification_sort($_POST['base'], $_POST['data']);

  if (!$sort) {
    echo json_encode(['status' => false, 'msg' => '错误的分类或顺序']);
    die();
  }

  $doamin = get_desktop_mobile_domain($_POST['domain_id'], $_POST['subdomain_id']);
  
  if (!$doamin['status']) {
    echo json_encode(['status' => 'failure?', 'msg' => $doamin['result']]);
    die();
  }

  $sql = combineUpdateClassificationSortSql($sort, $_SESSION['agent']->account, $doamin['result']['desktop'], $doamin['result']['mobile']);

  $updatesql = 'BEGIN;'.$sql.'COMMIT;';
  $update_result = runSQLtransactions($updatesql);

  if (!$update_result) {
    echo json_encode(['status' => 'failure', 'msg' => '排序更新失败']);
    die();
  }

  echo json_encode(['status' => 'success', 'msg' => '排序更新成功']);
// ----------------------------------------------------------------------------
} */ elseif($action == 'init_query' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // 初始化
  global $disable_var;

  $promotional_isopen = '';  
  $switch = '';

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
    $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
  }else{ $sql_order = 'ORDER BY id ASC';}

  $sql = sql($query_array);

  // 算資料總筆數
  $userlist_sql   = $sql.';';
  $count = runSQL($userlist_sql);

  if($count != 0){
     
    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records']     = $count;
    // 每頁顯示多少
    $page['per_size']        = $current_per_size;
    // 目前所在頁數
    $page['no']              = $current_page_no;

    // 取出資料
    $userlist_sql   = $sql. ' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
    // var_dump($userlist_sql);die();
    $result = runSQLall($userlist_sql);
    unset($result[0]);

    foreach($result as $k => $v){
      // var_dump($v->status);die();
  
      if($v->status == '1') {
        $promotional_isopen = 'checked';
      } elseif ($v->status == '0') {
        $promotional_isopen = '';
      }
  
      $switch =<<<HTML
      <div class="col-12 material-switch pull-left">
          <input id="offer_isopen{$v->id}" name="offer_isopen{$v->id}" class="checkbox_switch" value="{$v->id}" type="checkbox" {$promotional_isopen} {$disable_var}/>
          <label for="offer_isopen{$v->id}" class="label-success"></label>
      </div>
HTML;
      $show = '';
      if ($v->mobile_show == '1') {
        $show = '<span class="label label-success label-sm">mobile</span>&nbsp;';
      }
  
      if ($v->desktop_show == '1') {
        $show = $show.'<span class="label label-success label-sm">PC</span>&nbsp;';
      }
      $icon =<<<HTML
      <td class="text-center">
        <button type="button" class="btn btn-danger btn-sm delete_btn" id="delete_btn" value="{$v->id}" {$disable_var}><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
        <a class="btn btn-primary btn-sm  {$disable_var}" href="promotional_editor.php?i={$v->id}&di={$domain_id}&sdi={$sub_id}" role="button" title="{$tr['edit']}"  ><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
      </td>
HTML;
  
      $show_list_array[] = array(
        'id'=>$v->id,
        'name'=>$v->name,
        'classification'=>$v->classification,
        'effecttime'=>$v->effecttime,
        'endtime'=>$v->endtime,
        'status'=>$switch,
        'show'=>$show,
        'icon'=>$icon
      );
    };
    $output = array(
      "sEcho"                                 => intval($secho),
      "iTotalRecords"                 => intval($page['per_size']),
      "iTotalDisplayRecords"     => intval($page['all_records']),
      "data"                                     => $show_list_array
    );
  }else{
     // 搜尋區間沒資料
     $output = array(
      "sEcho"                                     => 0,
      "iTotalRecords"                     => 0,
      "iTotalDisplayRecords"         => 0,
      "data"                                         => ''
     );
  };
  echo json_encode($output);

}elseif($action == 'search_query' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  global $disable_var;

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
    $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
  }else{ $sql_order = 'ORDER BY id ASC';}

  $search_query = combine_sql($query_array);
  // $to_combine = sql($query_array).$search_query." ".$sql_order;
  $to_combine = sql_new().$search_query." ".$sql_order;
  // var_dump($to_combine);die();

  // 算資料總筆數
  $userlist_sql   = $to_combine.';';
  $count = runSQL($userlist_sql);
  
  if($count != 0){
    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records']     = $count;
    // 每頁顯示多少
    $page['per_size']        = $current_per_size;
    // 目前所在頁數
    $page['no']              = $current_page_no;

    // 取出資料
    $userlist_sql   = $to_combine.' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';

    $result = runSQLall($userlist_sql);
   
    unset($result[0]);

    foreach($result as $k => $v){
  
      if($v->status == '1') {
        $promotional_isopen = 'checked';
      } elseif ($v->status == '0') {
        $promotional_isopen = '';
      }

      // 啟用
      $switch =<<<HTML
      <div class="col-12 material-switch pull-left">
          <input id="offer_isopen{$v->id}" name="offer_isopen{$v->id}" class="checkbox_switch" value="{$v->id}" type="checkbox" {$promotional_isopen} {$disable_var}/>
          <label for="offer_isopen{$v->id}" class="label-success"></label>
      </div>
    HTML;
      // 顯示
      $show = '';
      if ($v->mobile_show == '1') {
        $show = '<span class="label label-success label-sm">mobile</span>&nbsp;';
      }
  
      if ($v->desktop_show == '1') {
        $show = $show.'<span class="label label-success label-sm">PC</span>&nbsp;';
      }
      // 功能
      $icon =<<<HTML
      <td class="text-center">
        <button type="button" class="btn btn-danger btn-sm delete_btn" id="delete_btn" value="{$v->id}" {$disable_var}><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
        <a class="btn btn-primary btn-sm  {$disable_var}" href="promotional_editor.php?i={$v->id}&di={$domain_id}&sdi={$sub_id}" role="button" title="{$tr['edit']}"  ><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
      </td>
    HTML;
  
        $show_list_array[] = array(
          'id'=>$v->id,
          'name'=>$v->name,
          'classification'=>$v->classification,
          'effecttime'=>$v->effecttime,
          'endtime'=>$v->endtime,
          'status'=>$switch,
          'show'=>$show,
          'icon'=>$icon
        );
      };
      $output = array(
        "sEcho"                                 => intval($secho),
        "iTotalRecords"                 => intval($page['per_size']),
        "iTotalDisplayRecords"     => intval($page['all_records']),
        "data"                                     => $show_list_array
      );
  }else{
      // 搜尋區間沒資料
    $output = array(
      "sEcho"                                     => 0,
      "iTotalRecords"                     => 0,
      "iTotalDisplayRecords"         => 0,
      "data"                                         => ''
    );
  }
  echo json_encode($output);
  
}elseif($action == 'edit_category'){
  // 分類存檔 
  // 編輯分類名稱、啟用/停用所有該分類的活動

  // 找出欲修改的新分類名稱在表中是否已存在
  // $pro_exist_result = check_promotion_isset($query_array);
  // $cla_exist_result = check_classification_exist($query_array);
  // // echo '<pre>', var_dump($a[1]), '</pre>';
  // // die();

  // if($pro_exist_result[0] == 0){
  //   // update 2張表
  //   $change_classification_category = update_classification_data($query_array);
  //   $change_category = update_promotions_data($query_array);
  //   if(!$change_classification_category OR !$change_category){
  //     echo json_encode(['status' => 'failure', 'msg' => '储存失败']);
  //     die();
  //   }
  // }
  // if($cla_exist_result[0] == 0){
  //   $change_category = update_classification_data($query_array);
  //   if(!$change_category){
  //     echo json_encode(['status' => 'failure', 'msg' => '储存失败']);
  //     die();
  //   }
  // }
  // if($pro_exist_result[0] != 0 or $cla_exist_result[0] != 0){
  //   echo json_encode(['status' => 'failure', 'msg' => '已有此分類名稱']);
  //   die();
  // }

    // 要修改的該分類在哪張表
    // 兩張表都有
    $sql =<<<SQL
    SELECT DISTINCT ON(classification)
      pro.classification,
      pro.desktop_domain,pro.mobile_domain,
      pro.status,pro.classification_status,
      cla.classification_name,
      cla.desktop_domain,cla.mobile_domain,
      cla.status

      FROM root_promotions AS pro

      JOIN root_promotions_classification AS cla
      ON pro.classification = cla.classification_name	
      AND cla.desktop_domain = pro.desktop_domain
      WHERE (classification = '{$query_array['origin']}' or classification_name = '{$query_array['origin']}')

      AND pro.desktop_domain = '{$query_array['modal_domain_name']}'
      AND pro.mobile_domain = '{$query_array['modal_sub_name']}'
      AND pro.status != 2
SQL;

    // echo '<pre>', var_dump($sql), '</pre>';
    // die();
    $pro_result = runSQLall($sql);
    if($pro_result[0] == 0){
      // 如果該分類只在root_promotions_classification有資料，root_promotions沒有，表示該分類底下沒有任何活動(root_promotions_classification 新增分類名稱)
      $sql=<<<SQL
      SELECT classification_name,status,desktop_domain,mobile_domain 
      FROM root_promotions_classification 
      WHERE classification_name = '{$query_array['origin']}'
      AND desktop_domain = '{$query_array['modal_domain_name']}'
      AND mobile_domain = '{$query_array['modal_sub_name']}'
SQL;
      // echo '<pre>', var_dump($sql), '</pre>';
      // die();
      $cla_result = runSQLall($sql);
      if($cla_result[0] == 0){
        $change_category = update_classification_data($query_array);

        if($change_category == 0){
          echo json_encode(['status' => 'failure', 'msg' => '储存失败']);
          die();
        }
      }
    }

    // update 2張表
    $change_classification_category = update_classification_data($query_array);
    $change_category = update_promotions_data($query_array);
    // echo '<pre>', var_dump($change_category), '</pre>';
    // die();
    if($change_classification_category == 0 AND $change_category == 0){
      echo json_encode(['status' => 'failure', 'msg' => '储存失败']);
      die();
    }
  
  echo json_encode(['status' => 'success', 'msg' => '储存成功']);

}elseif($action == 'add_category'){
  // 新增分類 到 root_promotions_classification

  // 該domain是否有此分類名稱
  foreach($query_array['add_new_category'] as $key){
    $sql=<<<SQL
    SELECT classification_name,desktop_domain,mobile_domain 
    FROM root_promotions_classification
    WHERE classification_name IN ('{$key}') 
    AND desktop_domain = '{$query_array['domain_name']}' 
    AND mobile_domain = '{$query_array['sub_name']}'
SQL;
    $sql_result = runSQLall($sql);

    if($sql_result[0] == 0){
      // 該domain沒有此分類
        $name = $key;
    
        $sql=<<<SQL
          INSERT INTO root_promotions_classification ("classification_name","build_time","desktop_domain","mobile_domain","status")
          VALUES ('{$name}','{$query_array['add_time']}','{$query_array['domain_name']}','{$query_array['sub_name']}',1)
    SQL;
    
      // var_dump($sql);die();
      $result = runSQL($sql);
      
      if($result == 0){
        echo json_encode(['status' => 'failure', 'msg' => '新增失败']);
        die();
      }

    }else{
      echo json_encode(['status' => 'failure', 'msg' => '已有此分類名稱']);
      die();
    }
  }
  
  echo json_encode(['status' => 'success', 'msg' => '新增成功']);

}elseif($action == 'switch_tab'){
  // 啟用 不啟用
  $promotions_sql = switch_tab($query_array['domain_name'],$query_array['sub_name'],$status_filter_query);

  // 專放分類
  $classification_sql = classification_sql($query_array['domain_name'],$query_array['sub_name'],$classification_query);

  $tab_listedit = switch_tab_html_o($promotions_sql,$classification_sql);
  echo $tab_listedit;

}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);
  echo 'ERROR';
};
?>