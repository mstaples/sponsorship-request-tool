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
        return $this->belongsTo(Page::class, 'page_page_id', 'page_id');
    }

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