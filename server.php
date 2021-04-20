<?php
if(!isset($argv[1])){
    echo "type help for more info\n";die;
}
$command = $argv[1];
// command hub
switch ($command) {
    case 'start':
        // check the pid
        $pid = file_get_contents(__DIR__."/runtime/pid");
        if($pid){
            echo "The service already started\n";die;
        }
        start();
        break;
    case 'stop':
        shutdown();
        break;
    case 'restart':
        shutdown();
        start();
        break;
    case 'help':
        echo "avaliable command:\n";
        echo "-start\n";
        echo "-stop\n";
        echo "-restart\n";die;
        break;
    default:
        echo "type help for more info\n";die;
        break;
}

// start server
function start(){
    $command = "php ".__DIR__."/msgHub.php";
    echo $command."\n";
    system($command);
    return true;
}

// shutdown server
function shutdown(){
    // check the pid
    $pid = file_get_contents(__DIR__."/runtime/pid");
    if(!$pid){
        echo "The service has never been up\n";die;
    }
    // shutdown at first
    $shutdown = "kill ".$pid;
    echo $shutdown."\n";
    system($shutdown);
    // clean pid file
    file_put_contents(__DIR__."/runtime/pid", '');
    return true;
}