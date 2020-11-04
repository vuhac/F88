#!/bin/bash
# --------------------------------------------------
# 檔名：agent_bonus_of_rg_lottery.sh
# 工作：10分鐘報表用
# 排程寫法：
# 每10分鐘一次計算
#[testgpk2demo@allinone bak]$ crontab -l
# for simuation MG betting
# */10 * * * * /home/testgpk2demo/web/begpk2/cron/agent_bonus_of_rg_lottery.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "$PHP7 ${BINPATH}agent_bonus_of_rg_lottery_payout_cmd.php run >> ${LOGPATH}agent_bonus_of_rg_lottery.log 2>&1 "
echo "$PHP7 ${BINPATH}agent_bonus_of_rg_lottery_payout_cmd.php run >> ${LOGPATH}agent_bonus_of_rg_lottery.log 2>&1 " | sh

echo "TZ='America/New Yorks' date >> ${LOGPATH}agent_bonus_of_rg_lottery.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}agent_bonus_of_rg_lottery.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}agent_bonus_of_rg_lottery.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}agent_bonus_of_rg_lottery.log" | sh
