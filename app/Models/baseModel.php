<?php
namespace App\Models;
use Model;

class baseModel extends Model
{
    function __construct($pool){
        parent::__construct($pool);
    }
}