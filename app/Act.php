<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Act extends Model
{
    //
    public function User()
    {
        return $this->belongsTo('App\MpUser','mp_user_id');
    }

    public function Rule()
    {
        return $this->belongsTo('App\Rule', 'rule_id');
    }

    public function Gift()
    {
        return $this->hasOne('App\Gift', 'id','gift_id');
    }
}
