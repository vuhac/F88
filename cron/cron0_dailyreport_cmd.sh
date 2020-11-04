#!/bin/bash
# --------------------------------------------------
# 檔名：test_0_daily_cmd.sh
# 工作：
# 每個站每日報表的排程, 可以抓取投注紀錄生成寫入每日報表. 定時需要執行, 1000 比紀錄約需要 100秒, 紀錄增加會增多。
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "${PHP7} ${BINPATH}statistics_daily_report_output_cmd.php run >> ${LOGPATH}0_daily_cmd.log "
echo "${PHP7} ${BINPATH}statistics_daily_report_output_cmd.php run >> ${LOGPATH}0_daily_cmd.log " | sh
