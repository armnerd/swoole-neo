<?php
/**
 * log类
 */
class Log{
    
    public static function info($content){
       $content = '[info] ' . date('Y-m-d H:i:s', time()) . ' | ' . $content;
       file_put_contents(__DIR__."/../../runtime/log/info", $content."\n", FILE_APPEND);
       return true;
    }

    public static function error($content){
        $content = '[error] ' . date('Y-m-d H:i:s', time()) . ' | ' . $content;
        echo $content."\n";
        file_put_contents(__DIR__."/../../runtime/log/error", $content."\n", FILE_APPEND);
        return true;
    }
}
