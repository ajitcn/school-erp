<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Records when a tenant user has read a published announcement. */
class NoticeRead extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function notice()
    {
        return $this->belongsTo(Notice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
