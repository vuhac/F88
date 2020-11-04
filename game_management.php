<?php
// ----------------------------------------------------------------------------
// Features:    後台--遊戲管理列表
// File Name:   game_management.php
// Author:      Ian
// Related:     game_management_action.php
// Log:
// 2019.01.31 新增 Gapi 遊戲清單管理頁籤 Letter
// 2019.06.17 區分權限顯示管理介面 Letter
// 2020.02.26 #3540 【後台】娛樂城、遊戲多語系欄位實作 - 修改娛樂城顯示名稱 Letter
// 2020.03.09 #3634 遊戲新增反水類型欄位 Letter
// 2020.05.06 #3794 娛樂城新增、編輯功能開發 Letter - 列表新增顯示名稱欄位
// 2020.05.14 Bug #3955 【CS】VIP站後台，投注記錄查詢 > 進階搜尋 > 体育 > 搜尋不到 - 修改類別顯示 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
require_once dirname(__FILE__) . "/casino_switch_process_lib.php";

// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();

// 初始化變數
global $su;
global $tr;
$debug = 0;
$opsSortStart = 1;
$opsSortEnd = 100;
$masterSortStart = 101;
$masterSortEnd = 200;
// 娛樂城函式庫
$casinoLib = new casino_switch_process_lib();

// 功能標題，放在標題列及meta
$function_title = $tr['Game Management'];

// 擴充 head 內的 css or js
$extend_head = '<!-- Jquery UI js+css  -->
	<script src="in/jquery-ui.js"></script>
	<link rel="stylesheet"  href="in/jquery-ui.css" >
	<!-- Jquery blockUI js  -->
	<script src="./in/jquery.blockUI.js"></script>
	<!-- jquery datetimepicker js+css -->
	<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
	<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
	<!-- Datatables js+css  -->
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>';

// 放在結尾的 js
$extend_js = '';

// body 內的主要內容
$indexbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';

$mct_html = (isset($_SESSION['agent']) and in_array($_SESSION['agent']->account, $su['ops'])) ? '<li><a href="maincategory_editor.php" target="_self">' . $tr['MainCategory Management'] . '</a></li>' : '';

$lottery_backoffice_html = (isset($_SESSION['agent']) and in_array($_SESSION['agent']->account, $su['superuser'])) ? '<li><a href="casino_backoffice.php" target="_self">' . $tr['Lottery Management'] . '</a></li>' : '';

// 依權限顯示 GAPI 遊戲清單管理頁籤
$gapi_gamelist_management_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ?
	'<li><a href="gapi_gamelist_management.php" target="_self">'.$tr['gapi gamelist management'].'</a></li>' : '';

$table_laststats_html = '<div class="col-12 tab mb-3">
<ul class="nav nav-tabs">
    <li><a href="casino_switch_process.php" target="_self">
	  ' . $tr['Casino Management'] . '</a></li>
    ' . $mct_html . '
    <li class="active"><a href="" target="_self">
	  ' . $tr['Game Management'] . '</a></li>
    ' . $lottery_backoffice_html .
	$gapi_gamelist_management_html . '
  </ul></div>';

// 查詢條件 - 娛樂城列表
$casinolist_option = '<option value="all"  selected="selected" >'.$tr['all casinos'].'</option>';

$menu_casinolist_item_sql    = 'SELECT * FROM casino_list WHERE "open" = 1 ORDER BY id;';
$menu_casinolist_item_result = runSQLall($menu_casinolist_item_sql, $debug, 'r');

for ($l = 1; $l <= $menu_casinolist_item_result[0]; $l++) {
    // 翻譯
    if (isset($tr[$menu_casinolist_item_result[$l]->casino_name]) and $menu_casinolist_item_result[$l]->casino_name != null) {
        $casinolist_option .= '<option value="' . $menu_casinolist_item_result[$l]->casinoid . '" >' .
        $casinoLib->getCasinoByCasinoId($menu_casinolist_item_result[$l]->casinoid, $debug)->getDisplayName() .
	        '</option>\\ ';
    } else {
        $casinolist_option .= '<option value="' . $menu_casinolist_item_result[$l]->casinoid . '" >' .
	        $casinoLib->getCasinoByCasinoId($menu_casinolist_item_result[$l]->casinoid, $debug)->getDisplayName() .
	        '</option>\\ ';
    }
}

// gameslist table
$table_laststats_html .= <<<HTML
<div id="gamelist"><table class="table table-hover" id="show_list"></table></div>
HTML;

echo in_array($_SESSION['agent']->account, $su);

// 依權限不同顯示不同的 datatables 介面
if (isset($_SESSION['agent']) and in_array($_SESSION['agent']->account, $su['ops'])) { // OPS 權限
    // 排序選單
    $order_option = '<option value=\'0\' >'. $tr['unsetting'] .'</option>';
    for ($l = $opsSortStart; $l <= $masterSortEnd; $l++) {
        $order_option .= '<option value=\'' . $l . '\' >' . $l . '</option>';
    }

	// 維運修改游戲視窗
    $table_laststats_html .= <<< HTML
	<div class="modal fade" id="gamelist_editor" tabindex="-1" role="dialog" aria-labelledby="gamelistEditor" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="editorLabel"></h4>
				</div>
				<div class="modal-body">
		            <div class="container-fluid">
			            <div class="row">
						    <div class="col-xs-2 m-2"><label for="gameeditor_id">ID</label></div>
						    <div class="col-xs-8"><label type="text" id="gameeditor_id"></label></div>
						    <input id="rowIndex" type="hidden">
				        </div>
				        <div class="row">
									<div class="col-xs-2 m-2 font-weight-bold"><span class="text-danger">*</span><label for="gameeditor_cname">{$tr['game chinese name']}</label></div>
									<div class="col-xs-8 form-inline">
										<input class="form-control" id="gameeditor_cname" >
										<input id="gameeditor_cname_fix_value" type="hidden">
										<button type="button" id="gameeditor_cname_fix" class="btn btn-sm btn-danger ml-2" data-fix="" onclick="reset_column('gameeditor_cname');">{$tr['reset']}</button>
									</div>
				        </div>
				        <div class="row">
									<div class="col-xs-2 m-2 font-weight-bold"><span class="text-danger">*</span><label for="gameeditor_ename">{$tr['game english name']}</label></div>
									<div class="col-xs-8 form-inline">
										<input class="form-control" id="gameeditor_ename" >
										<input id="gameeditor_ename_fix_value" type="hidden">
										<button type="button"  id="gameeditor_ename_fix" class="btn btn-sm btn-danger ml-2" data-fix="" onclick="reset_column('gameeditor_ename');">{$tr['reset']}</button>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-2 m-2 font-weight-bold"><span class="text-danger">*</span>{$tr['game name']}</div>
									<div class="col-xs-8 form-inline">
										<select id="i18n_name" class="form-control form-control-sm form-group">
											<option value=""></option>
										</select>
										<div class="form-group mx-sm-2">
											<input id="display_name" type="text" class="form-control" placeholder="{$tr['select language']}">
										</div>
											<button id="updateName" type="submit" class="btn btn-sm	btn-danger">{$tr['confirm']}</button>
									</div>
								</div>				                
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cid">{$tr['Casino']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cid"></label></div>
				        </div>       
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_mct">{$tr['marketing strategy']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_mct"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cate">{$tr['main cate']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cate"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cate2nd">{$tr['game second category']}</label></div>
							<div class="col-xs-8"><input class="form-control" id="gameeditor_cate2nd"></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_scate">{$tr['game sub category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_scate"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_playform">{$tr['technology']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_playform"></label></div>
				        </div>
						<div class="row">
							<div class="col-xs-2 m-2"><label for="favorable">{$tr['reward']}{$tr['classification']}</label></div>
							<div class="col-xs-8"><select id="favorable" class="form-control"></select></div>
						</div>				        
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_order">{$tr['Custom sort']}<span class="glyphicon glyphicon-info-sign"  data-toggle="tooltip" title="{$tr['Game menu sorting']}"></span></label></div>
							<div class="col-xs-8"><select class="form-control" id="gameeditor_order">{$order_option}</select></div>
				        </div>
				        <div class="row">
				        	<div class="col-xs-2 m-2"><label for="gameeditor_playform">{$tr['notify datetime']}</label></div>
				        	<div class="col-xs-8">
					            <div class="input-group add-on">
					                <input type="text" class="form-control" name="notify_date" id="notify_date" placeholder="{$tr['notify datetime placeholder']}">
					                <div class="input-group-btn">
					                    <button id="notify_date_check" class="btn btn-default" style="display: none;">
					                        <i class="glyphicon glyphicon-ok"></i>
					                    </button>
					                    <button id="notify_date_btn" class="btn btn-default" onclick="notifyDateModal()">
					                        <i class="glyphicon glyphicon-calendar"></i>
					                    </button>
					                </div>
					            </div>
							</div>
						</div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_hotgame">{$tr['top game']}</label></div>
							<div class="col-xs-8 material-switch pull-left">
								<input id="gameeditor_hotgame" name="gameeditor_hotgame" class="open_switch" type="checkbox"/>
								<label for="gameeditor_hotgame" class="label-success"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="marketingtag">{$tr['Marketing label']}</label></div>
							<div class="col-xs-8"><input class="form-control" id="marketingtag"></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_open">{$tr['enable']}</label></div>
							<div class="col-xs-8"><select class="form-control" id="gameeditor_open"><option value="0">{$tr['Deprecated']}</option><option value="1">{$tr['enable']}</option><option value="2">{$tr['Permanently disabled']}</option></select></div>
						</div>
						<div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_open">{$tr['images']}<span class="glyphicon glyphicon-info-sign mr-2" data-toggle="tooltip" data-placement="top"  title="{$tr['Image file does not exceed 2MB']}"></span></label></div>
							<div class="col-auto pl-0"><img id="gameeditor_gameicon" style="max-height: 200px;max-width: 200px;" onerror="this.src='in/component/common/error.png'" src=""><div id="gameeditor_img"></div></div>
							<div class="col-xs-6">
								<!--<select class="form-control" id="gameeditor_open" style="width:100px;"><option value="0">停用</option><option value="1">啟用</option><option value="2">永久停用</option></select>-->
								<div class="row border py-3 m-2 m-md-0">
								 	<div class="col-auto">							  
								     	<div class="dropdown">
							            	<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							              		{$tr['options']}
							            	</button>
							            	<div class="dropdown-menu"  id="v-pills-tab" role="tablist" aria-labelledby="dropdownMenuButton">
							              		<div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
									        		<a class="dropdown-item active" data-group="img" data-toggle="pill" href="#v-pills-upload" role="tab" aria-selected="true">{$tr['upload image']}</a>
									        		<a class="dropdown-item" data-group="img" data-toggle="pill" href="#v-pills-url" role="tab" aria-selected="false">{$tr['image url']}</a>
									      		</div>
							            	</div>
							          	</div>
							      	</div>
							      	<div class="col-sm-8 pl-0">
							      		<div class="tab-content ml-2" id="v-pills-tabContent">
							        		<div class="tab-pane fade show active" id="v-pills-upload" role="tabpanel">
							            		{$tr['upload files for 2mb']}
							          			<input class="mt-1" type="file" accept="image/*" name="file" id="upload_img" >
							        		</div>
							        		<div class="tab-pane fade" id="v-pills-url" role="tabpanel">
							            		{$tr['image url']}
							          			<input type="text" class="form-control mt-1" id="text_img" placeholder="{$tr['enter image url']}">
							        		</div>
							      		</div>
							      	</div>
						       </div>
						       <div class="row">
						       		<button type="button" class="btn btn-xs btn-danger m-3"  onclick="save_setting('clear-icon')">{$tr['reset image']}</button>
						       </div>
							</div>
						</div>
					</div>
				</div>	
				<div class="modal-footer">
					<div style="float:right;">
						<button type="button" class="btn btn-success" onclick="save_setting()">儲存</button>
						<button type="button" class="btn btn-danger" data-dismiss="modal" aria-hidden="true">關閉</button>
					</div>
				</div>
			</div>   
		</div>
	</div>
HTML;

    // 永久關閉提醒視窗
	$table_laststats_html .= <<< HTML
        <div id="deprecated_alert" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deprecated_alert_label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
				        <h1 class="modal-title text-danger">{$tr['warming']}</h1>
			        </div>
			        <div class="modal-body">
			            <input type="hidden" id="gid">
			            <div class="row">
			                <div class="col-12 h4">
					            <label for="pw_check">{$tr['pls enter pwd']}</label>
					            <input type="password" id="pw_check">
					        </div>    
				        </div>
				        <div class="row">
				            <div class="col-12 h4">
				                <p>{$tr['deprecate game alert sentence 1']}</p>
				                <p>{$tr['deprecate game alert sentence 2']}</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="recheck_deprecate" class="btn btn-default" onclick="deprecateGame()" disabled>{$tr['confirm']}</button>
                    </div>
                </div>
            </div>
        </div>
HTML;

    // 自訂排序
	$custom_order_html = <<< HTML
		{ "data": "custom_order",
		  "title": "{$tr["priority order"]}",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
		    var selectedOrder = rData.custom_order;
            var id = rData.id;
            var disabled = "";
            if (rData.open == 2) {
                disabled = "disabled";
            }
            var sortHtml = "<select id=\'customSort"+ id +"\' class=\'form-control\' onchange=\'customSortSelect("+ id +")\' "+ disabled +"><option value=\'0\'>{$tr['unsetting']}</option>";
            for(i = {$opsSortStart}; i <= {$masterSortEnd}; i++) {
                var selectedHtml = i == selectedOrder ? "selected" : "";
                sortHtml += "<option value=\'"+ i +"\' "+ selectedHtml +">"+ i + "</option>";
            }
            sortHtml += "</select>";
            $(td).html(sortHtml);
		  }
		},
HTML;

    // 上線醒目提示
	$notify_datetime_html = '
		{ "data": "notify_datetime", 
		  "title": "'. $tr["notify datetime"] .'",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
		  	var notifyDate = rData.notify_datetime;
            var id = rData.id;
            var disabled = "";
            if (rData.open == 2) {
                disabled = "disabled";
            }
            var autofill = "fake_cancel_autofill";
            var readonlyHtml = "readonly=true";
            if (notifyDate != "") {
            	autofill = "";
            	readonlyHtml = ""
            }
            $(td).html("<div class=\'input-group add-on\'><input type=\'text\' class=\'form-control nd_input "+ autofill +"\' id=\'notify_date"+ id +"\' value=\'"+ notifyDate +"\' "+ disabled +" placeholder=\''. $tr['notify datetime placeholder'] .'\' "+ readonlyHtml +" onclick=\'notifyDateDatepicker("+ id +")\'><div class=\'input-group-btn\'><button id=\'notify_date_check"+ id +"\' class=\'btn btn-default\' style=\'display: none;\'><i class=\'glyphicon glyphicon-ok\'></i></button><button id=\'notify_date_btn"+ id +"\' onclick=\'notifyDateDatepicker("+ id +")\' class=\'btn btn-default\' "+ disabled +"><i class=\'glyphicon glyphicon-calendar\'></i></button></div></div>");
		  }
		},';

	// 熱門遊戲
	$hot_game_html = <<< HTML
		{ "data": "hotgame_tag",
		  "title": "{$tr['top game']}",
		  createdCell: function (nTd, sData, oData, iRow, iCol) {
		  	var disabled = "";
            if (oData.open == 2) {
                disabled = "disabled";
            }
			$(nTd).html("<div class='col-12 material-switch pull-left' > \
				<input id='marketing_"+oData.id+"' name='marketing_"+oData.id+"' class='marketing_switch' value='"+oData.id+"' type='checkbox' "+oData.hotgame_tag+" "+ disabled +"/> \
				<label for='marketing_"+oData.id+"' class='label-success'></label></div>");
		  }
		},
HTML;

	// 啟用遊戲
	$on_off_switch_html = <<< HTML
		{ "data": "open",
		  "title": "{$tr['enable']}",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var onOff = rData.open;
            var checkedHtml = "";
            if (onOff == 1) {
                checkedHtml = "checked";
            } else if (onOff == 2) {
                checkedHtml = "disabled";
            }
            $(td).html("<div class='material-switch pull-left'><input id='offer_isopen"+ id +"' type='checkbox' class='open_switch' value='"+ id +"' "+checkedHtml +"/><label for='offer_isopen"+ id +"' class='label-success'></label></div>");
          }
		},
HTML;

	// 永久停用
	$deprecate_game_html = <<< HTML
		{ "data": "open",
		  "title": "{$tr['deprecated casinos']}",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var icon_color = "text-success"; 
            var icon_html = "<div id='deprecate_game"+ id +"' data-toggle='modal' data-target='#deprecated_alert' data-gid='"+ id +"'><i class='glyphicon glyphicon-off "+ icon_color +"'></i></div>";          
            if (rData.open == 2) {
                icon_color = "text-danger";
                icon_html = "<div id='deprecate_game"+ id +"'><i class='glyphicon glyphicon-off "+ icon_color +"'></i></div>";
            }
            $(td).html(icon_html);
          }
		},
HTML;

	// 編輯按鈕
	$editbtn_html = <<< HTML
	{ "data": "custom_order", "title": "{$tr['edit']}", orderable: false, "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
		var disabled = "";
    		if (oData.open == 2) {
                disabled = "disabled";
            }
	$(nTd).html('<button id="custom_order_top_'+oData.id+'" class="btn btn-xs btn-success" data-toggle="modal" '+ 
	'data-target="#gamelist_editor" data-gid="'+oData.id+'" data-cid="'+oData.casinoid+'" '+
	'data-mcttag="'+oData.mct_tag+'" data-category="'+oData.category+'" data-category2nd="'+oData.category2nd+'" '+ 
	'data-subcategory="'+oData.sub_category+'" data-gamename="'+oData.gamename+'" data-cgamename="'+oData.gamename_cn+'" '+ 
	'data-gamenamef="'+oData.gamename_fix+'" data-cgamenamef="'+oData.gamename_cn_fix+'" '+
	'data-gameplatform="'+oData.gameplatform+'" data-hotgametag="'+oData.hotgame_tag+'" '+ 
	'data-customorder="'+oData.custom_order+'" data-opentag="'+oData.open+'" data-gameicon="'+oData.gameicon+'" '+ 
	'data-notify="'+oData.notify_datetime+'" data-casino="'+oData.casino_name+'" '+
	'data-favorable="'+ oData.favorable +'" data-gametype="'+ oData.gametype +'" data-row="'+ iRow +'"'+
	disabled +' >{$tr['edit']}</button>');}}
HTML;

    // 欄位設定
	$datatables_editbtn = $custom_order_html;
	$datatables_editbtn .= $notify_datetime_html;
	$datatables_editbtn .= $hot_game_html;
	$datatables_editbtn .= $on_off_switch_html;
	$datatables_editbtn .= $deprecate_game_html;
    $datatables_editbtn .= $editbtn_html;

	// 維運修改游戲狀態用JS
    $extend_js .= <<<HTML
	<script>
$(document).ready(function(){
			$('[data-toggle="tooltip"]').tooltip();
			
			//切換上傳圖片 或 使用圖片URL
			$('a[data-group*=\"img\"]').on('show.bs.tab', function (e) {
		        $($(e.relatedTarget).attr('href')).find('input').val(''); // previous active tab
			});
			
			// 維運修改游戲狀態
			$('#gamelist_editor').on('show.bs.modal', function (event) {	
				//清除必填提示(未填寫後送出的樣式還原)
				$('#gameeditor_ename').removeClass('alert alert-danger mb-0');
				$('#gameeditor_cname').removeClass('alert alert-danger mb-0');
				// Button that triggered the modal
		        var gamedata = $(event.relatedTarget);
		        var modal = $(this);
		        		        
		        // 反水分類
		        let cid = gamedata.data('cid');
		        $.ajax({
					url: 'gapi_gamelist_management_action.php?a=favorableTypes&cid=' + cid,
		        	dataType: 'html',
		        	type: 'GET',
			    	cache:false,
			    	contentType: false,
			    	processData: false,
		        	success: function(data) {
		            	$('#favorable').html(data);
		            	modal.find('.modal-body #favorable').val(gamedata.data('favorable')).attr("selected", true);
		        	}
				});
		        
		        // 語系選項
		        genLanguageSelector(gamedata.data('gid'), '', 1);
		        $('#display_name').empty();
		        
		        // 遊戲類別(subhall)
			    let gametype = '';
	    		if (gamedata.data('gametype') == null || gamedata.data('gametype') == "undefined") {
	        		gametype = '';
	    		} else {
	    		    gametype = gamedata.data('gametype');
	    		}
		        modal.find('.modal-body #gametype').text(gametype);
	    		
		        modal.find('input').val('');
		        $('#v-pills-tab a[href="#v-pills-upload"]').tab('show');
						modal.find('.modal-title').text('{$tr['edit']} '+gamedata.data('cgamename'));
						modal.find('.modal-body #rowIndex').val(gamedata.data('row'));
		        modal.find('.modal-body #gameeditor_id').text(gamedata.data('gid'));
		        modal.find('.modal-body #gameeditor_cid').text(gamedata.data('casino'));
		        modal.find('.modal-body #gameeditor_mct').text(gamedata.data('mcttag'));
		        modal.find('.modal-body #gameeditor_cate').text(gamedata.data('category'));
		        modal.find('.modal-body #gameeditor_cate2nd').val(gamedata.data('category2nd'));
		        modal.find('.modal-body #gameeditor_scate').text(gamedata.data('subcategory'));
		        modal.find('.modal-body #gameeditor_ename').val(gamedata.data('gamename'));
		        modal.find('.modal-body #gameeditor_cname').val(gamedata.data('cgamename'));
		        modal.find('.modal-body #gameeditor_ename_fix_value').val(gamedata.data('gamenamef'));
		        modal.find('.modal-body #gameeditor_ename_fix').attr('data-fix',gamedata.data('gamenamef'));
		        modal.find('.modal-body #gameeditor_cname_fix_value').val(gamedata.data('cgamenamef'));
		        modal.find('.modal-body #gameeditor_cname_fix').attr('data-fix',gamedata.data('cgamenamef'));
		        modal.find('.modal-body #gameeditor_playform').text(gamedata.data('gameplatform'));
		        modal.find('.modal-body #gameeditor_hotgame').prop("checked", gamedata.data('hotgametag'));
		        modal.find('.modal-body #marketingtag').text(gamedata.data('marketingtag'));
		        var order = gamedata.data('customorder');
		        if (order == '') {
		        	order = 0;    
		        }
		        modal.find('.modal-body #gameeditor_order').val(order);
		        modal.find('.modal-body #gameeditor_open').val(gamedata.data('opentag'));
		        modal.find('.modal-body #gameeditor_gameicon').attr('src',gamedata.data('gameicon'));
		        modal.find('.modal-body #notify_date').val(gamedata.data('notify'));
			});
			
			switchShowDeprecated();
			onOffSwitch();
			hotGame();
			recheckPassword();
			initDepercateModal();
			updateDisplayName();
		});

		// 儲存設定
		function save_setting(act='') {
			//判斷必填未填寫不可送出
			var gename = $('#gameeditor_ename').val();
			var gcname = $('#gameeditor_cname').val();

			if( gename == '' || gcname == '' ){
				alert("尚有必填资料需要填写");
				if ( gename == '' ){
					$('#gameeditor_ename').addClass('alert alert-danger mb-0');
				}
				if ( gcname == '' ){
					$('#gameeditor_cname').addClass('alert alert-danger mb-0');
				}
			}else{
				var gid = $('#gameeditor_id').text();
				// var gename = $('#gameeditor_ename').val();
				// var gcname = $('#gameeditor_cname').val();
				var gorder = $('#gameeditor_order').val();
				var cate2 = $('#gameeditor_cate2nd').val();
				var gmtag = $('#marketingtag').val();
				var isopen = $('#gameeditor_open').val();
				var notify = $('#notify_date').val();
				var favorable = $('#favorable').val();
		
				if($("#text_img").val() != ''){
							var upload_img = $("#text_img").val();
				}
					else{
							var upload_img = $("#upload_img")[0].files[0];
					}
			
				if($('#gameeditor_hotgame').prop('checked')) {
					var ishot = 1;
				}else{
					var ishot = 0;
				}
				var i18n = $('#i18n_name').val();
				var displayName = $('#display_name').val();
		
				var formData = new FormData();
					formData.append('gename', gename);
					formData.append('gcname', gcname);
					formData.append('gorder', gorder);
					formData.append('ishot', ishot);
					formData.append('cate2', cate2);
					formData.append('gmtag', gmtag);
					formData.append('isopen', isopen);
					formData.append('gameicon', upload_img);
					if(act=='clear-icon'){
					var cdnact='clear';
					formData.append('cdnact', cdnact);
				}
					formData.append('notify', notify);
					formData.append('favorable', favorable);
					formData.append('i18n', i18n);
					formData.append('display', displayName);
		
				$('body').append('<div id=\"progress_bar\" style=\"width:100%;position: fixed;top: 47%;text-align: center;background-color: rgba(225, 225, 225, 0.3);\"><img width=\"40px\" height=\"40px\" src=\"./ui/loading_hourglass.gif\">请稍后...</div>');
				$.ajax({
							type: 'POST',
							url : 'game_management_action.php?a=edit_gamelist&gid='+gid,
							data : formData,
							cache:false,
							contentType: false,
							processData: false,
								success : function(result) {
										$('#progress_bar').remove();
										result = JSON.parse(result);
								if(!result.logger){
										$('#gamelist_editor').modal('hide');
							$("#show_list").DataTable().ajax.reload(null, false);		
						}else{
							alert(result.logger);
						}		
								},
						error: function(res) {
								$('#progress_bar').remove();
								if(res.status == 413) {
										alert('{$tr['The file is too large (more than 2MB)']}');
										// alert('档案过大(超过2MB)');
								} else {
									alert('{$tr['There was an error uploading the file. Please try again later.']}');
									// alert('上传文件发生错误，请稍后再试。');				
								}
						}
						});
			}
		}
		
		// 重設欄位值
		function reset_column(column_name) {
			var origin_value = $('#'+column_name+'_fix_value').val();	
			$('#'+column_name).val('').val(origin_value);
			console.log(origin_value);
			save_setting();
			//$('#'+column_name).val(origin_value)
		}
	
		// 狀態設定
		function selectopt(sel_val) {
			var status_opt = ['{$tr['disabled']}','{$tr['Enabled']}','{$tr['Permanently disabled']}'];
			// var status_opt = ['停用','啟用','永久停用'];
			var html = '';
			for (var i=0;i<=2;i++){
				if(i == sel_val){
					html += '<option value="'+i+'" selected>'+status_opt[i]+'</option>';
				}else{
					html += '<option value="'+i+'">'+status_opt[i]+'</option>';
				}
			}
			return(html);
		}
	
		// 儲存遊戲狀態
		function statuschg(gid) {
			var is_open = $('#'+gid+'_open').val();
			$.post('game_management_action.php?a=edit_status',
				{ id: gid,
					is_open: is_open},
				function(result){
					if(!result.logger){
						$("#show_list").DataTable().ajax.reload(null, false);
					}else{
						alert(result.logger);
					}
			}, 'JSON');
		}
		
		// 顯示永久停用開關
	    function switchShowDeprecated() {
	        $("#deprecate").on("click", ".material-switch", function() {
				var cid = $("#casino_query").val();
	            if (cid != 'all') {
	                var cidQuery = "&cid=" + cid;
	            } else {
	                var cidQuery = "";
	            }	                
	            if ($("#showDeprecated").prop("checked")) {
	                $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();    
	            } else {
	                $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	            }
	        });
	    }
		
		// 自訂順序
		function customSortSelect(id) {
		    var sortNum = $("#customSort" + id).val();
	        $.ajax({
	            url: "game_management_action.php?a=gameorderchg&gid="+ id,
	            type: "POST",
	            data: { gorder: sortNum },
	            success: function(e) {
	                var cid = $("#casino_query").val();
	                if (cid != 'all') {
	                    var cidQuery = "&cid=" + cid;
	                } else {
	                    var cidQuery = "";
	                }	                
	                if ($("#showDeprecated").prop("checked")) {
	                    $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();    
	                } else {
	                    $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	                }
	            }
	        });
		}

		// 上線醒目提醒
		function notifyDateDatepicker(id) {
		    $("#notify_date" + id).attr("readonly", false);
	        var datepicker = $("#notify_date" + id).datetimepicker({
	            format: "Y-m-d H:i",
	            step: 10,
	            closeOnDateSelect: false,
	            closeOnWithoutClick: false,
	            defaultDate: false,
	            defaultTime: false
	        });
	        datepicker.datetimepicker("show");
	        var checkBtn = $("#notify_date_check" + id);
	        var calenderBtn = $("#notify_date_btn" + id);
	        checkBtn.css("display", "block");
	        calenderBtn.css("display", "none");
	        checkBtn.on("click", function() {
	            var dateTime = datepicker.val();
	            $.ajax({
	                url: "game_management_action.php?a=notify&gid="+ id +"&datetime="+ dateTime,
	                success: function(e) {
						$("#show_list").DataTable().ajax.reload(null, false);
	                }
	            });
	            checkBtn.css("display", "none");
	            calenderBtn.css("display", "block");
	            datepicker.datetimepicker("close");
	            $("#notify_date" + id).attr("readonly", true);
	        });
	    }
	    
	    // 編輯視窗上線醒目
	    function notifyDateModal() {
		   	var checkBtn = $("#notify_date_check");
	        var calenderBtn = $("#notify_date_btn");
	    	var datepicker = $("#notify_date").datetimepicker({
	            format: "Y-m-d H:i",
	            step: 10,
	            closeOnDateSelect: false,
	            closeOnWithoutClick: false,
	            defaultDate: false,
	            defaultTime: false,
	            onClose: function() {
	              	checkBtn.css("display", "none");
	            	calenderBtn.css("display", "block");
	            }
	        });
	        datepicker.datetimepicker("show");
	        checkBtn.css("display", "block");
	        calenderBtn.css("display", "none");
	        checkBtn.on("click", function() {
	            checkBtn.css("display", "none");
	            calenderBtn.css("display", "block");
	            datepicker.datetimepicker("close");
	        });
	    }
	    
	    // 熱門遊戲
	    function hotGame() {
			$('#show_list').on('click', '.marketing_switch', function() {
				var id = $(this).val();
				if(id != '') {
					if($('#marketing_'+id).prop('checked')) {
						var is_open = 1;
					}else{
						var is_open = 0;
					}
					$.post('game_management_action.php?a=edit_hotgame',
						{ id: id,
						  is_open: is_open},
						  function(result){
							if(!result.logger){
								$("#show_list").DataTable().ajax.reload(null, false);
							}else{
								alert(result.logger);
						    }
					      }, 'JSON');
				}else{
					alert('(x)不合法的测试。');
				}
			});	      
	    }
	    
	    // 開啟關閉遊戲
	    function onOffSwitch() {
		    $("#show_list").on("click", ".open_switch", function() {
		    	var gid = $(this).val();
		    	var onOff = "";
		        if($("#offer_isopen" + gid).prop("checked")) {
		            onOff = "1";
		        } else {
		            onOff = "0";
		        }
		    	$.ajax({
		            url: "game_management_action.php?a=edit_status",
		            type: "POST",
		            data:{
		                id: gid,
		                is_open: onOff
		            },
		            success: function(e) {
		                $("#show_list").DataTable().ajax.reload(null, false); 
		            },
		            error: function(e) {
		            	alert("{$tr['Illegal test']}");  
		            }
	        	});
		    })
	    }
	    
	    // 永久關閉密碼確認
	    function recheckPassword() {
	        $("#pw_check").on("keyup", function() {
	            var pw = $("#pw_check").val();
	            $.ajax({
	                url: "game_management_action.php?a=recheck",
	                type: "post",
	                data: {
	                    pw: pw
	                },
	                success: function(data) {
	                    var data = JSON.parse(data);
	                    if (data.result == 1) {
	                        $("#recheck_deprecate").attr("disabled", false);
	                    } else {
	                        $("#recheck_deprecate").attr("disabled", true);
	                    }
	                }
	            });
	        });
	    }
	    
	    // 永久關閉遊戲
	    function deprecateGame() {
	    	var gid = $("#gid").val();
	    	$.ajax({
		            url: "game_management_action.php?a=edit_status",
		            type: "POST",
		            data:{
		                id: gid,
		                is_open: 2
		            },
		            success: function(e) {
		                var cid = $("#casino_query").val();
		                if (cid != 'all') {
		                    var cidQuery = "&cid=" + cid;
		                } else {
		                    var cidQuery = "";
		                }	                
		                if ($("#showDeprecated").prop("checked")) {
		                    $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();    
		                } else {
		                    $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
		                }
		                $("#deprecated_alert").modal('hide');
		            },
		            error: function(e) {
		            	alert("{$tr['Illegal test']}");  
		            }
	        	});
	    }
	    
	    // 開啟對話框前動作
	    function initDepercateModal() {
	        $("#deprecated_alert").on("show.bs.modal", function(e) {
	            var gdata = $(e.relatedTarget);
	            var modal = $(this);
	            
	            modal.find(".modal-body #gid").val(gdata.data("gid"));
	        });
	    }
	    
	    // 設定語系名稱
	    function updateDisplayName() {
		    $("#updateName").on("click", function() {
		    	let gid = $('#gameeditor_id').text();
	    		let lanKey = $('#i18n_name').val();
	    		let name = $('#display_name').val();
	    		$.ajax({
	                url: "game_management_action.php?a=updateName",
	                type: "post",
	                data: {
	                    id: gid,
	                    i18n: lanKey,
	                    name: name
	                },
	                success: function(data) {
	                    if (JSON.parse(data).result == 1) {
	                        genLanguageSelector(gid, lanKey, 1);
	                        let table = $("#show_list").DataTable();
	                        
	                    } else if (JSON.parse(data).result == 0) {
	                        // do nothing
	                    } else {
	                        alert('{$tr['game name']}{$tr['not']}{$tr['update']}!');
	                    }                      
	                }
	            });
		    });    	
	    }
	    
	    // 語系選項
	    function genLanguageSelector(gid, langKey, allLang) {
	    	$.ajax({
				url: 'game_management_action.php?a=i18nGameNames&gid='+ gid,
		       	dataType: 'json',
		       	type: 'GET',
			   	cache:false,
			   	contentType: false,
			   	processData: false,
		       	success: function(data) {
				    // 取得支援的語系
				    let languageKeys = Object.keys(data);
				    let lanHtml = $('#i18n_name');
				    lanHtml.empty().html("<option></option");
				    for(let i = 0; i < languageKeys.length; i++) {
				        if (allLang === 1 && (languageKeys[i] === 'zh-cn' || languageKeys[i] === 'en-us')) {
				                continue;
				        } else {
				            lanHtml.append($("<option value='"+ languageKeys[i] +"'>"+ data[languageKeys[i]].display +"</option>"));
				        }
				    	
				    }
				    
				    lanHtml.val(langKey);
					    
				    // 處理遊戲名稱顯示
				    let displayInput = $('#display_name');
				    displayInput.empty();
				    lanHtml.change(function() {
				    	let lanKey = lanHtml.val();
				    	displayInput.val(data[lanKey].name);
				    });
				    
		       	}
			});	
	    }
	</script>
HTML;

	// 初始化表格
	$extend_head .= <<< HTML
	<script type="text/javascript" language="javascript" class="init">
	$(document).ready(function() {
		$("#show_list").DataTable( {
			"bProcessing": true,
			"bServerSide": true,
			"bRetrieve": true,
			"searching": true,
			"order": [[ 0, "desc" ]],
			"dom": 'f<"#casino_select.pl-0">rtip',
			"ajax": "game_management_action.php?a=reload_gamelist",
			"oLanguage": {
				"sSearch": "{$tr['search']}{$tr['games']}",//"游戏或类别:",
				"sEmptyTable": "{$tr['Currently no information']}",//"目前没有资料!",
				"sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
				"sZeroRecords": "{$tr['Currently no information']}",//"目前没有资料",
				"sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
				"sInfoEmpty": "{$tr['Currently no information']}",//"目前没有资料",
				"sInfoFiltered": "({$tr['from']}_TOTAL_{$tr['filtering in data']})"//"(从 _TOTAL_ 笔资料中过滤)"
			},
			"columns": [			
				{ "data": "id", "title": "{$tr['ID']}" },
				{ "data": "gamename_cn", "title": "{$tr['game chinese name']}" },
				{ "data": "gamename", "title": "{$tr['game english name']}" },
				{ "data": "game_display_name", "title": "{$tr['display']}{$tr['name']}" },
				{ "data": "casino_short_name", "title": "{$tr['Casino']}" },
				{ "data": "category", "title": "{$tr['Game category']}" },											
				{ "data": "gameplatform", "title": "{$tr['technology']}" },
				{$datatables_editbtn}
			]
		});
		
		// 娛樂城選項
		$("#casino_select").html('<select class="form-control" id="casino_query">{$casinolist_option}</select><div id="deprecate" class="col-form-label mr-2"></div>');
		$("#casino_query").change(function(){
			var cid = this.value;
			if (cid != 'all') {
	            var cidQuery = "&cid=" + cid;
	        } else {
	            var cidQuery = "";
	        }	                
	        if ($("#showDeprecated").prop("checked")) {
	            $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();    
	        } else {
	            $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	        }
		});
		
		// 顯示停用遊戲開關
		$("#deprecate").html("" +
		 	"<div class=\'col-form-label mr-4\'>" +
		 		"<label for=\'showDeprecated\'>{$tr['show deprecated']}</label>" +
		 	"</div>" +
		 	"<div class=\'material-switch pull-left\'>" +
		 		"<input id=\'showDeprecated\' type=\'checkbox\' class=\'marketing_switch\' />" +
		 		"<label for=\'showDeprecated\' class=\'label-success\'></label>" +
		 	"</div>");
		
		// 搜尋框取消自動完成
		$("#show_list_filter input[type=search]").addClass('fake_cancel_autofill').attr('readonly', true);
		$("#show_list_filter input[type=search]").on("click", function() {
		    $("#show_list_filter input[type=search]").attr('readonly', false);
		}).on("blur", function() {
		  	$("#show_list_filter input[type=search]").attr('readonly', true);
		});
	})
</script>
HTML;

} elseif (isset($_SESSION['agent']) and in_array($_SESSION['agent']->account, $su['master'])) { // 站長權限
	// 詳細資料視窗
	$table_laststats_html .= <<< HTML
	<div class="modal fade" id="gamelist_editor" tabindex="-1" role="dialog" aria-labelledby="gamelistEditor" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="editorLabel"></h4>
				</div>
				<div class="modal-body">
		            <div class="container-fluid">
			            <div class="row">
						    <div class="col-xs-2 m-2"><label for="gameeditor_id">ID</label></div>
						    <div class="col-xs-8"><label id="gameeditor_id"><label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cname">{$tr['game chinese name']}</label></div>
							<div class="col-xs-8 form-inline"><label id="gameeditor_cname"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_ename">{$tr['game english name']}</label></div>
							<div class="col-xs-8 form-inline"><label id="gameeditor_ename"></label></div>
				        </div>				                
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cid">{$tr['Casino']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cid"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_mct">{$tr['main cate']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_mct"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cate">{$tr['Game category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cate"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cate2nd">{$tr['game second category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cate2nd"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_scate">{$tr['game sub category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_scate"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_playform">{$tr['technology']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_playform"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_order">{$tr['Custom sort']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_order" ></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="marketingtag">{$tr['Marketing label']}</label></div>
							<div class="col-xs-8"><label id="marketingtag"></label></div>
				        </div>
						<div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_open">{$tr['images']}</label></div>
							<div class="col-auto pl-0"><img id="gameeditor_gameicon" style="max-height: 200px;max-width: 200px;" onerror="this.src='in/component/common/error.png'" src=""><div id="gameeditor_img"></div></div>
						</div>
					</div>
				</div>	
				<div class="modal-footer">
				</div>
			</div>   
		</div>
	</div>
HTML;

	// 自訂排序
	$custom_order_html = <<< HTML
		{ "data": "custom_order",
		  "title": "{$tr["priority order"]}",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
			var selectedOrder = rData.custom_order;
            var id = rData.id;
            if (selectedOrder != 0 && selectedOrder < {$masterSortStart}) {
                var disabledHtml = "disabled";
            } else {
                var disabledHtml = "";
            }
            var sortHtml = "<select id=\'customSort"+ id +"\' class=\'form-control\' onchange=\'customSortSelect("+ id +")\' "+ disabledHtml +"><option value=\'0\'>{$tr['unsetting']}</option>";
            for(i = {$opsSortStart}; i <= {$opsSortEnd}; i++) {
                if(i == selectedOrder) {
                    sortHtml += "<option value=\'"+ i +"\' selected>"+ i + "</option>";
                }
            }
            for(i = {$masterSortStart}; i <= {$masterSortEnd}; i++) {
                var selectedHtml = i == selectedOrder ? "selected" : "";
                sortHtml += "<option value=\'"+ i +"\' "+ selectedHtml +">"+ i + "</option>";
            }
            sortHtml += "</select>";
            $(td).html(sortHtml);
		  }
		},
HTML;

	// 最新上線
	$notify_datetime_html = <<< HTML
		{ "data": "notify_datetime", 
		  "title": "{$tr["new casinos"]}",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
		  	var notify = rData.notify;
            var newAlertHtml = "";
            if(notify > 0) {
                newAlertHtml = "<div><button class='btn btn-outline-danger'>{$tr["new alert"]}</button></div>";
            }
            $(td).html(newAlertHtml);
		  }
		},
HTML;

	// 熱門遊戲
	$hot_game_html = <<< HTML
		{ "data": "hotgame_tag",
		  "title": "{$tr['top game']}",
		  createdCell: function (nTd, sData, oData, iRow, iCol) {
		  	var disabled = "";
            if (oData.open == 2) {
                disabled = "disabled";
            }
			$(nTd).html("<div class='col-12 material-switch pull-left' > \
				<input id='marketing_"+oData.id+"' name='marketing_"+oData.id+"' class='marketing_switch' value='"+oData.id+"' type='checkbox' "+oData.hotgame_tag+" "+ disabled +"/> \
				<label for='marketing_"+oData.id+"' class='label-success'></label></div>");
		  }
		},
HTML;

	// 啟用遊戲
	$on_off_switch_html = <<< HTML
		{ "data": "open",
		  "title": "{$tr['enable']}",
		  createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var onOff = rData.open;
            var checkedHtml = "";
            if (onOff == 1) {
                checkedHtml = "checked";
            } else if (onOff == 2) {
                checkedHtml = "disabled";
            }
            $(td).html("<div class='material-switch pull-left'><input id='offer_isopen"+ id +"' type='checkbox' class='open_switch' value='"+ id +"' "+checkedHtml +"/><label for='offer_isopen"+ id +"' class='label-success'></label></div>");
          }
		},
HTML;

	// 詳細資料
	$detail_info_html = <<< HTML
		{ "data": "id",
		  "title": "{$tr['detail info']}", 
		  orderable: false,
		  createdCell: function( td, cData, oData, rowIndex, colIndex) {
		  	 $(td).html("<button id='detail"+oData.id+"' class='btn btn-outline-success' data-toggle='modal' " + 
		  	 	"data-target='#gamelist_editor' data-gid='"+oData.id+"' data-cid='"+oData.casinoid+"' "+
				"data-mcttag='["+oData.mct_tag+"]' data-category='"+oData.category+"' data-category2nd='"+oData.category2nd+"'"+ 
				"data-subcategory='"+oData.sub_category+"' data-gamename='"+oData.gamename+"' data-cgamename='"+oData.gamename_cn+"'"+ 
				"data-gamenamef='"+oData.gamename_fix+"' data-cgamenamef='"+oData.gamename_cn_fix+"'"+
				"data-gameplatform='"+oData.gameplatform+"' data-hotgametag='"+oData.hotgame_tag+"'"+ 
				"data-customorder='"+oData.custom_order+"' data-opentag='"+oData.open+"' data-gameicon='"+oData.gameicon+"'"+ 
				"data-notify='"+oData.notify_datetime+"'>{$tr['detail info']}</button>");
		  }
		},
HTML;

	// 欄位設定
	$datatables_editbtn = $custom_order_html;
	$datatables_editbtn .= $notify_datetime_html;
	$datatables_editbtn .= $hot_game_html;
	$datatables_editbtn .= $on_off_switch_html;
	$datatables_editbtn .= $detail_info_html;

    // 站長修改游戲狀態用JS
    $extend_js .= <<< HTML
	<script>
	function selectopt(sel_val){
		var html = '';
		for (var i=0;i<100;i++){
			if(i == sel_val){
				html += '<option value="'+i+'" selected>'+i+'</option>';
			}else{
				html += '<option value="'+i+'">'+i+'</option>';
			}
		}
		return(html);
	}
	
	function gameorderchg(gid){
		var gorder = $('#'+gid+'_order').val();
		$.post('game_management_action.php?a=gameorderchg&gid='+gid,
			{ gorder : gorder},
			function(result){
				if(!result.logger){
					$("#show_list").DataTable().ajax.reload(null, false);
				}else{
					alert(result.logger);
				}
		}, 'JSON');
	}
	</script>
HTML;

	// 初始化表格
	$extend_head .= <<< HTML
	<script type="text/javascript" language="javascript" class="init">
	$(document).ready(function() {
		$("#show_list").DataTable( {
			"bProcessing": true,
			"bServerSide": true,
			"bRetrieve": true,
			"searching": true,
			"order": [[ 0, "desc" ]],
			"dom": 'f<"#casino_select.row">rtip',
			"ajax": "game_management_action.php?a=reload_gamelist",
			"oLanguage": {
				"sSearch": "{$tr['games']}",//"游戏",
				"sEmptyTable": "{$tr['Currently no information']}",//"目前没有资料!",
				"sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
				"sZeroRecords": "{$tr['Currently no information']}",//"目前没有资料",
				"sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
				"sInfoEmpty": "{$tr['Currently no information']}",//"目前没有资料",
				"sInfoFiltered": "({$tr['from']}_TOTAL_{$tr['filtering in data']})"//"(从 _TOTAL_ 笔资料中过滤)"
			},
			"columns": [			
				{ "data": "id", "title": "{$tr['ID']}" },
				{ "data": "gamename_cn", "title": "{$tr['game chinese name']}" },
				{ "data": "gamename", "title": "{$tr['game english name']}" },
				{ "data": "game_display_name", "title": "{$tr['display']}{$tr['name']}" },
				{ "data": "casino_short_name", "title": "{$tr['Casino']}" },
				{ "data": "category", "title": "{$tr['Game category']}" },											
				{ "data": "gameplatform", "title": "{$tr['technology']}" },
				{$datatables_editbtn}
			]
		});
		
		$("#casino_select").html('<label for="casino_query">{$tr['Casino']}</label> \
 				<select class="form-control" id="casino_query">{$casinolist_option}</select><div id="show_new_game" class="row"></div>');
		$("#casino_query").change(function(){
			var cid = this.value;
			if (cid != 'all') {
	            var cidQuery = "&cid=" + cid;
	        } else {
	            var cidQuery = "";
	        }
	        if ($("#showDeprecated").prop("checked")) {
	            $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();
	        } else {
	            $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	        }
		});
		$("#show_new_game").html("<div class='col-8 col-form-label'><input type='hidden' id='new_game_status' value=0><div class='btn btn-outline-danger' id='new_games_btn' onclick='newGames()'>{$tr['new casinos']}</div>");
		
		// 連結資料
		$('#gamelist_editor').on('show.bs.modal', function (event) {	
			// Button that triggered the modal
		    var gamedata = $(event.relatedTarget);
		    var modal = $(this);
		    modal.find('input').val('');
		    $('#v-pills-tab a[href="#v-pills-upload"]').tab('show');
			modal.find('.modal-title').text(gamedata.data('cgamename') + "{$tr['detail info']}");
		    modal.find('.modal-body #gameeditor_id').text(gamedata.data('gid'));
		    modal.find('.modal-body #gameeditor_cid').text(gamedata.data('cid'));
		    modal.find('.modal-body #gameeditor_mct').text(gamedata.data('mcttag'));
		    modal.find('.modal-body #gameeditor_cate').text(gamedata.data('category'));
		    modal.find('.modal-body #gameeditor_cate2nd').text(gamedata.data('category2nd'));
		    modal.find('.modal-body #gameeditor_scate').text(gamedata.data('subcategory'));
		    modal.find('.modal-body #gameeditor_ename').text(gamedata.data('gamename'));
		    modal.find('.modal-body #gameeditor_cname').text(gamedata.data('cgamename'));
		    modal.find('.modal-body #gameeditor_ename_fix').attr('data-fix',gamedata.data('gamenamef'));
		    modal.find('.modal-body #gameeditor_cname_fix').attr('data-fix',gamedata.data('cgamenamef'));
		    modal.find('.modal-body #gameeditor_playform').text(gamedata.data('gameplatform'));
		    modal.find('.modal-body #gameeditor_hotgame').prop("checked", gamedata.data('hotgametag'));
		    modal.find('.modal-body #marketingtag').text(gamedata.data('marketingtag'));
		    modal.find('.modal-body #gameeditor_order').text(gamedata.data('customorder'));
		    modal.find('.modal-body #gameeditor_open').text(gamedata.data('opentag'));
		    modal.find('.modal-body #gameeditor_gameicon').attr('src',gamedata.data('gameicon'));
		    modal.find('.modal-body #notify_date').text(gamedata.data('notify'));
		});
		
		onOffSwitch();
		hotGame();
	})
	
	// 自訂順序
	function customSortSelect(id) {
	    var sortNum = $("#customSort" + id).find(":selected").val();
		$.ajax({
	        url: "game_management_action.php?a=gameorderchg&gid="+ id,
	        type: "POST",
	        data: { gorder: sortNum },
	        success: function(e) {
	            var cid = $("#casino_query").val();
	            if (cid != 'all') {
	                var cidQuery = "&cid=" + cid;
	            } else {
	                var cidQuery = "";
	            }	                
	            if ($("#showDeprecated").prop("checked")) {
	                $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();    
	            } else {
	                $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	            }
	        }
	    });
	}
	
	// 熱門遊戲
	function hotGame() {
		$('#show_list').on('click', '.marketing_switch', function() {
			var id = $(this).val();
			if(id != '') {
				if($('#marketing_'+id).prop('checked')) {
					var is_open = 1;
				}else{
					var is_open = 0;
				}
				$.post('game_management_action.php?a=edit_hotgame',
					{ id: id,
					  is_open: is_open},
					  function(result){
						if(!result.logger){
							$("#show_list").DataTable().ajax.reload(null, false);
						}else{
							alert(result.logger);
					    }
				      }, 'JSON');
			}else{
				alert('(x)不合法的测试。');
			}
		});	      
	}
	    
	// 開啟關閉遊戲
	function onOffSwitch() {
	    $("#show_list").on("click", ".open_switch", function() {
	    	var gid = $(this).val();
	    	var onOff = "";
	        if($("#offer_isopen" + gid).prop("checked")) {
	           onOff = "1";
	        } else {
	            onOff = "0";
	        }
	    	$.ajax({
	            url: "game_management_action.php?a=edit_status",
	            type: "POST",
	            data:{
	                id: gid,
	                is_open: onOff
	            },
	            success: function(e) {
	                var cid = $("#casino_query").val();
	                if (cid != 'all') {
	                    var cidQuery = "&cid=" + cid;
	                } else {
	                    var cidQuery = "";
	                }	                
	                if ($("#showDeprecated").prop("checked")) {
	                    $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();    
	                } else {
	                    $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	                }
	            },
	            error: function(e) {
	            	alert("{$tr['Illegal test']}");  
	            }
	       	});
	    })
	}
	
	// 顯示最新上線
	function newGames() {
	    var status = $("#new_game_status").val();
	    var cid = $("#casino_query").val();
	    if (cid != 'all') {
	    	var cidQuery = "&cid=" + cid;
	    } else {
	        var cidQuery = "";
	    }
	    if (status == 0) {
	        $("#new_games_btn").removeClass("btn-outline-danger").addClass("btn-danger");
	        $("#new_game_status").val(1);
	        $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist"+ cidQuery +"&notify=new").load();
	    } else {
	        $("#new_games_btn").removeClass("btn-danger").addClass("btn-outline-danger");
	        $("#new_game_status").val(0);
	        $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist"+ cidQuery).load();
	    }
	}
</script>
HTML;
} else { // 客服權限
	// 詳細資料視窗
	$table_laststats_html .= <<< HTML
	<div class="modal fade" id="gamelist_editor" tabindex="-1" role="dialog" aria-labelledby="gamelistEditor" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="editorLabel"></h4>
				</div>
				<div class="modal-body">
		            <div class="container-fluid">
			            <div class="row">
						    <div class="col-xs-2 m-2"><label for="gameeditor_id">ID</label></div>
						    <div class="col-xs-8"><label id="gameeditor_id"><label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cname">{$tr['game chinese name']}</label></div>
							<div class="col-xs-8 form-inline"><label id="gameeditor_cname"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_ename">{$tr['game english name']}</label></div>
							<div class="col-xs-8 form-inline"><label id="gameeditor_ename"></label></div>
				        </div>				                
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cid">{$tr['Casino']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cid"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_mct">{$tr['main cate']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_mct"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cate">{$tr['Game category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cate"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_cate2nd">{$tr['game second category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_cate2nd"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_scate">{$tr['game sub category']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_scate"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_playform">{$tr['technology']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_playform"></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_order">{$tr['Custom sort']}</label></div>
							<div class="col-xs-8"><label id="gameeditor_order" ></label></div>
				        </div>
				        <div class="row">
							<div class="col-xs-2 m-2"><label for="marketingtag">{$tr['Marketing label']}</label></div>
							<div class="col-xs-8"><label id="marketingtag"></label></div>
				        </div>
						<div class="row">
							<div class="col-xs-2 m-2"><label for="gameeditor_open">{$tr['images']}</label></div>
							<div class="col-auto pl-0"><img id="gameeditor_gameicon" style="max-height: 200px;max-width: 200px;" onerror="this.src='in/component/common/error.png'" src=""><div id="gameeditor_img"></div></div>
						</div>
					</div>
				</div>	
				<div class="modal-footer">
				</div>
			</div>   
		</div>
	</div>
HTML;

	// 詳細資料
	$detail_info_html = <<< HTML
		{ "data": "id",
		  "title": "{$tr['detail info']}",
		  orderable: false,
		  createdCell: function( td, cData, oData, rowIndex, colIndex) {
		  	 $(td).html("<button id='detail"+oData.id+"' class='btn btn-outline-success' data-toggle='modal' " + 
		  	 	"data-target='#gamelist_editor' data-gid='"+oData.id+"' data-cid='"+oData.casinoid+"' "+
				"data-mcttag='["+oData.mct_tag+"]' data-category='"+oData.category+"' data-category2nd='"+oData.category2nd+"'"+ 
				"data-subcategory='"+oData.sub_category+"' data-gamename='"+oData.gamename+"' data-cgamename='"+oData.gamename_cn+"'"+ 
				"data-gamenamef='"+oData.gamename_fix+"' data-cgamenamef='"+oData.gamename_cn_fix+"'"+
				"data-gameplatform='"+oData.gameplatform+"' data-hotgametag='"+oData.hotgame_tag+"'"+ 
				"data-customorder='"+oData.custom_order+"' data-opentag='"+oData.open+"' data-gameicon='"+oData.gameicon+"'"+ 
				"data-notify='"+oData.notify_datetime+"'>{$tr['detail info']}</button>");
		  }
		}
HTML;

	// 熱門遊戲、啟用
    $datatables_editbtn = <<<HTML
	{ "data": "hotgame_tag", "title": "热门游戏", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
	(oData.hotgame_tag == 'checked') ? $(nTd).html("<span class='glyphicon glyphicon-ok' style='color:green' />") : $(nTd).html("<span class='glyphicon glyphicon-remove' style='color:red' />");
 	}},
	{ "data": "open_tag", "title": "启用", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
	(oData.open_tag == 'checked') ? $(nTd).html("<span class='glyphicon glyphicon-ok' style='color:green'/>") : $(nTd).html("<span class='glyphicon glyphicon-remove' style='color:red' />");
	}},
HTML;

    $datatables_editbtn .= $detail_info_html;

	// 初始化表格
	$extend_head .= <<< HTML
	<script type="text/javascript" language="javascript" class="init">
	$(document).ready(function() {
		$("#show_list").DataTable( {
			"bProcessing": true,
			"bServerSide": true,
			"bRetrieve": true,
			"searching": true,
			"order": [[ 0, "desc" ]],
			"dom": 'f<"#casino_select.row">rtip',
			"ajax": "game_management_action.php?a=reload_gamelist",
			"oLanguage": {
				"sSearch": "{$tr['games']}",//"游戏或类别:",
				"sEmptyTable": "{$tr['Currently no information']}",//"目前没有资料!",
				"sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
				"sZeroRecords": "{$tr['Currently no information']}",//"目前没有资料",
				"sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
				"sInfoEmpty": "{$tr['Currently no information']}",//"目前没有资料",
				"sInfoFiltered": "({$tr['from']}_TOTAL_{$tr['filtering in data']})"//"(从 _TOTAL_ 笔资料中过滤)"
			},
			"columns": [
				{ "data": "id", "title": "{$tr['ID']}" },
				{ "data": "gamename_cn", "title": "{$tr['game chinese name']}" },
				{ "data": "gamename", "title": "{$tr['game english name']}" },
				{ "data": "game_display_name", "title": "{$tr['display']}{$tr['name']}" },
				{ "data": "casino_short_name", "title": "{$tr['Casino']}" },
				{ "data": "category", "title": "{$tr['Game category']}" },
				{ "data": "gameplatform", "title": "{$tr['technology']}" },
				{$datatables_editbtn}
			]
		});
		
		$("#casino_select").html('<label for="casino_query"></label> \
 				<select class="form-control" id="casino_query">{$casinolist_option}</select>');
		$("#casino_query").change(function(){
			var cid = this.value;
			if (cid != 'all') {
	            var cidQuery = "&cid=" + cid;
	        } else {
	            var cidQuery = "";
	        }
	        if ($("#showDeprecated").prop("checked")) {
	            $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist&deprecated=show" + cidQuery).load();
	        } else {
	            $("#show_list").DataTable().ajax.url("game_management_action.php?a=reload_gamelist" + cidQuery).load();
	        }
		});
		
		$('#gamelist_editor').on('show.bs.modal', function (event) {	
			// Button that triggered the modal
		    var gamedata = $(event.relatedTarget);
		    var modal = $(this);
		    modal.find('input').val('');
		    $('#v-pills-tab a[href="#v-pills-upload"]').tab('show');
			modal.find('.modal-title').text(gamedata.data('cgamename') + "{$tr['detail info']}");
		    modal.find('.modal-body #gameeditor_id').text(gamedata.data('gid'));
		    modal.find('.modal-body #gameeditor_cid').text(gamedata.data('cid'));
		    modal.find('.modal-body #gameeditor_mct').text(gamedata.data('mcttag'));
		    modal.find('.modal-body #gameeditor_cate').text(gamedata.data('category'));
		    modal.find('.modal-body #gameeditor_cate2nd').text(gamedata.data('category2nd'));
		    modal.find('.modal-body #gameeditor_scate').text(gamedata.data('subcategory'));
		    modal.find('.modal-body #gameeditor_ename').text(gamedata.data('gamename'));
		    modal.find('.modal-body #gameeditor_cname').text(gamedata.data('cgamename'));
		    modal.find('.modal-body #gameeditor_ename_fix').attr('data-fix',gamedata.data('gamenamef'));
		    modal.find('.modal-body #gameeditor_cname_fix').attr('data-fix',gamedata.data('cgamenamef'));
		    modal.find('.modal-body #gameeditor_playform').text(gamedata.data('gameplatform'));
		    modal.find('.modal-body #gameeditor_hotgame').prop("checked", gamedata.data('hotgametag'));
		    modal.find('.modal-body #marketingtag').text(gamedata.data('marketingtag'));
		    modal.find('.modal-body #gameeditor_order').text(gamedata.data('customorder'));
		    modal.find('.modal-body #gameeditor_open').text(gamedata.data('opentag'));
		    modal.find('.modal-body #gameeditor_gameicon').attr('src',gamedata.data('gameicon'));
		    modal.find('.modal-body #notify_date').text(gamedata.data('notify'));
		});
	})
</script>
HTML;
}


// 將 checkbox 堆疊成 switch 的 css
$extend_head .= <<< HTML
<style>

.material-switch > input[type="checkbox"] {
    visibility:hidden;
}

.material-switch > label {
    cursor: pointer;
    height: 0px;
    position: relative;
    width: 0px;
}

.material-switch > label::before {
    background: rgb(0, 0, 0);
    box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    content: '';
    height: 16px;
    margin-top: -8px;
    margin-left: -18px;
    position:absolute;
    opacity: 0.3;
    transition: all 0.4s ease-in-out;
    width: 30px;
}
.material-switch > label::after {
    background: rgb(255, 255, 255);
    border-radius: 16px;
    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
    content: '';
    height: 16px;
    left: -4px;
    margin-top: -8px;
    margin-left: -18px;
    position: absolute;
    top: 0px;
    transition: all 0.3s ease-in-out;
    width: 16px;
}
.material-switch > input[type="checkbox"]:checked + label::before {
    background: inherit;
    opacity: 0.5;
}
.material-switch > input[type="checkbox"]:checked + label::after {
    background: inherit;
    left: 20px;
}
#casino_select{
	display: flex;
}
#casino_query{
	width: 200px;
}
#show_list_filter>label{
	display: flex;
}
#show_list_filter>label>input{
	width: 150px;
}
.pagination {
	float: right;
}

/* 日曆按鈕 */
.add-on .input-group-btn > .btn {
    border-left-width: 0;
    left:-2px;
    -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
            box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
}

.add-on .form-control:focus {
    -webkit-box-shadow: none; 
            box-shadow: none;
    border-color:#cccccc; 
}

/* 表頭、列文字 */
th, td {
    text-align: center;
}

/* 電源開關大小 */
.glyphicon.glyphicon-off {
    font-size: 24px;
}

#show_new_game, #deprecate {
	margin-left: 20px;
}

/* 取消自動完成 */
.fake_cancel_autofill:read-only, .nd_input {
	background-color: white;
}

</style>
HTML;

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author']      = $tr['host_author'];
$tmpl['html_meta_title']       = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $table_laststats_html;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
//include "template/beadmin.tmpl.php";
include "template/beadmin_fluid.tmpl.php"

?>