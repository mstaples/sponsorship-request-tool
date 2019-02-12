<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Answer extends Eloquent
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'respondent_id', 'question_id', 'choice_id', 'question', 'answer'
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class, 'submission_respondent_id', 'respondent_id');
    }

}