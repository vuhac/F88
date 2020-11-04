#!/bin/bash
# --------------------------------------------------
# 檔名：redis_monitor.sh
# 工作：用來監聽redis push來的設息，並強制更新memcache內資料以即時更新相關設定
# 排程寫法：
#  for www.gpk17.com and be.gpk17.com betting log
# */5 * * * * /home/deployer/cron/redis_monitor.sh
# --------------------------------------------------
source $(dirname "$0")/shellvar

count=`ps aux | grep redis_sub | grep php | grep -v 'grep' | wc -l`

if [ ${count} -lt '1' ]; then
  echo "${PHP7} ${BINPATH}/redis_sub.php >> ${LOGPATH}redis_sub.log 2>&1 &"
  echo "${PHP7} ${BINPATH}/redis_sub.php >> ${LOGPATH}redis_sub.log 2>&1 &" | sh
else
  echo "Still Runing!!" >> ${LOGPATH}redis_sub.log
fi

echo "TZ='America/New Yorks' date >> ${LOGPATH}redis_sub.log"
echo "TZ='America/New Yorks' date >> ${LOGPATH}redis_sub.log" | sh
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}redis_sub.log"
echo "TZ='Asia/Taipei' date >>  ${LOGPATH}redis_sub.log" | sh
