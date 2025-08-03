<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModeUser extends Model
{
    use SoftDeletes;
    protected $table = 'mode_user';

}
