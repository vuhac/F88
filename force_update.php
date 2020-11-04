<?php
    // EX: php force_update.php 2020-06-01 00:00:00 2020-07-14 00:00:00
    require_once dirname(__FILE__).'/config.php';

    ini_set('memory_limit', '400M');
    ignore_user_abort(true);
    set_time_limit(86400); // 最多跑一天

    function validateDate($date, $format='Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    // 開始日期
    if (empty($argv[1])) { // 開始日期為空，預設是當前日期
        // die("請輸入起始日期.\n");
        $start_date = date("Y-m-d");
    } else if (!validateDate($argv[1], 'Y-m-d')) {
        die("起始日期格式錯誤，正確格式為Y-m-d\n");
    } else {
        $start_date = $argv[1];
    }

    // 開始時間
    if (empty($argv[2])) { // 開始時間為空，預設是00:00:00
        // die("請輸入起始時間.\n");
        $start_time = '00:00:00';
    } else if (!validateDate($argv[2], 'H:i:s')) {
        die("起始時間格式錯誤，正確格式為H:i:s\n");
    } else {
        $start_time = $argv[2];
    }

    // 結束日期
    if (empty($argv[3])) { // 結束日期為空，預設是當前日期
        // die("請輸入結束日期.\n");
        $max_date = date("Y-m-d");
    } else if (!validateDate($argv[3], 'Y-m-d')) {
        die("結束日期格式錯誤，正確格式為Y-m-d\n");
    } else {
        $max_date = $argv[3];
    }

    // 結束時間
    if (empty($argv[4])) { // 結束時間為空，預設是當前時間前1個小時取整點
        // die("請輸入結束時間.\n");
        $max_time = date("H", strtotime("-1 hour")).':00:00';
    } else if (!validateDate($argv[4], 'H:i:s')) {
        die("結束時間格式錯誤，正確格式為H:i:s\n");
    } else {
        $max_time = $argv[4];
    }

    $start_datetime = "{$start_date} {$start_time}"; // 需要指定美東時間
    $max_datetime = "{$max_date} {$max_time}"; // 需要指定美東時間


    // 開始時間不得晚於結束時間
    if ( strtotime($start_datetime) > strtotime($max_datetime) ) {
        die("開始時間不得晚於結束時間\n");
    }

    $temp_start_datetime = $start_datetime;

    $add_min = "+10 minutes";

    // 刪除舊資料
    $start_date = date("Y-m-d", strtotime($start_datetime)); // 美東時間
    $end_date = date("Y-m-d", strtotime($max_datetime)); // 美東時間
    $stmt = <<<SQL
        DELETE
        FROM "root_statisticsbetting"
        WHERE '{$start_date}' <= "dailydate"
            AND "dailydate" <= '{$end_date}';
    SQL;
    runSQL($stmt);

    // 10分鐘報表
    do {
        // "Command: [test|run] time_interval Y-m-d h:i:s [web|sql] updatelog_id force_update=[0|1]\n"
        // print("php statistics_daily_betting_cmd.php run 10 {$start_datetime} web 0 1"); // 測試時間
        // print("\n");

        // 2020-06-09 05:10:00 ~ 2020-06-09 05:20:00
        // php test.php
        // php realtime_reward_cmd.php run sql 0 2019-04-10 10:00:00 2019-04-11 09:00:00

        system("php statistics_daily_betting_cmd.php run 10 {$start_datetime} web 0 1", $out);
        echo("產生{$start_datetime}的10分鐘資料\n\n");
        $start_datetime = date("Y-m-d H:i:s", strtotime($start_datetime.' '.$add_min));
    } while ( strtotime($max_datetime) >= strtotime($start_datetime) ); // 這邊為false就會停止執行


    // 時時返水資料
    $start_datetime = $temp_start_datetime;
    $end_datetime = ''; // 自動產生，不需填寫
    $add_min = "+1 hour"; // 預設

    $del_start_datetime = date("Y-m-d", strtotime($start_datetime)).' 00:00:00';
    $del_end_datetime = date("Y-m-d", strtotime($max_datetime)).' 23:59:59';

    // 刪除舊有資料
    $stmt = <<<SQL
        DELETE
        FROM "root_realtime_reward"
        WHERE '{$del_start_datetime}' <= "start_date"
            AND "end_date" <= '{$del_end_datetime}'
    SQL;
    runSQLall($stmt);

    do {
        // 以 $start_datetime 自動換算 $end_datetime
        $end_datetime = date("Y-m-d H:i:s", strtotime($start_datetime.' '.$add_min));

        system("php realtime_reward_cmd.php run sql 0 {$start_datetime} {$end_datetime}", $out);
        echo("產生{$start_datetime} ~ {$end_datetime}返水資料\n\n");

        // 執行一輪後，要把時間區間往後推一個小時
        $start_datetime = $end_datetime; // 下一輪的開始時間等於上一輪的結束時間
    } while ( strtotime($start_datetime) <= strtotime($max_datetime) ); // 這邊為false就會停止執行

    die("執行結束\n");
?>