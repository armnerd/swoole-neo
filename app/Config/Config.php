<?php

/**
 * 配置类
 */
class Config
{
    protected static $database;
    
	public static function getDatabase()
	{
        if (!self::$database) {
            $database = require(__DIR__."/database.php");
            self::$database = $database;
        }
        return self::$database;
	}
}
