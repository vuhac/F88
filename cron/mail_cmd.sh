#!/bin/bash
# --------------------------------------------------
# 檔名：groupmain.sh
# 工作：10分鐘報表用
# 排程寫法：
# 每10分鐘一次計算
#[testgpk2demo@allinone bak]$ crontab -l
# for simuation MG betting
# */10 * * * * /home/testgpk2demo/web/begpk2/cron/groupmain.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "$PHP7 ${BINPATH}mail_cmd.php run 1 >> ${LOGPATH}groupmain.log 2>&1 "
echo "$PHP7 ${BINPATH}mail_cmd.php run 1 >> ${LOGPATH}groupmain.log 2>&1 " | sh

echo "TZ='America/New Yorks' date >> ${LOGPATH}groupmain.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}groupmain.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}groupmain.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}groupmain.log" | sh
