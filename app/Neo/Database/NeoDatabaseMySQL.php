<?php

/**
 * Class to interface with a database
 *
 */
class NeoDatabaseMySQL extends NeoDatabase
{
	/**
	 * Array of constants for use in fetchArray
	 *
	 * @var    array
	 */
	protected $fetchtypes = [
		self::DBARRAY_NUM   => MYSQLI_NUM,
		self::DBARRAY_ASSOC => MYSQLI_ASSOC,
		self::DBARRAY_BOTH  => MYSQLI_BOTH
	];

	/**
	 * Database name
	 *
	 * @var    string
	 */
	protected $database = null;

	/**
	 * Link variable. The connection to the server.
	 *
	 * @var \mysqli
	 */
	protected $connection = null;

	/**
	 * Link variable. The connection to the slave/read server(s).
	 *
	 * @var    \mysqli
	 */
	protected $connection_slave = null;

	/**
	 * Link variable. The connection last used.
	 *
	 * @var     \mysqli
	 */
	protected $connection_recent = null;

	/**
	 * The contents of the most recent SQL query string.
	 *
	 * @var    string
	 */
	protected $sql = '';

	/**
	 * Whether or not to show and halt on database errors
	 *
	 * @var    boolean
	 */
	protected $reporterror = true;

	/**
	 * The text of the most recent database error message
	 *
	 * @var    string
	 */
	protected $error = '';

	/**
	 * The error number of the most recent database error message
	 *
	 * @var    integer
	 */
	protected $errno = '';

	/**
	 * SQL Query String
	 *
	 * @var    integer    The maximum size of query string permitted by the master server
	 */
	protected $maxpacket = 0;

	/**
	 * Track lock status of tables. True if a table lock has been issued
	 *
	 * @var    bool
	 */
	protected $locked = false;

	/**
	 * Number of queries executed
	 *
	 * @var    integer    The number of SQL queries run by the system
	 */
	protected $querycount = 0;

	/**
	 * Executed SQL queries
	 *
	 * @var    array    The array saved SQL queries run by the system
	 */
	protected $queryarray = [];

	/**
	 * Slave Database
	 * @var array
	 */
	public $slaveConfig = [];

	/**
	 * 是够强制走主库
	 *
	 * @var bool $fromMaster
	 */
	protected $fromMaster = false;

	/**
	 * NeoDatabaseMySQL constructor.
	 */
	public function __construct()
	{
		$this->trycatch = true;
	}

	/**
	 * Connects to the specified database server(s)
	 *
	 * @param string  $database   Name of the database that we will be using for selectDb()
	 * @param string  $servername Name of the server - should be either 'localhost' or an IP address
	 * @param integer $port       Port   for the server
	 * @param string  $username   Username to connect to the server
	 * @param string  $password   Password associated with the userName for the server
	 * @param string  $charset    Connection Charset MySQLi / PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	 *
	 */
	public function connect($database, $servername, $port, $username, $password, $charset = 'utf8')
	{
		$this->database = $database;

		$port = $port ? $port : 3306;

		$this->connection = $this->getDBConnect($servername, $port, $username, $password, $charset);

		if (!$this->connection)
		{
			throw new \Exception('No database connection. Please contact administrator for help.');
		}

		if (!empty($this->slaveConfig))
		{
			$this->connection_slave = $this->getDBConnect($this->slaveConfig['host'],
			                                              $port,
			                                              $username,
			                                              $password,
			                                              $charset);
		}

		$this->selectDB($this->database);

		if (empty($this->connection_slave))
		{
			$this->connection_slave =& $this->connection;
		}
		else
		{
			if (!@mysqli_select_db($this->connection_slave, $this->database))
			{
				throw new \Exception('No slave database connection.');
			}

		}
	}

	/**
	 * Initialize database connection(s)
	 *
	 * Connects to the specified master database server, and also to the slave server if it is specified
	 *
	 * @param string  $servername Name of the database server - should be either 'localhost' or an IP address
	 * @param integer $port       Port of the database server - usually 3306
	 * @param string  $userName   Username to connect to the database server
	 * @param string  $password   Password associated with the userName for the database server
	 * @param string  $charset    Mysqli Connection Charset PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	 *
	 * @return object  Mysqli Resource
	 */
	protected function getDBConnect($servername, $port = 3306, $userName = '', $password = '', $charset = 'utf8')
	{
		if (function_exists('catchDbError'))
		{
			set_error_handler('catchDbError');
		}

		$link = mysqli_init();

		// Fix server which do not support LOCAL
		// The used command is not allowed with this MySQL version
		@mysqli_options($link, MYSQLI_OPT_LOCAL_INFILE, true);
		@mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 2);

		// this will execute at most 5 times, see catchDbError()
		do
		{
			$connect = mysqli_real_connect($link, $servername, $userName, $password, '', $port);

		} while ($connect == false and $this->reporterror);

		restore_error_handler();

		if ($charset)
		{
			mysqli_set_charset($link, $charset);
		}

		return (!$connect) ? null : $link;
	}

	/**
	 * Selects a database to use
	 *
	 * @param string $database The name of the database located on the database server(s)
	 *
	 * @return boolean
	 */
	protected function selectDB($database = '')
	{
		if ($database != '')
		{
			$this->database = $database;
		}

		$this->connection_recent =& $this->connection;

		if ($check_write = @mysqli_select_db($this->connection, $this->database))
		{
			return true;
		}
		else
		{
			$this->trycatch = false;

			$this->halt('Cannot use database ' . $this->database);

			return false;
		}
	}

	/**
	 * Executes a data-writing SQL query through the 'master' database connection
	 *
	 * @param string  $sql      The text of the SQL query to be executed
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	 *
	 * @return \mysqli_result
	 * @throws Exception
	 */
	public function queryWrite($sql, $buffered = true)
	{
		$this->sql = &$sql;

		return $this->executeQuery($this->connection, $buffered);
	}

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param string  $sql      The text of the SQL query to be executed
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	 *
	 * @return \mysqli_result
	 */
	public function queryRead($sql, $buffered = true)
	{
		$this->sql = &$sql;

		$connection = $this->fromMaster ? $this->connection : $this->connection_slave;

		return $this->executeQuery($connection, $buffered);
	}

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param string  $sql      The text of the SQL query to be executed
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	 *
	 * @return \mysqli_result
	 */
	public function queryReadSlave($sql, $buffered = true)
	{
		return $this->queryRead($sql, $buffered);
	}

	/**
	 * Executes a data-reading SQL query, then returns an object of the data from the first row from the result set
	 *
	 * @param string $sql   The text of the SQL query to be executed
	 * @param string $class Object Name
	 *
	 * @return array
	 */
	public function queryObjectFirst($sql, $class = "")
	{
		$this->sql   = &$sql;
		$queryresult = $this->executeQuery($this->connection);
		$returnarray = $this->fetchObject($queryresult, $class);
		$this->freeResult($queryresult);

		return $returnarray;
	}

	/**
	 * Executes a data-reading SQL query, then returns an object of the data from the first row from the result set
	 *
	 * @param string $sql   The text of the SQL query to be executed
	 * @param string $class Object Name
	 *
	 * @return array
	 */
	public function queryObjectFirstSlave($sql, $class = "")
	{
		$this->sql   = &$sql;
		$queryresult = $this->queryReadSlave($sql);
		$returnarray = $this->fetchObject($queryresult, $class);
		$this->freeResult($queryresult);

		return $returnarray;
	}

	/**
	 * Executes a data-reading SQL query, then returns first data from the first row from the result set
	 *
	 * @param string  $sql  The text of the SQL query to be executed
	 * @param integer $type One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	 *
	 * @return mixed(one var)
	 */
	public function queryOne($sql, $type = self::DBARRAY_NUM)
	{
		list($one) = $this->queryFirst($sql, $type);

		return $one;
	}

	/**
	 * Executes a data-reading SQL query, then returns first data from the first row from the result set
	 *
	 * @param string  $sql  The text of the SQL query to be executed
	 * @param integer $type One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	 *
	 * @return mixed(one var)
	 */
	public function queryOneSlave($sql, $type = self::DBARRAY_NUM)
	{
		list($one) = $this->queryFirstSlave($sql, $type);

		return $one;
	}

	/**
	 * Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	 *
	 * @param string  $sql  The text of the SQL query to be executed
	 * @param integer $type $type One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	 *
	 * @return array
	 */
	public function queryFirst($sql, $type = self::DBARRAY_ASSOC)
	{
		$this->sql   = &$sql;
		$queryresult = $this->executeQuery($this->connection);
		$returnarray = $this->fetchArray($queryresult, $type);
		$this->freeResult($queryresult);

		return $returnarray;
	}

	/**
	 * Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	 *
	 * @param string  $sql  The text of the SQL query to be executed
	 * @param integer $type $type One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	 *
	 * @return array
	 */
	public function queryFirstSlave($sql, $type = self::DBARRAY_ASSOC)
	{
		$this->sql   = &$sql;
		$queryresult = $this->queryReadSlave($sql);
		$returnarray = $this->fetchArray($queryresult, $type);
		$this->freeResult($queryresult);

		return $returnarray;
	}

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param string  $sql      The text of the SQL query to be executed
	 * @param string  $element  array element, if null,return all element in row
	 * @param string  $key      array key
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	 *
	 * @return array
	 */
	public function queryArray($sql, $element = null, $key = null, $buffered = true)
	{
		$rows = $this->queryRead($sql, $buffered);

		return $this->_processArray($rows, $key, $element);
	}

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param string  $sql      The text of the SQL query to be executed
	 * @param string  $element  array element, if null,return all element in row
	 * @param string  $key      array key
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	 *
	 * @return array
	 */
	public function queryArraySlave($sql, $element = null, $key = null, $buffered = true)
	{
		$rows = $this->queryReadSlave($sql, $buffered);

		return $this->_processArray($rows, $key, $element);
	}

	/**
	 * @param \mysqli_result $rows
	 * @param string         $element array element, if null,return all element in row
	 * @param string         $key     array key
	 *
	 * @return array
	 */
	private function _processArray($rows, $key, $element)
	{
		$data = $this->fetchAllArray($rows);
		$this->freeResult($rows);

		$element || $element = null;
		$key || $key = null;

		return array_column($data, $element, $key);
	}

	/**
	 * Executes an INSERT INTO query, using extended inserts if possible
	 *
	 * @param string  $table    Name of the table into which data should be inserted
	 * @param string  $fields   Comma-separated list of the fields to affect
	 * @param array   $values   Array of SQL values
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	 *
	 * @return mixed
	 */
	public function insert($table, $fields, $values, $buffered = true)
	{
		return $this->insertMultiple("INSERT INTO $table ($fields) VALUES", $values, $buffered);
	}

	/**
	 * Executes a REPLACE INTO query, using extended inserts if possible
	 *
	 * @param string  $table    Name of the table into which data should be inserted
	 * @param string  $fields   Comma-separated list of the fields to affect
	 * @param array   $values   Array of SQL values
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	 *
	 * @return mixed
	 */
	public function queryReplace($table, $fields, $values, $buffered = true)
	{
		return $this->insertMultiple("REPLACE INTO $table ($fields) VALUES", $values, $buffered);
	}

	/**
	 * Executes an INSERT or REPLACE query with multiple values, splitting large queries into manageable chunks based on $this->maxpacket
	 *
	 * @param string  $sql      The text of the first part of the SQL query to be executed - example "INSERT INTO table (field1, field2) VALUES"
	 * @param mixed   $values   The values to be inserted. Example: (0 => "('value1', 'value2')", 1 => "('value3', 'value4')")
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	 *
	 * @return mixed
	 */
	public function insertMultiple($sql, $values, $buffered = true)
	{
		$data = [];
		foreach ($values as $value)
		{
			$data[] = '(' . implode(',', array_map([$this, 'e'], $value)) . ')';
		}

		$this->sql = $sql . ' ' . implode(', ', $data);

		$this->executeQuery($this->connection, $buffered);

		return $this->affectedRows();
	}

	/**
	 * Returns the number of rows contained within a query result set
	 *
	 * @param \mysqli_result $queryresult The query result ID we are dealing with
	 *
	 * @return integer
	 */
	public function numRows($queryresult)
	{
		return @mysqli_num_rows($queryresult);
	}

	/**
	 * Executes a FOUND_ROWS query to get the results of SQL_CALC_FOUND_ROWS
	 *
	 * @return    integer
	 */
	public function foundRows()
	{
		$this->sql   = "SELECT FOUND_ROWS()";
		$queryresult = $this->executeQuery($this->connection_recent);
		$returnarray = $this->fetchArray($queryresult, self::DBARRAY_NUM);
		$this->freeResult($queryresult);

		return intval($returnarray[0]);
	}

	/**
	 * Returns the ID of the item just inserted into an auto-increment field
	 *
	 * @return integer
	 */
	public function insertId()
	{
		return @mysqli_insert_id($this->connection);
	}

	/**
	 * Returns the name of the character set
	 *
	 * @return string
	 */
	protected function clientEncoding()
	{
		return @mysqli_character_set_name($this->connection);
	}

	/**
	 * Closes the connection to the database server
	 *
	 * @return integer
	 */
	public function close()
	{
		@mysqli_close($this->connection);
		@mysqli_close($this->connection_slave);

		return 1;
	}

	/**
	 * Escapes a string to make it safe to be inserted into an SQL query
	 *
	 * @param string $string The string to be escaped
	 *
	 * @return string
	 */
	public function e($string)
	{
		return "'" . $this->escapeString($string) . "'";
	}

	/**
	 * Escapes a string to make it safe to be inserted into an SQL query
	 *
	 * @param string $string The string to be escaped
	 *
	 * @return string
	 */
	public function elike($string)
	{
		return "'%" . $this->escapeStringLike($string) . "%'";
	}

	/**
	 * Escapes a string to make it safe to be inserted into an SQL query
	 *
	 * @param string $string The string to be escaped
	 *
	 * @return string
	 */
	public function escapeString($string)
	{
		return mysqli_real_escape_string($this->connection, $string);
	}

	/**
	 * Escapes a string using the appropriate escape character for the RDBMS for use in LIKE conditions
	 *
	 * @param string $string The string to be escaped
	 *
	 * @return string
	 */
	public function escapeStringLike($string)
	{
		return str_replace([
			                   '%',
			                   '_'
		                   ],
		                   [
			                   '\%',
			                   '\_'
		                   ],
		                   $this->escapeString($this->htmlSpecialcharsUni($string)));
	}

	/**
	 * Executes an SQL query through the specified connection
	 *
	 * @param \mysqli $link     The connection ID to the database server
	 * @param boolean $buffered Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	 *
	 * @return \mysqli_result|bool
	 * @throws Exception
	 */
	protected function executeQuery($link = null, $buffered = true)
	{
		$this->connection_recent =& $link;

		// 强制从主库查询
		if ($this->fromMaster && $this->sql[0] != '/')
		{
			$this->sql = '/*FORCE_MASTER*/ ' . $this->sql;
		}

		$this->querycount ++;
		$this->queryarray[] = htmlentities($this->sql);

		if ($queryresult = mysqli_query($link, $this->sql, ($buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT)))
		{
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			$this->halt();

			return false;
		}
	}

	/**
	 * Execute the query
	 *
	 * @param string $sql the SQL query
	 *
	 * @return \mysqli_result|bool
	 */
	public function _execute($sql)
	{
		return @mysqli_query($this->connection, $sql);
	}

	/**
	 * Fetches a row from a query result and returns the values from that row as an array
	 *
	 * The value of $type defines whether the array will have numeric or associative keys, or both
	 *
	 * @param \mysqli_result $queryresult The query result ID we are dealing with
	 * @param integer        $type        One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	 *
	 * @return array
	 */
	public function fetchArray($queryresult, $type = self::DBARRAY_ASSOC)
	{
		return @mysqli_fetch_array($queryresult, $this->fetchtypes["$type"]);
	}

	/**
	 * Fetches a row from a query result and returns the values from that row as an array
	 *
	 * The value of $type defines whether the array will have numeric or associative keys, or both
	 *
	 * @param \mysqli_result $queryresult The query result ID we are dealing with
	 * @param integer        $type        One of self::DBARRAY_ASSOC / self::DBARRAY_NUM / self::DBARRAY_BOTH
	 *
	 * @return array
	 */
	public function fetchAllArray($queryresult, $type = self::DBARRAY_ASSOC)
	{
		return @mysqli_fetch_all($queryresult, $this->fetchtypes["$type"]);
	}

	/**
	 * Fetches a row from a query result and returns the values from that row as an object
	 *
	 * The value of $class defines whether the object is a class
	 *
	 * @param \mysqli_result $queryresult The query result ID we are dealing with
	 * @param string         $class       Object Name
	 *
	 * @return array
	 */
	public function fetchObject($queryresult, $class = "")
	{
		if (empty($class))
		{
			return @mysqli_fetch_object($queryresult);
		}
		else
		{
			return @mysqli_fetch_object($queryresult, $class);
		}
	}

	/**
	 * Fetches a row from a query result and returns the values from that row as an array with numeric keys
	 *
	 * @param \mysqli_result $queryresult The query result ID we are dealing with
	 *
	 * @return array
	 */
	public function fetchRow($queryresult)
	{
		return @mysqli_fetch_row($queryresult);
	}

	/**
	 * Frees all memory associated with the specified query result
	 *
	 * @param \mysqli_result $queryresult The query result ID we are dealing with
	 *
	 * @return boolean
	 */
	public function freeResult($queryresult)
	{
		$this->sql = '';

		return @mysqli_free_result($queryresult);
	}

	/**
	 * Retuns the number of rows affected by the most recent insert/replace/update query.
	 *
	 * An integer greater than zero indicates the number of rows affected or retrieved.
	 * Zero indicates that no records were updated for an UPDATE statement,
	 * no rows matched the WHERE clause in the query or that no query has yet been executed.
	 * -1 indicates that the query returned an error.
	 *
	 * @return integer
	 */
	public function affectedRows()
	{
		$this->rows = mysqli_affected_rows($this->connection_recent);

		return $this->rows;
	}

	/**
	 * Lock tables
	 *
	 * @param mixed $tablelist List of tables to lock
	 *
	 */
	public function lockTables($tablelist)
	{
		if (!empty($tablelist) and is_array($tablelist))
		{
			$sql = '';
			foreach ($tablelist as $name => $type)
			{
				$sql .= (!empty($sql) ? ', ' : '') . DB_TBPREFIX . $name . " " . $type;
			}

			$this->queryWrite("LOCK TABLES $sql");
			$this->locked = true;
		}
	}

	/**
	 * Unlock tables
	 *
	 */
	public function unlockTables()
	{
		# must be called from exec_shutdown as tables can get stuck locked if pconnects are enabled
		# note: the above case never actually happens as we skip the lock if pconnects are enabled (to be safe) =)
		if ($this->locked)
		{
			$this->queryWrite("UNLOCK TABLES");
		}
	}

	/**
	 * Returns the text of the error message from previous database operation
	 *
	 * @return string
	 */
	public function getError()
	{
		if ($this->connection_recent === null)
		{
			$this->error = '';
		}
		else
		{
			$this->error = mysqli_error($this->connection_recent);
		}

		return $this->error;
	}

	/**
	 * Returns the numerical value of the error message from previous database operation
	 *
	 * @return integer
	 */
	public function getErrno()
	{
		if ($this->connection_recent === null)
		{
			$this->errno = 0;
		}
		else
		{
			$this->errno = mysqli_errno($this->connection_recent);
		}

		return $this->errno;
	}

	/**
	 * Switches database error display ON
	 */
	public function showErrors()
	{
		$this->reporterror = true;
	}

	/**
	 * Switches database error display OFF
	 */
	public function hideErrors()
	{
		$this->reporterror = false;
	}

	/**
	 * Halts execution of the entire system and displays an error message
	 *
	 * @param string $errortext Text of the error message. Leave blank to use $this->sql as error text.
	 *
	 */
	public function halt($errortext = '')
	{
		if (!$this->reporterror)
		{
			if ($errortext)
			{
				$this->error = $errortext;
			}

			return;
		}

		$errortext = $errortext ?: preg_replace('/\r|\n/', ' ', trim($this->sql));

		$this->sql = '';

		$error = $this->getError();
		$errno = $this->getErrno();

		if ($this->trans)
		{
			$this->transRollback();
		}

		$message = 'Error SQL: ' . $errortext . PHP_EOL . 'Error No.: ' . $errno . PHP_EOL . 'Error: ' . $error . PHP_EOL;
		Log::error($message);

		// 抛出
		if ($this->trycatch)
		{
			throw new \Exception($message);
		}
		else
		{
			$this->close();
		}
	}

	/**
	 * Returns an array of the fields in given table name.
	 *
	 * @param string $table Name of database table to inspect
	 *
	 * @return array Fields in table. Keys are name and type
	 */
	public function describeDBTable($table)
	{
		$fields = [];

		$cols = $this->queryRead('DESCRIBE ' . $table);
		while ($column = $this->fetchArray($cols))
		{
			if ($column['Field'] != $table . 'id' && $column['Field'] != 'deleted')
			{
				$fields[$column['Field']] = preg_replace('#\(.*\)#', '', $column['Type']);
			}
		}
		$this->freeResult($cols);
		unset($cols);

		return $fields;
	}

	/**
	 * @return int
	 */
	public function getQueryCount()
	{
		return $this->querycount;
	}

	/**
	 * @return array
	 */
	public function getQueryArray()
	{
		return [];
	}

	/**
	 * 在主数据库上开启事务
	 */
	public function transStart()
	{
		$this->fromMaster = true;
		$this->trans      = true;

		return mysqli_begin_transaction($this->connection);
	}

	/**
	 * 在主数据库上回滚事务
	 */
	public function transRollback()
	{
		$this->fromMaster = false;
		$this->trans      = false;

		return mysqli_rollback($this->connection);
	}

	/**
	 * 在主数据库上提交事务
	 */
	public function transComplete()
	{
		$this->fromMaster = false;
		$this->trans      = false;

		return mysqli_commit($this->connection);
	}

	/**
	 * 设置是否从主数据库获取数据
	 *
	 * @param bool $fromMaster
	 */
	public function setFromMaster($fromMaster = false)
	{
		$this->fromMaster = $fromMaster;
	}

	/**
	 * 是否从主数据库获取数据
	 *
	 * @return bool
	 */
	public function getFromMaster()
	{
		return $this->fromMaster;
	}

	/**
	 * 是否有效连接
	 *
	 * @return bool
	 */
	public function is_connected(){
		return @mysqli_ping($this->connection);
	}

	/**
	 * 将数组转化为sql字符串, 当为where子句时，请将$join赋值为“AND”
	 *
	 * 如果数组的值是“xxx <> yyy”的样式，请预先对字符串进行转义处理。
	 *
	 * @param array  $arr
	 * @param string $glue
	 *
	 * @return string
	 */
	public function arrayToSQL($arr, $glue = ',')
	{
		$sql = [];

		foreach ($arr as $key => $value)
		{

			// WHERE 字句可能有IN的情况
			if ("AND" == $glue && is_array($value))
			{
				$sql[] = $key . " IN('" . implode("', '", array_map([$this, 'escapeString'], $value)) . "')";
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
					$sql[] = $key . " = " . $this->e($value);
				}
			}
		}

		return ' ' . implode(' ' . $glue . ' ', $sql);
	}
}