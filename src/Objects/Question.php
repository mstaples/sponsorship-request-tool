<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Question extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [

        'question', 'page_page_id', 'question_id', 'url', 'prompt_type', 'prompt_subtype', 'min', 'max'

    ];

    protected $attributes = [
        'min' => null,
        'max' => null
    ];

    public $primaryKey = 'question_id';

    public function page()
    {
        return $this->belongsTo('App\Object\Page', 'page_page_id', 'page_id');
    }

    public function choices()
    {
        return $this->hasMany('App\Object\Choice');
    }

    public function levels()
    {
        return $this->hasMany('App\Object\Level');
    }

    public function getMaxValue()
    {
        // multiple choice
        if ($this->attributes['min'] === null) {
            $top = $this->choices()->orderBy('weight', 'desc')->firstOrFail();
            return $top->weight;
        }
        // slider
        return $this->levels()->count();
    }
}