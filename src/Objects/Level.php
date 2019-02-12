<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Level extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'question_question_id', 'minimum', 'level'
    ];

    protected $attributes = [
        'minimum' => 0
    ];

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_question_id', 'question_id');
    }

}