<?php
// ----------------------------------------------------------------------------
// Features:	後台 - 顯示某個會員的組織樹狀圖（四層）
// File Name:	member_treemap.php
// Author:		Barkley
// Related:		index.html
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 專用處理會員樹的函式
require_once dirname(__FILE__) ."/member_treemap_lib.php";

// var_dump($_SESSION);


// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
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
$function_title 		= $tr['Member Treemap'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Member inquiry'] = '會員查詢';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
  <li><a href="member.php">'.$tr['Member inquiry'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
$extend_head			= '';
$extend_js				= '';
$paneltitle_content 	= '';
$panelbody_content		= '';
$page_title				= '';


// title and 功能說明文字
$page_title	= $page_title.'<h2><strong>'.$tr['Member Treemap'].'</strong></h2><hr>';



// --------------------------------------------------------------------------------
// (0) 指定查詢的帳號，往上查詢到 root 的層數
// --------------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main start
// ----------------------------------------------------------------------------

// var_dump($_GET['id']);
// 如果 get 有 id
if(isset($_GET['id']) AND $_GET['id'] != NULL) {
  // 以登入的使用者，為預設的 id
  // $member_id = $_GET['id'];
  $member_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
  $sql = "SELECT * FROM root_member WHERE id = '".$member_id."';";
  $r = runSQLall($sql);

  // 判斷式否正常只能有一個帳號, 並取得正常的資料。
  if($r[0] == 1) {
    $user = $r[1];
    // 正常
  }else{
    // echo 'ID 不存在，以登入的使用者，為預設的 id. ';
    // 以登入的使用者，為預設的 id
    $member_id = $_SESSION['agent']->id;
    $sql = "SELECT * FROM root_member WHERE id = '".$member_id."';";
    $r = runSQLall($sql);
    $user = $r[1];
  }

}else{
  // 以登入的使用者，為預設的 id
  // echo '以登入的使用者，為預設的 id. ';
  $member_id = $_SESSION['agent']->id;
  $sql = "SELECT * FROM root_member WHERE id = '".$member_id."';";
  $r = runSQLall($sql);
  $user = $r[1];
}
//var_dump($sql);




// salt 用來當作亂術密碼使用,避免被刺探 json 檔案名稱
$member_salt =  $user->salt;
//var_dump($member_salt);
//var_dump($user);

// -------------------------------------------
// 找出會員所在的 tree 直到 root
// -------------------------------------------
//var_dump($member_id);
// 如果查詢的使用者 account 是 mtchang , 檢查是否為他的上線(上線才有權限查詢)
$current_query_account = $_SESSION['agent']->account;
$check_is_agentsmember = check_is_agentsmember($member_id, $current_query_account);
// var_dump($check_is_agentsmember);

if($_SESSION['agent']->therole == 'R') {
  //echo("目前帳號 $current_query_account 身份為管理員，可以查詢。");
}elseif($check_is_agentsmember['current_query_account_permission'] == true) {
  //echo("目前帳號 $current_query_account 和目前查詢ID $member_id 有上下層關係，可以查詢。");
}else{
  //die("目前帳號 $current_query_account 和目前查詢ID $member_id 沒有上下層關係，所以不能查詢。");

}



// --------------------------------------------------------------------------------
// (1) 管理員觀看會員結構
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 列出本身下面直接 第一線 會員數量 count , 及列出會員可以提供點擊查詢
// --------------------------------------------------------------------------
$member_info_string = '';
/*
$sql_M = "SELECT id,account,parent_id, therole FROM root_member WHERE parent_id = $member_id;";
$sql_M_result = runSQLALL($sql_M);
// var_dump($sql_M_result);
$member_info_string = '會員<span class="badge">'.$sql_M_result[0].'</span><span class="glyphicon glyphicon-user" aria-hidden="true"></span>';

$sql_A = "SELECT id,account,parent_id, therole FROM root_member WHERE therole = 'A' AND parent_id = $member_id;";
$sql_A_result = runSQLALL($sql_A);
$member_info_string = $member_info_string.'(其中有<span class="badge">'.$sql_A_result[0].'</span><span class="glyphicon glyphicon-knight" aria-hidden="true"></span>為代理商)';


$item = '';
for($i=1;$i<=$sql_M_result[0];$i++) {
  // var_dump($M_value);
  if($sql_M_result[$i]->therole == 'A' OR $sql_M_result[$i]->therole == 'R') {
    $item_mark = '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>';
    $item = $item.'<a href="member_treemap.php?id='.$sql_M_result[$i]->id.'" class="btn btn-success btn-sm" role="button">'.$item_mark.$sql_M_result[$i]->account.'</a>&nbsp;';
  }else{
    $item_mark = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>';
    $item = $item.'<a href="member_treemap.php?id='.$sql_M_result[$i]->id.'" class="btn btn-default btn-sm" role="button">'.$item_mark.$sql_M_result[$i]->account.'</a>&nbsp;';
  }
}
*/


// --------------------------------------------------------------------------
// 依據使用者身份不同，顯示不同的圖示 R A M
// --------------------------------------------------------------------------
$user_title = '';
if($user->therole == 'A') {
  $user_mark = '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>';
  $user_title = '代理商';
}elseif($user->therole == 'R'){
  $user_mark = '<span class="glyphicon glyphicon-king" aria-hidden="true"></span>';
  $user_title = '管理员';
}else{
  $user_mark = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>';
  $user_title = '会员';
}
// output 1
$listuser_title = '<span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>
目前查詢的帳號&nbsp;<a href="member_account.php?a='.$user->id.'" class="btn btn-default btn-sm" role="button">'.$user_mark.'&nbsp;'.$user->account.'</a>';

$listuser_title = $listuser_title.'身份为'.$user_title;

// --------------------------------------------------------------------------
// MAIN
// --------------------------------------------------------------------------

  // 查詢深度
  $query_depth = 4;

  // 目前查询帳號
  $member_list_html = '<p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>'.
  '&nbsp;'.$tr['Query account'].'&nbsp;<a href="member_account.php?a='.$user->id.'" class="btn btn-default btn-sm" role="button">'.$user->account.'</a></p><hr>';


  // 帳號往上到公司
  $member2root_list_title_html = '<p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true">'.$tr['the upline agents'].'</p>';

  // 帳號往上到公司 , 最多 20 代
  $member2root_list = get_member2root_list($user->id, $query_depth);
  //var_dump($member2root_list);

  // 轉成 html 輸出
  $member2root_list_html = '<p><a href="#" class="btn btn-default btn-sm" role="button">
  <span class="glyphicon glyphicon-king" aria-hidden="true"></span>
  公司
  </a>';
  for($i=$member2root_list[0];$i>=1;$i--) {


    if($member2root_list[$i]->therole == 'M') {
      $user_mark = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>';
    }elseif($member2root_list[$i]->therole == 'A'){
      $user_mark = '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>';
    }elseif($member2root_list[$i]->therole == 'R'){
      $user_mark = '<span class="glyphicon glyphicon-king" aria-hidden="true"></span>';
    }else{
      $user_mark = '<span class="glyphicon glyphicon-baby-formula" aria-hidden="true"></span>';
    }

    $member2root_list_node = '<a href="member_treemap.php?id='.$member2root_list[$i]->id.'" class="btn btn-default btn-sm" role="button">
    '.$user_mark.$member2root_list[$i]->account.'</a>';

    $member2root_list_html = $member2root_list_html.'<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>'.$member2root_list_node;
  }

  $member2root_list_html = $member2root_list_html.'</p><hr>';

/*
  // 限制使用者需要為該會員下線會員
  $checkin_member2root_list_return = checkin_member2root_list( $_SESSION['member']->id, 20, $user->id );
  var_dump($checkin_member2root_list_return);

  $checkin_member2root_list_return = checkin_member2root_list( 18, 20, $user->id );
  var_dump($checkin_member2root_list_return);
  if($checkin_member2root_list_return[0] == 0) {
    die('此帳號非你下線');
  }
*/



  // ID = 18, 往下深度 4
  // $agent_treemap = get_agent_treemap($user->id, 3);
  //var_dump($agent_treemap);

  // 表格標題
  $table_colname_html = '
  <tr>
  <td>ID</td>
  <td>'.$tr['Member Identity'].'</td>
  <td>'.$tr['member'].$tr['Account'].'</td>
  <td>'.$tr['first_store_report-member-member_content-registered_datetime'].'</td>
  <td>'.$tr['realname'].'</td>
  <td>'.$tr['State'].'</td>
  <td>'.$tr['Next generation'].'</td>
  <td>'.$tr['Subordinate level'].'</td>
  </tr>
  ';

  // 列出資料, 主表格架構
  $show_list_html = '
  <table id="show_list" class="display" cellspacing="0" width="100%">
    <thead>
    '.$table_colname_html.'
    </thead>
    <tfoot>
    '.$table_colname_html.'
    </tfoot>
  </table>
  ';


  // 參考使用 datatables 顯示
  // https://datatables.net/examples/styling/bootstrap.html
  // 加 datetime picker 及 datatables 的 js
  $extend_head =
  '<!-- Jquery UI js+css  -->
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
      var table = $("#show_list").DataTable( {
          "bProcessing": true,
          "bServerSide": true,
          "bRetrieve": true,
          "searching": false,
          "ajax": "member_treemap_action.php?id='.$user->id.'",
          "columns": [
            { "data": "id"},
            { "data": "therole", "orderable": false },
            { "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"&t='.$csrftoken.'\' blank=\"_BLANK\"  >"+oData.account+"</a>")} },
            { "data": "enrollmentdate"},
            { "data": "nickname"},
            { "data": "status", "orderable": false },
            { "data": "depth"},
            { "data": "parent_id_count"}
          ]
      } );
    } )

    function query_str(csrftoken, account){
      //console.log(account);
      //console.log(csrftoken);
      var goto_url = "member_treemap.php?id=account&t=csrftoken";
      console.log(goto_url);
    }
  </script>
    ';
// $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"&account="+oData.account+"&token='.$csrftoken.'\'>"+oData.account+"</a>")}};








// ----------------------------------------------------------------------------
// Start D3 tree
// ----------------------------------------------------------------------------

  // 格式為了配合 D3 的套件，所以使用這樣的寫法可以直接輸出為陣列。目前上限未知。


  // -----------------------
  // level 0 到 level 3 的 tree 關係圖 json 產生
  // -----------------------
  // 將 array 轉成 json tree
  $root_tree  = root_tree($member_id);
  // print("<pre>" . print_r($root_tree['tree_leaf_count'], true) . "</pre>");die();
  $tree_json = json_encode($root_tree['tree'][0]);
  // echo $tree_json;

  // 葉子的數量 , level 0 ,1 ,2 ,3 各層的會員數量。
  //var_dump($tree0_leaf_count);
  //var_dump($tree1_leaf_count);
  //var_dump($tree2_leaf_count);
  //var_dump($tree3_leaf_count);
  // 帳號下的的4代人數分佈
  $member_tree_stats_html = '<p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>'.$tr['level 1~4 Number of people  statistics'].'<br>';
  $member_tree_stats_html = $member_tree_stats_html.$tr['Account'].$tr['1st generation member'].$root_tree['tree_leaf_count'][0]." 人<br>";
  $member_tree_stats_html = $member_tree_stats_html.$tr['Account'].$tr['2nd generation member'].$root_tree['tree_leaf_count'][1]." 人<br>";
  $member_tree_stats_html = $member_tree_stats_html.$tr['Account'].$tr['3rd generation member'].$root_tree['tree_leaf_count'][2]." 人<br>";
  $member_tree_stats_html = $member_tree_stats_html.$tr['Account'].$tr['4th generation member'].$root_tree['tree_leaf_count'][3]." 人<br>";
  $member_tree_stats_html = $member_tree_stats_html."</p><hr>";



  // ----------------
  // 顯示會員關係圖
  // ----------------
  // ref: https://bl.ocks.org/mbostock/4339184 全部展開
  // ref: https://bl.ocks.org/mbostock/4339083 可以點開
  // tree add link ref: http://bl.ocks.org/serra/5012770

  // 葉數決定高度，寬度3代剛好
  // 60 leaf --> 1200 px , 約 20px/leaf
  $member_tilford_tree_height = ($root_tree['tree_leaf_count'][0]+$root_tree['tree_leaf_count'][1]+$root_tree['tree_leaf_count'][2]+$root_tree['tree_leaf_count'][3])*18;
  if($member_tilford_tree_height <= 100) {
    $member_tilford_tree_height = 400;
  }
  $member_tilford_tree_width 	= 1138;



  // tree 用 css
  $member_tilford_tree_head = '';
  // SVG 樹狀圖畫在哪裡
  $member_tilford_tree_html = '<hr><p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true">'.$tr['Account'].'：'.$user->account.'&nbsp;'.$tr['Member 4th generation organization chart'].'</p>';
  // 沒有資料就顯示沒有資料
  if($tree_json == 'null') {
    $member_tilford_tree_html = '<hr><p>'.$tr['No next-generation data'].'</p>';
  }else{
    // 確認有資料才寫入 json 及顯示

    $member_tilford_tree_html = $member_tilford_tree_html.'<tilford_tree_area></tilford_tree_area>';
    // 樹狀圖的關係資料 JSON 格式
    // 產生 sha1 亂碼的檔案 json + $member_salt , 避免被猜出來
    $member_tree_filename = 'member_tree_'.sha1($member_salt.$member_id).'.json';
    //var_dump($member_tree_filename);
    // 絕對路徑 -- 寫入 json 用
    $member_tilford_tree_jsonfile = dirname(__FILE__).'/tmp_jsondata/'.$member_tree_filename;
    // var_dump(file_put_contents("$member_tilford_tree_jsonfile", "$tree_json"));
    // die();
    // 2019/05/16，vip站會員關係圖，檔案存在時，並不會更新，故手動檢查，並刪除
    if (file_exists($member_tilford_tree_jsonfile)) {
      unlink($member_tilford_tree_jsonfile);
    }

    file_put_contents("$member_tilford_tree_jsonfile", "$tree_json");
    // 相對路徑 -- 顯示資料用
    $member_tilford_tree_jsonurl = 'tmp_jsondata/'.$member_tree_filename;

    // D3 v3 api ref: https://github.com/d3/d3-3.x-api-reference/blob/master/Requests.md
    // 繪製樹狀圖的 javascript
    //var_dump($tree_json);
    // D3 JS show tree
    // <script src="./in/d3/highlight.min.js"></script>
    $member_tilford_tree_html = $member_tilford_tree_html.'
    <script src="./in/d3/d3.v3.min.js"></script>

    <script>

    var width = '.$member_tilford_tree_width.',
        height = '.$member_tilford_tree_height.';

    var tree = d3.layout.tree()
        .size([height, width - 200]);

    var diagonal = d3.svg.diagonal()
        .projection(function(d) { return [d.y, d.x]; });

    var svg = d3.select("tilford_tree_area").append("svg")
        .attr("width", width)
        .attr("height", height)
      .append("g")
        .attr("transform", "translate(100,0)");

    d3.json("'.$member_tilford_tree_jsonurl.'", function(error, json) {
      if (error) throw error;

      var nodes = tree.nodes(json),
          links = tree.links(nodes);

      var link = svg.selectAll("path.link")
          .data(links)
          .enter().append("path")
          .attr("class", "link")
          .attr("d", diagonal);

      var node = svg.selectAll("g.node")
          .data(nodes)
          .enter().append("g")
          .attr("class", "node")
          .attr("transform", function(d) { return "translate(" + d.y + "," + d.x + ")"; })

      node.append("circle")
          .attr("r", 4.5);

      node.append("text")
          .attr("dx", function(d) { return d.children ? -8 : 8; })
          .attr("dy", 3)
          .attr("text-anchor", function(d) { return d.children ? "end" : "start"; })
          .text(function(d) { return d.name; });

      node
          .append("a")
             .attr("xlink:href", function(d) { return d.linkurl; })
          .append("rect")
              .attr("class", "clickable")
              .attr("y", -6)
              .attr("x", function (d) { return d.children || d._children ? -60 : 10; })
              .attr("width", 75)
              .attr("height", 12)
              .style("fill", function(d) { return d.color; })
              .style("fill-opacity", .3);
    });

    d3.select(self.frameElement).style("height", height + "px");

    </script>
    ';


    // 繪製 tree 多加需要的 css style , 否則很醜
    $member_tilford_tree_head = '
    <style>
      .node circle {
        fill: #fff;
        stroke: steelblue;
        stroke-width: 1.5px;
      }

      .node {
        font: 10px sans-serif;
      }

      .link {
        fill: none;
        stroke: #ccc;
        stroke-width: 1.5px;
      }
    </style>
    ';
  }
  // ----


  // 組合 html D3  + css
  $member_tilford_content		= $member_tilford_tree_head.$member_tilford_tree_html;
  // ----------------------------------------------------------------------------
  // END D3 tree
  // ----------------------------------------------------------------------------

  // ----------------------------------------------------------------------------
  // 排版
  // ----------------------------------------------------------------------------
  $indexbody_content = '
  <div class="row">
    <div class="col-12 col-md-12">
      '.$member_list_html.'
    </div>

    <div class="col-12 col-md-12">
      '.$member2root_list_title_html.'
    </div>
    <div class="col-12 col-md-12">
      '.$member2root_list_html.'
    </div>


    <div class="col-12 col-md-12">
      '.$member_tree_stats_html.'
    </div>


    <div class="col-12 col-md-12">
    '.$show_list_html.'
    </div>


    <div class="col-12 col-md-12">
      '.$member_tilford_content.'
    </div>
    <hr>
  </div>

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
include("template/beadmin.tmpl.php");

?>
