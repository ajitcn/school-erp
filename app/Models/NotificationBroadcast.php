<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Keeps the author and recipient total for each manual in-app broadcast. */
class NotificationBroadcast extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
