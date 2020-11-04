<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 未定義頁面管理
// File Name: page_management_lib.php
// Author: Damocles
// Related: page_management_*.php
// Log:
// ----------------------------------------------------------------------------

// 更新頁面資料
function updatePage($page_data)
{
    /* 參數樣板
        $page_data = [
            'page_name' => '',
            'function_name' => [
                'old' => '',
                'new' => ''
            ],
            'page_description' => ''
        ]
    */
    if ( isset($page_data['page_name']) && !empty($page_data['page_name']) ) {
        $page_name = (string)$page_data['page_name'];
        if ( isset($page_data['function_name']) && !empty($page_data['function_name']) ) {
            $new_function_name = (string)$page_data['function_name']['new'];
            $old_function_name = (string)$page_data['function_name']['old'];
        } else { // 如果沒有設定function name，直接回傳0
            return 0;
        }
        $page_description = ( isset($page_data['page_description']) && !empty($page_data['page_description']) ? nl2br( (string)trim($page_data['page_description']) ) : '' );

        $stmt = <<<SQL
            UPDATE "site_page" SET
            "function_name" = '{$new_function_name}',
            "page_description" = '{$page_description}'
            WHERE ("page_name"='{$page_name}') AND ("function_name"='{$old_function_name}')
        SQL;
        return runSQLall($stmt)[0];
    } else { // 如果沒有設定頁面名稱的話，直接回傳0
        return 0;
    }
}

// 以page name與function name，取得指定的頁面資料
function queryPage($search, $function_name)
{
    $stmt = <<<SQL
        SELECT "site_page"."page_name",
               "site_page"."function_name",
               "site_functions"."function_title",
               "site_page_group"."group_name",
               "site_page"."page_description"
        FROM "site_page"
        JOIN "site_functions" ON ("site_page"."function_name" = "site_functions"."function_name")
        JOIN "site_page_group" ON ("site_functions"."group_name" = "site_page_group"."group_name")
        WHERE ("site_page"."page_name" = '{$search}.php') AND
              ("site_page"."function_name" = '{$function_name}')
    SQL;
    $result = runSQLall($stmt);
    return $result;
}

// 取得頁面資料
function queryPages($search=NULL, $function_search=NULL, $start=0, $length=NULL, $order_column='site_page.page_name', $order_dir='DESC')
{
    $stmt = <<<SQL
        SELECT "site_page"."page_name",
               "site_page"."function_name",
               "site_functions"."function_title",
               "site_page_group"."group_name",
               "site_page"."page_description"
        FROM "site_page"
        JOIN "site_functions" ON ("site_page"."function_name" = "site_functions"."function_name")
        JOIN "site_page_group" ON ("site_functions"."group_name" = "site_page_group"."group_name")
    SQL;

    if ( !is_null($search) && !empty($search) ) {
        $search = (string)'%'.$search.'%';
        $stmt .= <<<SQL
            WHERE (
                ("site_page"."page_name" LIKE '{$search}') OR
                ("site_page"."function_name" LIKE '{$search}') OR
                ("site_functions"."function_title" LIKE '{$search}') OR
                ("site_page_group"."group_name" LIKE '{$search}') OR
                ("site_page"."page_description" LIKE '{$search}')
            )
        SQL;
    }

    if ( !is_null($function_search) && !empty($function_search) ) {
        $function_search = (string)'%'.$function_search.'%';
        if ( !is_null($search) ) {
            $stmt .= <<<SQL
                AND ("site_page"."function_name" LIKE '{$function_search}')
            SQL;
        } else {
            $stmt .= <<<SQL
                WHERE ("site_page"."function_name" LIKE '{$function_search}')
            SQL;
        }
    }

    $order_column = (string)$order_column;
    $order_dir = (string)$order_dir;
    $stmt .= <<<SQL
        ORDER BY {$order_column} {$order_dir}
    SQL;

    if ( !is_null($length) ) {
        $length = (int)$length;
        $stmt .= <<<SQL
            LIMIT $length
        SQL;
    }

    $start = (int)$start;
    $stmt .= <<<SQL
        OFFSET $start
    SQL;

    $result = runSQLall($stmt);
    return $result;
}

// 取得所有function資料
function queryFunctions()
{
    $stmt = <<<SQL
        SELECT "site_functions"."function_name",
               "site_functions"."function_title",
               "site_functions"."function_description",
               "site_functions"."group_name",
               "site_page_group"."group_description",
               "site_functions"."function_public",
               "site_functions"."function_status",
               "site_functions"."function_maintain_status"
        FROM "site_functions"
        JOIN "site_page_group" ON "site_functions"."group_name" = "site_page_group"."group_name"
    SQL;
    $result = runSQLall($stmt);
    unset($result[0]);
    return $result;
}
?>
