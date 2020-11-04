<!--actor_management_view.php -->
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
    <style type="text/css">
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
<?php end_section(); ?>
<!-- end of extend_head -->

<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
    <ol class="breadcrumb">
    <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
    <li><a href="#"><?php echo $tr['maintenance']; ?></a></li>
    <li class="active"><?php echo $function_title; ?></li>
    </ol>
<?php end_section(); ?>
<!-- end of page_title -->

<!-- 主要內容  title -->
<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
    <i class="fas fa-user-lock"></i><?php echo $tr['role managment']; ?>
    <div id="csv"  style="float:right;margin-bottom:auto"></div>
<?php end_section(); ?>
<!-- end of paneltitle_content -->

<!-- 主要內容 content -->
<?php begin_section('panelbody_content'); ?>
    <!-- 分頁 -->
    <div class="col-12 tab">
        <ul class="nav nav-tabs">
            <li>
                <a href="admin_management.php" target="_self"><?php echo $tr['sub-account management'];?></a>
            </li>
            <li  class="active">
                <a href="" target="_self"><?php echo $tr['role managment'];?></a>
            </li>
            <?php
                // 必須是維運角色才可以使用權限管理(站長、客服都不可以)
                if ( ($_SESSION['agent']->therole == 'R') && (in_array($_SESSION['agent']->account, $su['ops'])) ) {
                    echo <<<HTML
                        <li class="">
                            <a href="page_management.php" target="_self">{$tr['page_management-title']}</a>
                        </li>
                    HTML;
                }
            ?>
        </ul>
    </div>
    <br>

    <form class="form form-inline"  id="form_main" autocomplete="Off" >

        <!-- 搜尋框 -->
        <div class="form-group col-4">
            <span class="form-control-lg"><?php echo $tr['function name'] ?? 'function name';?></span>
            <div class="input-group"><!-- col-6  -->
                <input type="text" id="function_name" class="form-control form-control-lg" placeholder="<?php echo $tr['function name'] ?? 'function name';?>">
                <button class="btn bg-transparent" id="clear_function_name" style="margin-left: -30px; z-index: 100; display:none;" title='clear'>
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>

        <!-- function 狀態選擇 -->
        <div class="form-group col-2">
            <span class="form-control-lg"><?php echo $tr['enabled'] ?? 'status';?></span>
            <select id="function_status" class="form-control form-control-lg col-6">
                <option value="all"><?php echo $tr['all'] ?? 'all';?></option>
                <option value="t"><?php echo $tr['y'] ?? 'enabled';?></option>
                <option value="f"><?php echo $tr['n'] ?? 'disabled';?></option>
            </select>
        </div>


        <div class="col-1">
            <button  class="btn btn-primary" id="submit_to_inquiry" style="display:none;"><?php echo $tr['search'] ?? 'search';?></button>
        </div>

        <div class="col-1">
        </div>
    </form>
    <hr>

    <div id="inquiry_result_area">
        <table id="show_list"  class="display" cellspacing="0" width="100%" >
            <thead>
                <tr>
                    <th><?php echo ( isset($tr['ID']) ? $tr['ID'] : 'num' ); ?></th>
                    <th><?php echo ( isset($tr['function code']) ? $tr['function code'] : 'function code' ); ?></th>
                    <th><?php echo ( isset($tr['function name']) ? $tr['function name'] : 'function name' ); ?></th>
                    <th><?php echo ( isset($tr['function group name']) ? $tr['function group name'] : 'group name' ); ?></th>
                    <th><?php echo ( isset($tr['open state']) ? $tr['open state'] : 'open state' ); ?></th>
                    <th><?php echo ( isset($tr['State']) ? $tr['State'] : 'state' ); ?></th>
                    <th><?php echo ( isset($tr['function maintain status']) ? $tr['function maintain status'] : 'maintain status' ); ?></th>
                    <th><?php echo ( isset($tr['update time']) ? $tr['update time'] : 'updated at' ); ?></th>
                    <th><?php echo ( isset($tr['operation']) ? $tr['operation'] : 'operation' ); ?></th>
                </tr>
            </thead>

            <tfoot>
                <tr>
                    <th><?php echo ( isset($tr['ID']) ? $tr['ID'] : 'num' ); ?></th>
                    <th><?php echo ( isset($tr['function code']) ? $tr['function code'] : 'function code' ); ?></th>
                    <th><?php echo ( isset($tr['function name']) ? $tr['function name'] : 'function name' ); ?></th>
                    <th><?php echo ( isset($tr['function group name']) ? $tr['function group name'] : 'group name' ); ?></th>
                    <th><?php echo ( isset($tr['open state']) ? $tr['open state'] : 'open state' ); ?></th>
                    <th><?php echo ( isset($tr['State']) ? $tr['State'] : 'state' ); ?></th>
                    <th><?php echo ( isset($tr['function maintain status']) ? $tr['function maintain status'] : 'maintain status' ); ?></th>
                    <th><?php echo ( isset($tr['update time']) ? $tr['update time'] : 'updated at' ); ?></th>
                    <th><?php echo ( isset($tr['operation']) ? $tr['operation'] : 'operation' ); ?></th>
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

    // auto scroll to top of page
    function paginateScroll(){
        $("html, body").animate({
            scrollTop: 0
        }, 100);
    } // end paginateScroll


    $(function() {
        // 如果初次載入時，搜尋框不為空，則要顯示清空鈕
        if( $('#function_name').val() != '' ){
            $('#clear_query_account').show();
        }

        $("#show_list").DataTable({
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
            "aaSorting": [
                [ 5, "desc" ]
            ],
            'ajax': {
                'url': 'actor_management_action.php',
                'type': 'POST',
                'data': {}
            },
            'columnDefs': [
                {'targets': [0], 'className': 'dt-center', 'orderable': false},
                {'targets': [1], 'className': 'dt-left'},
                {'targets': [2], 'className': 'dt-left'},
                {'targets': [3], 'className': 'dt-left'},
                {'targets': [4], 'className': 'dt-center'},
                {'targets': [5], 'className': 'dt-center'},
                {'targets': [6], 'className': 'dt-center'},
                {'targets': [7], 'className': 'dt-center'},
                {'targets': [8], 'className': 'dt-center', 'orderable': false}
            ],

            'columns': [
                { 'data': 'id', 'width':'6%' },
                { 'data': 'function_code', 'width':'21%'},
                { 'data': 'function_name', 'width':'15%'},
                { 'data': 'function_group_name', 'width': '10%'},
                { 'data': 'public_status', 'width':'9%'},
                { 'data': 'status', 'width':'7%'},
                { 'data': 'maintain_status', 'width':'9%'},
                { 'data': 'updated_at', 'width':'15%'},
                { 'data': 'operation', 'width':'8%'}
            ],
            'rowCallback': function( row, data ){
                // 調整顯示N筆的select寬度
                $('#show_list_length > label > select').css('width', 'auto');
                $('#show_list_filter').remove();
            },
            'drawCallback': function( settings ){
                // 調整顯示N筆的select寬度
                $('#show_list_length > label > select').css('width', 'auto');
                $('#show_list_filter').remove();
            }
        }); // end DataTable

        $('body').on('click', '#submit_to_inquiry', function(){
            var search_detail = {};
                search_detail.function_name = $('#function_name').val();
                search_detail.function_status = $('#function_status').val();
                $('#show_list').DataTable().search( JSON.stringify(search_detail) ).draw(true);
                return false;
        }); // end on

        // 輸入同時執行搜尋
        $('body').on('keyup', '#function_name', function(){
            $('#submit_to_inquiry').click();
            if( $(this).val() != '' ){
                $('#clear_function_name').show();
            }
            else{
                $('#clear_function_name').hide();
            }
        }); // end on

        $('body').on('change', '#function_status', function(){
            $('#submit_to_inquiry').click();
        }); // end on

        $('body').on('click', '#clear_function_name', function(){
            $('#function_name').val('');
            $(this).hide();
            $('#submit_to_inquiry').click();
            return false;
        }); // end on
    });  // END FUNCTION

    </script>
<?php end_section(); ?>
<!-- end of extend_js