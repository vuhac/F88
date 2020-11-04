#!/bin/bash
# --------------------------------------------------
# 檔名：10m_update.sh
# 工作：10分鐘報表用
# 排程寫法：
# 每10分鐘一次計算
#[testgpk2demo@allinone bak]$ crontab -l
# for simuation MG betting
# */10 * * * * /home/testgpk2demo/web/begpk2/cron/10m_update.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "$PHP7 ${BINPATH}statistics_daily_betting_cmd.php run 10 >> ${LOGPATH}10m_update.log 2>&1 "
echo "$PHP7 ${BINPATH}statistics_daily_betting_cmd.php run 10 >> ${LOGPATH}10m_update.log 2>&1 " | sh

echo "$PHP7 ${BINPATH}statistics_daily_site_cmd.php run 10 >> ${LOGPATH}10m_update.log 2>&1 "
echo "$PHP7 ${BINPATH}statistics_daily_site_cmd.php run 10 >> ${LOGPATH}10m_update.log 2>&1 " | sh

echo "TZ='America/New Yorks' date >> ${LOGPATH}10m_update.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}10m_update.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}10m_update.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}10m_update.log" | sh
