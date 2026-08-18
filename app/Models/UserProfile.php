<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'firstname',
        'lastname',
        'middlename',
        'suffix',
        'salutation',
        'gender',
        'birthdate',
        'profile_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }
}
