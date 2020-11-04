<?php // page_management_detail_view.php ?>
<?php function_exists('use_layout') or die() ?>
<?php use_layout("template/beadmin.tmpl.php") ?>


<?php // 頁首的CSS與js ?>
<?php begin_section('extend_head') ?>
    <style>
        textarea{
            width: 100%;
            min-height: 90px;
            overflow: auto;
            resize: none;
        }
    </style>
<?php end_section() ?>


<?php // 瀏覽器頁籤顯示的標題 ?>
<?php begin_section('html_meta_title') ?>
<?php echo $tr['page_management_detail-title'].'-'.$tr['host_name'] ?>
<?php end_section() ?>


<?php // 頁面中的標題 ?>
<?php begin_section('paneltitle_content')  ?>
    <span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>
    <?php echo $tr['page_management_detail-title']; ?>
<?php end_section() ?>


<?php // 頁面中的內容 ?>
<?php begin_section('panelbody_content') ?>
    <div class="panel-body">

        <?php // 頁面資訊 ?>
        <div class="row">
            <div class="col-12 col-md-12">
                <span class="label label-primary">
                    <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
                    <?php echo $tr['page_management_detail-page_information']; ?>
                </span>
            </div>
        </div>
        <br>

        <?php // 檔案名稱 ?>
        <div class="row">
            <div class="col-12 col-md-2">
                <p class="text-right"><?php echo $tr['page_management_detail-form-page_name']; ?></p>
            </div>
            <div class="col-4">
                <input type="text" class="form-control" id="page_name" value="<?php echo $page_data->page_name;?>" disabled>
            </div>
        </div>

        <?php // 所屬功能名稱 ?>
        <div class="row" style="margin-top: 20px;">
            <div class="col-12 col-md-2">
                <p class="text-right"><?php echo $tr['page_management_detail-form-function_name']; ?></p>
            </div>
            <div class="col-4">
                <select id="function_name" class="form-control">
                    <?php
                        if ( isset($function_datas) && (count(function_datas) > 0) ) {
                            foreach ($function_datas as $val) {
                                if ( isset($page_data->function_name) && ($page_data->function_name == $val->function_name) ) { // 已選選項
                                    echo '<option value="'.$val->function_name.'" selected>'.$val->function_title.'</option>';
                                } else { // 未選選項
                                    echo '<option value="'.$val->function_name.'">'.$val->function_title.'</option>';
                                }
                            }
                        }
                    ?>
                </select>
            </div><br>
        </div>

        <?php // 頁面描述 ?>
        <div class="row" style="margin-top: 20px;">
            <div class="col-12 col-md-2">
                <p class="text-right"><?php echo $tr['page_management_detail-form-page_description']; ?></p>
            </div>
            <div class="col-4">
                <textarea id="page_description" class="form-control"><?php
                    if ( isset($page_data->page_description) && !is_null($page_data->page_description) ) {
                        $page_description = (string)trim($page_data->page_description);
                        $page_description = preg_replace('/<br\\s*?\/??>/i', '', $page_description);
                        echo $page_description;
                    }
                ?></textarea>
            </div><br>
        </div>

        <?php // 功能項按鈕 ?>
        <div class="col-12 mx-auto" style="margin-top:15px;">
            <p class="text-center">
                <?php // 儲存 ?>
                <button id="submit" class="btn btn-success ml-3">
                    <span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp; <?php echo $tr['page_management_detail-form-save']; ?>
                </button>

                <?php // 取消 ?>
                <button id="cancel" class="btn btn-secondary ml-3">
                    <span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp; <?php echo $tr['page_management_detail-form-cancel']; ?>
                </button>
            </p>
        </div>

    </div>
<?php end_section() ?>


<?php begin_section('extend_js'); ?>
    <script>
        $(function() {
            $('#page_description').css('overflow', 'auto').css('width', '100%');

            <?php // 送出 ?>
            $(document).on('click', '#submit', function()
            {
                if ( $('#page_description').val() != '' ) {
                    if ( $('#page_description').val().trim().length == 0 ) {
                        var page_description = '';
                    } else {
                        var page_description = $('#page_description').val().trim();
                    }
                } else {
                    var page_description = '';
                }

                $.ajax({
                    url: 'page_management_detail_action.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        'page_name': $('#page_name').val(),
                        'function_name': {
                            'old': '<?php echo ( isset($page_data->function_name) ? $page_data->function_name : '' ); ?>',
                            'new': $('#function_name').val()
                        }, 'page_description': page_description
                    },
                    success: function(data) {
                        if (data.status == 'success') {
                            location.replace('page_management.php');
                        } else {
                            alert(data.title);
                        }
                    }, complete: function(XMLHttpRequest, textStatus) {}
                });
            });

            <?php // 取消，導向頁面管理 ?>
            $(document).on('click', '#cancel', function()
            {
                if ( confirm('<?php echo $tr['page_management_detail-form-cancel_alert_msg']; ?>') ) {
                    location.replace('page_management.php');
                }
            });
        });
    </script>
<?php end_section(); ?>
