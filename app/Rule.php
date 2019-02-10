<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    //
    public function Act()
    {
        return $this->hasMany('App/Act');
    }
}
