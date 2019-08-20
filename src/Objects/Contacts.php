<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Contacts extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'choice_id', 'name', 'email', 'teamwork_id'
    ];

    protected $attributes = [
        'teamwork_id' => null
    ];
}
