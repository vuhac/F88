#!/bin/bash
# --------------------------------------------------
# 檔名：auto_casino_retrieve.sh
# 工作：10分鐘報表用
# 排程寫法：
# 每10分鐘一次計算
#[testgpk2demo@allinone bak]$ crontab -l
# for simuation MG betting
# */10 * * * * /home/testgpk2demo/web/begpk2/cron/auto_casino_retrieve.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "$PHP7 ${BINPATH}auto_casino_retrieve.php >> ${LOGPATH}auto_casino_retrieve.log 2>&1 "
echo "$PHP7 ${BINPATH}auto_casino_retrieve.php >> ${LOGPATH}auto_casino_retrieve.log 2>&1 " | sh

echo "TZ='America/New Yorks' date >> ${LOGPATH}auto_casino_retrieve.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}auto_casino_retrieve.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}auto_casino_retrieve.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}auto_casino_retrieve.log" | sh
