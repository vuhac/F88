#!/bin/bash
# --------------------------------------------------
# 檔名：agent_profitloss_calculation.sh
# 工作：聯營股東損益計算
# 排程寫法：
# 排在日報後面做運算處理
#[testgpk2demo@allinone bak]$ crontab -l
# 0 1 * * * /home/testgpk2demo/web/begpk2/cron/agent_profitloss_calculation.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "$PHP7 ${BINPATH}agent_profitloss_calculation_cmd.php run >> ${LOGPATH}agent_profitloss_calculation_cmd.log 2>&1 "
echo "$PHP7 ${BINPATH}agent_profitloss_calculation_cmd.php run >> ${LOGPATH}agent_profitloss_calculation_cmd.log 2>&1 " | sh

echo "TZ='America/New Yorks' date >> ${LOGPATH}agent_profitloss_calculation_cmd.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}agent_profitloss_calculation_cmd.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}agent_profitloss_calculation_cmd.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}agent_profitloss_calculation_cmd.log" | sh
