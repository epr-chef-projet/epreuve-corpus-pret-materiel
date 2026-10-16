<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Accounts\Models;

use App\Modules\IdentityAccess\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $id
 * @property string $workshop_id
 * @property string $login
 * @property string $display_name
 * @property string $password
 * @property 'borrower'|'staff'|'manager' $role
 * @property string $workshop_name
 */
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;

    protected $table = 'identity_access_users';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['login', 'display_name', 'password'];

    /** @var list<string> */
    protected $hidden = ['password'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
