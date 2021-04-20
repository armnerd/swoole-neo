<?php

/**
 * 模型基类
 *
 */
class Model
{

	/**
	 * Database poll.
	 *
	 * @var \Neo\Pool
	 */
	private $pool;

	/**
	 * 模型的映射表
	 * @var string
	 */
	protected $table;
	/**
	 * 缺省主键，如果主键不是表名+ID，请务必指定主键名称
	 * @var string
	 */
	protected $tableid;

	/**
	 * 映射表中是否有主键
	 * @var boolean
	 */
	protected $hasPrimaryKey = true;

	/**
	 * 表的前缀
	 * @var string
	 */
	private $tablePrefix;

	/**
	 * 错误号
	 * @var int
	 */
	private $errorno = 0;
	/**
	 * 错误信息
	 *
	 * @var string
	 */
	private $errormsg = null;

	/**
	 * 最后的sql
	 * @var string
	 */
	protected $lastsql;

	/**
	 * 软删除字段
	 * @var string
	 */
	protected $softDelete = '';

	/**
	 * 非删除状态标识
	 * @var string
	 */
	protected $unDeleted = '';

	/**
	 * 包含删除的数据
	 * @var string
	 */
	protected $withTrashed = false;

	/**
	 * 构造函数
	 *
	 */
	public function __construct(Pool $pool)
	{
		$this->pool = &$pool;

		if (!$this->table)
		{
			$className   = get_class($this);
			$this->table = str_replace('app\models\\', '', strtolower($className));
		}

		// 缺省主键，如果主键不是表名+ID，请务必指定主键名称
		if (!$this->tableid)
		{
			$this->tableid = $this->table . 'id';
		}

		// 前缀
		$this->setDefaultTablePrefix();
	}

	/**
	 * 获取数据库连接
	 *
	 * @param bool $init 如果没有链接，则初始化
	 *
	 * @return Database\NeoDatabaseMySQL|NeoDatabase
	 *
	 */
	public function getDB()
	{
		return $this->pool->getDB();
	}

	/**
	 * 数据库操作后归还实例到连接池
	 *
	 */
	public function backToPool($instant)
	{
		return $this->pool->backToPool($instant);
	}

	/**
	 * 是否事务中
	 *
	 */
	public function isTrans()
	{
		return $this->pool->isTrans();
	}

	/**
	 * 在主数据库上开启事务
	 */
	public function transStart()
	{
		$this->pool->transToggle(TRUE);
		$this->getDB()->transStart();
	}

	/**
	 * 在主数据库上提交事务
	 */
	public function transComplete()
	{
		$this->getDB()->transComplete();
		$this->pool->transToggle(FALSE);
	}

	/**
	 * 在主数据库上回滚事务
	 */
	public function transRollback()
	{
		$this->getDB()->transRollback();
		$this->pool->transToggle(FALSE);
	}

	/**
	 * 获取当前操作的表
	 *
	 * @return mixed|string
	 */
	public function getTable()
	{
		return $this->table;
	}

	/**
	 * 获取表前缀
	 *
	 * @return string
	 */
	public function getTablePrefix()
	{
		return $this->tablePrefix;
	}

	/**
	 * 设置表前缀
	 *
	 * @param string $prefix
	 */
	public function setTablePrefix($prefix)
	{
		$this->tablePrefix = $prefix;
	}

	/**
	 * 恢复缺省的表前缀。
	 * ！！当表前缀发生变化时，一定要进行此操作！！
	 */
	public function setDefaultTablePrefix()
	{
		if (!defined('DB_TBPREFIX'))
		{
			define('DB_TBPREFIX', '');
		}

		$this->tablePrefix = DB_TBPREFIX;
	}

	/**
	 * 根据条件生成SQL
	 *
	 * SELECT
	 *      [ALL | DISTINCT | DISTINCTROW ]
	 *      [HIGH_PRIORITY]
	 *      [MAX_STATEMENT_TIME = N]
	 *      [STRAIGHT_JOIN]
	 *      [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
	 *      [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS]
	 * select_expr [, select_expr ...]
	 * [FROM table_references
	 *      [PARTITION partition_list]
	 * [WHERE where_condition]
	 * [GROUP BY {col_name | expr | position}
	 *      [ASC | DESC], ... [WITH ROLLUP]]
	 * [HAVING where_condition]
	 * [ORDER BY {col_name | expr | position}
	 *      [ASC | DESC], ...]
	 * [LIMIT {[offset,] row_count | row_count OFFSET offset}]
	 *
	 * 下面中的 [] 表示可选项,不是表示数组
	 * $more = array(
	 *      'selectext' => [ALL | DISTINCT | DISTINCTROW ]
	 *                  [HIGH_PRIORITY]
	 *                  [MAX_STATEMENT_TIME = N]
	 *                  [STRAIGHT_JOIN]
	 *                  [SQL_SMALL_RESULT] [SQL_BIG_RESULT] [SQL_BUFFER_RESULT]
	 *                  [SQL_CACHE | SQL_NO_CACHE] [SQL_CALC_FOUND_ROWS],
	 *      'field'     => ['a.xxx, b.yyy, c.zzz'],
	 *      'from'      => ['tablea AS a'],
	 *      'left'      => [array('tableb AS b on b.id = a.id') | 'tableb AS b on b.id = a.id'],
	 *      'inner'     => [array('tablec AS c on c.id = a.id') | 'tablec AS c on c.id = a.id'],
	 *      'partition' => ['partition_list'],
	 *      'groupby'   => ['GROUP BY xxx'],
	 *      'having'    => ['HAVING xxx'],
	 *      'orderby'   => ['ORDER BY xxx'],
	 *      'limit'     => [array(offset, perpage) | offset]
	 * )
	 *
	 * @param array $conditions 条件
	 * @param array $more       ORDER BY, LIMIT, GROUP BY 等等
	 * @param array $ret        指定返回的数组元素
	 *
	 * @return string
	 */
	public function sql(array $conditions = [], array $more = [], array $ret = [])
	{
		// SELECT 的扩展: DISTINCT, SQL_CALC_FOUND_ROWS 等等
		$selectext = $more['selectext'] ?: '';

		// 查询内容
		$key   = $ret['k'] ?: $this->tableid;
		$field = $more['field'] ?: '';
		if (!$field)
		{
			if ($ret['e'])
			{
				$field = $ret['e'] . ', ' . $key;
			}
			else if ($ret['es'])
			{
				$field = $ret['es'] . ', ' . $key;
			}
			else
			{
				$field = '*';
			}
		}

		// FROM 字句
		$from = $more['from'] ?: "{$this->table} AS {$this->table}";
		$from = $this->getTablePrefix() . $from;

		// JOIN 字句
		$join = '';
		if ($more['left'])
		{
			if (is_array($more['left']))
			{
				foreach ($more['left'] as $table)
				{
					$join .= ' LEFT JOIN ' . $this->getTablePrefix() . $table;
				}
			}
			else
			{
				$join .= ' LEFT JOIN ' . $this->getTablePrefix() . $more['left'];
			}
		}

		if ($more['inner'])
		{
			if (is_array($more['inner']))
			{
				foreach ($more['inner'] as $table)
				{
					$join .= ' INNER JOIN ' . $this->getTablePrefix() . $table;
				}
			}
			else
			{
				$join .= ' INNER JOIN ' . $this->getTablePrefix() . $more['inner'];
			}
		}

		// 分区
		$partition = $more['partition'] ? "PARTITION {$more['partition']}" : '';

		// WHERE 字句
		if ($this->softDelete && !$this->withTrashed)
        {
			$unDeleted  = $this->unDeleted ? $this->unDeleted : 0;
			$softDelete = $this->softDelete . "' = '" . $unDeleted. "'";
			$where = $conditions ? $this->where($conditions) . " AND '" . $softDelete  : "WHERE '" . $softDelete;
        } else {
			$where = $conditions ? $this->where($conditions) : '';
        }

        if ($this->withTrashed)
        {
          $this->withTrashed = false;
        }

		// GROUP BY 字句
		$groupby = $more['groupby'] ? "GROUP BY {$more['groupby']}" : '';

		// HAVING 字句
		$having = $more['having'] ? "HAVING {$more['having']}" : '';

		// ORDER BY 字句
		$orderby = $more['orderby'] ? "ORDER BY {$more['orderby']}" : '';

		// LIMIT 字句
		$limit = '';
		if (isset($more['limit']))
		{
			if (is_array($more['limit']))
			{
				$more['offset']  = (int) $more['limit'][0];
				$more['perpage'] = (int) $more['limit'][1];
				$more['perpage'] < 1 && $more['perpage'] = PERPAGE;

				$limit = $more['offset'] < 0 ? '' : "LIMIT {$more['offset']}, {$more['perpage']}";
			}
			else
			{
				$limit = $more['limit'] ? "LIMIT {$more['limit']}" : '';
			}
		}

		$sql = "SELECT {$selectext} {$field} FROM {$from} {$join} {$partition} {$where} {$groupby} {$having} {$orderby} {$limit}";
		$this->lastsql = $sql;
		return $sql;
	}

	/**
	 * 根据条件生成SQL
	 *
	 * @param array $conditions 条件
	 * @param array $more       ORDER BY, LIMIT, GROUP BY 等等
	 * @param array $ret        指定返回的数组元素
	 *
	 * @return array
	 */
	public function items(array $conditions = [], array $more = [], array $ret = [])
	{
		$sql = $this->sql($conditions, $more, $ret);

		// k存在，且不为空，则返回kv数组，key为$ret['k']
		// k存在，且为null，则返回无k数组
		// k不存在，则返回kv数组，key为tableid
		$key     = $ret['k'] ?: (array_key_exists('k', $ret) ? null : $this->tableid);
		$element = $ret['e'] ?: null;

		$db   = $this->getDB();
		$func = $db->getFromMaster() ? 'queryArray' : 'queryArraySlave';
		$res  = $db->$func($sql, $element, $key);
		if (!$this->isTrans()) {
			$this->backToPool($db);
		}

		return $res;
	}

	/**
	 * 获取单条信息
	 *
	 * @param array $conditions  条件
	 * @param array $more        字句
	 * @param array $returnField 指定返回的数组元素
	 *
	 * @return string|array
	 */
	public function item($conditions = [], array $more = [], $returnField = null)
	{
		if (!$more || !$more['field'])
		{
			$field         = is_array($returnField) ? implode(',', $returnField) : $returnField;
			$field         = $field ?: '*';
			$more['field'] = $field;
		}

		$more['limit'] = 1;

		$sql = $this->sql($conditions, $more);

		$db   = $this->getDB();
		$func = $db->getFromMaster() ? 'queryFirst' : 'queryFirstSlave';
		$row  = $db->$func($sql);
		if (!$this->isTrans()) {
			$this->backToPool($db);
		}

		if (is_string($returnField))
		{
			return $row ? $row[$returnField] : [];
		}

		return $row ? $row : [];
	}

	/**
	 * 获取一个值
	 *
	 * @param array $conditions 条件
	 * @param array $more       字句
	 *
	 * @return string
	 */
	public function single($conditions = [], array $more = [])
	{
		$more['limit'] = 1;
		$sql  = $this->sql($conditions, $more);
		$db   = $this->getDB();
		$func = $db->getFromMaster() ? 'queryOne' : 'queryOneSlave';
		$res  = $db->$func($sql);
		if (!$this->isTrans()) {
			$this->backToPool($db);
		}

		return $res;
	}

	/**
	 * 获取信息
	 *
	 * @param array   $conditions 条件
	 * @param string  $orderby    排序规则
	 * @param integer $offset     起始位置
	 * @param integer $perpage    数据条数
	 * @param array   $ret        指定返回的数组元素
	 *
	 * @return array
	 */
	public function getItems($conditions = [], $orderby = '', $offset = 0, $perpage = PERPAGE, $ret = [])
	{
		$more = ['limit' => [$offset, $perpage]];

		if ($orderby)
		{
			$more['orderby'] = $orderby;
		}

		return $this->items($conditions, $more, $ret);
	}

	/**
	 * 获取所有信息
	 *
	 * @param array  $conditions 条件
	 * @param string $orderby    排序规则
	 * @param array  $ret        指定返回的数组元素
	 *
	 * @return array
	 */
	public function getAll($conditions = [], $orderby = '', $ret = [])
	{
		$more = [];
		if ($orderby)
		{
			$more['orderby'] = $orderby;
		}

		return $this->items($conditions, $more, $ret);
	}

	/**
	 * 获取单条信息
	 *
	 * @param array  $conditions  条件
	 * @param string $orderby     排序规则
	 * @param array  $returnField 指定返回的数组元素
	 *
	 * @return string|array
	 */
	public function getOne($conditions = [], $orderby = null, $returnField = null)
	{
		$more = [];

		if ($orderby)
		{
			$more['orderby'] = $orderby;
		}

		return $this->item($conditions, $more, $returnField);
	}

	/**
	 * 根据主键获取一项
	 *
	 * @param $id
	 *
	 * @return array
	 */
	public function getItem($id)
	{
		return $this->item([$this->tableid => $id]);
	}

	/**
	 * 获取表某个字段的最大值
	 *
	 * @param string $field
	 *
	 * @return integer
	 */
	public function getMaxId($field = null)
	{
		$field || $field = $this->tableid;
		$max = $this->single([], ['field' => "MAX({$field})"]);

		return (int) $max;
	}

	/**
	 * 获取自增表的最新一条数据
	 *
	 * @return array
	 */
	public function getLatest()
	{
		return $this->item([], ['orderby' => "{$this->tableid} DESC"]);
	}

	/**
	 * 聚合
	 *
	 * @param string $ele        聚合的元素
	 * @param array  $conditions 条件
	 * @param string $groupby    分组的元素
	 *
	 * @return integer
	 */
	public function getSum($ele, $conditions = [], $groupby = '')
	{
		$more = ['field' => "SUM({$ele})"];
		if ($groupby)
		{
			$more['groupby'] = $groupby;
		}

		$total = $this->single($conditions, $more);

		return (int) $total;
	}

	/**
	 * 获取符合条件的数目
	 *
	 * @param array  $conditions 条件
	 * @param string $field      统计字段
	 *
	 * @return integer
	 */
	public function getTotal($conditions = [], $field = '*')
	{
		$total = $this->single($conditions,
		                       [
			                       'field' => "COUNT({$field})",
		                       ]);

		return (int) $total;
	}

	/**
	 * 保存数据
	 *
	 * @param array   $data       数据
	 * @param array   $conditions 条件
	 * @param boolean $replace    当前数据是插入还是替换
	 *
	 * @return mixed(integer or boolean)
	 */
	public function save($data, $conditions = [], $replace = false)
	{
		if (empty($data))
		{
			return false;
		}

		return empty($data[$this->tableid]) && empty($conditions) ? $this->newItem($data,
		                                                                           $replace) : $this->updateItem($data,
		                                                                                                         $conditions);
	}

	/**
	 * 添加新记录
	 *
	 * @param array   $data     数据
	 * @param boolean $replace  当前数据是插入还是替换
	 * @param string  $delayed  延迟插入: LOW_PRIORITY | DELAYED
	 * @param boolean $assignId 是否强制指定ID
	 *
	 * @return integer 最后插入的数据的自增ID(或者指定ID)或者SQL语句产生数据的行数
	 */
	public function newItem($data, $replace = false, $delayed = '', $assignId = false)
	{
		$rep = $replace ? 'REPLACE' : 'INSERT';

		$rep .= ' ' . $delayed;
		if ($delayed && !in_array($delayed, ['LOW_PRIORITY', 'DELAYED']))
		{
			return 0;
		}

		if (!$replace && !$assignId)
		{
			unset($data[$this->tableid]);
		}


		$db = $this->getDB();
		$db->queryWrite("
			{$rep} INTO " . $this->getTablePrefix() . "{$this->table}
			SET
				" . $db->arrayToSQL($data) . "
		");
		if (!$this->isTrans()) {
			$this->backToPool($db);
		}
		if ($this->hasPrimaryKey)
		{
			return $db->insertId();
		}
		else
		{
			return $db->affectedRows();
		}
	}

	/**
	 * 添加新记录
	 *
	 * @param array   $data        数据
	 * @param array   $conditions  条件
	 * @param boolean $lowPriority Update low priority
	 *
	 * @return mixed(integer or void)
	 */
	public function updateItem($data, $conditions = [], $lowPriority = false)
	{
		if ($data[$this->tableid])
		{
			$id = $data[$this->tableid];
			unset($data[$this->tableid]);

			if (!in_array(($this->tableid), array_keys($conditions)))
			{
				$conditions[$this->tableid] = $id;
			}
		}

		$db = $this->getDB();
		$db->queryWrite("
			UPDATE " . ($lowPriority ? 'LOW_PRIORITY ' : '') . $this->getTablePrefix() . "{$this->table}
			SET " . $db->arrayToSQL($data) . $this->where($conditions));
		if (!$this->isTrans()) {
			$this->backToPool($db);
		}
		$affected = $db->affectedRows();

		//	An integer greater than zero indicates the number of rows affected or retrieved.
		//	Zero indicates that no records were updated for an UPDATE statement,
		//	no rows matched the WHERE clause in the query or that no query has yet been executed.
		//	-1 indicates that the query returned an error.
		if (!$affected)
		{
			$affected = PHP_INT_MAX;
		}
		else if (- 1 == $affected)
		{
			$affected = 0;
		}

		return $affected;
	}

	/**
	 * 添加新记录
	 *
	 * @param array $data       数据
	 * @param array $conditions 条件
	 *
	 * @return mixed(integer or void)
	 */
	public function updateItemLowPriority($data, $conditions = [])
	{
		return $this->updateItem($data, $conditions, true);
	}

	/**
	 * 删除记录
	 *
	 * @param array   $conditions 条件
	 * @param boolean $isflag     是否标记删除，默认为标记
	 *
	 * @return integer
	 */
	public function delete($conditions, $isflag = true)
	{
		// 不允许不带条件的删除
		if (empty($conditions))
		{
			return false;
		}

		$db = $this->getDB();

		// 是否标记为删除
		if ($isflag)
		{
			$db->queryWrite("UPDATE " . $this->getTablePrefix() . "{$this->table} SET deleted = 1" . $this->where($conditions));
		}
		else
		{
			$db->queryWrite("DELETE FROM " . $this->getTablePrefix() . "{$this->table}" . $this->where($conditions));
		}
		if (!$this->isTrans()) {
			$this->backToPool($db);
		}

		return $db->affectedRows();
	}

	/**
	 * 按照id删除记录
	 *
	 * @param integer $id     id
	 * @param boolean $isflag 是否标记删除，默认为标记
	 *
	 * @return integer
	 */
	public function deleteItem($id, $isflag = true)
	{
		return $this->delete([$this->tableid => $id], $isflag);
	}

	/**
	 * 设置错误信息
	 *
	 * @param integer $no  错误号
	 * @param string  $msg 错误信息
	 */
	protected function setErrorInfo($no, $msg)
	{
		$this->errormsg = $msg;
		$this->errorno  = (int) $no;
	}

	/**
	 * 获取错误号
	 *
	 * @return int
	 */
	public function getErrorno()
	{
		return $this->errorno;
	}

	/**
	 * 获取错误信息
	 *
	 * @return string
	 */
	public function getErrormsg()
	{
		return $this->errormsg;
	}

	/**
	 * 获取最后的sql
	 *
	 * @return int
	 */
	public function getLastSql()
	{
		return $this->lastsql;
	}

	/**
	 * 包含删除数据
	 *
	 * @return string
	 */
	public function withTrashed()
	{
		$this->withTrashed = true;
		return $this;
	}

	/**
	 * 生成WHERE字句
	 *
	 * @param array  $arr
	 * @param string $glue
	 * @param bool   $where
	 *
	 * @return string
	 */
	public function where(array $arr, $glue = 'AND', $where = true)
	{
		if (!$arr || !is_array($arr))
		{
			return '';
		}

		$sql = [];

		foreach ($arr as $key => $value)
		{
			$op = $this->getOperator($key);

			if ($op)
			{
				$key = trim(str_replace($op, '', $key));
				$op  = strtoupper($op);
			}

			if (is_array($value))
			{
				// 特例
				if ($op == 'BETWEEN')
				{
					$sql[] = "{$key} BETWEEN {$value[0]} AND {$value[1]}";
				}
				else
				{
					$op || $op = 'IN';
					$sql[] = "{$key} {$op} ('" . implode("', '", array_map([$this, 'escapeString'], $value)) . "')";
				}
			}
			else
			{
				// 如果$key是数字，则表示条件中含有"<>, !="等非等号(=)的判断
				// 比如： array('invalid' => 0, '1' => "image <> ''")
				// 这里的转义处理稍微复杂，如果需要转义，请在$arr传值之前处理。
				if (is_numeric($key))
				{
					// 不进行转义处理
					$sql[] = $value;
				}
				else
				{
					if ($op == 'IN' || $op == 'NOT IN' || $op == 'EXISTS' || $op == 'NOT EXISTS')
					{
						$sql[] = "{$key} {$op} ({$value})";
					}
					else if ($op == 'LIKE' || $op == 'NOT LIKE')
					{
						$sql[] = "{$key} {$op} " . $this->elike($value);
					}
					else
					{
						$op || $op = '=';

						$sql[] = "{$key} {$op} " . $this->e($value);
					}
				}
			}
		}

		return ($where ? ' WHERE' : '') . ' ' . implode(' ' . $glue . ' ', $sql);
	}

	/**
	 * Returns the SQL string operator
	 *
	 * @param    string
	 *
	 * @return    string
	 */
	public function getOperator($str)
	{
		$_operators = [
			'\s*(?:<|>|!)?=\s*',        // =, <=, >=, !=
			'\s*<>?\s*',                // <, <>
			'\s*>\s*',                  // >
			'\s+IS NULL',               // IS NULL
			'\s+IS NOT NULL',           // IS NOT NULL
			'\s+EXISTS',                // EXISTS(sql)
			'\s+NOT EXISTS',            // NOT EXISTS(sql)
			'\s+BETWEEN',               // BETWEEN value AND value
			'\s+IN',                    // IN(list)
			'\s+NOT IN',                // NOT IN (list)
			'\s+LIKE',                  // LIKE 'expr'
			'\s+NOT LIKE'               // NOT LIKE 'expr'
		];

		return preg_match('/' . implode('|', $_operators) . '/i', $str, $match) ? trim($match[0]) : false;
	}
}
