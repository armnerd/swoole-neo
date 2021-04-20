<?php
namespace App\Services;
use App\Models\Products;

class demoService extends baseService
{

    public function list()
    {
        $res = [];
        $model = $this->getModel(Products::class);
        $res   = $model->list();
        return $res;
    }

    public function add()
    {
        $res = [];
        $model = $this->getModel(Products::class);
        $res   = $model->add();
        return $res;
    }
}
