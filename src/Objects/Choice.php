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
        'question_question_id', 'choice', 'weight', 'choice_id'
    ];

    protected $attributes = [
        'weight' => 1
    ];

    // Eloquent will auto-cast keys as ints if this is not definedv
    protected $casts = [
        'question_question_id' => 'string',
        'choice_id' => 'string'
    ];

    public $primaryKey = 'choice_id';

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_question_id', 'question_id');
    }
}
