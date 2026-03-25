<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteType extends Model
{
    protected $fillable = ['slug', 'name', 'color', 'sort_order'];
}
