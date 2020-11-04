<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- file LIB
// File Name: lib_file.php
// Author   : Dright
// Log      :
// 2019/04/03 新增資料直接轉換 excel 類 Letter
// ----------------------------------------------------------------------------
// 功能說明
// classes:
//  CSVStream
//
// 1. classes for file manipulation
//
//    example:
//
//   $csv_stream = new CSVStream($filename);
//   $csv_stream->begin();
//   $csv_stream->writeRow(['aaa', 'bbb', 'ccc']);
//   $csv_stream->end();
//

class CSVWriter
{
  /**
   * file pointer
   * @var File
   */
  protected $fp;

  /**
   * file path
   * @var string
   */
  protected $file_path;

  /**
   * lines
   * @var integer
   */
  protected $lines = 0;

  function __construct($filename)
{
    $this->file_path  = $filename;
    // $this->fileName   = $filename;
}


  public function begin()
  {
    // open the "output" stream
    // see http://www.php.net/manual/en/wrappers.php.php#refsect2-wrappers.php-unknown-unknown-unknown-descriptioq
    $this->fp = fopen($this->file_path, 'w+');

    // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
    fwrite($this->fp,chr(0xEF).chr(0xBB).chr(0xBF));
  }

  public function end()
  {
    fclose($this->fp);
  }

  public function writeRow(array $fields)
  {
    fputcsv($this->fp, $fields);
    $this->lines++;
  }

  public function write($collection, $callback = null)
  {
    if(!$this->isIterable($collection)) {
      return;
    }

    foreach ($collection as $row) {

      if(!empty($callback) && is_callable($callback)) {
        $fields = $callback($row);
      } else {
        $fields = $row;
      }

      if(!empty($fields)) {
        $this->writeRow($fields);
      }
    }
  }

  protected function isIterable($obj)
  {
    return is_array($obj) || $obj instanceof \Traversable;
  }

  static function getRowSize(array $fields)
  {
    $tmp_fp = fopen('php://temp', 'r+');
    fputcsv($tmp_fp, $fields);
    rewind($tmp_fp);
    $tmp_data = rtrim(stream_get_contents($tmp_fp), "\n");
    $rowSize = strlen($tmp_data);
    fclose($tmp_fp);

    return $rowSize;
  }
}


class CSVStream extends CSVWriter
{
  private $fileName;
  private $perRowSize;
  private $totalRow;

  function __construct($filename)
  {
    $this->file_path = 'php://output';
    $this->fileName = $filename;
    $this->perRowSize = $perRowSize;
    $this->totalRow = $totalRow;
  }

  public function begin()
  {
    if(ob_get_level()) {
      ob_end_clean();
    }

    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="' . $this->fileName . '";');
    header( "Pragma: no-cache" );
    header( "Expires: 0" );

    parent::begin();

    flush();
  }

  public function writeRow(array $fields)
  {
    parent::writeRow($fields);

    // Attempt to flush output to the browser every 500 lines.
    if($this->lines % 500 == 0) {
      flush();
    }
  }

}


class CSVStringGenerator extends CSVWriter
{
  private $csvString_length;

  function __construct()
  {
    $this->file_path = 'php://temp';
    $this->csvString_length = 0;
  }

  public function end()
  {
    rewind($this->fp);
    $data = rtrim(stream_get_contents($this->fp), "\n");
    fclose($this->fp);

    $this->csvString_length = strlen($data);

    return $data;
  }

  public function getLength()
  {
    return $this->csvString_length;
  }
}


class csvtoexcel{
  protected $file_name;
  protected $file_path;

  function __construct($file_name,$file_path)
  {
      $this->file_name  = str_replace(".csv","",$file_name);
      $this->file_path  = $file_path;
  }
  public function begin(){

    if(ob_get_level()) {
      ob_end_clean();
    }
   
    // require 'vendor/autoload.php';
    // use PhpOffice\PhpSpreadsheet\Spreadsheet;
    // use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    
    // use PhpOffice\PhpSpreadsheet\IOFactory;
    // var_dump($this->file_name);
    
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
    $spreadsheet = $reader->load($this->file_path);

    $worksheet = $spreadsheet->getActiveSheet();
    foreach(range('A',$worksheet->getHighestColumn()) as $column) {
      $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }
    
    // var_dump($spreadsheet);die();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$this->file_name.'.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
  }


}


/**
 * 匯出 excel 檔
 */
class exportExcel
{
    private $fileName;
    private $filePath;
    private $type;
    private $spreadsheet;

    /**
         * exportExcel constructor.
         *
         * @param string $fileName 檔案名稱
         * @param string $filePath 檔案路徑
         * @param string $type excel 檔案類型 區分為 Xls Xlsx
         */
    public function __construct($fileName, $filePath, $type)
    {
        $this->fileName = $fileName;
        $this->filePath = $filePath;
        $this->type = $type;
        $this->spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    }


    /**
         * 將資料庫取得資料轉換為 excel 檔
         *
         * @param array $titleArr 標題列矩陣
         * @param array $dataArr  資料列矩陣
         * @param array $casinoAccounts 娛樂城帳號
         *
         * @throws \PhpOffice\PhpSpreadsheet\Exception
         * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
         */
    public function dataToExcel($titleArr, $dataArr, $casinoAccounts)
    {
      // 轉出 excel
      header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      header('Content-Disposition: attachment; filename="'. $this->fileName .'"');
      header('Cache-Control: max-age=0');

      $counter = ceil(count($dataArr)/100);//分批處理,每100筆資料循環一次，以避免執行時超過執行時間上限而被跳轉至504

      // 取得 active sheet
      $worksheet = $this->spreadsheet->getActiveSheet();
    
      // 標題列
      for ($i = 0; $i < count($titleArr); $i++) {
        $worksheet->setCellValueByColumnAndRow($i + 1, 1, $titleArr[$i]);
      }

      // 資料列
      $colIndexStr = $worksheet->getHighestColumn();
      $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colIndexStr);
      $index = 2; // row index
      for($x=0;$x<$counter;$x++){
        for ($i = $x*100; $i < 100*($x+1) && $i < count($dataArr); $i++) {
            $item = $dataArr[$i];
            $item = transColumnValue($item);
            for ($j = 0; $j < count($item); $j++) {
                $worksheet->setCellValueByColumnAndRow($j + 1, $index, array_values($item)[$j]);
            }
            if (is_array($casinoAccounts) and count($casinoAccounts) > 0) {
                exportCasinoAccountToExcel($casinoAccounts, $worksheet, $index, $colIndex, $i);
            }
            $index++;
        }
        
        // 自動寬度
        $colIndexStr = $worksheet->getHighestColumn();
        $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colIndexStr);
        for ($i = 1; $i < $colIndex; $i++) {
            $worksheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, $this->type);
        $writer->save('php://output');
      }
    }
    
    
    /*
    public function dataToExcel($titleArr, $dataArr, $casinoAccounts)
    {
        // 取得 active sheet
        $worksheet = $this->spreadsheet->getActiveSheet();

        // 標題列
        for ($i = 0; $i < count($titleArr); $i++) {
            $worksheet->setCellValueByColumnAndRow($i + 1, 1, $titleArr[$i]);
        }

        // 資料列
        $colIndexStr = $worksheet->getHighestColumn();
        $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colIndexStr);
        $index = 2; // row index
        for ($i = 0; $i < count($dataArr); $i++) {
            set_time_limit(0);
            $item = $dataArr[$i];
            $item = transColumnValue($item);
            for ($j = 0; $j < count($item); $j++) {
                $worksheet->setCellValueByColumnAndRow($j + 1, $index, array_values($item)[$j]);
            }
            if (is_array($casinoAccounts) and count($casinoAccounts) > 0) {
                exportCasinoAccountToExcel($casinoAccounts, $worksheet, $index, $colIndex, $i);
            }
            $index++;
        }

        // 自動寬度
        $colIndexStr = $worksheet->getHighestColumn();
        $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colIndexStr);
        for ($i = 1; $i < $colIndex; $i++) {
            $worksheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        // 轉出 excel
		    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		    header('Content-Disposition: attachment; filename="'. $this->fileName .'"');
		    header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, $this->type);
        $writer->save('php://output');
    }
*/
}


/**
 * 轉換資料欄位至 excel
 *
 * @param array $item 資料庫資料
 *
 * @return array 轉換後資料
 */
function transColumnValue(array $item)
{
    global $tr;
    for ($i = 0; $i < count($item); $i++) {
        $keys = array_keys($item);
        switch ($keys[$i]) {
            case 'status':
                $status = array(
                    '0' => $tr['Wallet Disable'],
                    '1' => $tr['Wallet Valid'],
                    '2' => $tr['Wallet Freeze'],
                    '3' => $tr['blocked'],
                    '4' => $tr['auditing']
                );
                $item[$keys[$i]] = $status[$item[$keys[$i]]];
                break;
            case 'therole':
                $therole = array(
                    'A' => $tr['Identity Agent Title'],
                    'R' => $tr['Identity Management Title'],
                    'M' => $tr['Identity Member Title'],
                    'T' => $tr['Identity Trial Account Title']
                );
                $item[$keys[$i]] = $therole[$item[$keys[$i]]];
                break;
            case 'parent_id':
                $item[$keys[$i]] = getAccountById($item['id']);
                break;
            case 'sex':
                $gender = array(
                    '0' => $tr['Gender Female'],
                    '1' => $tr['Gender Male'],
                    '2' => $tr['Not known']
                );
                if (is_null($item[$keys[$i]])) {
                    $item[$keys[$i]] = $gender['2'];
                } else {
                    $item[$keys[$i]] = $gender[$item[$keys[$i]]];
                }
                break;
            case 'grade':
                $item[$keys[$i]] = getGradeById($item[$keys[$i]]);
                break;
            default:
                break;
        }
    }
    return $item;
}


/**
 * 用 ID 取得帳號
 *
 * @param int $id ID
 * @param int $debug 除錯模式
 *
 * @return string
 */
function getAccountById(int $id, $debug = 0)
{
    $sql = 'SELECT account FROM root_member WHERE "id" = \''. $id .'\';';
    $result = runSQLall($sql, $debug);
    return $result[0] > 0 ? $result[1]->account : '';
}


/**
 * 用 ID 取得會員等級
 *
 * @param int $id ID
 * @param int $debug 除錯模式
 *
 * @return string
 */
function getGradeById(int $id, $debug = 0)
{
    $sql = 'SELECT gradename FROM root_member_grade WHERE "id" = \''. $id .'\';';
    $result = runSQLall($sql, $debug);
    return $result[0] > 0 ? $result[1]->gradename : '';
}


/**
 * 將 娛樂城帳號 轉換為 excel
 *
 * @param array                                        $casinoAccount 娛樂城帳號矩陣
 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $activeSheet 資料表
 * @param int                                           $row 寫入資料表列數
 * @param int                                           $colIndex 寫入工作表欄位
 * @param int                                           $count 資料計數
 */
function exportCasinoAccountToExcel(array $casinoAccount, \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $activeSheet, int
$row, int $colIndex, $count)
{
    global $tr;
    $loop = 0;
    foreach ($casinoAccount as $account) {
        if ($loop == $count) {
            foreach ($account as $key => $value) {
                foreach ($value as $k => $v) {
                    if ($k == 'password') continue;
                    $activeSheet->setCellValueByColumnAndRow($colIndex, $row, $key . $tr['Casino'] . ' ' .$tr[$k] . ' ' . $v);
                    $colIndex++;
                }
            }
        }
        $loop++;
    }
}

function exceltocsv($source,$destination,$ext){

    if($ext=='xlsx'){
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    }elseif($ext=='xls'){
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
    }

    $spreadsheet = $reader->load($source);



    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
    // Writing UTF-8 CSV files
    $writer->setUseBOM(true);
    $writer->save($destination);

    return $destination;
}

function delete_upload_xls_tempfile($file_path){
  if(file_exists($file_path)){
    unlink($file_path);
  }

}


?>
