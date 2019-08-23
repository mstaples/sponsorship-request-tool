<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Condition extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'choice_id', 'question_question_id', 'question_condition_id', 'condition_state', 'condition_choice'
    ];

    public $primaryKey = 'choice_id';

    public function question()
    {
        return Question::find($this->question_question_id);
    }

    public function condition()
    {
        return Question::find($this->question_condition_id);
    }
}
