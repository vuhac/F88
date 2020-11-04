<?php
// ----------------------------------------------------------------------------
// Features:	後台--
// File Name:	member_treemap_lib.php
// Author:
// Related:
// Log:
// ----------------------------------------------------------------------------


// -------------------------------------------------------------------------
// 尋找符合業績達成的上層, 共 n 代. 直到最上層 root 會員。
// 再以計算出來的代數 account 判斷，哪些代數符合達成業績標準的會員。
// -------------------------------------------------------------------------
// 1.1 以節點找出使用者的資料 -- from root_member
// -------------------------------------------------------------------------
function find_member_node($member_id, $tree_level) {

// 加上 memcached 加速,宣告使用 memcached 物件 , 連接看看. 不能連就停止.
$memcache = new Memcached();
$memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
// 把 query 存成一個 key in memcache
$key = 'member_treemap_findroot'.$member_id;
$key_alive_show = sha1($key);

// 取得看看記憶體中，是否有這個 key 存在, 有的話直接抓 key
$getfrom_memcache_result = $memcache->get($key_alive_show);
if(!$getfrom_memcache_result) {

		$tree_level = $tree_level;
		//$tree_level = 1; 不論 status 為何, 都要找出來. 否則會 lost data 問題.
		$member_sql = "SELECT id, account, parent_id, therole, status FROM root_member WHERE id = '$member_id';";
		//var_dump($member_sql);
		$member_result = runSQLall($member_sql);
		//var_dump($member_result);
		if($member_result[0]==1){
			$tree = $member_result[1];
			$tree->level = $tree_level;
		}else{
			$logger ="ID = $member_id 資料遺失, 請聯絡客服人員處理.";
			die($logger);
		}


// save to memcached ref:http://php.net/manual/en/memcached.set.php
$memcached_timeout = 120;
$memcache->set($key_alive_show, $tree, time()+$memcached_timeout) or die ("Failed to save data at the memcache server");
//echo "Store data in the cache (data will expire in $memcached_timeout seconds)<br/>\n";
}else{
	// 資料有存在記憶體中，直接取得 get from memcached
	$tree = $getfrom_memcache_result;
}

//var_dump($tree);
return($tree);
}


// -------------------------------------------------------------------------
// 1.2 找出上層節點的所有會員，直到 root -- from root_member
// -------------------------------------------------------------------------
function find_parent_node($member_id) {

	// 最大層數 100 代
	$tree_level_max = 100;

	$tree_level = 0;
	// treemap 為正常的組織階層
	$treemap[$member_id][$tree_level] = find_member_node($member_id, $tree_level);

	// $treemap_performance 唯有達標的組織階層
	//$treemap_performance[$member_id][$tree_level] = find_agent_performance_node($member_id, $tree_level);
	while($tree_level<=$tree_level_max) {
		$m_id = $treemap[$member_id][$tree_level]->parent_id;
		$m_account = $treemap[$member_id][$tree_level]->account;
		$tree_level = $tree_level+1;
		// 如果到了 root 的話跳離迴圈。表示已經到了最上層的會員了。
		if($m_account == 'root') {
			break;
		}else{
			$treemap[$member_id][$tree_level] = find_member_node($m_id, $tree_level);
		}
	}

	// var_dump($treemap);
	return($treemap);
}
// -------------------------------------------------------------------------
// END function
// -------------------------------------------------------------------------






// --------------------------------------------------------------------------------
// (2) 計算產生會員關係的 JSON
// --------------------------------------------------------------------------------

// ----------------------
// 依據 id 找出 parent_id 為這個 id 的使用者
// ----------------------
function find_children($root_id) {

  // 加上 memcached 加速,宣告使用 memcached 物件 , 連接看看. 不能連就停止.
  $memcache = new Memcached();
  $memcache->addServer('localhost', 11211) or die ("Could not connect memcache server !! ");
  // 把 query 存成一個 key in memcache
  $key = 'member_treemap_find_children'.$root_id;
  $key_alive_show = sha1($key);

  // 取得看看記憶體中，是否有這個 key 存在, 有的話直接抓 key
  $getfrom_memcache_result = $memcache->get($key_alive_show);
  // print("<pre>" . print_r($getfrom_memcache_result, true) . "</pre>");die();
  if(!$getfrom_memcache_result) {

    // -----------------------
  	// $root_id = 1;
  	// level (root) , 找出上層為 root id 的 member. 但是帳號不能為 root
	$sql_root = "SELECT root_member.id, root_member.account, root_member.parent_id, root_member.therole 
				FROM root_member
				join root_member_wallets on root_member.id=root_member_wallets.id
	 			WHERE root_member.parent_id = '$root_id'  AND root_member.account != 'root'
	;";
	// echo($sql_root);
  	$rt = runSQLALL($sql_root);

  	// 該節點下面，沒有資料就不要做
  	if($rt[0] > 0) {
		$root_info = runSQLALL_prepared("SELECT * FROM root_member WHERE id = $root_id")[0] ?? null;
		$t0['name'] = '('.$root_id.')'.$root_info->account;
  		for($i=0;$i<$rt[0];$i++) {
  			$j = $i+1;
  			// 樹狀中顯示的文字
  			$t[$i]['name'] = '('.$rt[$j]->id.')'.$rt[$j]->account;
        // 樹狀中顯示的文字
        // 顏色屬性參數
        if($rt[$j]->therole == 'A'){
            $t[$i]['color'] = "#8cc152";
        }elseif($rt[$j]->therole == 'R'){
            $t[$i]['color'] = "#a94442";
        }else{
            $t[$i]['color'] = "lightsteelblue";
        }
  			// 連結屬性參數 use onclick href
  			$t[$i]['linkurl'] = "member_treemap.php?id=".$rt[$j]->id;
  			// test url
  			$t[$i]['size'] = rand(500,1000);
  			//$t[$i]['balance'] = rand(9500,91000);
  			$t0['children'][$i]	= $t[$i];
  			// 下一個 children 的 id list
  			$next_children[$i] = $rt[$j]->id;
  		}
  	}else{
  		$next_children = NULL;
  		$t0 = NULL;
  	}

  	// 下一個 children 的 id list
  	//var_dump($next_children);
  	// 每一個 id 的 account list
  	//var_dump($t0);

  	$r['next_children'] = $next_children;
  	$r['account_list'] = $t0;
    // -----------------------



  // save to memcached ref:http://php.net/manual/en/memcached.set.php
  $memcached_timeout = 120;
  $memcache->set($key_alive_show, $r, time()+$memcached_timeout) or die ("Failed to save data at the memcache server");
  //echo "Store data in the cache (data will expire in $memcached_timeout seconds)<br/>\n";
  }else{
  	// 資料有存在記憶體中，直接取得 get from memcached
  	$r = $getfrom_memcache_result;
  }


	return($r);
}
// ----------------------
// 依據 id 找出 parent_id 為這個 id 的使用者 END
// ----------------------


// ----------------------
// 從指定的 $member_id 找出往下 4 代，level 0 到 level 3 的 tree 關係圖 json 產生
// root_tree($member_id) 從 $member_id 節點, 計算到四層的末端
// ----------------------
function root_tree($member_id){

	// 葉數
	$tree0_leaf_count = 0;
	$tree1_leaf_count = 0;
	$tree2_leaf_count = 0;
	$tree3_leaf_count = 0;
	// level 0
	$r = find_children($member_id);
	// print("<pre>" . print_r($r, true) . "</pre>");die();
	$tree0 = $r['account_list'];
	$next0 = $r['next_children'];
	//var_dump($r);
	//var_dump($tree0);
	//var_dump($next0);
	// 算一下 level 0 leaf 數量
	$tree0_leaf_count = (is_array($next0)) ? count($next0) : 0;

	// level 1 連接
	$n_count = (is_array($next0)) ? count($next0) : 0;
	for($n=0;$n<$n_count;$n++) {
		$parent_index = $n;
		$children_id = $next0[$n];
		// 將上一層的樹，和下一層的樹接起來。
		$r = find_children($children_id);
		$tree1 = $r['account_list'];
		$next1 = $r['next_children'];
		$tree0['children'][$parent_index]['children'] = $r['account_list']['children'];
		//var_dump($tree0['children'][$parent_index]['children']);

		// 算 level 1 leaf 數量
		$next1_count = (is_array($next1)) ? count($next1) : 0;
		$tree1_leaf_count = $tree1_leaf_count + $next1_count;


		// level 2
		//var_dump($next1);
		$n2_count = (is_array($next1)) ? count($next1) : 0;
		for($n2=0;$n2<$n2_count;$n2++) {

			$parent_index2 = $n2;
			$children_id2 = $next1[$n2];
			// 將上一層的樹，和下一層的樹接起來。
			$r2 = find_children($children_id2);
			$tree2 = $r2['account_list'];
			$next2 = $r2['next_children'];
			$tree0['children'][$parent_index]['children'][$parent_index2]['children'] = $r2['account_list']['children'];

			// 算 level 2 leaf 數量
			$next2_count = (is_array($next2)) ? count($next2) : 0;
			$tree2_leaf_count = $tree2_leaf_count + $next2_count;


			// level 3
			//var_dump($next2);
			$n3_count = (is_array($next2)) ? count($next2) : 0;
			for($n3=0;$n3<$n3_count;$n3++) {

				$parent_index3 = $n3;
				$children_id3 = $next2[$n3];
				// 將上一層的樹，和下一層的樹接起來。
				$r3 = find_children($children_id3);
				$tree3 = $r3['account_list'];
				$next3 = $r3['next_children'];
				$tree0['children'][$parent_index]['children'][$parent_index2]['children'][$parent_index3]['children'] = $r3['account_list']['children'];

				// 算 level 3 leaf 數量
				$next3_count = (is_array($next3)) ? count($next3) : 0;
				$tree3_leaf_count = $tree3_leaf_count + $next3_count;
			}
			// end level 3

		}
		// end level 2
	}
	// end level 1

  // 葉數
	$r['tree_leaf_count'][0]  = $tree0_leaf_count;
  $r['tree_leaf_count'][1]  = $tree1_leaf_count;
	$r['tree_leaf_count'][2]  = $tree2_leaf_count;
	$r['tree_leaf_count'][3]  = $tree3_leaf_count;

  // 節點資料
  $r['tree'][0]  = $tree0;

return($r);
}
// -------------------------------
// end root_tree
// -------------------------------


// -------------------------------------------
// 函式: 檢查是否為你的上線並列出從指定 member_id 到 root 的所有節點
// 因為透過 memcache 所以只要有查詢過的節點，可以加速存取
// $member_id : 查詢的使用者節點 id
// $current_query_account : 目前的身份
// 相關函式： find_parent_node() , find_member_node()
// --------------------------------------------------------------------
function check_is_agentsmember($member_id, $current_query_account) {

  // 預設先設定沒有權限
  $current_query_account_permission = false;

  // 找出會員所在的 tree 直到 root
  $findroot_tree = find_parent_node($member_id);
  // var_dump($findroot_tree);

  $item2root_html = '';
  // 計算有幾代
  $item2root_count = 0;
  $findroot_tree_count = count($findroot_tree[$member_id]);
  //var_dump($findroot_tree_count);
  for($j=($findroot_tree_count-1);$j>=0;$j--){
  	$find_pnode = $findroot_tree[$member_id][$j];

    // 檢查權限, 如果有出現的話就是有權限
    if($find_pnode->account == $current_query_account){
      $current_query_account_permission = true;
      //var_dump($find_pnode->account);
    }

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


  // 從使用者到 root 的路徑及階層數量。
  // var_dump($item2root_html);

  // 樹狀結構全部
  $r['findroot_tree']   = &$findroot_tree;
  // 樹狀往上的代數
  $r['findroot_tree_count']   = &$findroot_tree_count;
  // ID 對應的代理商
  $r['current_query_account']   = &$current_query_account;
  // 對應於代理商，是否為他的下線
  $r['current_query_account_permission']   = &$current_query_account_permission;
  // 顯示的 html
  $r['item2root_html']  = &$item2root_html;

  return($r);

}
// --------------------------------------------------------------------
// return check_is_agentsmember
// --------------------------------------------------------------------


// --------------------------------------------------------------------
// 從指定的 parent_id 往下遞迴搜尋, 並指定深度 $depth 層
// 並且把每代的代理商下線數量也找出來
// 使用方式： ID = 18, 深度 4
// $get_ag_list = get_agent_treemap(18, 3);
// --------------------------------------------------------------------
function get_agent_treemap($agent_id=NULL, $depth=3) {

  $recursive_sql =<<<SQL
  SELECT * FROM
  (WITH RECURSIVE upperlayer(id, parent_id, account, therole, nickname, favorablerule, grade, favorablerule, feedbackinfo, status, depth) AS (
    SELECT id, parent_id, account, therole, nickname, favorablerule, grade, favorablerule, feedbackinfo, status, 1
    FROM root_member WHERE parent_id= $agent_id
  UNION ALL
    SELECT p.id, p.parent_id, p.account, p.therole, p.nickname, p.favorablerule, p.grade, p.favorablerule, p.feedbackinfo, p.status, u.depth+1
    FROM root_member p
    INNER JOIN upperlayer u ON u.id = p.parent_id
    WHERE u.depth <= $depth
  )
	SELECT * FROM upperlayer ) as agent_tree

	LEFT JOIN (SELECT  parent_id, count(parent_id) as parent_id_count FROM root_member GROUP BY parent_id) AS agent_user_count
	ON agent_tree.id = agent_user_count.parent_id ORDER BY agent_tree.parent_id , agent_tree.depth, agent_tree.id;

SQL;

  //print_r($recursive_sql);
  $recursive_result = runSQLall($recursive_sql, 0, 'r');
  // $recursive_result = runSQLall_prepared($recursive_sql, $prepare_array = [], $fetch_classname="", 0, 'r');

  return($recursive_result);
}
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// 取得會員到公司帳號的路徑, 使用 SQL 遞迴查詢
// 往上搜尋到公司帳號, 並且深度不超過 $depth , 也同時避免迴圈出現
// 使用方式：   ID = 1351, 往上深度 10
// $member2root_list = get_member2root_list(1351, 10);
// ----------------------------------------------------------------------------
function get_member2root_list($member_id = NULL, $depth = 20) {

  //-- 從指定的 id 往上遞迴搜尋
  $recursive_sql =<<<SQL
  SELECT * FROM
  (WITH RECURSIVE subordinates(id, parent_id, account, nickname, wechat, therole, feedbackinfo, grade, enrollmentdate, commissionrule, status, depth)
  AS( SELECT id, parent_id, account, nickname, wechat, therole, feedbackinfo, grade, enrollmentdate, commissionrule, status, 1
  FROM root_member WHERE id = $member_id
  UNION
  SELECT m.id, m.parent_id, m.account, m.nickname, m.wechat, m.therole, m.feedbackinfo, m.grade, m.enrollmentdate, m.commissionrule, m.status, s.depth+1
  FROM root_member m
  INNER JOIN subordinates s
  ON s.parent_id = m.id
  WHERE m.account != 'root' AND s.depth <= $depth
  )
	SELECT * FROM subordinates )as rootpath

	LEFT JOIN (SELECT  parent_id, count(parent_id) as parent_id_count FROM root_member GROUP BY parent_id) AS agent_user_count
	ON rootpath.id= agent_user_count.parent_id ORDER BY rootpath.depth;
SQL;

  //print_r($recursive_sql);
  $recursive_result = runSQLall($recursive_sql, 0, 'r');

  return($recursive_result);
}
// ----------------------------------------------------------------------------

// ----
// 檢查訪客 $member_id 是否存在於 $guest_id 前往 root 的階層中
// 使用方式：
// 成功 checkin_member2root_list( 18, 20, 1353 )
// 失敗 checkin_member2root_list( 15, 20, 1353 )
// ----------------------------------------------------------------------------
function checkin_member2root_list($member_id = NULL, $depth = 20, $guest_id = NULL) {

  //-- 從指定的 id 往上遞迴搜尋
  $recursive_sql =<<<SQL
  SELECT * FROM
  (WITH RECURSIVE subordinates(id, parent_id, account, nickname, wechat, therole, feedbackinfo, grade, enrollmentdate, commissionrule, status, depth)
  AS( SELECT id, parent_id, account, nickname, wechat, therole, feedbackinfo, grade, enrollmentdate, commissionrule, status, 1
  FROM root_member WHERE id = $guest_id
  UNION
  SELECT m.id, m.parent_id, m.account, m.nickname, m.wechat, m.therole, m.feedbackinfo, m.grade, m.enrollmentdate, m.commissionrule, m.status, s.depth+1
  FROM root_member m
  INNER JOIN subordinates s
  ON s.parent_id = m.id
  WHERE m.account != 'root' AND s.depth <= $depth
  )
	SELECT * FROM subordinates )as rootpath
	WHERE rootpath.id = $member_id;
SQL;

  // print_r($recursive_sql);
  $recursive_result = runSQLall($recursive_sql, 0, 'r');

  return($recursive_result);
}
// ----------------------------------------------------------------------------

?>
