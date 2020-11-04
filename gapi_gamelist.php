<?php
// ----------------------------------------------------------------------------
// Features:	API 遊戲清單物件
// File Name:	gapi_gamelist.php
// Author:		Letter
// Related:
// Log:
// 2019.01.31 新建 Letter
// ----------------------------------------------------------------------------

class gapi_gamelist implements JsonSerializable
{
	private $id;
	private $gamehall;
	private $gamecode;
	private $name;
	private $name_cn;
	private $category;
	private $platform;
	private $is_open;
	private $sub_gamehall;

	/**
	 * gapi_gamelist constructor.
	 *
	 * @param $id
	 * @param $gamehall
	 * @param $gamecode
	 * @param $name
	 * @param $name_cn
	 * @param $category
	 * @param $platform
	 * @param $is_open
	 * @param $sub_gamehall
	 */
	public function __construct($id, $gamehall, $gamecode, $name, $name_cn, $category, $platform, $is_open, $sub_gamehall)
	{
		$this->id = $id;
		$this->gamehall = $gamehall;
		$this->gamecode = $gamecode;
		$this->name = $name;
		$this->name_cn = $name_cn;
		$this->category = $category;
		$this->platform = $platform;
		$this->is_open = $is_open;
		$this->sub_gamehall = $sub_gamehall;
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
	public function getGamehall()
	{
		return $this->gamehall;
	}

	/**
	 * @param mixed $gamehall
	 */
	public function setGamehall($gamehall)
	{
		$this->gamehall = $gamehall;
	}

	/**
	 * @return mixed
	 */
	public function getGamecode()
	{
		return $this->gamecode;
	}

	/**
	 * @param mixed $gamecode
	 */
	public function setGamecode($gamecode)
	{
		$this->gamecode = $gamecode;
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param mixed $name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * @return mixed
	 */
	public function getNameCn()
	{
		return $this->name_cn;
	}

	/**
	 * @param mixed $name_cn
	 */
	public function setNameCn($name_cn)
	{
		$this->name_cn = $name_cn;
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
	public function getPlatform()
	{
		return $this->platform;
	}

	/**
	 * @param mixed $platform
	 */
	public function setPlatform($platform)
	{
		$this->platform = $platform;
	}

	/**
	 * @return mixed
	 */
	public function getisOpen()
	{
		return $this->is_open;
	}

	/**
	 * @param mixed $is_open
	 */
	public function setIsOpen($is_open)
	{
		$this->is_open = $is_open;
	}

	/**
	 * @return mixed
	 */
	public function getSubGamehall()
	{
		return is_null($this->sub_gamehall) ? 'NULL' : $this->sub_gamehall;
	}

	/**
	 * @param mixed $sub_gamehall
	 */
	public function setSubGamehall($sub_gamehall)
	{
		$this->sub_gamehall = $sub_gamehall;
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