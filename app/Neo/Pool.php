<?php

/**
 * 连接池
 *
 */
class Pool
{

	/**
	 * 数据库单例
	 *
	 */
	private $db;

	/**
	 * 是否开启事务
	 *
	 */
	private $trans = false;

	/**
	 * 数据库连接池
	 *
	 */
	private $pool = [];

	/**
	 * 数据库连接实例数量
	 *
	 */
	private $instant_num = 0;

	/**
	 * 数据库连接池中的实例数量
	 *
	 */
	private $in_pool_num = 0;

	/**
	 * 是否在终端显示explain
	 *
	 * @var boolean
	 */
	private $explain;

	function __construct($explain){
		$this->explain = $explain;
	}
	
	/**
	 * 获取数据库连接
	 *
	 */
	public function getDB($init = true)
	{
		// 开启事务时，返回单例
		if ($this->trans){
			return $this->transDB($init);
		}

		// 从连接池中获取
		if (empty($this->pool)) {
			$create = $this->initDatabase();
			$this->instant_num += 1;
			echo "new db instant for empty pool\n";
			return $create;
		}

		$instant = array_pop($this->pool);
		$this->in_pool_num -= 1;

		// 检查有效性
		if (!$instant || !$instant instanceof NeoDatabase){
			$create = $this->initDatabase();
			return $create;
		}
		
		// 检查是否有效链接
		$is_connected = $instant->is_connected();
		if (!$is_connected)
		{
			$create = $this->initDatabase();
			echo "new db instant for loss connect\n";
			return $create;
		}

		return $instant;
	}

	/**
	 * 数据库操作后归还实例到连接池
	 *
	 */
	public function backToPool($instant){
		$this->pool[] = $instant;
		$this->in_pool_num += 1;
		echo "connect num = " . $this->instant_num . " & in pool num = " . $this->in_pool_num . "\n";
		return true;
	}

	/**
	 * 事务开关
	 *
	 */
	public function transToggle($is_trans)
	{
		$this->trans = $is_trans;
		return true;
	}

	/**
	 * 是否事务中
	 *
	 */
	public function isTrans()
	{
		return $this->trans;
	}

	/**
	 * 获取单例数据库实例
	 *
	 */
	private function transDB($init = true)
	{
		if ($init && (!$this->db || !$this->db instanceof NeoDatabase))
		{
			$this->db = $this->initDatabase();
		}

		return $this->db;
	}

	/**
	 *
	 * 初始化数据库连接
	 *
	 * @param string  $type      主从数据库
	 * @param boolean $withSlave 是否启用从数据库
	 *
	 * @return Neo\Database\NeoDatabaseMySQL
	 */
	private function initDatabase($type = 'master', $withSlave = true){
		$database = Config::getDatabase();
		// 主库配置
		$config = $database['master'];
		// 从库配置
		$slaveConfig = $database['slave'];
		// 是否在终端输出Explain
		$explain = $this->explain;

		if ($explain)
		{
			$db = new NeoDatabaseMySQLExplain();
		}
		else
		{
			$db = new NeoDatabaseMySQL();
		}

		// 从库
		if ($withSlave && $slaveConfig)
		{
			// 从库配置
			$db->slaveConfig = $slaveConfig;
		}
		
		// 创建数据库连接
		$db->connect($config['database'],
					 $config['host'],
					 $config['port'],
					 $config['user'],
					 $config['password'],
					 $config['charset']);

		return $db;
	}
}
