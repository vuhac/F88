<?php
// ----------------------------------------------------------------------------
// Features:	後台-- API 遊戲清單管理
// File Name:	gapi_gamelist_management.php
// Author:		Letter
// Related:     gapi_gamelist_management_lib.php
//              gapi_gamelist_management_action.php
//              gapi_gamelist_params.php
// Log:
// 2019.01.31 新建 Letter
// 2020.04.06 Feature #3540 【後台】娛樂城、遊戲多語系欄位實作 - 遊戲顯示名稱 Letter
// 2020.05.05 Feature #3794 娛樂城新增、編輯功能開發 - Letter
//                          1. 列表顯示語系名稱
//                          2. 遊戲匯入預設關閉
// 2020.05.14 Bug #3955 【CS】VIP站後台，投注記錄查詢 > 進階搜尋 > 体育 > 搜尋不到 - 修改反水類別 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 管理頁函式庫
require_once "gapi_gamelist_management_lib.php";
require_once "gapi_gamelist_params.php";

// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();

// 檢查是否有權限操作, 可操作權限 ops (維運)
global $su;
if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['ops']))) {
	echo '<script>alert("您无此操作权限!");history.go(-1);</script>';
	die();
}

// 取得系統分頁參數
global $page_config;
$page_limit = $page_config['page_limit'];
$page_rate = $page_config['page_rate'];
$datatables_pagelength = $page_config['datatables_pagelength'];

// 標題列
$function_title 		= $tr['gapi gamelist management'];
// 擴充 head 內的 css or js
$extend_head =
	'<!-- jquery datetimepicker js+css -->
     <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
     <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
     <!-- Datatables js+css  -->
     <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
     <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
     <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' .$tr['System Management'].' </a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

// tab 遊戲管理
$mct_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ? '<li><a href="maincategory_editor.php" target="_self">'.$tr['MainCategory Management'].'</a></li>' : '';

// tab 彩票後台
$lottery_backoffice_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['superuser'])) ? '<li><a href="casino_backoffice.php" target="_self">'.$tr['Lottery Management'].'</a></li>' : '';

// all tab show
$gapi_gamelist_management_html =
	'<div class="col-12 tab mb-3">
		<ul class="nav nav-tabs">
   			<li><a href="casino_switch_process.php" target="_self">'.$tr['Casino Management'].'</a></li>
    		'.$mct_html.'
    		<li><a href="game_management.php" target="_self">'.$tr['Game Management'].'</a></li>
    		'.$lottery_backoffice_html.'
    		<li class="active"><a href="" target="_self">'.$tr['gapi gamelist management'].'</a></li>
		</ul>
	</div>';

// DataTables 設定
// 同步標示
$datatablesSyncGameBtn = <<< HTML
createdCell: function( td, cData, rData, rowIndex, colIndex) {
	var syncNum = rData.is_new;
	var id = rData.id;
	var syncClass = syncNum == 0 ? 'btn btn-primary' : 'btn btn-warning';
	var syncWord = syncNum == 0 ? '{$tr['synchronized']}' : '{$tr['not synchronized']}';
	var disabled = syncNum == 0 ? '' : 'onclick=\'quickSyncGame('+ id +', '+ rowIndex +')\'';
	$(td).html("<div id='{$sync_game_icon_id}' class='"+ syncClass +"' "+ disabled +">"+ syncWord +"</div>");
}
HTML;

// 編輯彈出視窗
$datatablesGameEditor = <<< HTML
createdCell: function( td, cData, rData, rowIndex, colIndex) {
	$(td).html('<button id="'+ rData.id +'" class="btn btn-success" ' +
		'data-toggle="modal" data-target="#{$game_editor_id}" data-row="'+ rowIndex +'" ' + 
		'data-gid="'+ rData.id +'" data-cid="'+ rData.casino_id +'" data-category="'+ rData.category +'" '+
		'data-gamename="'+ rData.gamename +'" data-gamename_cn="'+ rData.gamename_cn +'" '+
		'data-gameplatform="'+ rData.gameplatform +'" data-ms_hot="'+ rData.marketing_strategy["hotgame"] +'" '+
		'data-ms_tag="'+ rData.marketing_strategy["marketing_tag"] +'" data-ms_mct="'+ rData.marketing_strategy["mct"] +'" '+
		'data-ms_category2="'+ rData.marketing_strategy["category_2nd"] +'" data-game_id="'+ rData.gameid +'" '+
		'data-icon="'+ rData.imagefilename +'" data-casino_name="'+ rData.casino_name +'" '+
		'data-open="'+ rData.open +'" data-favorable_type="'+ rData.favorable_type +'" data-gametype="'+ rData.gametype +'" '+
		'data-display="'+ rData.display_name +' data-images="'+ rData.marketing_strategy["image"] +'">{$tr['edit']}</button>');
}
HTML;

// 列表啟用狀態
$datatableOpen = <<< HTML
createdCell: function( td, cData, rData, rowIndex, colIndex) {
	// 處理狀態
	let open = '';
	if ( rData.open == 0) {
	    open = '{$tr['off']}';
	} else if (rData.open == 1) {
	    open = '{$tr['Enabled']}';
	}
	$(td).html(open);
}
HTML;

// 遊戲編輯視窗
$modalGameEditorHtml = <<< HTML
<div id="{$game_editor_id}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="{$game_editor_id}_label" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="modal-title"></h2>
			</div>
			<div class="modal-body">
				<input type="hidden" id="{$game_data_row_id}"/>
				<input type="hidden" id="{$game_data_gameid_id}"/>
				<input type="hidden" id="{$game_data_cid_id}"/>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_gid_id}">{$tr['ID']}</label></div>
					<div class="col-9 form-control-plaintext" id="{$game_data_gid_id}"></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_gameid_id}">ID</label></div>
					<div class="col-9 form-control-plaintext" id="{$game_data_gameid_id}"></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_casino_name_id}">{$tr['Casino']}</label></div>
					<div class="col-9 form-control-plaintext" id="{$game_data_casino_name_id}"></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_gametype_id}">{$tr['games']}{$tr['category']}</label></div>
					<div class="col-9 form-control-plaintext" id="{$game_data_gametype_id}"></div>
				</div>				
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_category_id}">{$tr['main cate']}</label></div>
					<div class="col-9">
						<select id="{$game_data_category_id}" class="form-control">
						</select>
					</div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_sub_category_id}">{$tr['games']}{$tr['sub']}{$tr['category']}</label></div>
					<div class="col-9"><input id="{$game_data_sub_category_id}" type="text" class="form-control"/></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_name_id}">{$tr['gapi game english name']}</label></div>
					<div class="col-9"><input id="{$game_data_name_id}" type="text" class="form-control"/></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_cn_name_id}">{$tr['gapi game chinese name']}</label></div>
					<div class="col-9"><input id="{$game_data_cn_name_id}" type="text" class="form-control"/></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_i18n_name_id}">{$tr['game name']}</label></div>
					<div class="col-9 form-inline">
						<select id="{$game_data_i18n_name_id}" class="form-control form-control-sm form-group">
							<option value=""></option>
						</select>
						<div class="form-group mx-sm-2">
							<input id="{$game_data_display_name_id}" type="text" class="form-control" placeholder="{$tr['select language']}">
						</div>
						<button id="{$update_game_name_btn_id}" type="submit" class="btn btn-sm	btn-danger">{$tr['confirm']}</button>
					</div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_platform_id}">{$tr['platform']}</label></div>
					<div class="col-9"><select id="{$game_data_platform_id}" class="form-control"></select></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_favorable_type_id}">{$tr['reward']}{$tr['classification']}</label></div>
					<div class="col-9"><select id="{$game_data_favorable_type_id}" class="form-control"></select></div>
				</div>				
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_open_id}">{$tr['Enabled']}</label></div>
					<div class="col-9 form-control-plaintext" id="{$game_data_open_id}"></div>
				</div>
				<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_marketing_strategy_id}">{$tr['marketing strategy']}</label></div>
					<div class="col-9 row">
						<div class="col-3 col-form-label"><label for="{$game_data_ms_hotgame_id}">{$tr['HotGame']}</label></div>
						<div class="col-1"></div>
						<div class="col-8 material-switch pull-left">
							<input id="{$game_data_ms_hotgame_id}" type="checkbox" class="form-control open_switch"/>
							<label for="{$game_data_ms_hotgame_id}" class="label-success"></label>
						</div>
						<div class="col-3 col-form-label"><label for="{$game_data_ms_category_id}">{$tr['category']}</label></div>
						<div class="col-9">
							<select id="{$game_data_ms_category_id}" class="form-control">
							</select>
						</div>					
						<div class="col-3 col-form-label"><label for="{$game_data_ms_category_2nd_id}">{$tr['second']}{$tr['category']}</label></div>
						<div class="col-9"><input id="{$game_data_ms_category_2nd_id}" type="text"	class="form-control"/></div>
						<div class="col-3 col-form-label"><label for="{$game_data_ms_marketing_tag_id}">{$tr['marketing tag']}</label></div>
						<div class="col-9"><input id="{$game_data_ms_marketing_tag_id}" type="text"	class="form-control"/></div>
					</div>
				</div>
				<!--<div class="row">
					<div class="col-3 col-form-label"><label for="{$game_data_image_file_name_id}">{$tr['icon file name']}</label></div>
					<div class="col-9"><input id="{$game_data_image_file_name_id}" type="text" class="form-control"/></p></div>
				</div>-->
			</div>
			<div class="modal-footer">
				<button id="{$edit_game_btn_id}" class="btn btn-success save" onclick="saveImportGame()">{$tr['Save']}</button>
			</div>
		</div>
	</div>
</div>
HTML;

// jQuery & javascript
$extend_head .= <<< HTML
<script type="text/javascript" language="JavaScript">
// 生成 DataTables
function genDataTables(){
    // DataTables setting
    $('#{$api_game_list_id}').DataTable({
    	processing: true,
    	serverSide: true,
    	retrieve: true,
    	searching: true,
    	order: [0, 'desc'],
    	dom: 'f<"#{$casino_select_id}.form-inline">rtip',
        ajax: 'gapi_gamelist_management_action.php?a=game_list&cid=none',
        language: {
    	    'search': '{$tr['search']}{$tr['games']}',
    	    'emptyTable': '{$tr['Currently no information']}',
    	    'lengthMenu': '{$tr['Every page shows']} _MENU_ {$tr['Count']}',
    	    'infoEmpty': '{$tr['Currently no information']}',
    	    'zeroRecords': '{$tr['Currently no information']}',
    	    'info': '{$tr['Currently in the']} _PAGE_ {$tr['page']} {$tr['total']} _PAGES_ {$tr['page']}',
    	    'infoFiltered': '( {$tr['From']} _TOTAL_ {$tr['counts data filtering']} )'
        },
        columns: [
            {data: 'id', title: '{$tr['ID']}'},
            {data: 'casino_name', title: '{$tr['Casino']}', orderable: false},	
            {data: 'game_category_name', title: '{$tr['category']}'},
            {data: 'category_name', title: '{$tr['marketing strategy']}', orderable: false},
            {data: 'gamename', title: '{$tr['gapi game english name']}'},
            {data: 'gamename_cn', title: '{$tr['gapi game chinese name']}', orderable: false},
            {data: 'language_name', title: '{$tr['display']}{$tr['name']}', orderable: false},
            {data: 'gameplatform', title: '{$tr['games']}{$tr['platform']}'},
            {data: 'open', title: '{$tr['State']}', {$datatableOpen} },
            {data: 'is_new', title: '{$tr['edit']}', orderable: false, {$datatablesGameEditor} },
            {data: 'is_new', title: '{$tr['State']}', orderable: false, {$datatablesSyncGameBtn} }
        ]
    });     
    
}

// 娛樂城選項及按鈕
function casinoSelector(){
    $.ajax({
    	url: 'gapi_gamelist_management_action.php?a=hall',
    	dataType: 'html',
    	success: function(data) {
    		$("#{$casino_select_id}").html(data);
    	  
    	  	$("#{$casino_query_id}").change(function() {
    	  	    if ($("#all")) {
    	  	        $("#all").remove();
    	  	    }

    			let cid = this.value;
    			$('.d-inline').removeClass('d-inline').addClass('d-none');
    			$("#" + cid +"_btn").removeClass('d-none').addClass('d-inline');
    			$("#" + cid +"_btn").on('click', function() {
    		    	$('#batchSync').removeClass('d-none').addClass('d-inline');
    				$('#{$api_game_list_id}').DataTable()
    					.ajax.url('gapi_gamelist_management_action.php?a=game_list&update=0&cid=' + cid)
    					.load(function(data) {
    				  		$('#{$api_game_list_id}').DataTable().ajax.url('gapi_gamelist_management_action.php?a=game_list&update='+ data.update +'&cid=' + cid);
    					});
    				batchSync();
    			});
    		});
    	}
    });
}

// 批次同步
function batchSync() {
  	$('#batchSync').unbind('click').on('click', function() {
  		let table = $('#{$api_game_list_id}').DataTable();
  		let games = table.rows().data();
  		let form_data = new FormData();
  		let j = 0;
  		for (let i=0; i < games.length; i++) {
  		    if (games[i].is_new == 1) {
  		        form_data.append(j.toString(), games[i].id);
  		        j++;
  		    }
  		}
  		
  		if (j > 0) {
  		    $.ajax({
  				url: 'gapi_gamelist_management_action.php?a=batchSync',
  				type: 'POST',
	        	data : form_data,
	        	cache:false,
		    	contentType: false,
		    	processData: false,
  				success: function(result) {
					var result = JSON.parse(result);
			        if (result.result == 1)  {
			            let table = $('#{$api_game_list_id}').DataTable();
						table.ajax.reload(null, false);
			        } else if(result.result == -1) {
			            alert('{$tr['games']}{$tr['platform']} {$tr['or']} {$tr['category']} {$tr['or']} {$tr['reward']}{$tr['classification']} {$tr['is not matched']}!{$tr['please edit it']}.');
			        } else if(result.result == 0) {
			            alert('{$tr['does not need']} {$tr['synchronize']}');
			        }
  				}
  			});
  		} else {
  		    alert('{$tr['does not need']} {$tr['synchronize']}');
  		}
  	});
}

// 顯示編輯視窗前動作
function initGameEditor()
{
    // 取得值
	$('#{$game_editor_id}').on('show.bs.modal', function(e) {
	    var gameData = $(e.relatedTarget);
		var modal = $(this);
		
		// 組成平台選項
	    var form_data = new FormData();
	    let cid = gameData.data('cid');
	    form_data.append('cid', cid);
		form_data.append('platform', gameData.data('gameplatform'));
	    $.ajax({
			url: 'gapi_gamelist_management_action.php?a=getGameplatform',
	        dataType: 'html',
	        type: 'POST',
	        data : form_data,
		    cache:false,
		    contentType: false,
		    processData: false,
	        success: function(data) {
	            $('#{$game_data_platform_id}').html(data);
	            modal.find('.modal-body #{$game_data_platform_id}').val(gameData.data('gameplatform'));
	        }
		});
	    
	    // 反水分類
	    $.ajax({
			url: 'gapi_gamelist_management_action.php?a=favorableTypes&cid=' + cid,
	        dataType: 'html',
	        type: 'GET',
		    cache:false,
		    contentType: false,
		    processData: false,
	        success: function(data) {
	            $('#{$game_data_favorable_type_id}').html(data);
	            modal.find('.modal-body #{$game_data_favorable_type_id}').val(gameData.data('favorable_type')).attr("selected", true);
	        }
		});
	    
	    // 行銷類別
	    $.ajax({
			url: 'gapi_gamelist_management_action.php?a=mct&cid=' + cid,
	        dataType: 'html',
	        type: 'GET',
		    cache:false,
		    contentType: false,
		    processData: false,
	        success: function(data) {
				$('#{$game_data_ms_category_id}').html(data);
				modal.find('.modal-body #{$game_data_ms_category_id}').val(gameData.data('ms_mct')).attr("selected", true);
	        }
		});
	    
	    // 語系選項
	    genLanguageSelector(gameData.data('gid'), '', 1);
	    $('#{$game_data_display_name_id}').val('');
	    
	    
	    // 取得平台遊戲類別選項
	    $.ajax({
	    	url: 'gapi_gamelist_management_action.php?a=getCategoryByCasino&cid=' + cid,
	        dataType: 'html',
	        success: function(data) {
	        	$('#{$game_data_category_id}').html(data);
	        	modal.find('.modal-body #{$game_data_category_id}').val(gameData.data('category')).attr("selected", true);
	        }
	    });
	    
	    // 處理狀態
	    let open = '';
	    if (gameData.data('open') == 0) {
	        open = '{$tr['off']}';
	    } else if (gameData.data('open') == 1) {
	        open = '{$tr['Enabled']}';
	    }
	    
	    // 遊戲類別
	    let gametype = '';
	    if (gameData.data('gametype') != null) {
	        gametype = gameData.data('gametype');
	    } 
	    
	    // 綁定資料
		modal.find('.modal-title').text('{$tr['edit']} '+gameData.data('gamename_cn')+' {$tr['data']}');
		modal.find('.modal-body #{$game_data_gid_id}').text(gameData.data('gid'));
		modal.find('.modal-body #{$game_data_cid_id}').val(gameData.data('cid'));
		modal.find('.modal-body #{$game_data_casino_name_id}').text(gameData.data('casino_name'));
		modal.find('.modal-body #{$game_data_gametype_id}').text(gametype);
		modal.find('.modal-body #{$game_data_gameid_id}').text(gameData.data('game_id'));
		modal.find('.modal-body #{$game_data_sub_category_id}').val(gameData.data('sub_category'));
		modal.find('.modal-body #{$game_data_name_id}').val(gameData.data('gamename'));
		modal.find('.modal-body #{$game_data_cn_name_id}').val(gameData.data('gamename_cn'));
		modal.find('.modal-body #{$game_data_ms_hotgame_id}').prop("checked", gameData.data('ms_hot'));
		modal.find('.modal-body #{$game_data_ms_marketing_tag_id}').val(gameData.data('ms_tag'));
		modal.find('.modal-body #{$game_data_ms_category_2nd_id}').val(gameData.data('ms_category2'));
		modal.find('.modal-body #{$game_data_gameid_id}').val(gameData.data('game_id'));
		modal.find('.modal-body #{$game_data_open_id}').text(open);
		modal.find('.modal-body #{$game_data_row_id}').val(gameData.data('row'));
	});
	
	updateDisplayName();
}

// 寫入修改遊戲參數
function saveImportGame(){
    // 取得寫入資料
    var row = $('#{$game_data_row_id}').val();
	var cid = $('#{$game_data_cid_id}').val();
	var gid = $('#{$game_data_gid_id}').text();
	var game_id =  $('#{$game_data_gameid_id}').val();
	var category = $('#{$game_data_category_id}').val();
	var sub_category = $('#{$game_data_sub_category_id}').val();
	var name = $('#{$game_data_name_id}').val();
	var cn_name = $('#{$game_data_cn_name_id}').val();
	var platform = $('#{$game_data_platform_id}').val();
	var hot = '0'; 
	if($('#{$game_data_ms_hotgame_id}').prop('checked')) { hot = '1' };
	var mct = $('#{$game_data_ms_category_id}').val();
	var tag = $('#{$game_data_ms_marketing_tag_id}').val();
	var category2 = $('#{$game_data_ms_category_2nd_id}').val();
	var icon = $('#{$game_data_image_file_name_id}').val();
	var favorable_type = $('#{$game_data_favorable_type_id}').val();
	var i18n = $('#{$game_data_i18n_name_id}').val();
	var displayName = $('#{$game_data_display_name_id}').val();
	
	// 組成傳遞參數
	var form_data = new FormData();
	form_data.append('row', row);
	form_data.append('cid', cid);
	form_data.append('gid', gid);
	form_data.append('game_id', game_id);
	form_data.append('category', category);
	form_data.append('sub_category', sub_category);
	form_data.append('name', name);
	form_data.append('cn_name', cn_name);
	form_data.append('platform', platform);
	form_data.append('hot', hot);
	form_data.append('mct', mct);
	form_data.append('tag', tag);
	form_data.append('category2', category2);
	form_data.append('icon', icon);
	form_data.append('favorable_type', favorable_type);
	form_data.append('i18n', i18n);
	form_data.append('display', displayName);
	
	// 寫 ajax 寫進 DB
	$.ajax({
		url: 'gapi_gamelist_management_action.php?a=save_import',
		type: 'POST',
		data : form_data,
	    cache:false,
	    contentType: false,
	    processData: false,
	    success: function(result) {
	    	  var result = JSON.parse(result);
	    	  if (result.result == 1)  {
	    	      // 更新單列
	    	      var newGame = getGameById(gid);
	    	      var table = $('#{$api_game_list_id}').DataTable();
	    	      var row = table.row(result.row);
	    	      row.data(newGame);
	    	      row.draw(false);
	    	      // 隱藏編輯視窗
	    	      $('#{$game_editor_id}').modal('hide');
	    	      // 恢復同步按鈕
	    	      $('#{$sync_game_btn_id}').attr('disabled', false);
	    	  }
	    },
	    error: function() {
	    	window.alert('{$tr['error, please contact the developer for processing.']}');
	    }
	});
}

// 取得單一遊戲
function getGameById(id){
    var form_data = new FormData();
    form_data.append('id', id);
    $.ajax({
        url: 'gapi_gamelist_management_action.php?a=getGame',
        type: 'POST',
		data : form_data,
	    cache:false,
	    contentType: false,
	    processData: false,
	    success: function(result){
            return result;
	    },
	    error: function() {
            return null;
	    }
    });
}


// 快速同步遊戲
function quickSyncGame(id, row){
    // 組成傳遞參數
	var form_data = new FormData();
	form_data.append('row', row);
	form_data.append('id', id);
	
	// 寫 ajax 寫進 DB
	$.ajax({
		url: 'gapi_gamelist_management_action.php?a=quickSync',
		type: 'POST',
		data : form_data,
	    cache:false,
	    contentType: false,
	    processData: false,
	    success: function(result) {
	    	  var result = JSON.parse(result);
	    	  if (result.result == 1)  {
	    	      // 更新單列
	    	      var newGame = getGameById(id);
	    	      var table = $('#{$api_game_list_id}').DataTable();
	    	      var row = table.row(result.row);
	    	      row.data(newGame);
	    	      row.draw(false);
	    	      // 隱藏編輯視窗
	    	      $('#{$game_editor_id}').modal('hide');
	    	  } else if(result.result == -1) {
	    	      alert('{$tr['games']}{$tr['platform']} {$tr['or']} {$tr['category']} {$tr['or']} {$tr['reward']}{$tr['classification']} {$tr['is not matched']}!{$tr['please edit it']}.');
	    	  }
	    },
	    error: function() {
	    	window.alert('{$tr['error, please contact the developer for processing.']}');
	    }
	});
}


// 語系選項
function genLanguageSelector(gid, langKey, allLang) {
	$.ajax({
		url: 'gapi_gamelist_management_action.php?a=i18nGameNames&gid='+ gid,
	   	dataType: 'json',
	   	type: 'GET',
	  	cache:false,
	   	contentType: false,
	   	processData: false,
	   	success: function(data) {
		    // 取得支援的語系
		    let languageKeys = Object.keys(data);
		    let lanHtml = $('#{$game_data_i18n_name_id}');
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
			let displayInput = $('#{$game_data_display_name_id}');
			lanHtml.change(function() {
			   	let lanKey = lanHtml.val();
			    displayInput.val(data[lanKey].name);
			});
				    
		}
	});	
}


// 設定語系名稱
function updateDisplayName() {
    $("#{$update_game_name_btn_id}").on("click", function() {
    	updateLanguageName();
	});    	
}


// 更新語系名稱
function updateLanguageName() {
    let row = $('#{$game_data_row_id}').val();
	let gid = $('#{$game_data_gid_id}').text();
   	let lanKey = $('#{$game_data_i18n_name_id}').val();
   	let name = $('#{$game_data_display_name_id}').val();
   	$.ajax({
	    url: "gapi_gamelist_management_action.php?a=updateName",
	    type: "post",
	    data: {
			id: gid,
	        i18n: lanKey,
	        name: name
	    },
	    success: function(data) {
	        if (JSON.parse(data).result == 1) {
	    	    genLanguageSelector(gid, lanKey, 1);
	    	    // 更新單列
	    	    var newGame = getGameById(gid);
	    	    var table = $('#{$api_game_list_id}').DataTable();
	    	    var row = table.row(row);
	    	    row.data(newGame);
	    	    row.draw(false);
	        } else if (JSON.parse(data).result == 0) {
	            // do nothing
	        } else {
	            alert('{$tr['game name']}{$tr['not']}{$tr['update']}!');
	        }                      
	    }
	});     
}


$(document).ready(function() {
	// 進頁面後檢查是否是第一次進入
	$.ajax({
		url: 'gapi_gamelist_management_action.php?a=first',
	    success: function() {
	        genDataTables();
	        casinoSelector();
	        initGameEditor();
	    },
	    error: function(e) {
	      console.log(e);
	    }
	});
	
})
</script>
HTML;

// table HTML
$gapi_gamelist_management_html .= <<< HTML
<div id="{$api_game_manage_id}">
	<table class="table table-hover" id="{$api_game_list_id}"></table>	
</div>
HTML;

// 編輯頁 HTML
$gapi_gamelist_management_html .= $modalGameEditorHtml;

// 單頁 CSS
$extend_head .= <<< HTML
<style>
/*分頁*/
.pagination {
	float: right;
}
#{$casino_select_id}{
	display: flex;
}
#{$casino_query_id}{
	width: 200px;
}

#api_list_filter>label{
	width: 250px;
	display: flex;
}
#api_list_filter>label>input{
	width: 200px;
	display: flex;
}

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
</style>
HTML;

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title']	= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']	= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $gapi_gamelist_management_html;

// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示
//include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");
?>