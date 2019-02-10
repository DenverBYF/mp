<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    //
    public function Act()
    {
        return $this->belongsTo('App/Act');
    }
}
