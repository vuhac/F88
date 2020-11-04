#!/bin/bash
# --------------------------------------------------
# 檔名：setup.sh
# 工作：安裝及更新系統用SHELL
# 用法：
# 執行前請先編輯將shellvar_orig覆制為shellvar 並修改相關的路徑設定
# ./setup.sh [install|upgrade|reverse]
# --------------------------------------------------
# enable extglob extension
shopt -s extglob

NOW=$(date +"%Y%m%d")
RED=`tput setaf 1`
reset=`tput sgr0`
if [ -f $(dirname "$0")/shellvar ]; then
  source $(dirname "$0")/shellvar

  case $1 in
    install)
      echo '1.Check destination directory'
      if [ ! -d $DSTPATH ]; then
        echo  -e "${RED}""Please check your DSTPATH($DSTPATH) and the directory if it exist or not!!""${reset}"
        exit;
      fi
      if [ -f $DSTPATH/version.txt ]; then
        echo -e "${RED}"'You already installed your product!!'
        echo -e "If you want upgrade your product,please type ${reset} ./setup.sh upgrade"
        exit;
      fi
      echo '2.Install to destination directory!'
      rsync  -az --no-perms --no-owner --no-group --no-times $SRCPATH/* $DSTPATH/
      echo '3.Setting basic config file!'
      cp $SRCPATH/config_orig.php $DSTPATH/config.php
      cp $SRCPATH/cron/shellvar_orig $DSTPATH/cron/shellvar
      mv $DSTPATH/cron $CRONDSTPATH
      # cd $CRONDSTPATH
      # crontab crontab.txt
      echo '4.Installation Success!!'
      echo -e "${RED}"'Please remember to edit your config file "config.php"'"${reset}"
      echo -e "${RED}"'Please remember to edit your crontab using "crontab -e"'"${reset}"
      ;;
    upgrade)
      echo '1.Check destination directory'
      if [ ! -d $DSTPATH ]; then
        echo  -e "${RED}""Please check your DSTPATH($DSTPATH) and the directory if it exist or not!!""${reset}"
        exit;
      fi
      if [ ! -d $CRONDSTPATH ]; then
        mkdir -p $CRONDSTPATH
      fi
      echo '2.Check backup directory'
      if [ ! -d $BAKPATH/${NOW} ]; then
        rm -Rf $BAKPATH/*
        mkdir -p $BAKPATH/${NOW}
        mkdir -p $BAKPATH/${NOW}_cron
      else
        echo -e "${RED}"'You already upgrade your product!!'
        if [[ -z $2 ]]; then
          read -p "Are you sure you want upgrade again? ${reset}" -n 1 -r
          echo    # (optional) move to a new line
        fi
        if [[ $REPLY =~ ^[Yy]$ ]] || [[ $2 =~ ^[Yy]$ ]]
        then
          echo 'continue to upgrade process!'
          mv $BAKPATH/${NOW} $BAKPATH/${NOW}.old
        else
          exit;
        fi
      fi
      echo '3.Backup destination directory to backup directory!'
      rsync -az $DSTPATH/* $BAKPATH/${NOW} --exclude-from=rsync_ignore
      rsync -az $CRONDSTPATH $BAKPATH/${NOW}_cron
      echo '4.Update destination directory!'
      rsync  -azd --no-perms --no-owner --no-group --no-times --delete $SRCPATH/* $DSTPATH/ --exclude-from=rsync_ignore
      echo '5.Prepare config update patch!'
      diff -Naur $BAKPATH/${NOW}/config_orig.php $SRCPATH/config_orig.php > config.patch
      echo '6.Apply patch to config file!'
      cp $BAKPATH/${NOW}/config.php $DSTPATH/config.php
      patch $DSTPATH/config.php < config.patch
      rsync -az --no-perms --no-owner --no-group --no-times $DSTPATH/cron/* $CRONDSTPATH
      if [ "$3" != "notdelcron" ]; then
        echo 'Del CRON files From source!'
        rm -Rf $DSTPATH/cron
      fi
      cp $BAKPATH/${NOW}_cron/cron/shellvar $CRONDSTPATH/shellvar
      echo '7.Update Success!!'
      echo -e "${RED}"'Please remember to edit your config file "config.php"'"${reset}"
      echo -e "${RED}"'Please remember to edit your crontab using "crontab -e"'"${reset}"
      ;;
    reverse)
      echo '1.Check destination directory'
      if [ ! -d $DSTPATH ]; then
        echo  -e "${RED}""Please check your DSTPATH($DSTPATH) and the directory if it exist or not!!""${reset}"
        exit;
      fi
      echo '2.Check backup directory'
      if [ ! -d $BAKPATH/${NOW} ]; then
        echo -e "${RED}"'Please check your BAKPATH and the directory if it exist or not!!'"${reset}"
        exit;
      fi
      echo '3.Roll back destination directory to backed up version!'
      rsync -az --no-perms --no-owner --no-group --no-times $BAKPATH/${NOW}/* $DSTPATH/ --exclude-from=rsync_ignore
      echo '4.Roll back Success!!'
      ;;
    *)
      echo './setup.sh [install|reverse]'
      echo 'install: for new install or upgrade to new version use.'
      echo 'reverse: roll back to old version.'
      ;;
  esac
else
  echo 'please copy shellvar_orig to shellvar and edit it!!'
fi
