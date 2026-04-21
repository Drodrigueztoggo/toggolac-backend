<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'last_name',
        'personal_id',
        'driver_license_number',
        'phone_number',
        'address',
        'birth_date',
        'country_id',
        'state_id',
        'city_id',
        'postal_code',
        'gender',
        'bank_name',
        'account_number',
        'account_type_id',
        'routing_number',
        'is_premium',
        'image_user',
        'activation_token',
        'is_active'
    ];

    protected $appends = ['image'];

    public function getImageAttribute()
    {
        return isset($this->image_user) ? url('storage/' . $this->image_user) : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

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

    public function evaluations()
    {
        return $this->hasMany(EvaluationsPersonalShoper::class, 'user_id');
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'user_id')->where('reception_center_ok', 1);
    }

    public function totalCommissionsByDate($startDate, $endDate)
    {
        return $this->commissions()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
    }

    public function accountBank()
    {
        return $this->belongsTo(TypesBankAccount::class, 'account_type_id')->select('id', 'name');
    }
    public function countryInfo()
    {
        return $this->belongsTo(Country::class, 'country_id')->select('id', 'name', 'iso2');
    }

    public function stateInfo()
    {
        return $this->belongsTo(State::class, 'state_id')->select('id', 'name');
    }

    public function cityInfo()
    {
        return $this->belongsTo(City::class, 'city_id')->select('id', 'name');
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id')->select('id', 'name');
    }


    public function orders()
    {
        return $this->hasMany(PurchaseOrderHeader::class, 'client_id');
    }
    public function ordersCompletes()
    {
        return $this->hasMany(PurchaseOrderHeader::class, 'client_id')->where('purchase_status_id', 2);
    }

    public function ordersCompletesShopper()
    {
        return $this->hasMany(PurchaseOrderHeader::class, 'personal_shopper_id')->where('purchase_status_id', 2);
    }

   
}
