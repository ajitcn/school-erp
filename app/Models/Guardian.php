<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = ['password' => 'hashed'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class)->withPivot('is_primary')->withTimestamps();
    }

    public function userAccounts()
    {
        return $this->belongsToMany(User::class, 'guardian_user');
    }
}
