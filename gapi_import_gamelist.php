<?php
// ----------------------------------------------------------------------------
// Features:	暫存遊戲清單物件
// File Name:	gapi_import_gamelist.php
// Author:		Letter
// Related:
// Log:
// 2019.02.12 新建 Letter
// ----------------------------------------------------------------------------

class gapi_import_gamelist implements JsonSerializable
{
	private $id;
	private $category;
	private $sub_category;
	private $gametype;
	private $gamename;
	private $gameid;
	private $gameplatform;
	private $gamename_cn;
	private $imagefilename;
	private $marketing_strategy;
	private $casino_id;
	private $note;
	private $open;
	private $moduleid;
	private $clientid;
	private $favorable_type;
	private $slot_line;
	private $custom_icon;
	private $custom_order;
	private $category_cn;
	private $is_new;
	private $casino_name;
	private $category_name;
	private $sub_category_name;
	private $game_category_name;
	private $display_name;
	private $language_name;


	/**
	 * gapi_import_gamelist constructor.
	 *
	 * @param $id
	 * @param $category
	 * @param $sub_category
	 * @param $gametype
	 * @param $gamename
	 * @param $gameid
	 * @param $gameplatform
	 * @param $gamename_cn
	 * @param $imagefilename
	 * @param $marketing_strategy
	 * @param $casino_id
	 * @param $note
	 * @param $open
	 * @param $moduleid
	 * @param $clientid
	 * @param $favorable_type
	 * @param $slot_line
	 * @param $custom_icon
	 * @param $custom_order
	 * @param $category_cn
	 * @param $is_new
	 * @param $casino_name
	 * @param $category_name
	 * @param $sub_category_name
	 * @param $game_category_name
	 * @param $display_name
	 * @param $language_name
	 */
	public function __construct($id, $category, $sub_category, $gametype, $gamename, $gameid, $gameplatform,
	                            $gamename_cn, $imagefilename, $marketing_strategy, $casino_id, $note, $open,
	                            $moduleid, $clientid, $favorable_type, $slot_line, $custom_icon, $custom_order,
	                            $category_cn, $is_new, $casino_name, $category_name, $sub_category_name,
	                            $game_category_name, $display_name, $language_name)
	{
		$this->id = $id;
		$this->category = $category;
		$this->sub_category = $sub_category;
		$this->gametype = $gametype;
		$this->gamename = $gamename;
		$this->gameid = $gameid;
		$this->gameplatform = $gameplatform;
		$this->gamename_cn = $gamename_cn;
		$this->imagefilename = $imagefilename;
		$this->marketing_strategy = $marketing_strategy;
		$this->casino_id = $casino_id;
		$this->note = $note;
		$this->open = $open;
		$this->moduleid = $moduleid;
		$this->clientid = $clientid;
		$this->favorable_type = $favorable_type;
		$this->slot_line = $slot_line;
		$this->custom_icon = $custom_icon;
		$this->custom_order = $custom_order;
		$this->category_cn = $category_cn;
		$this->is_new = $is_new;
		$this->casino_name = $casino_name;
		$this->category_name = $category_name;
		$this->sub_category_name = $sub_category_name;
		$this->game_category_name = $game_category_name;
		$this->display_name = $display_name;
		$this->language_name = $language_name;
	}

	/**
	 * @return mixed
	 */
	public function getLanguageName()
	{
		return $this->language_name;
	}

	/**
	 * @param mixed $language_name
	 */
	public function setLanguageName($language_name): void
	{
		$this->language_name = $language_name;
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
	public function getCategory()
	{
		return $this->category;
	}

	/**
	 * @param mixed $category
	 */
	public function setCategory($category)
	{
		$this->category = $category;
	}

	/**
	 * @return mixed
	 */
	public function getSubCategory()
	{
		return $this->sub_category;
	}

	/**
	 * @param mixed $sub_category
	 */
	public function setSubCategory($sub_category)
	{
		$this->sub_category = $sub_category;
	}

	/**
	 * @return mixed
	 */
	public function getGametype()
	{
		return $this->gametype;
	}

	/**
	 * @param mixed $gametype
	 */
	public function setGametype($gametype)
	{
		$this->gametype = $gametype;
	}

	/**
	 * @return mixed
	 */
	public function getGamename()
	{
		return $this->gamename;
	}

	/**
	 * @param mixed $gamename
	 */
	public function setGamename($gamename)
	{
		$this->gamename = $gamename;
	}

	/**
	 * @return mixed
	 */
	public function getGameid()
	{
		return $this->gameid;
	}

	/**
	 * @param mixed $gameid
	 */
	public function setGameid($gameid)
	{
		$this->gameid = $gameid;
	}

	/**
	 * @return mixed
	 */
	public function getGameplatform()
	{
		return $this->gameplatform;
	}

	/**
	 * @param mixed $gameplatform
	 */
	public function setGameplatform($gameplatform)
	{
		$this->gameplatform = $gameplatform;
	}

	/**
	 * @return mixed
	 */
	public function getGamenameCn()
	{
		return $this->gamename_cn;
	}

	/**
	 * @param mixed $gamename_cn
	 */
	public function setGamenameCn($gamename_cn)
	{
		$this->gamename_cn = $gamename_cn;
	}

	/**
	 * @return mixed
	 */
	public function getImagefilename()
	{
		return $this->imagefilename;
	}

	/**
	 * @param mixed $imagefilename
	 */
	public function setImagefilename($imagefilename)
	{
		$this->imagefilename = $imagefilename;
	}

	/**
	 * @return mixed
	 */
	public function getMarketingStrategy()
	{
		return $this->marketing_strategy;
	}

	/**
	 * @param mixed $marketing_strategy
	 */
	public function setMarketingStrategy($marketing_strategy)
	{
		$this->marketing_strategy = $marketing_strategy;
	}

	/**
	 * @return mixed
	 */
	public function getCasinoId()
	{
		return $this->casino_id;
	}

	/**
	 * @param mixed $casino_id
	 */
	public function setCasinoId($casino_id)
	{
		$this->casino_id = $casino_id;
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
	public function getModuleid()
	{
		return $this->moduleid;
	}

	/**
	 * @param mixed $moduleid
	 */
	public function setModuleid($moduleid)
	{
		$this->moduleid = $moduleid;
	}

	/**
	 * @return mixed
	 */
	public function getClientid()
	{
		return $this->clientid;
	}

	/**
	 * @param mixed $clientid
	 */
	public function setClientid($clientid)
	{
		$this->clientid = $clientid;
	}

	/**
	 * @return mixed
	 */
	public function getFavorableType()
	{
		return $this->favorable_type;
	}

	/**
	 * @param mixed $favorableType
	 */
	public function setFavorableType($favorableType)
	{
		$this->favorable_type = $favorableType;
	}

	/**
	 * @return mixed
	 */
	public function getSlotLine()
	{
		return $this->slot_line;
	}

	/**
	 * @param mixed $slot_line
	 */
	public function setSlotLine($slot_line)
	{
		$this->slot_line = $slot_line;
	}

	/**
	 * @return mixed
	 */
	public function getCustomIcon()
	{
		return $this->custom_icon;
	}

	/**
	 * @param mixed $custom_icon
	 */
	public function setCustomIcon($custom_icon)
	{
		$this->custom_icon = $custom_icon;
	}

	/**
	 * @return mixed
	 */
	public function getCustomOrder()
	{
		return $this->custom_order;
	}

	/**
	 * @param mixed $custom_order
	 */
	public function setCustomOrder($custom_order)
	{
		$this->custom_order = $custom_order;
	}

	/**
	 * @return mixed
	 */
	public function getCategoryCn()
	{
		return $this->category_cn;
	}

	/**
	 * @param mixed $category_cn
	 */
	public function setCategoryCn($category_cn)
	{
		$this->category_cn = $category_cn;
	}

	/**
	 * @return mixed
	 */
	public function getIsNew()
	{
		return $this->is_new;
	}

	/**
	 * @param mixed $is_new
	 */
	public function setIsNew($is_new)
	{
		$this->is_new = $is_new;
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
	public function getCategoryName()
	{
		return $this->category_name;
	}

	/**
	 * @param mixed $category_name
	 */
	public function setCategoryName($category_name)
	{
		$this->category_name = $category_name;
	}

	/**
	 * @return mixed
	 */
	public function getSubCategoryName()
	{
		return $this->sub_category_name;
	}

	/**
	 * @param mixed $sub_category_name
	 */
	public function setSubCategoryName($sub_category_name)
	{
		$this->sub_category_name = $sub_category_name;
	}

	/**
	 * @return mixed
	 */
	public function getGameCategoryName()
	{
		return $this->game_category_name;
	}

	/**
	 * @param mixed $game_category_name
	 */
	public function setGameCategoryName($game_category_name)
	{
		$this->game_category_name = $game_category_name;
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