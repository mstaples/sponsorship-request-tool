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
        'question', 'question_id', 'prompt_type', 'prompt_subtype', 'conditional'
    ];

    protected $attributes = [
        'conditional' => false
    ];

    public $primaryKey = 'question_id';

    public function choices()
    {
        return $this->hasMany(Choice::class);
    }

    public function levels()
    {
        return $this->hasMany(Level::class);
    }

    public function getMaxValue()
    {
        if ($this->attributes['min'] === null) {
            try {
                // multiple choice
                $top = $this->choices()->orderBy('weight', 'desc')->firstOrFail();
                return $top->weight;
            } catch (\Exception $e) {
                // text
                return 0;
            }
        }
        // slider
        return $this->levels()->count();
    }
}
