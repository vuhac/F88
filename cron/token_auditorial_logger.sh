#!/bin/bash
# --------------------------------------------------
# 檔名：token_auditorial_cmd.sh
# 工作：稽核定期運算用
# 排程寫法：
#[testgpk2demo@allinone bak]$ crontab -l
# for simuation MG betting
# */10 * * * * /home/testgpk2demo/web/begpk2/cron/token_auditorial_cmd.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

echo "$PHP7 ${BINPATH}token_auditorial_cmd.php ALL >> ${LOGPATH}token_auditorial_cmd.log 2>&1 "
echo "$PHP7 ${BINPATH}token_auditorial_cmd.php ALL >> ${LOGPATH}token_auditorial_cmd.log 2>&1 " | sh

echo "TZ='America/New Yorks' date >> ${LOGPATH}token_auditorial_cmd.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}token_auditorial_cmd.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}token_auditorial_cmd.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}token_auditorial_cmd.log" | sh
