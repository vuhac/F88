<?php
// ----------------------------------------------------------------------------
// Features:	API 遊戲廠商物件
// File Name:	gapi_hall.php
// Author:		Letter
// Related:
// Log:
// 2019.01.31 新建 Letter
// ----------------------------------------------------------------------------

class gapi_hall implements JsonSerializable
{
	private $id;
	private $gamehall;
	private $fullname;
	private $new;

	/**
	 * gapi_hall constructor.
	 *
	 * @param $id
	 * @param $gamehall
	 * @param $fullname
	 * @param $new
	 */
	public function __construct($id, $gamehall, $fullname, $new)
	{
		$this->id = $id;
		$this->gamehall = $gamehall;
		$this->fullname = $fullname;
		$this->new = $new;
	}

	/**
	 * @return mixed
	 */
	public function getNew()
	{
		return $this->new;
	}

	/**
	 * @param mixed $new
	 */
	public function setNew($new)
	{
		$this->new = $new;
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
	public function getFullname()
	{
		return $this->fullname;
	}

	/**
	 * @param mixed $fullname
	 */
	public function setFullname($fullname)
	{
		$this->fullname = $fullname;
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
}