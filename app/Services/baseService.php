<?php
namespace App\Services;

class baseService
{
    protected $pool;

    function __construct($pool){
        $this->pool = &$pool;
    }

    protected function getModel($model){
        if (!$this->$model) {
            $this->$model = new $model($this->pool);
        }
        return $this->$model;
    }
}
