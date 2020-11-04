<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 針對 systemconfig_announce_read  執行對應動作
// File Name: systemconfig_announce_read_action.php
// Author:    Mavis
// Related:   systemconfig_announce_read.php
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

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(isset($_GET['a']) AND $_SESSION['agent'] ->therole == 'R'){
  $action = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
} else {
  die('(x)不合法的測試');
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
// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數  END
// -------------------------------------------------------------------------


// ---------------------------------------------------
// 公告 新增已讀功能
// --------------------------------------------------

// click我知道了
if($action == 'read' AND (isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' )){

  // watchingstatus:
  // 0 = 未讀
  // 1 = 確認已讀
  // 2 = 已讀不確認

  // 公告編號
  $read_id = filter_var($_POST['id'],FILTER_SANITIZE_NUMBER_INT);
  // 讀取時間
  $announce_time = date('Y-m-d H:i:s');

  if($read_id != '') {
    // 檢查site_announcement_status有沒有這筆資料，有 就update,沒有就 insert
      $sql = <<<SQL
        SELECT * FROM site_announcement_status WHERE account = '{$_SESSION['agent']->account}' AND ann_id = '{$read_id}'
SQL;

    if (!runSQL($sql)) {
      $sql = <<<SQL
        INSERT INTO site_announcement_status (account,ann_id,watchingtime,watchingstatus) VALUES ('{$_SESSION['agent']->account}','{$read_id}','{$announce_time}','1') 
SQL;
        // echo '<script>alert("新增已讀");</script>';
        //die();
    }else{
      $sql=<<<SQL
        UPDATE site_announcement_status SET watchingstatus = '1' WHERE account = '{$_SESSION['agent']->account}' AND ann_id = '{$read_id}'
SQL;
    }

    // echo '<script>alert("已讀過不新增");</script>';
        
      $insert_result = runSQL($sql);

  }

} elseif( $action == 'select' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
    // 已讀、未讀、全部  
   
    $query_type = filter_var($_GET['query'],FILTER_SANITIZE_STRING);

    if ($query_type == 'read') {
      $sql = <<<SQL
        SELECT * FROM site_announcement AS announcement
          WHERE id IN
            (SELECT ann_id FROM site_announcement_status AS status
              WHERE status.account='{$_SESSION['agent']->account}' AND status.watchingstatus='1'
            ) 
            AND effecttime <= current_timestamp 
            AND endtime >= current_timestamp
            AND status = '1'
SQL;
          //var_dump($sql);
          //die();
    } elseif($query_type == 'unread') {
      $sql = <<<SQL
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
SQL;
        //var_dump($sql);
        //die();
    }else {
      $sql = <<<SQL
        SELECT * FROM site_announcement 
          WHERE showinmessage = '1' 
            AND status = '1'
            AND effecttime <= current_timestamp 
            AND endtime >= current_timestamp
SQL;
        //var_dump($sql);
        //die();
    }

  //處理 datatables 傳來的排序需求
    if( isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != '') {

      if($_GET['order'][0]['dir'] == 'asc'){ 
        $sql_order_dir = 'ASC';
      }else{ 
        $sql_order_dir = 'DESC';
      }

      if($_GET['order'][0]['column'] == 0){ 
        $sql_order = 'ORDER BY id '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 1){ 
        $sql_order = 'ORDER BY watchingtime '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 2){ 
        $sql_order = 'ORDER BY effecttime '.$sql_order_dir;
      }elseif($_GET['order'][0]['column'] == 3){ 
        $sql_order = 'ORDER BY ann_id '.$sql_order_dir;
      }else{ 
        $sql_order = 'ORDER BY id ASC';
      }
    }else{ 
      $sql_order = 'ORDER BY id ASC';
    }

    /*
    $sql_select = <<<SQL
      {$sql_order} LIMIT 100
SQL;
    */
    
    // 算資料數
    $count_list_sql = $sql.';';
    $count_list = runSQL($count_list_sql);

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

    $datalist = runSQLall($list_sql); 
    // var_dump($datalist);
    // die();
    
    // 閱讀button的內容
    $show = '';

    if($datalist[0] >=1 ) {

      for($i=1;$i<=$datalist[0];$i++) {
    
        // 表格內所要顯示的資料
        $ann['id'] = $datalist[$i]->id;
        $ann['title'] = $datalist[$i]->title; 
        $ann['name'] = $datalist[$i]->name;
        $ann['content'] = htmlspecialchars_decode($datalist[$i]->content); // 去除html tags
        $ann['effecttime'] = gmdate('Y-m-d H:i:s', strtotime($datalist[$i]->effecttime.' -04') + 8*3600 ). '-04'; // 美東時間
        
        // click閱讀button後會出現的內容(modal)
        $id = $datalist[$i]->id;
        $title = $datalist[$i]->title;
        $name = $datalist[$i]->name;
        $content = htmlspecialchars_decode($datalist[$i]->content);
        $effecttime = gmdate('Y-m-d H:i:s', strtotime($datalist[$i]->effecttime.' -04') + 8*3600). '-04';

        $show =<<<HTML
          <td>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal$query_type$id">{$tr['readed']}</button>
          </td>
        <!-- modal -->
          <div class="modal fade" id="modal$query_type$id" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                  <div class="modal-header">
                  <h3 class="modal-title" id="exampleModalLabel">{$tr['announcement name']} : {$name} </h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>                    
                   </div>
                  <div class="modal-body">
                    {$tr['announcement title']} : {$title}
                    <br>
                    {$tr['Starting time']} : {$effecttime}
                    <br>
                    {$tr['announcement content']}: {$content}
                    
                  </div>
                  <div class="modal-footer footer">
                    <a href="systemconfig_announce_read.php?s={$id}"><button type="button" class="btn btn-primary readed" name="watchingstatus" id="watchingstatus" value="$id" data-dismiss="modal">{$tr['i know']}</button></a>
                  </div>
              </div>
            </div>
          </div>
HTML;

      // 顯示的表格資料內容
        $show_array[] = array(
           'id' => $ann['id'],
           'name' => mb_substr($ann['name'],0,8,"utf-8"),
           'title' => $ann['title'],
           'content' => mb_substr($ann['content'],0,15,"utf-8"),
           'effecttime' => $ann['effecttime'],
           'details' => $show
        );
      }

      $output = array(
        "sEcho" => intval($secho),
        "iTotalRecords" => intval($page['per_size']),
        "iTotalDisplayRecords" => intval($page['all_records']),
        "data" => $show_array
      );

  } else{
      $output = array(
        "sEcho" => 0,
        "iTotalRecords" => 0,
        "iTotalDisplayRecords" => 0,
        "data" => ''
      );
  }

echo json_encode($output);

} elseif($action == 'test') {
  var_dump($_POST);
  echo 'ERROR';
}

?>