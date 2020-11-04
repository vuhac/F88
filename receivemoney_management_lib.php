<?php
// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------
// 選單頁內容
function index_menu_stats_ad(){
    global $tr;
  // 選單頁陣列
  $tabmenu_array=['timeout'             =>$tr['time out'],
                  'cancel'              =>$tr['Cancel'],
                  'received'            =>$tr['received'],
                  'expired'             =>$tr['expired'],
                  'lottosum_canreceive' =>$tr['lottosum'].$tr['Can receive'],
                  'lottosum_timeout'    =>$tr['lottosum'].$tr['time out'],
                  'lottosum_cancel'     =>$tr['lottosum'].$tr['Cancel'],
                  'lottosum_received'   =>$tr['lottosum'].$tr['received'],
                  'lottosum_expired'    =>$tr['lottosum'].$tr['expired']
                 ];
  $nav_item=$tab_pane='';
  // print("<pre>" . print_r($tabmenu_array, true) . "</pre>");die();

  foreach($tabmenu_array as $key=>$val){
      $nav_item.=<<<HTML
          <li class="nav-item">
              <a class="nav-link" id="pills-{$key}-tab" data-toggle="pill" href="#pills-{$key}" role="tab" aria-controls="pills-{$key}" aria-selected="false">{$val}</a>
          </li>
      HTML;
      $tab_pane.=<<<HTML
           <div class="tab-pane fade" id="pills-{$key}" role="tabpanel" aria-labelledby="pills-{$key}-tab">
              <div id="{$key}_data"></div>
              <div id="{$key}_pagination"></div>
          </div>
      HTML;
  }

  $index_menu_content_html = <<<HTML
      <div id="loading"></div>
      <ul class="nav nav-pills mb-3 receivemoney_ul" id="pills-tab" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="pills-canreceive-tab" data-toggle="pill" href="#pills-canreceive" role="tab" aria-controls="pills-canreceive" aria-selected="true">{$tr['Can receive']}</a>
        </li>
        {$nav_item}
      </ul>
      <div class="tab-content" id="pills-tabContent">
        <div class="tab-pane fade show active" id="pills-canreceive" role="tabpanel" aria-labelledby="pills-canreceive-tab">
            <div id="can_receive_data"></div>
            <div id="can_receive_pagination"></div>
        </div>
        {$tab_pane}
      </div>
  HTML;

  return ($index_menu_content_html);
}

// 左上角選單按鈕及樣式
function index_menu()
{
    global $tr;
    // -------------------------------------------------------------------------
    // 輸出 系統內資料庫 root_receivemoney 的獎金報表, 以有已經有資料的為列表.
    // -------------------------------------------------------------------------
    // 左邊欄位的索引資料
    $index_menu_content_html = index_menu_stats_ad();
    // 加上 on / off開關 $tr['Menu'] = '選單';
    // <ul class="flymenu_button">
    // <li><a href="#" class="close_btn"><i class="fas fa-times"></i></a></li>
    // <li><a href="#" id="islottotab_menu" class="active">非彩票</a></li>
    // <li><a href="#" id="nonlottotab_menu">彩票</a></li>
    // </ul>
    $index_menu_switch_html = <<<HTML
      <span style="
          position: fixed;
          top: 5px;
          left: 5px;
          width: 420px;
          height: 20px;
          z-index: 1000;
          ">
          <button class="btn btn-primary btn-xs" style="display: none" id="hide">{$tr['Menu']}OFF</button>
          <button class="btn btn-success btn-xs" id="show">{$tr['Menu']}ON</button>
      </span>
      <div id="index_menu" 
          class="position-fixed d-none"
          style="
          background-color: rgb(255, 255, 255);
          top: 30px;
          left: 5px;
          width: 1000px;
          height: 720px;
          max-height: 90%;
          z-index: 999;
		  padding: 14px 20px;
          -webkit-box-shadow: 0px 8px 35px #333;
          -moz-box-shadow: 0px 8px 35px #333;
          box-shadow: 0px 8px 35px #333;
          ">
          {$index_menu_content_html}
      </div>
      <script>
        $(document).ready(function(){
        //非彩票與彩票切換
        $('#nonlottotab_menu').click(function(){
            $('.receivemoney_ul li').hide();
            $('.receivemoney_ul li:nth-of-type(5) ~ li').show();
            $('.flymenu_button li a').removeClass('active');
            $('#nonlottotab_menu').addClass('active');
            $('#pills-lottosum_canreceive-tab').click();
        });

        $('#islottotab_menu').click(function(){
            $('.receivemoney_ul li').show();
            $('.receivemoney_ul li:nth-of-type(5) ~ li').hide();
            $('.flymenu_button li a').removeClass('active');
            $('#islottotab_menu').addClass('active');
            $('#pills-canreceive-tab').click();
        });

           // $("#index_menu").fadeOut( "fast" );

            $('.close_btn').click(function(){
                $("#index_menu").fadeOut( "fast" );
                $("#index_menu").addClass('d-none');
                $("#hide").hide();
                $("#show").show();
            });

            $("#hide").click(function(){
                $("#index_menu").fadeOut( "fast" );
                $("#index_menu").addClass('d-none');
                $("#hide").hide();
                $("#show").show();
            });
            $("#show").click(function(){
                $("#index_menu").removeClass('d-none');
                $("#index_menu").fadeIn( "fast" );
                $("#hide").show();
                $("#show").hide();
            });
            $("#index_menu").on("click",".category",function(e){

              var category = $(this).attr('attr-category');
              var givetime = $(this).attr('attr-givetime');
              var astatus = $(this).attr('attr-status');
              var aloto = $(this).attr('attr-loto');
              var atimepoint = $(this).attr('attr-timepoint');
            // 存入db的時間，因有些資料存入的時間有毫秒，因此無法顯示
              var db_time = $(this).attr('attr-give_mony_db_time');

              $("#bonus_type").val(category);
              $("#bons_givemoneytime").val(givetime);
              $("#bonus_status").val(astatus);
              $("#yn_lotto").val(aloto);
              $("#timepoint").val(atimepoint);
              $("#member_account").val("");
              $("#bonus_validdatepicker_start").val("");
              $("#bonus_validdatepicker_end").val("");

             
              $("#db_time").val(db_time);

              query_receivemoney('0');
              $("#index_menu").fadeOut( "fast" );
              $("#hide").hide();
              $("#show").show();
            });
        });
      </script>

HTML;
    return ($index_menu_switch_html);
}

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -------------------------------------------------------------------------
// 本程式使用的 function END
// -------------------------------------------------------------------------


// action
// 將特殊字元加上跳脫
function escape_specialcharator($str)
{
    $reture_str = urldecode($str);
    $reture_str = preg_replace('/([\'])/ui', '&#146;', $reture_str);
    $reture_str = preg_replace('/([""])/ui', '&#148;', $reture_str);
    $reture_str = preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '\\\\$1', $reture_str);
    //$reture_str = preg_replace('/([\'])/ui', '\'\'',$reture_str);

    return $reture_str;
}

// 建立DB查詢條件
function sqlquery_str($query_str_arr)
{
    /*
    // var_dump($query_str_arr);
    $and_chk = 0;
    $return_sqlstr = '';
    if (isset($query_str_arr['bonus_type']) and $query_str_arr['bonus_type'] != '') {
        $return_sqlstr = $return_sqlstr . 'prizecategories like \'%'.$query_str_arr['bonus_type'].'%\'';
        // echo($return_sqlstr);
        $and_chk = 1;
    }

    if ($query_str_arr['bonus_status']!='') {
        if ($and_chk == 1) {
            $return_sqlstr = $return_sqlstr . ' AND ';
        }
        if ($query_str_arr['bonus_status'] == '3') {
            $return_sqlstr .= 'receivetime IS NOT NULL';
        } elseif ($query_str_arr['bonus_status'] == '4') {
            $return_sqlstr .= '(receivedeadlinetime < now() AND receivetime IS NULL)';
        } else {
            $return_sqlstr .= '(status = \'' . $query_str_arr['bonus_status'] . '\' AND receivedeadlinetime >= now() AND receivetime IS NULL)';
        }

        $and_chk = 1;
    }
    if (isset($query_str_arr['member_account']) and $query_str_arr['member_account'] != '') {
        if ($and_chk == 1) {
            $return_sqlstr = $return_sqlstr . ' AND ';
        }
        $return_sqlstr = $return_sqlstr . 'member_account = \'' . $query_str_arr['member_account'] . '\'';
        $and_chk = 1;
    }
    if (isset($query_str_arr['bonus_validdatepicker_start']) and $query_str_arr['bonus_validdatepicker_start'] != '') {
        if ($and_chk == 1) {
            $return_sqlstr = $return_sqlstr . ' AND ';
        }
        $return_sqlstr = $return_sqlstr . 'receivedeadlinetime >= \'' . $query_str_arr['bonus_validdatepicker_start'] . '\'';
        $and_chk = 1;
    }
    if (isset($query_str_arr['bonus_validdatepicker_end']) and $query_str_arr['bonus_validdatepicker_end'] != '') {
        if ($and_chk == 1) {
            $return_sqlstr = $return_sqlstr . ' AND ';
        }
        $return_sqlstr = $return_sqlstr . 'givemoneytime <= \'' . $query_str_arr['bonus_validdatepicker_end'] . '\'';
        $and_chk = 1;
    }

    if (isset($query_str_arr['bons_givemoneytime']) and $query_str_arr['bons_givemoneytime'] != '') {
        if ($and_chk == 1) {
            $return_sqlstr = $return_sqlstr . ' AND ';
        }
        $return_sqlstr = $return_sqlstr . 'givemoneytime = \'' . $query_str_arr['bons_givemoneytime'] . '\'';
        // $return_sqlstr = $return_sqlstr . 'givemoneytime = \'' . $query_str_arr['bons_givemoneytime_astime'] . '\'';

        $and_chk       = 1;
    }


    if ($and_chk == 1) {
        $return_sqlstr = ' WHERE ' . $return_sqlstr;
    } else {
        //$return_sqlstr = ' WHERE status = \'1\' AND receivedeadlinetime >= \''.$query_str_arr['current_datetimepicker'].'\'';
        $return_sqlstr = ' WHERE receivedeadlinetime >= \'' . $query_str_arr['current_datetimepicker'] . '\'';
    }
    // echo($return_sqlstr);die();
    return $return_sqlstr;
    */

    
    $and_chk = 0;//紀錄各參數是否有值
    $return_sqlstr = '';//init SQL條件式

    $bonus_type                     = (isset($query_str_arr['bonus_type']))?$query_str_arr['bonus_type']:'';
    $bonus_status                   = (isset($query_str_arr['bonus_status']))?$query_str_arr['bonus_status']:'';
    $member_account                 = (isset($query_str_arr['member_account']))?$query_str_arr['member_account']:'';
    $bonus_validdatepicker_start    = (isset($query_str_arr['bonus_validdatepicker_start']))?$query_str_arr['bonus_validdatepicker_start']:'';
    $bonus_validdatepicker_end      = (isset($query_str_arr['bonus_validdatepicker_end']))?$query_str_arr['bonus_validdatepicker_end']:'';
    $bons_givemoneytime_astime      = (isset($query_str_arr['bons_givemoneytime_astime']))?$query_str_arr['bons_givemoneytime_astime']:'';


    if ( $bonus_type != '') {
        $return_sqlstr .= 'prizecategories like \'%'.$bonus_type.'%\'';
        $and_chk = 1;//此參數有條件
    }else{
        $return_sqlstr .= '';
    }

    if ($bonus_status != '') {
        if ($and_chk == 1) {
            $return_sqlstr .= ' AND ';
        }
        
        switch($bonus_status){
            case '3':
                $return_sqlstr .= 'receivetime IS NOT NULL';
                break;
            case '4':
                $return_sqlstr .= '(receivedeadlinetime < now() AND receivetime IS NULL)';
                break;
            default:
                $return_sqlstr .= '(status = \'' . $bonus_status . '\' AND receivedeadlinetime >= now() AND receivetime IS NULL)';
                break;
        }
        $and_chk = 1;//此參數有條件
    }else{
        $return_sqlstr .= '';
    }


    if ($member_account != '') {
        if ($and_chk == 1) {
            $return_sqlstr .= ' AND ';
        }
        $return_sqlstr .= 'member_account = \'' . $member_account . '\'';
        $and_chk = 1;//此參數有條件
    }else{
        $return_sqlstr .= '';
    }


    
    if ($bonus_validdatepicker_start != '' && $bonus_validdatepicker_end != ''){
        if ($and_chk == 1) {
            $return_sqlstr .= ' AND ';
        }
        $return_sqlstr .= 'givemoneytime <= \'' . $bonus_validdatepicker_end . '\' AND receivedeadlinetime >= \'' . $bonus_validdatepicker_start . '\'';
        $and_chk = 1;
    }else if($bonus_validdatepicker_start != '' && $bonus_validdatepicker_end == ''){
        if ($and_chk == 1) {
            $return_sqlstr .= ' AND ';
        }
        $return_sqlstr .= 'receivedeadlinetime >= \'' . $bonus_validdatepicker_start . '\'';
        $and_chk = 1;
    }else{
        $return_sqlstr .= '';
    }

    if ($bons_givemoneytime_astime != '') {
        if ($and_chk == 1) {
            $return_sqlstr .= ' AND ';
        }
        // $return_sqlstr .= 'givemoneytime = \'' . $query_str_arr['bons_givemoneytime'] . '\'';
        $return_sqlstr .= 'givemoneytime = \'' . $bons_givemoneytime_astime . '\'';

        $and_chk = 1;//此參數有條件
    }else{
        $return_sqlstr .= '';
    }

    $return_sqlstr = ($and_chk == 1)?' WHERE ' . $return_sqlstr:'';//總和所有條件式
    
    return $return_sqlstr;
}

// 建立DB查詢條件
function sqlquery_arr($query_sql, $array_name,$k='')
{
    $query_result = runSQLall($query_sql);
    if($k=='lotto'){
            if ($query_result[0] >= 1) {
                for ($i = 1; $i <= $query_result[0]; $i++) {
                    for ($j = 1; $j <= $array_name[0]; $j++) {
                        if ($array_name[$j]->prizecategories == $query_result[$i]->prizecategories) {
                            $return_arr[$array_name[$j]->prizecategories] = $query_result[$i]->query_num;
                        } elseif (!isset($return_arr[$array_name[$j]->prizecategories])) {
                            $return_arr[$array_name[$j]->prizecategories] = 0;
                        }
                    }
                }
            } else {
                for ($j = 1; $j <= $array_name[0]; $j++) {
                    $return_arr[$array_name[$j]->prizecategories] = 0;
                }
            }

    }else{
            if ($query_result[0] >= 1) {
                for ($i = 1; $i <= $query_result[0]; $i++) {
                    for ($j = 1; $j <= $array_name[0]; $j++) {
                        if ($array_name[$j]->prizecategories == $query_result[$i]->prizecategories && $array_name[$j]->givemoneytime == $query_result[$i]->givemoneytime) {
                            $return_arr[$array_name[$j]->prizecategories . '_' . $array_name[$j]->givemoneytime] = $query_result[$i]->query_num;
                        } elseif (!isset($return_arr[$array_name[$j]->prizecategories . '_' . $array_name[$j]->givemoneytime])) {
                            $return_arr[$array_name[$j]->prizecategories . '_' . $array_name[$j]->givemoneytime] = 0;
                        }
                    }
                }
            } else {
                for ($j = 1; $j <= $array_name[0]; $j++) {
                    $return_arr[$array_name[$j]->prizecategories . '_' . $array_name[$j]->givemoneytime] = 0;
                }
            }
    } 
    // var_dump($return_arr);die();
    return $return_arr;
}

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
// function validateDate($date, $format = 'Y-m-d H:i:s')
// {
//     $d = DateTime::createFromFormat($format, $date);
//     return $d && $d->format($format) == $date;
// }

function status_helper($receivemoney)
{
    global $tr;

    if (!empty($receivemoney->receivetime)) {
        return $tr['received'];
    }

    $current_datetimepicker = gmdate("Y-m-d H:i:s", time()+8*3600) ;
    if ($current_datetimepicker > $receivemoney->receivedeadlinetime) {
        return $tr['expired'];
    }

    //$tr['Cancel'] = '取消';  $tr['Can receive'] = '可領取';   $tr['time out'] = '暫停';
    $status_option = [
        '0' => $tr['Cancel'],
        '1' => $tr['Can receive'],
        '2' => $tr['time out'],
        '3' => $tr['received'],
    ];

    return $status_option[$receivemoney->status];
}

function status_mapi18n(){
    global $tr;
    return [ 
          '0' => $tr['Cancel'],
          '1' => $tr['Can receive'],
          '2' => $tr['time out'],
          '3' => $tr['received'],
          '4' => $tr['expired']
      ];
}

// 選單頁沒資料時，表格輸出
// function menu_no_data_table($col=6){
//     global $tr;
//     $string='<td colspan="'.$col.'"  class="text-center nodata_table">'.$tr['no_data'].'</td>';
//     return '<tr>'.$string.'</tr>';
// }
// // 選單頁沒資料時，表格輸出
// function menu_no_data_table($col=6){
//     $string='';
//     for ($i=1;$i<=$col;$i++){
//         $string.='<td></td>';
//     }
//     return '<tr>'.$string.'</tr>';
// }

// 選單-輸出表格資料
function menu_table_data($loto='0',$status=0,$tmp,$now_datetime=''){
            global $tr;
            $index=[];
            $return_str='';
            if($loto=='1'){
                for ($j = 1; $j <= $tmp[0]; $j++) {
                    $index['category'] = $tmp[$j]->prizecategories;
                    $index['cash'] = $tmp[$j]->gcash_total;
                    $index['token'] = $tmp[$j]->gtoken_total;            
                    $index['member_count'] = $tmp[$j]->member_count;
                    $batch_receive_button = '';
                    if ($status == '1') {
                            $batch_receive_button = <<<HTML
                            <button id="batched_receive_{$j}" class="btn btn-sm btn-primary" onclick="batched_receive({i: $j, prizecategories: '{$index['category']}' ,ynloto:'{$loto}'});">{$tr['batch receive']}</button>
                    HTML;
                    }
                    $return_str.=<<<HTML
                        <tr>
                            <td class="category" style="width:130px;text-align:left;cursor:pointer;" 
                            attr-category="{$index['category']}" 
                            attr-givetime=""
                            attr-loto="{$loto}"
                            attr-status="{$status}"
                            attr-timepoint="{$now_datetime}"
                            ><a>{$index['category']}</a></td>
                            <td style="text-align:right;">{$index['cash']}</td>
                            <td style="text-align:right;">{$index['token']}</td>
                            <td style="text-align:right;">{$index['member_count']}</td>
                            <td style="text-align:center;">{$batch_receive_button}</td>
                        </tr>
                    HTML;
                }
            }else{
                for ($j = 1; $j <= $tmp[0]; $j++) {
                    $index['category']        = $tmp[$j]->prizecategories;
                    $index['givemoneytime']   = $tmp[$j]->givemoneytime;
                    $index['cash']            = $tmp[$j]->gcash_total;            
                    $index['token']           = $tmp[$j]->gtoken_total;
                    $index['member_count']    = $tmp[$j]->member_count;
                    
                    $index['db_givemoney_time'] = $tmp[$j]->givemoney_time;

                    $batch_receive_button = '';
                    if ($status == '1') {
                            $batch_receive_button = <<<HTML
                            <button id="batched_receive_{$j}" class="btn btn-sm btn-primary" onclick="batched_receive({i: $j, prizecategories: '{$index['category']}',bons_givemoneytime:'{$index['givemoneytime']}',ynloto:'{$loto}'});">{$tr['batch receive']}</button>
                    HTML;
                    }
                    $return_str.=<<<HTML
                        <tr>
                            <td class="category" style="width:130px;text-align:left;cursor:pointer;" 
                            attr-category="{$index['category']}" 
                            attr-givetime="{$index['givemoneytime']}"
                            attr-loto="{$loto}"
                            attr-status="{$status}"
                            attr-timepoint="{$now_datetime}" 
                            
                            attr-give_mony_db_time = "{$index['db_givemoney_time']}"
                            
                            ><a>{$index['category']}</a></td>
                            <td style="text-align:center;">{$index['givemoneytime']}</td>
                            <td style="text-align:right;">{$index['cash']}</td>
                            <td style="text-align:right;">{$index['token']}</td>
                            <td style="text-align:right;">{$index['member_count']}</td>
                            <td style="text-align:center;">{$batch_receive_button}</td>
                        </tr>
                    HTML;
                }
            }
            
            return $return_str;

}

// 判斷彩金狀態之sql where條件
function where_sql_switch($menu_fun,$query_str_arr){
    //$now_datetime = gmdate("Y-m-d H:i:s", time()+8*3600). ' +08:00';
    $curr_time = $query_str_arr['current_datetimepicker'];
    $start_time = $query_str_arr['bonus_validdatepicker_start'];
    $end_time = $query_str_arr['bonus_validdatepicker_end'];
    
    if($end_time == ''){
        $addtional_sql = <<<SQL
            AND receivedeadlinetime >='{$start_time}'
        SQL;
    }else{
        $addtional_sql = <<<SQL
            AND givemoneytime <='{$end_time}' 
            AND receivedeadlinetime >='{$start_time}'
        SQL;
    }
    $where=[];
    switch ($menu_fun){
        case 'can_receive':
            $where['string']=<<<SQL
                WHERE receivetime IS NULL
                AND receivedeadlinetime >='{$curr_time}'
                AND status='1'
            SQL.$addtional_sql;
            $where['status']='1';
            break;
        case 'timeout':
            $where['string']=<<<SQL
                WHERE receivetime IS NULL
                AND receivedeadlinetime >='{$curr_time}'
                AND status='2'
            SQL.$addtional_sql;
            $where['status']='2';
            break;
        case 'cancel':
            $where['string']=<<<SQL
                WHERE receivetime IS NULL
                AND receivedeadlinetime >='{$curr_time}'
                AND status='0'
            SQL.$addtional_sql;
            $where['status']='0';
            break;
        case 'received':
            $where['string']=<<<SQL
                WHERE receivetime IS NOT NULL
            SQL.$addtional_sql;
            $where['status']='3';
            break;
        case 'expired':
            $where['string']=<<<SQL
                WHERE receivetime IS NULL
                AND receivedeadlinetime <'{$curr_time}'
            SQL.$addtional_sql;
            $where['status']='4';
            break;
        case 'lottosum_canreceive':
            $where['string']=<<<SQL
                WHERE status='1'
                AND receivetime IS NULL
                AND receivedeadlinetime >='{$curr_time}'
            SQL.$addtional_sql;
            $where['status']='1';
            break;
        case 'lottosum_timeout':
            $where['string']=<<<SQL
                WHERE status='2'
                AND receivetime IS NULL
                AND receivedeadlinetime >='{$curr_time}'
            SQL.$addtional_sql;
            $where['status']='2';
            break;
        case 'lottosum_cancel':
            $where['string']=<<<SQL
                WHERE status='0'
                AND receivetime IS NULL
                AND receivedeadlinetime >='{$curr_time}'
            SQL.$addtional_sql;
            $where['status']='0';
            break;
        case 'lottosum_received':
            $where['string']=<<<SQL
                WHERE receivetime IS NOT NULL
            SQL.$addtional_sql;
            $where['status']='3';
            break;
        case 'lottosum_expired':
            $where['string']=<<<SQL
                WHERE receivetime IS NULL
                AND receivedeadlinetime <'{$curr_time}'
            SQL.$addtional_sql;
            $where['status']='4';
            break;
        default:
        break;
    }
    return $where;
}

// 判斷彩票及非彩票彩金sql
function ynlotto_sql($yn){
    $return=[];
    if($yn=='0'){
         $return['select']=<<<SQL
            SELECT
                prizecategories,
                givemoneytime as givemoney_time,
                to_char((givemoneytime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as givemoneytime,
                sum(gcash_balance) AS gcash_total,
                sum(gtoken_balance) AS gtoken_total,
                count(member_id) AS member_count
            FROM root_receivemoney
        SQL;
        $return['group']=<<<SQL
            GROUP BY prizecategories, givemoneytime
            ORDER BY givemoneytime DESC ,prizecategories DESC
        SQL;
    }else{
        $return['select']=<<<SQL
        SELECT
            prizecategories,
            sum(gcash_balance) AS gcash_total,
            sum(gtoken_balance) AS gtoken_total,
            count(member_id) AS member_count
        FROM root_receivemoney
        SQL;
        $return['group']=<<<SQL
            GROUP BY prizecategories
            ORDER BY prizecategories DESC
        SQL;
    }
    return $return;
}


// 選單-執行SQL
function execute_menu_sql($offset=0,$limit=10,$menu_fun,$query_str_arr){
    global $tr;
    $now_datetime_ast = gmdate("Y-m-d H:i:s", time()-4*3600);
    $tmp=[];
    $load_menu_tab=['can_receive','timeout','cancel','received','expired'];

    if(in_array($menu_fun,$load_menu_tab)){
        $where_lotto_sql=" AND (prizecategories NOT LIKE '%lottery%' AND prizecategories NOT LIKE '%彩票%')";
        $ynlotto_sql=ynlotto_sql('0');
        $select=$ynlotto_sql['select'];
        $group =$ynlotto_sql['group'];
        $loto='0';
        $no_data_repeat=6;
    }else{
        $where_lotto_sql=" AND (prizecategories  LIKE '%lottery%' OR prizecategories LIKE '%彩票%')";
        $ynlotto_sql=ynlotto_sql('1');
        $select=$ynlotto_sql['select'];
        $group =$ynlotto_sql['group'];
        $loto='1';
        $no_data_repeat=5;
    }

    $where=where_sql_switch($menu_fun,$query_str_arr);
    $re_index_count_sql = $select.$where['string'].$where_lotto_sql.$group;
    // var_dump($re_index_count_sql);die();
    $data['count']=runSQL($re_index_count_sql);
    $data['content']='';

    if ($data['count']=='0'){
            $data['content']= '';
    }else{
        $tmp=runSQLall($re_index_count_sql.' OFFSET '.$offset.' LIMIT '.$limit);
        $data['content']=menu_table_data($loto,$where['status'],$tmp,$now_datetime_ast);
    }
    // var_dump($tmp);die();
    return $data;
}

// 分頁工具列
function create_page($total_page,$pageSize,$cur_page,$tab_type,$is_querybutton='0'){
        $previous_btn = true;
        $next_btn     = true;
        $first_btn    = true;
        $last_btn     = true;
        $no_of_paginations=$total_page;
        if($is_querybutton=='0'){
            $page_enab='enab';
            $go_button='go_button';
        }else{
            $page_enab='buenab';
            $go_button='querygo_button';
        }

        // $msg = "<div class='data'><ul>" . $msg . "</ul></div>"; // Content for Data

        /* ---------------Calculating the starting and endign values for the loop----------------------------------- */
        if ($cur_page >= 7) {
            $start_loop = $cur_page - 3;
            if ($no_of_paginations > $cur_page + 3)
                $end_loop = $cur_page + 3;
            else if ($cur_page <= $no_of_paginations && $cur_page > $no_of_paginations - 6) {
                $start_loop = $no_of_paginations - 6;
                $end_loop = $no_of_paginations;
            } else {
                $end_loop = $no_of_paginations;
            }
        } else {
            $start_loop = 1;
            if ($no_of_paginations > 7)
                $end_loop = 7;
            else
                $end_loop = $no_of_paginations;
        }
        /* ----------------------------------------------------------------------------------------------------------- */


        // FOR ENABLING THE FIRST BUTTON
        if ($first_btn && $cur_page > 1) {
            $first_htm = <<<HTML
                <li p="1" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item enab">
                    <a class="page-link">First</a>
                </li>
        HTML;
        } else if ($first_btn) {
            $first_htm =<<<HTML
                <li p="1" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item disabled">
                    <a class="page-link" tabindex="-1" aria-disabled="true">First</a>
                </li>
        HTML;
        }

        // FOR ENABLING THE PREVIOUS BUTTON
        if ($previous_btn && $cur_page > 1) {
            $pre = $cur_page - 1;
            $pre_htm =<<<HTML
                <li p="{$pre}" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item enab">
                    <a class="page-link">Previous</a>
                </li>
        HTML; 
        } else if ($previous_btn) {
            $pre_htm =<<<HTML
                <li p="1" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item disabled">
                    <a class="page-link" tabindex="-1" aria-disabled="true">Previous</a>
                </li>
        HTML; 
        }
        for ($i = $start_loop; $i <= $end_loop; $i++) {
            if ($cur_page == $i)
                $pre_htm .=<<<HTML
                <li p="{$i}" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item active" aria-current="page">
                    <span class="page-link">
                        {$i}<span class="sr-only">(current)</span></span>
                </li>
        HTML;
            else
                $pre_htm .=<<<HTML
                <li p="{$i}" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item enab">
                    <a class="page-link">{$i}</a>
                </li>
        HTML;
        }


        // TO ENABLE THE NEXT BUTTON
        if ($next_btn && $cur_page < $no_of_paginations) {
            $nex = $cur_page + 1;
            $nex_htl = <<<HTML
                <li p="{$nex}" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item enab">
                    <a class="page-link">Next</a>
                </li>
        HTML;
        } else if ($next_btn) {
            $nex_htl = <<<HTML
                <li class="page-item disabled">
                    <a class="page-link" tabindex="-1" aria-disabled="true">Next</a>
                </li>
        HTML;
        }


        // TO ENABLE THE END BUTTON
        if ($last_btn && $cur_page < $no_of_paginations) {
            $last_htl = <<<HTML
                <li p="{$no_of_paginations}" attr_type="{$tab_type}" attr_isquery="{$is_querybutton}" class="page-item enab">
                    <a class="page-link">Last</a>
                </li>
        HTML;
        } else if ($last_btn) {
            $last_htl = <<<HTML
                <li class="page-item disabled">
                    <a class="page-link" tabindex="-1" aria-disabled="true">Last</a>
                </li>

        HTML;
        }

        // GOTO BUTTON
        $goto = "<input type='text' class='ml-2 goto_$tab_type' size='3' style='margin-top:-1px;'/>
                 <input type='button' id='go_btn' attr_type='$tab_type' attr_isquery='$is_querybutton' class='go_button' value='Go'/>";
        $total_string = "<span class='total_$tab_type ml-2 pt-2' a='$no_of_paginations'>Page <b>$cur_page</b> of <b>$no_of_paginations</b></span>";

        $final_htm=<<<HTML
            <nav aria-label="..." class="d-flex w-100 mt-2">                
                {$goto}
                {$total_string}
                <ul class="pagination justify-content-center d-flex ml-auto mb-0">
                    {$first_htm}
                    {$pre_htm}
                    {$nex_htl}
                    {$last_htl}                  
                </ul>
            </nav>
        HTML;

        // echo($final_htm);die();
        return $final_htm;
}


// 產生sql查詢
function create_sql(){
    return "SELECT *,
                to_char((givemoneytime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS' ) as givemoneytime_fix,
                to_char((receivetime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivetime_fix,
                to_char((receivedeadlinetime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivedeadlinetime_fix 
                FROM root_receivemoney ";
}

//彩票彩金sql
function is_lotto($sql_str){
    $sql_count=<<<SQL
        SELECT
            prizecategories,
            sum(gcash_balance) AS gcash_total,
            sum(gtoken_balance) AS gtoken_total,
            count(member_id) AS member_count,
            status
        FROM root_receivemoney
            {$sql_str}
            AND (prizecategories  LIKE '%lottery%' OR prizecategories LIKE '%彩票%')
        GROUP BY prizecategories,status
        ORDER BY prizecategories DESC
SQL;
    return $sql_count;
}

//非彩票彩金sql
function not_lotto($sql_str){
    $sql_count=<<<SQL
        SELECT
            prizecategories,
            to_char(("givemoneytime" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as givemoneytime,
            sum(gcash_balance) AS gcash_total,
            sum(gtoken_balance) AS gtoken_total,
            count(member_id) AS member_count,
            status
        FROM root_receivemoney
            {$sql_str}
            AND (prizecategories NOT LIKE '%lottery%' AND prizecategories NOT LIKE '%彩票%')
        GROUP BY prizecategories, givemoneytime,status
        ORDER BY givemoneytime DESC ,prizecategories DESC
SQL;
    return $sql_count;
}

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------
?>