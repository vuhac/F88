<?php // page_management_view.php ?>
<?php function_exists('use_layout') or die() ?>
<?php use_layout("template/beadmin.tmpl.php") ?>


<?php // 頁首的CSS與js ?>
<?php begin_section('extend_head') ?>
    <?php // jquery blockui ?>
    <script type="text/javascript" src="in/jquery.blockUI.js"></script>

    <?php // dataTable ?>
    <script src="in/datatables/js/jquery.dataTables.min.js"></script>
    <link href="in/datatables/css/jquery.dataTables.min.css" rel="stylesheet">

    <style>
    </style>
<?php end_section() ?>


<?php // 瀏覽器頁籤顯示的標題 ?>
<?php begin_section('html_meta_title') ?>
<?php echo $tr['page_management-title'].'-'.$tr['host_name'] ?>
<?php end_section() ?>


<?php // 頁面中的標題 ?>
<?php begin_section('paneltitle_content')  ?>
    <span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>
    <?php echo $tr['page_management-title']; ?>
<?php end_section() ?>


<?php // 頁面中的內容 ?>
<?php begin_section('panelbody_content') ?>
    <?php // 分頁 ?>
    <div class="col-12 tab">
        <ul class="nav nav-tabs">
            <?php // 子帳號管理 ?>
            <li class="">
                <a href="admin_management.php" target="_self"><?php echo $tr['sub-account management'];?></a>
            </li>
            <?php // 權限管理 ?>
            <li class="">
                <a href="actor_management.php" target="_self"><?php echo $tr['role managment'];?></a>
            </li>
            <?php // 頁面管理 ?>
            <li class="active">
                <a href="page_management.php" target="_self"><?php echo $tr['page_management-title'];?></a>
            </li>
        </ul>
    </div>
    <br>

    <form class="form form-inline"  id="main_form" autocomplete="Off" >
        <?php // 搜尋框 ?>
        <div class="form-group">
            <span class="form-control-lg"><?php echo $tr['page_management-search'];?></span>
            <div class="input-group">
                <input type="text" id="form_search" class="form-control form-control-lg" placeholder="<?php echo $tr['page_management-search_placeholder'];?>">
                <button class="btn bg-transparent" id="form_search_clear" style="margin-left: -30px; z-index: 100; display:none;" title='Clear'>
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>

        <?php // Function選擇 ?>
        <div class="form-group">
            <span class="form-control-lg"><?php echo $tr['page_management-function']; ?></span>
            <select id="form_function_search" class="form-control form-control-lg">
                <option value="" selected disabled><?php echo $tr['page_management-function_option_placeholder']; ?></option>
                <?php
                    if ( isset($function_datas) && (count($function_datas) > 0) ) {
                        foreach ($function_datas as $val) {
                            echo '<option value="'.$val->function_name.'">'.$val->function_title.'</option>';
                        }
                    }
                ?>
            </select>
        </div>

        <div class="col-4">
            <button  class="btn btn-primary" id="form_submit"><?php echo $tr['page_management-search']; ?></button>
        </div><hr>
    </form><hr>

    <table id="main_table">
        <thead>
            <tr>
                <th><?php echo $tr['page_management-table-thead-num']; ?></th>
                <th><?php echo $tr['page_management-table-thead-page_name']; ?></th>
                <th><?php echo $tr['page_management-table-thead-function_title']; ?></th>
                <th><?php echo $tr['page_management-table-thead-group_name']; ?></th>
                <th><?php echo $tr['page_management-table-thead-page_description']; ?></th>
                <th><?php echo $tr['page_management-table-thead-operate']; ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
<?php end_section() ?>


<?php begin_section('extend_js'); ?>
    <script>
        $(function() {

            <?php // 如果初次載入時，搜尋框不為空，則要顯示清空鈕 ?>
            if ( $('#form_search').val() != '' ) {
                $('#form_search_clear').show();
            } else {
                $('#form_search_clear').hide();
            }

            <?php // 在搜尋框輸入時，偵測鍵盤事件 ?>
            $(document).on('keyup', '#form_search', function(e)
            {
                <?php // 按下Enter鍵，執行搜尋 ?>
                if (e.which == 13) {
                    $('#form_submit').click();
                    return false;
                }

                <?php // 搜尋框有值時，要顯示清除的叉叉 ?>
                var _val = $(this).val();
                if ( (_val != '') && (_val.length > 0) ) {
                    $('#form_search_clear').show();
                }
            });

            <?php // 按下搜尋框中的清除按鈕 ?>
            $(document).on('click', '#form_search_clear', function()
            {
                $('#form_search').val('');
                $(this).hide();
                return false;
            });

            <?php // DataTable ?>
            $('#main_table').DataTable({
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
                    // [ 5, "desc" ]
                ],
                'ajax': {
                    'url': 'page_management_action.php',
                    'type': 'GET',
                    'data': {}
                },
                'columnDefs': [
                    {'targets': [0], 'className': 'dt-center', 'orderable': false},
                    {'targets': [1], 'className': 'dt-left'},
                    {'targets': [2], 'className': 'dt-left'},
                    {'targets': [3], 'className': 'dt-left'},
                    {'targets': [4], 'className': 'dt-left', 'orderable': false},
                    {'targets': [5], 'className': 'dt-center', 'orderable': false}
                ],
                'columns': [
                    { 'data': 'num', 'width':'7%' },
                    { 'data': 'page_name', 'width':'20%'},
                    { 'data': 'function_title', 'width':'20%'},
                    { 'data': 'group_name', 'width': '10%'},
                    { 'data': 'page_description', 'width':'35%'},
                    { 'data': 'edit', 'width':'8%'}
                ],
                'rowCallback': function(row, data) {
                    <?php // // 調整顯示N筆的select寬度 ?>
                    $('#main_table_length > label > select').css('width', 'auto').css('margin-left', '5px').css('margin-right', '5px');
                    $('#main_table_filter').remove();
                },
                'drawCallback': function(settings) {
                    <?php // // 調整顯示N筆的select寬度 ?>
                    $('#main_table_length > label > select').css('width', 'auto').css('margin-left', '5px').css('margin-right', '5px');
                    $('#main_table_filter').remove();
                }
            });

            <?php // 執行搜尋 ?>
            $(document).on('click', '#form_submit', function()
            {
                var search = {};
                    search.form_search = $('#form_search').val();
                    search.function_search = $('#form_function_search').val();
                    $('#main_table').DataTable().search( JSON.stringify(search) ).draw(true);
                    return false;
            });
        });
    </script>
<?php end_section(); ?>
