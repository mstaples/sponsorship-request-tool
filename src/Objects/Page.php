<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Page extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [

        'survey_id', 'page_id', 'url', 'conference', 'hackathon', 'event', 'minimum', 'data'

    ];

    protected $attributes = [
        'conference' => true,
        'hackathon' => true,
        'event' => true,
        'minimum' => false,
        'data' => false
    ];

    public $primaryKey = 'page_id';

    public function questions()
    {
        return $this->hasMany('App\Object\Question');
    }
}