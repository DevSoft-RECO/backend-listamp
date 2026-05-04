<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'username',
        'email',
        'telefono',
        'id_agencia',
        'puesto',
        'avatar',
        'roles_list',
        'permissions_list',
        'jti',
    ];

    public $incrementing = true; // El ID ahora es autoincrementable para usuarios locales nuevos
    public $timestamps = false; // Sincronización manual en login


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        //
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles_list' => 'array',
            'permissions_list' => 'array',
        ];
    }

    /**
     * Get the agency associated with the user.
     */
    public function agencia()
    {
        return $this->belongsTo(Agencia::class, 'id_agencia');
    }

    /**
     * Verifica si el usuario tiene un rol específico.
     */
    public function hasRole($role)
    {
        if (is_array($role)) {
            return count(array_intersect($role, $this->roles_list ?? [])) > 0;
        }
        return in_array($role, $this->roles_list ?? []);
    }

    /**
     * Verifica si el usuario tiene un permiso específico.
     */
    public function hasPermission($permission)
    {
        if (is_array($permission)) {
            return count(array_intersect($permission, $this->permissions_list ?? [])) > 0;
        }
        return in_array($permission, $this->permissions_list ?? []);
    }
}
