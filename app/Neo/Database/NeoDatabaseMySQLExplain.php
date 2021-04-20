<?php

/**
 * Class NeoDatabaseMySQLExplain
 * @package Neo\Database
 */
class NeoDatabaseMySQLExplain extends NeoDatabaseMySQL
{
	var $message = '';
	var $prev_messages = '';

	var $message_title = [];

	var $memory_before = [];

	var $time_before = [];

	var $time_total = 0;

	var $explain = true;

	/**
	 * @param $str
	 */
	private function output($str)
	{
		$this->message .= $str;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDBConnect($servername, $port = 3306, $userName = '', $password = '', $charset = 'utf8', $slave = false)
	{
		$this->timerStart("Connect to Database on Server: $servername");
		$return = parent::getDBConnect($servername, $port, $userName, $password, $charset);
		$this->timerStop();

		return $return;
	}

	/**
	 * @param null $link
	 * @param bool $buffered
	 *
	 * @return bool|\mysqli_result|mysqli_result
	 */
	public function &executeQuery($link = null, $buffered = true)
	{
		if ($link == $this->connection AND preg_match('#^\s*SELECT\s#s', $this->sql))
		{
			$this->explainQuery($link);
		}
		else
		{
			$this->output(trim((preg_match('#(\n\s+)(UPDATE|INSERT|REPLACE)\s#s',
			                                         $this->sql,
			                                         $match) ? str_replace($match[1],
			                                                               "\n",
			                                                               $this->sql) : $this->sql)) . "\n");
		}

		$this->timerStart('SQL Query');
		$return = parent::executeQuery($link, $buffered);
		$this->timerStop();

		return $return;
	}

	/**
	 * @param $link
	 */
	private function explainQuery(&$link)
	{
		$this->output(trim((preg_match('#(\n\s+)SELECT\s#s', $this->sql, $match) ? str_replace($match[1],
		                                                                                                 "\n",
		                                                                                                 $this->sql) : $this->sql)) . "\n");

		$results = mysqli_query($link, 'EXPLAIN ' . $this->sql);
		$this->output("\n");
		while ($field = mysqli_fetch_field($results))
		{
			$this->output($this->handleStr($field->name));
		}
		$this->output("\n");
		$numfields = mysqli_num_fields($results);
		while ($result = $this->fetchRow($results))
		{
			for ($i = 0; $i < $numfields; $i ++)
			{
				$this->output($this->handleStr($result["$i"]));
			}
			$this->output("\n");
		}
		$this->output("\n");
	}

	/**
	 * @param string $str
	 */
	private function timerStart($str = '')
	{
		$this->message_title[] = $str;

		if (function_exists('memory_get_usage'))
		{
			$this->memory_before[] = memory_get_usage();
		}
		$this->time_before[] = microtime();
	}

	/**
	 * @param bool $add_total
	 */
	private function timerStop($add_total = true)
	{
		$time_after = microtime();

		$pagestart = explode(' ', TIMESTART);
		$pagestart = $pagestart[0] + $pagestart[1];

		$time_before = explode(' ', array_pop($this->time_before));
		$time_before = $time_before[0] + $time_before[1] - $pagestart;

		$time_after = explode(' ', $time_after);
		$time_after = $time_after[0] + $time_after[1] - $pagestart;

		$time_taken = $time_after - $time_before;

		if ($add_total)
		{
			$this->time_total += $time_taken;
		}

		$this->output("Time Before: " . number_format($time_before, 5) . " seconds | ");
		$this->output("Time After: " . number_format($time_after, 5) . " seconds | ");
		$this->output("Time Taken: " . number_format($time_taken, 5) . " seconds | ");
		$this->output("Time Total: " . number_format($this->time_total, 5) . " seconds\n");

		if (function_exists('memory_get_usage'))
		{
			$memory_before = array_pop($this->memory_before);
			$memory_after  = memory_get_usage();

			$this->output("Memory Before: " . number_format($memory_before / 1024, 3) . " KB | ");
			$this->output("Memory After: " . number_format($memory_after / 1024, 3) . " KB | ");
			$this->output("Memory Used: " . number_format(($memory_after - $memory_before) / 1024, 3) . " KB\n");
		}

		if (sizeof($this->message_title) == 1)
		{
			$this->message = $this->prev_messages . "\n" . $this->message;
		}

		$output = array_pop($this->message_title) . "\n" . $this->message . "\n";

		if (sizeof($this->message_title) == 0)
		{
			echo $output;
			$this->prev_messages = '';
		}
		else
		{
			$this->prev_messages .= $output;
		}
		$this->message = '';

		flush();
		ob_flush();
	}

	/**
	 * @param string $str
	 */
	private function handleStr($str)
	{
		$str  = $str ?? ' ';
		$str .= " | ";
		return $str;
	}

	/**
	 * Closes the connection to the database server
	 *
	 * @return integer
	 */
	public function close()
	{
		parent::close();
		return 1;
	}
}