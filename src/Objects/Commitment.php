<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Commitment extends Eloquent
{
    public $yes = [];
    public $no = [];
    public $requests = [];
    public $score = 0;
    public $max = 0;

    public function appendYes(Answer $answer)
    {
        $this->yes[] = [
            'question' => $answer->question,
            'answer' => $answer->answer
        ];
    }
}
