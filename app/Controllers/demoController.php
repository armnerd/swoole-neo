<?php
namespace App\Controllers;
use App\Services\demoService;

class demoController extends baseController
{

    public function apiAction()
    {
        $res = [];
        $service = $this->getService(demoService::class);
        $res = $service->list();
        return $res;
    }

    public function taskAction()
    {
        $service = $this->getService(demoService::class);
        $res = $service->add();
        return true;
    }

    public function exceptionAction()
    {
        throw new \Exception('error message');
    }
}
