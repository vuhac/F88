<?php
// ----------------------------------------------------------------------------
// Features:	後台 - 顯示某個會員的組織樹狀圖（無限層數）
// File Name:	member_treemap_all.php
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
// 此計算程式所使用的 LIB
require_once dirname(__FILE__) ."/agent_profitloss_calculation_lib.php";

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
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li><a href="#">會員與加盟聯營股東</a></li>
	<li><a href="member.php">會員查詢</a></li>
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


// var_dump($_GET['id']);
// 如果 get 有 id
if(isset($_GET['id']) AND $_GET['id'] != NULL) {
	// 以登入的使用者，為預設的 id
	// $member_id = $_GET['id'];
	$member_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

	$sql = "SELECT * FROM root_member WHERE id = '".$member_id."';";
	$r = runSQLALL($sql);

	// 判斷式否正常只能有一個帳號, 並取得正常的資料。
	if($r[0] == 1) {
		$user = $r[1];
		// 正常
	}else{
		// echo 'ID 不存在，以登入的使用者，為預設的 id. ';
		// 以登入的使用者，為預設的 id
		$member_id = $_SESSION['agent']->id;
		$sql = "SELECT * FROM root_member WHERE id = '".$member_id."';";
		$r = runSQLALL($sql);
		$user = $r[1];
	}

}else{
	// 以登入的使用者，為預設的 id
	// echo '以登入的使用者，為預設的 id. ';
	$member_id = $_SESSION['agent']->id;
	$sql = "SELECT * FROM root_member WHERE id = '".$member_id."';";
	$r = runSQLALL($sql);
	$user = $r[1];
}
//var_dump($sql);


// --------------------------------------------------------------------------------
// (0) 指定查詢的帳號，往上查詢到 root 的層數
// --------------------------------------------------------------------------------



// -------------------------------------------
// 找出會員所在的 tree 直到 root
// -------------------------------------------
$findroot_tree = find_parent_node($member_id);
//var_dump($findroot_tree);
$item2root_html = '';
// 計算有幾代
$item2root_count = 0;
$findroot_tree_count = count($findroot_tree[$member_id]);
for($j=($findroot_tree_count-1);$j>=0;$j--){
	$find_pnode = $findroot_tree[$member_id][$j];

	if($find_pnode->therole == 'A' OR $find_pnode->therole == 'R') {
		$item_mark = '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>';
	}else{
		$item_mark = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>';
	}
	if($j == 0) {
		$account_icon_classcolor = 'btn btn-primary btn-sm';
	}else{
		$account_icon_classcolor = 'btn btn-default btn-sm';
	}
	$item2root_html = $item2root_html.'<a href="member_treemap.php?id='.$find_pnode->id.'" class="'.$account_icon_classcolor.'" role="button">'.$item_mark.$find_pnode->account.'</a>&nbsp;';
	if($j>0) {
		$item2root_html = $item2root_html.'<span class="glyphicon glyphicon-arrow-right" aria-hidden="true"></span>';
	}


	$item2root_count = $item2root_count + 1;
}

$item2root_html = '<p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>帳號往上到 root 共有'.$item2root_count.'層</p>'.$item2root_html.'<hr>';
// 從使用者到 root 的路徑及階層數量。
// var_dump($item2root_html);


// --------------------------------------------------------------------------------
// (1) 管理員觀看會員結構
// --------------------------------------------------------------------------------

// --------------------------------------------------------------------------
// 列出本身下面直接 第一線 會員數量 count , 及列出會員可以提供點擊查詢
// --------------------------------------------------------------------------
$member_info_string = '';

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



// --------------------------------------------------------------------------
// 依據使用者身份不同，顯示不同的圖示 R A M
// --------------------------------------------------------------------------
if($user->therole == 'A') {
	$user_mark = '<span class="glyphicon glyphicon-knight" aria-hidden="true"></span>';
}elseif($user->therole == 'R'){
	$user_mark = '<span class="glyphicon glyphicon-king" aria-hidden="true"></span>';
}else{
	$user_mark = '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>';
}
$listuser_title = '<span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>目前查詢的帳號&nbsp;<a href="member_account.php?a='.$user->id.'" class="btn btn-success btn-sm" role="button">'.$user_mark.'&nbsp;'.$user->account.'</a><hr>';

// --------------------------------------------------------------------------
// 會員下線列表
// --------------------------------------------------------------------------
$member_list_html = $listuser_title.$item2root_html.'
	<span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>帳號往下第1代帳號列表, '.$member_info_string.'
	<p style="line-height: 36px;">'.$item.'</p><hr>';

// 加入顯示行列
$member_list_html = $member_list_html;



// --------------------------------------------------------------------------------
// (2) 計算產生會員關係的 JSON
// --------------------------------------------------------------------------------

// ----------------------
// 從指定的 $member_id 找出往下 4 代，level 0 到 level 3 的 tree 關係圖 json 產生
// 格式為了配合 D3 的套件，所以使用這樣的寫法可以直接輸出為陣列。目前上限未知。
// ----------------------
	// tree 用 css
	$member_tilford_tree_head = '';

	// 葉數
	$tree0_leaf_count = 0;
	$tree1_leaf_count = 0;
	$tree2_leaf_count = 0;
	$tree3_leaf_count = 0;

  $current_datepicker = date("Y-m-d h");
	// level 0
  $r = find_children_memcache($member_id, $current_datepicker);
	//$r = find_children($member_id);
	$tree0 = $r['account_list'];
	$next0 = $r['next_children'];
	var_dump($r);
	var_dump($tree0);
	var_dump($next0);
	// 算一下 level 0 leaf 數量
	$tree0_leaf_count =  count($next0);

  // 把所有的節點掃描一次, 屬於 therole A 的存入 stack , 等等取出運算

  // 依據
  function find_children_next($r, $next0, $current_datepicker){
    // 計算本層有多少節點
    $tree1_leaf_count = 0;

    // level 1 連接
  	$n_count = count($next0);
  	for($n=0;$n<$n_count;$n++) {
  		$parent_index = $n;
  		$children_id = $next0[$n];
  		// 將上一層的樹，和下一層的樹接起來。
      $r = find_children_memcache($children_id, $current_datepicker);
  		$tree1 = $r['account_list'];
  		$next1 = $r['next_children'];
  		$tree0['children'][$parent_index]['children'] = $r['account_list']['children'];
  		//var_dump($tree0['children'][$parent_index]['children']);

  		// 算 level 1 leaf 數量
  		$next1_count = count($next1);
  		$tree1_leaf_count = $tree1_leaf_count + $next1_count;
    }

    $result['tree1_leaf_count']  = $tree1_leaf_count;
    $result['next1']             = $next1;
    $result['leaf_data']         = $r;
    $result['tree1']             = $tree1;
    $result['tree0']             = $tree0;

    return($result);
  }

  $result = find_children_next($r, $next0, $current_datepicker);
  var_dump($result);




/*
	// level 1 連接
	$n_count = count($next0);
	for($n=0;$n<$n_count;$n++) {
		$parent_index = $n;
		$children_id = $next0[$n];
		// 將上一層的樹，和下一層的樹接起來。
    $r = find_children_memcache($children_id, $current_datepicker);
		//$r = find_children($children_id);
		$tree1 = $r['account_list'];
		$next1 = $r['next_children'];
		$tree0['children'][$parent_index]['children'] = $r['account_list']['children'];
		//var_dump($tree0['children'][$parent_index]['children']);

		// 算 level 1 leaf 數量
		$next1_count = count($next1);
		$tree1_leaf_count = $tree1_leaf_count + $next1_count;


		// level 2
		//var_dump($next1);
		$n2_count = count($next1);
		for($n2=0;$n2<$n2_count;$n2++) {

			$parent_index2 = $n2;
			$children_id2 = $next1[$n2];
			// 將上一層的樹，和下一層的樹接起來。
      $r2 = find_children_memcache($children_id2, $current_datepicker);
			//$r2 = find_children($children_id2);
			$tree2 = $r2['account_list'];
			$next2 = $r2['next_children'];
			$tree0['children'][$parent_index]['children'][$parent_index2]['children'] = $r2['account_list']['children'];

			// 算 level 2 leaf 數量
			$next2_count = count($next2);
			$tree2_leaf_count = $tree2_leaf_count + $next2_count;


			// level 3
			//var_dump($next2);
			$n3_count = count($next2);
			for($n3=0;$n3<$n3_count;$n3++) {

				$parent_index3 = $n3;
				$children_id3 = $next2[$n3];
				// 將上一層的樹，和下一層的樹接起來。
        $r3 = find_children_memcache($children_id3, $current_datepicker);
				//$r3 = find_children($children_id3);
				$tree3 = $r3['account_list'];
				$next3 = $r3['next_children'];
				$tree0['children'][$parent_index]['children'][$parent_index2]['children'][$parent_index3]['children'] = $r3['account_list']['children'];

				// 算 level 3 leaf 數量
				$next3_count = count($next3);
				$tree3_leaf_count = $tree3_leaf_count + $next3_count;
			}
			// end level 3

		}
		// end level 2
	}
	// end level 1
*/

	// -----------------------
	// level 0 到 level 3 的 tree 關係圖 json 產生
	// -----------------------
	// 將 array 轉成 json tree
	$tree_json = json_encode($tree0);
	//echo $tree_json;

	// 葉子的數量 , level 0 ,1 ,2 ,3 各層的會員數量。
	//var_dump($tree0_leaf_count);
	//var_dump($tree1_leaf_count);
	//var_dump($tree2_leaf_count);
	//var_dump($tree3_leaf_count);
	$member_tree_stats_html = '<p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true"></span>帳號的4代人數分佈：<br>';
	$member_tree_stats_html = $member_tree_stats_html."帳號的第 1 代有 $tree0_leaf_count 人<br>";
	$member_tree_stats_html = $member_tree_stats_html."帳號的第 2 代有 $tree1_leaf_count 人<br>";
	$member_tree_stats_html = $member_tree_stats_html."帳號的第 3 代有 $tree2_leaf_count 人<br>";
	$member_tree_stats_html = $member_tree_stats_html."帳號的第 4 代有 $tree3_leaf_count 人<br>";
	$member_tree_stats_html = $member_tree_stats_html."</p><hr>";



	// ----------------
	// 顯示會員關係圖
	// ----------------
	// ref: https://bl.ocks.org/mbostock/4339184 全部展開
	// ref: https://bl.ocks.org/mbostock/4339083 可以點開
	// tree add link ref: http://bl.ocks.org/serra/5012770

	// 葉數決定高度，寬度3代剛好
	// 60 leaf --> 1200 px , 約 20px/leaf
	$member_tilford_tree_height = ($tree1_leaf_count+$tree2_leaf_count+$tree3_leaf_count+$tree0_leaf_count)*18;
	if($member_tilford_tree_height <= 100) {
		$member_tilford_tree_height = 400;
	}
	$member_tilford_tree_width 	= 960;


	// SVG 樹狀圖畫在哪裡
	$member_tilford_tree_html = '<p><span class="glyphicon glyphicon glyphicon-tree-deciduous" aria-hidden="true">帳號：'.$user->account.'&nbsp;會員4代組織圖</p>';
	/*
	$member_tilford_tree_html = $member_tilford_tree_html.'&nbsp;<span class="label label-info">
	帳號:('.$_SESSION['member']->id.')'.$_SESSION['member']->account.'</span></h4>';'
	*/
	$member_tilford_tree_html = $member_tilford_tree_html.'<tilford_tree_area></tilford_tree_area>';

	// 樹狀圖的關係資料 JSON 格式
	// $member_tilford_tree_jsonfile = 'test/flare.json';
	$member_tilford_tree_jsonfile = 'tmp_jsondata/member_tree_'.$member_id.'.json';
	file_put_contents("$member_tilford_tree_jsonfile", "$tree_json");

	// D3 v3 api ref: https://github.com/d3/d3-3.x-api-reference/blob/master/Requests.md
	// 繪製樹狀圖的 javascript
	// var_dump($tree_json);

	// D3 JS show tree
	$member_tilford_tree_html = $member_tilford_tree_html.'
	<script src="./in/d3/d3.v3.min.js"></script>
	<script src="./in/d3/highlight.min.js"></script>
	<script>

	var width = '.$member_tilford_tree_width.',
	    height = '.$member_tilford_tree_height.';

	var tree = d3.layout.tree()
	    .size([height, width - 160]);

	var diagonal = d3.svg.diagonal()
	    .projection(function(d) { return [d.y, d.x]; });

	var svg = d3.select("tilford_tree_area").append("svg")
	    .attr("width", width)
	    .attr("height", height)
	  .append("g")
	    .attr("transform", "translate(40,0)");

	d3.json("'.$member_tilford_tree_jsonfile.'", function(error, json) {
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
	          .attr("width", 50)
	          .attr("height", 12)
	          .style("fill", "lightsteelblue")
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

	// 組合 html D3  + css
	$member_tilford_content		= $member_tilford_tree_head.$member_tilford_tree_html;


	// ----------------------------------------------------------------------------
	// 排版
	// ----------------------------------------------------------------------------
	$indexbody_content = '
	<div class="row">
	  <div class="col-12 col-md-12">
	  	'.$member_list_html.'
	  </div>
		<div class="col-12 col-md-12">
	  	'.$member_tree_stats_html.'
	  </div>
		<div class="col-12 col-md-12">
	  	'.$member_tilford_content.'
	  </div>
	</div>
	<br>
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
