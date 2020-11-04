<?php @session_start();?>
<!-- admin_management_view_new.php -->
<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>

    <!-- Jquery UI js+css  -->
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
    <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>

    <!-- 自訂css -->
    <link rel="stylesheet" type="text/css" href="ui/style_seting.css">

    <style>
    .input-group>.custom-select:not(:first-child), .input-group>.form-control:not(:first-child){
        border-radius:3px;
    }
    </style>

<?php end_section(); ?>
<!-- end of extend_head -->

<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
    <ol class="breadcrumb">
        <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
        <li><a href="#"><?php echo $tr['webmaster']; ?></a></li>
        <li class="active"><?php echo $function_title; ?></li>
    </ol>
<?php end_section(); ?>
<!-- end of page_title -->

<!-- 主要內容  title -->
<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
    <i class="fas fa-user-lock"></i><?php echo $tr['sub-account management']; ?>
    <div id="csv" style="float:right; margin-bottom:auto;"></div>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<!-- 主要內容 content -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>
    <div class="col-12 tab">
        <ul class="nav nav-tabs">
            <li class="active">
                <a href="" target="_self"><?php echo $tr['sub-account management'];?></a>
            </li>
            <?php
                // 必須是維運角色才可以使用權限管理(站長、客服都不可以)
                if ( ($_SESSION['agent']->therole == 'R') && (in_array($_SESSION['agent']->account, $su['ops'])) ) {
                    echo <<<HTML
                        <li>
                            <a href="actor_management.php" target="_self">{$tr['role managment']}</a>
                        </li>
                        <li class="">
                            <a href="page_management.php" target="_self">{$tr['page_management-title']}</a>
                        </li>
                    HTML;
                }
            ?>
        </ul>
    </div>
    <br>

    <form class="form form-inline" id="form_main" autocomplete="Off" >
        <!-- 管理員帳號 -->
        <div class="input-group col-3.5">
            <span class=" form-control-lg"><?php echo $tr['administrator account'];?></span>
            <input type="text" id="query_account" class="form-control form-control-lg" placeholder="<?php echo $tr['administrator account'];?>" value="">
            <button class="btn bg-transparent" id="clear_query_account" style="margin-left: -30px; z-index: 100; display:none;">
                <i class="fa fa-times"></i>
            </button>
        </div>

        <!-- 帳號狀態 -->
        <div class="input-group col-2.5">
            <span class="form-control-lg"><?php echo $tr['enabled'];?></span>
            <select class="form-control form-control-lg" id="account_status">
                <option value="3"><?php echo $tr['all'] ;?></option>
                <option value="1"><?php echo $tr['y'];?></option>
                <option value="0"><?php echo $tr['n'];?></option>
                <option value="2"><?php echo $tr['freeze'];?></option>
            </select>
        </div>

        <!-- 搜尋按鈕 -->
        <div class="col-1">
            <button  class="btn btn-primary" id="submit_to_inquiry" style="display:none;"> <?php echo $tr['search'];?> </button>
        </div>

        <!-- 新增管理員按鈕 -->
        <div class="col-1 ml-auto">
            <a class="btn btn-success float-right" id="add_admin_account" href="#" role="button" >
            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span> <?php echo $tr['add administrator'] ;?> </a>
        </div>
    </form>
    <hr>

    <div id="inquiry_result_area">
        <table id="show_list"  class="display" cellspacing="0" width="100%" >
            <thead>
                <tr>
                    <th><?php echo $tr['NUM'] ?? 'NUM'; ?></th>
                    <th><?php echo $tr['administrator']; ?></th>
                    <th><?php echo $tr['State']; ?></th>
                    <th><?php echo $tr['last update time']; ?></th>
                    <th><?php echo $tr['note']; ?></th>
                    <th><?php echo $tr['operation']; ?></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th><?php echo $tr['NUM'] ?? 'NUM'; ?></th>
                    <th><?php echo $tr['administrator']; ?></th>
                    <th><?php echo $tr['State']; ?></th>
                    <th><?php echo $tr['last update time']; ?></th>
                    <th><?php echo $tr['note']; ?></th>
                    <th><?php echo $tr['operation']; ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <br>
    <div class="row">
        <div id="preview_result"></div>
    </div>
<?php end_section(); ?>
<!-- end of panelbody_content -->

<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>

<script>
$(function(){
    // 如果初次載入時，搜尋框不為空，則要顯示清空鈕
    if( $('#query_account').val() != '' ){
        $('#clear_query_account').show();
    }

    function paginateScroll() { // auto scroll to top of page
        $("html, body").animate({
            scrollTop: 0
        }, 100);
    } // end paginateScroll

    // 表格內容
    $('#show_list').DataTable({
        'bLengthChange': true,
        'bProcessing': true,
        'bServerSide': true,
        'bRetrieve': true,
        'searching': true,
        'pageLength': 10,
        'info': true,
        'paging': true,
        'stateSave': false,
        'lengthMenu': [
            [10, 15, 25, 50],
            [10, 15, 25, 50]
        ],
        'language': {
            'url': './in/datatables/languages/Simplified_Chinese.json'
        },
        'aaSorting': [
            [3, 'desc' ]
        ],
        'ajax': {
            'url': 'admin_management_action.php',
            'type': 'POST',
            'data': {}
        },
        'columnDefs': [
            {'targets': [0], 'className': 'dt-center'},
            {'targets': [1], 'className': 'dt-center'},
            {'targets': [2], 'className': 'dt-center'},
            {'targets': [3], 'className': 'dt-center'},
            {'targets': [4], 'className': 'dt-center w-50'},
            {'targets': [5], 'className': 'dt-center', 'orderable': false}
        ],
        'columns': [
            {'data': 'id'},
            {'data': 'account'},
            {'data': 'status'},
            {'data': 'changetime'},
            {'data': 'notes'},
            {'data': 'opt'}
        ],
        'rowCallback': function( row, data ){
            $('#show_list_length > label > select').css('width', 'auto');
            $('#show_list_filter').remove();
        },
        'drawCallback': function( settings ){
            $('#show_list_length > label > select').css('width', 'auto');
            $('#show_list_filter').remove();
        }
    }); // end DataTable

    // 執行搜尋
    $('body').on('click', '#submit_to_inquiry', function(){
        var search_detail = {};
            search_detail.account = $('#query_account').val();
            search_detail.account_status = $('#account_status').val();
            $('#show_list').DataTable().search( JSON.stringify(search_detail) ).draw(true);
            return false;
    }); // end on

    // 輸入同時執行搜尋
    $('body').on('keyup', '#query_account', function(){
        $('#submit_to_inquiry').click();
        if( $(this).val() != '' ){
            $('#clear_query_account').show();
        }
        else{
            $('#clear_query_account').hide();
        }
    }); // end on

    // 新增管理員帳號
    $('body').on('click', '#add_admin_account', function(){
        <?php unset($_SESSION['edit_account']); ?>
        location.replace('admin_management_edit.php');
    }); // end on

    $('body').on('change', '#account_status', function(){
        $('#submit_to_inquiry').click();
    }); // end on

    $('body').on('click', '#clear_query_account', function(){
        $('#query_account').val('');
        $(this).hide();
        $('#submit_to_inquiry').click();
        return false;
    }); // end on

}); // END FUNCTION

</script>
<?php end_section(); ?>
<!-- end of extend_js