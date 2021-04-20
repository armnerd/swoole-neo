<?php
namespace App\Models;

class Products extends baseModel
{
    public $id;
    public $name;

    public function list(){
        $res = [];
        $res = $this->getAll();
        return $res;
    }

    public function add(){
        return $this->newItem(['name'  => "neo:" . date('Y-m-d H:i:s')]);
    }
}