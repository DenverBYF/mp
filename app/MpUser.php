<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MpUser extends Model
{
    //
    public function Acts()
    {
        return $this->hasMany('App/Act');
    }
}
