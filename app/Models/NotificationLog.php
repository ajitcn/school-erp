<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'sent_at' => 'datetime', 'read_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
