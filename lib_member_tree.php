<?php
// ----------------------------------------------------------------------------
// Features :	後台 --  member tree LIB
// File Name: lib_member_tree.php
// Author   : Dright
// Related  : preferential_calculation
// Log      :
// ----------------------------------------------------------------------------
// 對應資料表
// 相關的檔案
// 功能說明
// 1. class for member tree manipulation
// update   : yyyy.mm.dd
//
//
//    example:
//
//    $member_list = MemberTreeNode::getMemberListByDate('2017-12-20');
//    $tree_root = $member_list[1];
//    MemberTreeNode::buildMemberTree($tree_root, $member_list);
//    MemberTreeNode::visitBottomUp($tree_root, function($member)  {
//      // do something...
//    }
//


/**
 * [MemberTreeNode description]
 *   public $id               => int(1)
 *   public $account          => string(4) "root"
 *   public $status           => string(1) "1"
 *   public $therole          => string(1) "R"
 *   public $parent_id        => int(1)
 *   public $predecessor_id_list => array(12)
 *   public $commissionrule   => string(7) "default"
 *   public $children         =>   array(12)
 *   public $node_data        =>  object
 */
class MemberTreeNode {
  public $id;
  public $account;
  public $status;
  public $therole;
  public $parent_id;
  public $commissionrule;
  public $favorablerule;
  public $feedbackinfo;

  public $member_level;
  public $predecessor_id_list;
  public $children;
  public $node_data;

  public static function getPredecessorList($member_id) {
    $recursive_sql =<<<SQL
      WITH RECURSIVE subordinates AS (
        SELECT
        id,
        parent_id,
        account,
        therole,
        status,
        commissionrule,
        favorablerule,
        feedbackinfo
        FROM
        root_member
        WHERE
        id = :id
        UNION
        SELECT
        m.id,
        m.parent_id,
        m.account,
        m.therole,
        m.status,
        m.commissionrule,
        m.favorablerule,
        m.feedbackinfo
        FROM
        root_member m
        INNER JOIN subordinates s ON s.parent_id = m.id
      ) SELECT
        *
      FROM
        subordinates;
SQL;

    $recursive_result = runSQLall_prepared($recursive_sql, [':id' => $member_id], self::class, 0, 'r');

    // print_r($recursive_result);

    return $recursive_result;
  }

  public static function getSuccessorList($agent_id) {
    $recursive_sql =<<<SQL
      WITH RECURSIVE subordinates AS (
        SELECT
          id,
          parent_id,
          account,
          therole,
          status,
          commissionrule,
          favorablerule,
          feedbackinfo
        FROM
          root_member
        WHERE
          id = :id
        UNION
          SELECT
            m.id,
            m.parent_id,
            m.account,
            m.therole,
            m.status,
            m.commissionrule,
            m.favorablerule,
            m.feedbackinfo
          FROM
            root_member m
          INNER JOIN subordinates s ON m.parent_id = s.id
      ) SELECT
        *
      FROM
        subordinates;
SQL;

    $recursive_result = runSQLall_prepared($recursive_sql, [':id' => $agent_id], self::class, 0, 'r');

    // print_r($recursive_result);

    return $recursive_result;
  }

  public static function getSuccessorListByDate($agent_id, $date) {

    $recursive_sql =<<<SQL
      WITH RECURSIVE subordinates AS (
        SELECT
          member_id,
          member_account,
          member_therole,
          member_parent_id
        FROM root_statisticsdailyreport
        WHERE dailydate = :date AND member_id = :id
        UNION
          SELECT
            m.member_id,
            m.member_account,
            m.member_therole,
            m.member_parent_id
          FROM root_statisticsdailyreport as m
          INNER JOIN subordinates s ON m.member_parent_id = s.member_id AND m.dailydate = :date
      ) SELECT
        member_id as id,
        member_account as account,
        root_member.status,
        member_therole as therole,
        member_parent_id as parent_id,
        root_member.commissionrule,
        root_member.favorablerule,
        root_member.feedbackinfo
      FROM subordinates
        LEFT JOIN root_member on root_member.id = subordinates.member_id
      ORDER BY parent_id, id
;
SQL;

    $recursive_result = runSQLall_prepared($recursive_sql, [':id' => $agent_id, ':date' => $date], self::class, 0, 'r');

    // print_r($recursive_result);

    return $recursive_result;
  }

  public static function getMemberList($root_id = null) {
    if(empty($root_id)) {
      $all_member_sql = "SELECT
      id,
      account,
      status,
      therole,
      parent_id,
      commissionrule,
      favorablerule,
      feedbackinfo
      FROM root_member
      ORDER BY parent_id, id
      ;";

      $all_member_sql_result = runSQLall_prepared($all_member_sql, [], self::class, 0, 'r');

    } else {

      $all_member_sql_result = self::getSuccessorList($root_id);

    }

    /**
     * [$member_list   key is member id]
     * @var array
     */
    $member_list = [];

    // construct member_list
    // unset($all_member_sql_result[0]);
    foreach($all_member_sql_result as $member) {
      $member_list[(int)$member->id] = $member;
    }

    // print_r($member_list[1783]);

    return $member_list;
  }

  public static function getMemberListByDate($date, $root_id = null) {
    if(empty($root_id)) {
      $all_member_sql = "SELECT
        member_id as id,
        member_account as account,
        root_member.status,
        member_therole as therole,
        member_parent_id as parent_id,
        root_member.commissionrule,
        root_member.favorablerule,
        root_member.feedbackinfo
      FROM root_statisticsdailyreport
        LEFT JOIN root_member on root_member.id = root_statisticsdailyreport.member_id
      WHERE root_statisticsdailyreport.dailydate = :date
      ORDER BY parent_id, id
      ;";

      $all_member_sql_result = runSQLall_prepared($all_member_sql, [':date' => $date], self::class, 0, 'r');
    } else {
      $all_member_sql_result = self::getSuccessorListByDate($root_id, $date);
    }

    /**
     * [$member_list   key is member id]
     * @var array
     */
    $member_list = [];

    // construct member_list
    // unset($all_member_sql_result[0]);
    foreach($all_member_sql_result as $member) {
      $member_list[(int)$member->id] = $member;
    }

    return $member_list;
  }

  public static function buildMemberTree($current_node, &$member_list, $init_data = [], $tree_level = 1, $predecessor_id_list = []) {

    $current_node->member_level = $tree_level;
    $current_node->predecessor_id_list = $predecessor_id_list;
    $parent_id = $current_node->id;

    if($current_node->id == $current_node->parent_id || empty($current_node->parent_id)) {
      $current_node->parent = null;
    } else {
      if(!empty($member_list[$current_node->parent_id])) {
        $current_node->parent = $member_list[$current_node->parent_id];
      }
    }

    // init node_data
    $current_node->node_data = null;
    if(is_array($init_data)) {
      $current_node->node_data = (object) $init_data;
    } elseif (is_callable($init_data)) {
      $init_data($current_node);
    }

    $current_node->children = array_filter($member_list, function($member) use ($parent_id) {
      return $member->parent_id == $parent_id && $member->id != $parent_id;
    });

    array_push($predecessor_id_list, $current_node->id);
    foreach($current_node->children as $member) {
      self::buildMemberTree($member, $member_list, $init_data, $tree_level + 1, $predecessor_id_list);
    }

  }

  /**
  * [visit_tree_buttom_up description]
  * @param  [type]   $tree_node [description]
  * @param  callable $callback  [description]
  * @return [type]              [description]
  */
  public static function visitBottomUp($tree_node, callable $callback) {

    foreach($tree_node->children as $node) {
      self::visitBottomUp($node, $callback);
    }

    $callback($tree_node);
  }

  /**
  * [visit_tree_top_down description]
  * @param  [type]   $tree_node [description]
  * @param  callable $callback  [description]
  * @return [type]              [description]
  */
  public static function visitTopDown($tree_node, callable $callback) {

    $callback($tree_node);

    foreach($tree_node->children as $node) {
      self::visitTopDown($node, $callback);
    }

  }

  public function isRoot()
  {
    return $this->id == 1;
  }

  public function isFirstLevelAgent()
  {
    return ($this->parent_id == 1 && $this->therole == 'A');
  }

  public function isActive()
  {
    return $this->status == 1;
  }
}

?>
