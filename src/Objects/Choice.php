<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Choice extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'question_question_id', 'choice_id', 'choice', 'weight'
    ];

    protected $attributes = [
        'weight' => 1
    ];

    public $primaryKey = 'choice_id';

    public function question()
    {
        return $this->belongsTo('App\Object\Question', 'question_question_id', 'question_id');
    }
}
