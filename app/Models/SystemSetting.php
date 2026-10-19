<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public const LOGIN_BACKGROUND = 'login_background';

    public const LANDING_HERO_IMAGE = 'landing_hero_image';

    protected $fillable = ['key', 'value'];
}
