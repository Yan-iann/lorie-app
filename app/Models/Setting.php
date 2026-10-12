<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value settings from before accounts existed, when the app held a single
 * person's profile. Profiles now live on the users table; these rows are only
 * read once, by LegacyDataClaimer, to hand that profile to the first account.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
