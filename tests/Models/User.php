<?php

declare(strict_types=1);

namespace AhmedEssam\SubSphere\Tests\Models;

use AhmedEssam\SubSphere\Traits\HasSubscriptions;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasSubscriptions;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $attributes = [
        'password' => 'test-password',
    ];
}
