<?php

/**
 * Class to interface with a database
 *
 */
abstract class NeoDatabase
{
	/**
	 * The type of result set to return from the database for a specific row.
	 */
	const DBARRAY_BOTH = 0;
	const DBARRAY_ASSOC = 1;
	const DBARRAY_NUM = 2;

	/**
	 * Database name
	 *
	 * @var    string
	 */
	protected $database = null;

	/**
	 * Link variable. The connection to the server.
	 *
	 * @var    string
	 */
	protected $connection = null;

	/**
	 * Link variable. The connection to the slave/read server(s).
	 *
	 * @var    string
	 */
	protected $connection_slave = null;

	/**
	 * Link variable. The connection last used.
	 *
	 * @var     string
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
	 * Affected rows
	 *
	 * @var int
	 */
	protected $rows;

	/**
	 * MySQL事务状态 是否开始
	 * @var bool
	 */
	protected $trans = false;

	/**
	 * 使用try...catch抛出
	 * @var bool
	 */
	protected $trycatch = false;


	/**
	 * Constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * Connects to the specified database server(s)
	 *
	 * @param    string  $database   Name of the database that we will be using for selectDb()
	 * @param    string  $servername Name of the server - should be either 'localhost' or an IP address
	 * @param    integer $port       Port for the server
	 * @param    string  $username   Username to connect to the server
	 * @param    string  $password   Password associated with the userName for the server
	 * @param    string  $charset    (Optional) Connection Charset MySQLi / PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	 *
	 * @return    void
	 */
	public function connect($database, $servername, $port, $username, $password, $charset = '')
	{
	}

	/**
	 * Initialize database connection(s)
	 *
	 * Connects to the specified master database server, and also to the slave server if it is specified
	 *
	 * @param    string $servername Name of the database server - should be either 'localhost' or an IP address
	 *
	 * @return    boolean
	 */
	abstract protected function getDBConnect($servername);

	/**
	 * Executes an SQL query through the specified connection
	 *
	 * @param    string $link The connection ID to the database server
	 *
	 * @return    string
	 */
	abstract protected function executeQuery($link = null);

	/**
	 * Simple Query
	 * This is a simplified version of the query() function.  Internally
	 * we only use it when running transaction commands since they do
	 * not require all the features of the main query() function.
	 *
	 * @param    string $sql the sql query
	 *
	 * @return    mixed
	 */
	public function simpleQuery($sql)
	{
		return $this->_execute($sql);
	}

	/**
	 * Execute the query
	 *
	 * @param    string $sql the SQL query
	 *
	 * @return    resource|null
	 */
	public function _execute($sql)
	{
		return null;
	}

	/**
	 * Executes a data-writing SQL query through the 'master' database connection
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    string
	 */
	abstract public function queryWrite($sql);

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    string
	 */
	abstract public function queryRead($sql);

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    string
	 */
	abstract public function queryReadSlave($sql);

	/**
	 * Executes a data-reading SQL query, then returns an object of the data from the first row from the result set
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    array
	 */
	abstract public function queryObjectFirst($sql);

	/**
	 * Executes a data-reading SQL query, then returns an object of the data from the first row from the result set
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    array
	 */
	abstract public function queryObjectFirstSlave($sql);

	/**
	 * Executes a data-reading SQL query, then returns first data from the first row from the result set
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    mixed(one var)
	 */
	abstract public function queryOne($sql);

	/**
	 * Executes a data-reading SQL query, then returns first data from the first row from the result set
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    mixed(one var)
	 */
	abstract public function queryOneSlave($sql);

	/**
	 * Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    array
	 */
	abstract public function queryFirst($sql);

	/**
	 * Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	 *
	 * @param    string $sql The text of the SQL query to be executed
	 *
	 * @return    array
	 */
	abstract public function queryFirstSlave($sql);

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param    string $sql     The text of the SQL query to be executed
	 * @param    string $element array element, if null,return all element in row
	 * @param    string $key     array key
	 *
	 * @return    array
	 */
	abstract public function queryArray($sql, $element = null, $key = null);

	/**
	 * Executes a data-reading SQL query through the 'master' database connection
	 * we don't know if the 'read' database is up to date so be on the safe side
	 *
	 * @param    string $sql     The text of the SQL query to be executed
	 * @param    string $element array element, if null,return all element in row
	 * @param    string $key     array key
	 *
	 * @return    array
	 */
	abstract public function queryArraySlave($sql, $element = null, $key = null);

	/**
	 * Returns the number of rows contained within a query result set
	 *
	 * @param    string $queryresult The query result ID we are dealing with
	 *
	 * @return    integer
	 */
	abstract public function numRows($queryresult);

	/**
	 * Returns the ID of the item just inserted into an auto-increment field
	 *
	 * @return    integer
	 */
	abstract public function insertId();

	/**
	 * Returns the name of the character set
	 *
	 * @return    string
	 */
	abstract protected function clientEncoding();

	/**
	 * Closes the connection to the database server
	 *
	 * @return    integer
	 */
	abstract public function close();

	/**
	 * Escapes a string to make it safe to be inserted into an SQL query
	 *
	 * @param    string $string The string to be escaped
	 *
	 * @return    string
	 */
	abstract public function escapeString($string);

	/**
	 * Escapes a string to make it safe to be inserted into an SQL query
	 *
	 * @param    string $string The string to be escaped
	 *
	 * @return    string
	 */
	abstract public function e($string);

	/**
	 * Escapes a string using the appropriate escape character for the RDBMS for use in LIKE conditions
	 *
	 * @param    string $string The string to be escaped
	 *
	 * @return    string
	 */
	abstract public function escapeStringLike($string);

	/**
	 * Fetches a row from a query result and returns the values from that row as an array
	 *
	 * The value of $type defines whether the array will have numeric or associative keys, or both
	 *
	 * @param    string $queryresult The query result ID we are dealing with
	 *
	 * @return    array
	 */
	abstract public function fetchArray($queryresult);

	/**
	 * Frees all memory associated with the specified query result
	 *
	 * @param    string $queryresult The query result ID we are dealing with
	 *
	 * @return    boolean
	 */
	abstract public function freeResult($queryresult);

	/**
	 * Retuns the number of rows affected by the most recent insert/replace/update query
	 *
	 * @return    integer
	 */
	abstract public function affectedRows();

	/**
	 * Lock tables
	 *
	 * @param    mixed $tablelist List of tables to lock
	 *
	 */
	public function lockTables($tablelist)
	{
		if (!empty($tablelist) and is_array($tablelist))
		{
			$sql = '';
			foreach ($tablelist as $name => $type)
			{
				$sql .= (!empty($sql) ? ', ' : '') . DB_TBPREFIX . $name . ' ' . $type;
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
	 * @return    string
	 */
	abstract public function getError();

	/**
	 * Returns the numerical value of the error message from previous database operation
	 *
	 * @return    integer
	 */
	abstract public function getErrno();

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
	 * @param    string $errortext Text of the error message. Leave blank to use $this->sql as error text.
	 *
	 */
	abstract public function halt($errortext = '');

	/**
	 * 将数组转化为sql字符串, 当为where子句时，请将$join赋值为“AND”
	 *
	 * 如果数组的值是“xxx <> yyy”的样式，请预先对字符串进行转义处理。
	 *
	 * @param array  $arr  Sub SQL
	 * @param string $glue Separator
	 *
	 * @return string
	 */
	abstract public function arrayToSQL($arr, $glue = ',');

	abstract public function getQueryCount();

	abstract public function getQueryArray();

	/**
	 * translates all non-unicode entities
	 *
	 * @param string  $text     Text to translate
	 * @param boolean $entities Special char
	 *
	 * @return string
	 */
	protected function htmlSpecialcharsUni($text, $entities = true)
	{
		// preg_replace: translates all non-unicode entities
		// replace special html characters
		return str_replace([
			                   '<',
			                   '>',
			                   '"'
		                   ],
		                   [
			                   '&lt;',
			                   '&gt;',
			                   '&quot;'
		                   ],
		                   preg_replace('/&(?!' . ($entities ? '#[0-9]+|shy' : '(#[0-9]+|[a-z]+)') . ';)/si',
		                                '&amp;',
		                                $text));
	}

	/**
	 * Tests whether the string has an SQL operator
	 *
	 * @param    string
	 *
	 * @return    bool
	 */
	public function hasOperator($str)
	{
		return (bool) preg_match('/(<|>|!|=|\sIS NULL|\sIS NOT NULL|\sEXISTS|\sBETWEEN|\sLIKE|\sIN\s*\(|\s)/i',
		                         trim($str));
	}

	/**
	 * 是否启动try...catch
	 *
	 * @param bool $trycatch
	 */
	public function setTrycatch($trycatch = false)
	{
		$this->trycatch = $trycatch;
	}
}