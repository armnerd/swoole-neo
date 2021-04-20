<?php
namespace App\Controllers;
use Pool;

class baseController
{
    protected $request;
    protected $pool;

    function __construct($data){
        $this->request = $data;
        $explain = isset($data['get']['explain']) ?? false;
        $this->pool = new Pool($explain);
    }
    
    protected function getService($service){
        if (!$this->$service) {
            $this->$service = new $service($this->pool);
        }
        return $this->$service;
    }

    protected function apiOnly(){
        $mission = $this->request['mission'];
        if ($mission != 'api'){
            throw new \Exception('api only!');
        }
    }

    protected function taskOnly(){
        $mission = $this->request['mission'];
        if ($mission != 'task'){
            throw new \Exception('task only!');
        }
    }
}
