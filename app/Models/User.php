<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'premium_until' => 'datetime',
            'banned_at' => 'datetime',
        ];
    }


    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function isPremium(): bool
    {
        return $this->premium_until !== null && $this->premium_until->isFuture();
    }

    public function upgradeToPremium($months)
    {
        if ($this->premium_until !== null && $this->premium_until->isFuture()) {
            $this->premium_until = $this->premium_until->addMonths($months);
        } else {
            $this->premium_until = Carbon::now()->addMonths($months);
        }
        $this->save();
        return true;
    }

    public function isBanned()
    {
        return !is_null($this->banned_at);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function transactionsToday()
    {
        return $this->transactions()->whereDate('created_at', Carbon::today())->get();
    }
}
