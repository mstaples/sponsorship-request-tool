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

    // Eloquent will auto-cast keys as ints if this is not defined
    protected $casts = [
        'question_question_id' => 'string',
        'choice_id' => 'string',
        'respondent_id' => 'string'
    ];

    public function processSliderAnswer(Question $question)
    {
        $levels = $question->levels()->orderBy('level', 'desc')->get();
        $answerValue = $this->answer;
        $score = 0;
        foreach ($levels as $level) {
            if ($answerValue >= $level->minimum) {
                $score = $level->level;
                break;
            }
        }
        return $score;
    }

    public function hasCondition()
    {
        $condition = Condition::where('question_question_id', $this->question_id)->firstOrFail();
        $conditionedOn = Answer::where('question_question_id', $condition->condition_question_id)
                            ->where('respondent_id', $this->respondent_id)
                            ->firstOrFail();
        switch($condition->condition_state) {
            case 'Selected':
                if ($conditionedOn->choice_id == $condition->condition_choice) {
                    return true;
                }
                break;
            case 'NotSelected':
                if ($conditionedOn->choice_id != $condition->condition_choice) {
                    return true;
                }
                break;
        }

        return false;
    }
}
