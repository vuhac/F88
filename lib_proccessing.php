<?php
// ----------------------------------------------------------------------------
// Features :    後台 -- backgroud proccessing utils
// File Name: lib_proccessing.php
// Author   : Dright
// Related  :
// Log      :
// ----------------------------------------------------------------------------
// 功能說明
//
// functions:
// 1. dispatch_proccessing(string $command, string $message, string $reload_url, string $logfile = null)
// 2. notify_proccessing_start(string $message, string $logfile = null)
// 3. notify_proccessing_progress(string $message, string $progress, string $logfile = null)
// 4. notify_proccessing_complete(string $summary, string $del_log_url = null, string $logfile = null)
//
// class:
// 1. WebProgressMonitor
// 2. TerminalProgressMonitor
//
// example:
// preferential_calculation_action.php
// preferential_payout_cmd.php
//

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

/**
 * [dispatch_proccessing]
 * @param  string $command    [command to dispatch]
 * @param  string $message    [message in loading view]
 * @param  string $reload_url [reload url in loading view]
 * @param  string $logfile    [logfile name]
 * @return
 */
function dispatch_proccessing(string $command, string $message, string $reload_url, string $logfile = null)
{
    // dispatch command
    system($command, $return_var);

    // init logfile
    if (!empty($logfile)) {
        $output_html = <<<HTML
      <p align="center"><img src="ui/loading.gif" /></p>
      <p align="center">$message</p>
      <script>setTimeout(function(){location.reload()},1000);</script>
HTML;
        file_put_contents($logfile, $output_html);
    }

    // return loading view
    $loading_html = <<<HTML
    <p align="center">$message<img src="ui/loading.gif" /></p>
    <script>
      setTimeout(function(){window.location.href="$reload_url"},1000);
    </script>
HTML;

    echo $loading_html;

    return $loading_html;
}

/**
 * [notify_proccessing_start]
 * @param  string $message [message in proccess start view]
 * @param  string $logfile [logfile name]
 * @return
 */
function notify_proccessing_start(string $message, string $logfile = null)
{
    // if no logfile do nothing
    if (empty($logfile)) {
        return;
    }

    // write logfile
    $output_html = <<<HTML
    <p align="center"><img src="ui/loading.gif" /></p>
    <p align="center">$message</p>
    <script>setTimeout(function(){location.reload()},500);</script>
HTML;
    file_put_contents($logfile, $output_html);
}

/**
 * [notify_proccessing_progress]
 * @param  string $message  [message in progress view]
 * @param  string $progress [progress to show in progress view]
 * @param  string $logfile [logfile name]
 * @return
 */
function notify_proccessing_progress(string $message, string $progress, string $logfile = null)
{
    // if no logfile do nothing
    if (empty($logfile)) {
        return;
    }

    // write logfile
    $output_html = <<<HTML
    <p align="center"><img src="ui/loading.gif" /></p>
    <p align="center">$message</p>
    <script>setTimeout(function(){location.reload()},1000);</script>
    <p align="center">$progress</p>
HTML;
    file_put_contents($logfile, $output_html);
}

/**
 * [notify_proccessing_complete]
 * @param  string $summary     [summary to show in proccessing complete view]
 * @param  [type] $del_log_url [delete log url in proccessing complete view]
 * @param  [type] $logfile     [logfile name]
 * @return
 */
function notify_proccessing_complete(string $summary, string $del_log_url = null, string $logfile = null)
{
    global $tr;
    // if no logfile do nothing
    if (empty($logfile)) {
        return;
    }

    // write logfile
    $output_html = <<<HTML
    <p align="center">$summary</p>
    <p align="center"><button type="button" onclick="dellogfile();">{$tr['close the window']}</button></p>

    <script src="in/jquery/jquery.min.js"></script>
  	<script type="text/javascript" language="javascript" class="init">
      alert('已完成資料更新！');
    	function dellogfile(){
    		$.get("$del_log_url",
    		function(result){
    			window.close();
    		});
    	}
        $(window).unload(function(){
            dellogfile();
        });

  	</script>
HTML;
    file_put_contents($logfile, $output_html);
}

abstract class ProgressMonitor
{
    protected $totalStepCount;
    protected $executedStepCount;

    abstract public function notifyProccessingStart(string $message);

    public function setTotalProgressStep($step)
    {
        $this->totalStepCount = $step;
    }

    public function forwardProgress()
    {
        $this->executedStepCount = $this->executedStepCount + 1;
    }

    public function resetProgress()
    {
        $this->executedStepCount = 0;
    }

    abstract public function notifyProccessingProgress(string $message);

    abstract public function notifyProccessingComplete(string $summary);
}

/**
 * [WebProgressMonitor description]
 */
class WebProgressMonitor extends ProgressMonitor
{
    private $logFile;
    private $delLogUrl;

    public function __construct(string $logfile, string $del_log_url)
    {
        $this->totalStepCount = 0;
        $this->executedStepCount = 0;

        $this->logFile = $logfile;
        $this->delLogUrl = $del_log_url;
    }

    public function notifyProccessingStart(string $message)
    {
        notify_proccessing_start($message, $this->logFile);
    }

    public function notifyProccessingProgress(string $message)
    {
        global $program_start_time;

        $process_record = $this->executedStepCount / $this->totalStepCount;
        $percentage = round($process_record, 2) * 100;
        $process_times = round((microtime(true) - $program_start_time), 3);
        $counting_r = $percentage % 5;

        if ($counting_r == 0) {
            notify_proccessing_progress($message, $percentage . ' %', $this->logFile);
        }

    }

    public function notifyProccessingComplete(string $summary)
    {
        notify_proccessing_complete(nl2br($summary), $this->delLogUrl, $this->logFile);
    }

}

/**
 * [TerminalProgressMonitor description]
 */
class TerminalProgressMonitor extends ProgressMonitor
{
    private $originMemoryUsage;
    private $percentageCurrent;

    public function __construct()
    {
        $this->totalStepCount = 0;
        $this->executedStepCount = 0;
        $this->percentageCurrent = 0;

        $this->originMemoryUsage = memory_get_usage();
    }

    public function notifyProccessingStart(string $message)
    {
        echo $message . "\n";
    }

    public function notifyProccessingProgress(string $message = null)
    {
        global $program_start_time;

        $process_record = $this->executedStepCount / $this->totalStepCount;
        $percentage = round($process_record, 2) * 100;
        $process_times = round((microtime(true) - $program_start_time), 3);
        $counting_r = $percentage % 10;

        // no echo when percentage is not updated
        if ($percentage == $this->percentageCurrent) {
            return;
        }

        $this->percentageCurrent = $percentage;

        if ($counting_r == 0) {
            echo "\n目前處理 紀錄: $this->executedStepCount ,執行進度: $percentage% ,花費時間: " . $process_times . "秒\n";
            return;
        }

        echo $percentage . '% ';
    }

    public function notifyProccessingComplete(string $summary)
    {
        global $program_start_time;

        echo "\n" . $summary . "\n";
        echo 'Total execution time in seconds: ' . round((microtime(true) - $program_start_time), 3) . " sec\n";
        echo 'memmory usage: ' . round((memory_get_usage() - $this->originMemoryUsage) / (1024 * 1024), 3) . " MB.\n";
    }

}
