<?php
// ----------------------------------------------------------------------------
// Features:	後台--娛樂城管理 娛樂城類別
// File Name:	casino.php
// Author:		Letter
// Related:		casino_switch_process_lib.php
// Log:
// 2019.05.21 新建 Letter
// 2020.02.07 新增對應語系顯示名稱欄位 Letter
// ----------------------------------------------------------------------------

class casino implements JsonSerializable
{
	// ID
	private $id;
	// 娛樂城 ID
	private $casinoid;
	// 娛樂城名稱
	private $casino_name;
	// 娛樂城使用的資料表
	private $casino_dbtable;
	// 備註
	private $note;
	// 娛樂城啟用開關
	private $open;
	// 會員帳號欄名稱
	private $account_column;
	// 會員投注記錄表
	private $bettingrecords_tables;
	// 娛樂城自訂排序
	private $casino_order;
	// 娛樂城的返水分類
	private $game_flatform_list;
	// 上線醒目時間
	private $notify_datetime;
	// 總後台 API 更新數值
	private $api_update;
	// 娛樂城顯示名稱
	private $display_name;
	// 最新上線
	private $new_alert;
	// 簡中名稱
	private $zh_cn_name;
	// 英文名稱
	private $en_us_name;

	// 娛樂城狀態
	public static $casinoOff = '0';
	public static $casinoOn = '1';
	public static $casinoOffProcessing = '2';
	public static $casinoEmg = '3';
	public static $casinoEmgForCasinoOff = '30';
	public static $casinoEmgForCasinoOn = '31';
	public static $casinoEmgForCasinoCloseOff = '340';
	public static $casinoEmgForCasinoCloseOn = '341';
	public static $casinoClose = '4';
	public static $casinoCloseForCasinoOff = '40';
	public static $casinoCloseForCasinoOn = '41';
	public static $casinoCloseForCasinoEmgOff = '430';
	public static $casinoCloseForCasinoEmgOn = '431';
	public static $casinoDeprecated = '5';
	public static $casinoNew = 'new';

	// 維護
	public static $maintenanceOn = '1';
	public static $maintenanceOff = '0';

	// 分類
	public static $gameFlatform = ['live', 'game', 'html5', 'fish', 'lotto', 'lottery', 'sports', 'card', 'lottosum'];

	/**
	 * casino constructor.
	 *
	 * @param $id
	 * @param $casinoid
	 * @param $casino_name
	 * @param $casino_dbtable
	 * @param $note
	 * @param $open
	 * @param $account_column
	 * @param $bettingrecords_tables
	 * @param $casino_order
	 * @param $game_flatform_list
	 * @param $notify_datetime
	 * @param $api_update
	 * @param $display_name
	 * @param $new_alert
	 * @param $zh_cn_name
	 * @param $en_us_name
	 */
	public function __construct($id, $casinoid, $casino_name, $casino_dbtable, $note, $open, $account_column,
	                            $bettingrecords_tables, $casino_order, $game_flatform_list, $notify_datetime,
	                            $api_update, $display_name, $new_alert, $zh_cn_name, $en_us_name)
	{
		$this->id = $id;
		$this->casinoid = $casinoid;
		$this->casino_name = $casino_name;
		$this->casino_dbtable = $casino_dbtable;
		$this->note = $note;
		$this->open = $open;
		$this->account_column = $account_column;
		$this->bettingrecords_tables = $bettingrecords_tables;
		$this->casino_order = $casino_order;
		$this->game_flatform_list = $game_flatform_list;
		$this->notify_datetime = $notify_datetime;
		$this->api_update = $api_update;
		$this->display_name = $display_name;
		$this->new_alert = $new_alert;
		$this->zh_cn_name = $zh_cn_name;
		$this->en_us_name = $en_us_name;
	}

	/**
	 * @return mixed
	 */
	public function getZhCnName()
	{
		return $this->zh_cn_name;
	}

	/**
	 * @param mixed $zh_cn_name
	 */
	public function setZhCnName($zh_cn_name): void
	{
		$this->zh_cn_name = $zh_cn_name;
	}

	/**
	 * @return mixed
	 */
	public function getEnUsName()
	{
		return $this->en_us_name;
	}

	/**
	 * @param mixed $en_us_name
	 */
	public function setEnUsName($en_us_name): void
	{
		$this->en_us_name = $en_us_name;
	}


	/**
	 * @return mixed
	 */
	public function getNewAlert()
	{
		return $this->new_alert;
	}

	/**
	 * @param mixed $new_alert
	 */
	public function setNewAlert($new_alert): void
	{
		$this->new_alert = $new_alert;
	}

	/**
	 * @return mixed
	 */
	public function getDisplayName()
	{
		return $this->display_name;
	}

	/**
	 * @param mixed $display_name
	 */
	public function setDisplayName($display_name): void
	{
		$this->display_name = $display_name;
	}


	/**
	 * @return mixed
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param mixed $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * @return mixed
	 */
	public function getCasinoid()
	{
		return $this->casinoid;
	}

	/**
	 * @param mixed $casinoid
	 */
	public function setCasinoid($casinoid)
	{
		$this->casinoid = $casinoid;
	}

	/**
	 * @return mixed
	 */
	public function getCasinoName()
	{
		return $this->casino_name;
	}

	/**
	 * @param mixed $casino_name
	 */
	public function setCasinoName($casino_name)
	{
		$this->casino_name = $casino_name;
	}

	/**
	 * @return mixed
	 */
	public function getCasinoDbtable()
	{
		return $this->casino_dbtable;
	}

	/**
	 * @param mixed $casino_dbtable
	 */
	public function setCasinoDbtable($casino_dbtable)
	{
		$this->casino_dbtable = $casino_dbtable;
	}

	/**
	 * @return mixed
	 */
	public function getNote()
	{
		return $this->note;
	}

	/**
	 * @param mixed $note
	 */
	public function setNote($note)
	{
		$this->note = $note;
	}

	/**
	 * @return mixed
	 */
	public function getOpen()
	{
		return $this->open;
	}

	/**
	 * @param mixed $open
	 */
	public function setOpen($open)
	{
		$this->open = $open;
	}

	/**
	 * @return mixed
	 */
	public function getAccountColumn()
	{
		return $this->account_column;
	}

	/**
	 * @param mixed $account_column
	 */
	public function setAccountColumn($account_column)
	{
		$this->account_column = $account_column;
	}

	/**
	 * @return mixed
	 */
	public function getBettingrecordsTables()
	{
		return $this->bettingrecords_tables;
	}

	/**
	 * @param mixed $bettingrecords_tables
	 */
	public function setBettingrecordsTables($bettingrecords_tables)
	{
		$this->bettingrecords_tables = $bettingrecords_tables;
	}

	/**
	 * @return mixed
	 */
	public function getCasinoOrder()
	{
		return $this->casino_order;
	}

	/**
	 * @param mixed $casino_order
	 */
	public function setCasinoOrder($casino_order)
	{
		$this->casino_order = $casino_order;
	}

	/**
	 * @return mixed
	 */
	public function getGameFlatformList()
	{
		return $this->game_flatform_list;
	}

	/**
	 * @param mixed $game_flatform_list
	 */
	public function setGameFlatformList($game_flatform_list)
	{
		$this->game_flatform_list = $game_flatform_list;
	}

	/**
	 * @return mixed
	 */
	public function getNotifyDatetime()
	{
		return $this->notify_datetime;
	}

	/**
	 * @param mixed $notify_datetime
	 */
	public function setNotifyDatetime($notify_datetime)
	{
		$this->notify_datetime = $notify_datetime;
	}

	/**
	 * @return mixed
	 */
	public function getApiUpdate()
	{
		return $this->api_update;
	}

	/**
	 * @param mixed $api_update
	 */
	public function setApiUpdate($api_update)
	{
		$this->api_update = $api_update;
	}


	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @link  https://php.net/manual/en/jsonserializable.jsonserialize.php
	 * @return mixed data which can be serialized by <b>json_encode</b>,
	 * which is a value of any type other than a resource.
	 * @since 5.4.0
	 */
	public function jsonSerialize()
	{
		return get_object_vars($this);
	}


	/**
	 * 取得物件參數
	 *
	 * @return array 物件參數
	 */
	public function getKeyValueMap()
	{
		$kvm = array();
		foreach ($this as $key => $value) {
			$kv = array( $key => $value);
			array_push($kvm, $kv);
		}
		return $kvm;
	}
}