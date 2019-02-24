<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Submission extends Eloquent
{
    // the bias is an attempt to account for the decreased impact we can have per attendee at the larger events
    protected $attendanceRanks = [
        0 => [
            'minimum' => 0,
            'bias' => 0
        ],
        1 => [
            'minimum' => 100,
            'bias' => -0.25
        ],
        2 => [
            'minimum' => 200,
            'bias' => 0
        ],
        3 => [
            'minimum' => 600,
            'bias' => 0.4
        ],
        4 => [
            'minimum' => 1200,
            'bias' => 0.7
        ],
        5 => [
            'minimum' => 3000,
            'bias' => 0.75
        ]
    ];

    protected $eventTypeModifiers = [
        // trying to account for the different level of intensity for an attendee of a hackathon
        // and how we perceive that value
        'Hackathon' => 2,
        // Tech events that are neither Hackathons nor Conferences tend to have lower logistical requirements
        // and have less overhead - trying to account for that on the assumption that they cost less per person to run
        'Event' => 0.85
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [

        'survey_id', 'respondent_id', 'date_modified', 'total_time', 'analyze_url', 'event_type', 'url', 'minimums', 'commitments', 'speaker_count', 'attendee_estimate', 'score', 'max_score', 'recommended_level', 'recommended_cash', 'devangel_email', 'last_email', 'state', 'speaker_count', 'event_name', 'requests', 'shenanigans', 'start_date', 'end_date'

    ];

    protected $attributes = [
        'minimums' => false,
        'commitments' => 0,
        'speaker_count' => 0,
        'attendee_estimate' => 0,
        'score' => 0,
        'max_score' => 0,
        'recommended_level' => 0,
        'recommended_cash' => 0,
        'requests' => 0,
        'devangel_email' => null,
        'devangel_name' => null,
        'last_email' => null,
        'start_date' => null,
        'end_date' => null,
        'event_name' => null,
        'state' => 'unprocessed',
        'shenanigans' => false
    ];

    public $primaryKey = 'respondent_id';

    public function answers()
    {
        return $this->hasMany('App\Object\Answer');
    }

    public function setEventType($eventType)
    {
        if ($eventType != 'Event') {
            $this->event_type = $eventType;

            return;
        }
        if ($this->attendee_estimate > 200 || $this->start_date != $this->end_date) {
            $this->event_type = 'Conference';
            $this->shenanigans = true;

            return;
        }

        $this->event_type = $eventType;
    }

    public function setAttendeeEstimate($attendeeEstimate)
    {
        $this->attendee_estimate = $attendeeEstimate;
        if ($this->attendee_estimate > 200 && $this->event_type == 'Event') {
            $this->event_type = 'Conference';
            $this->shenanigans = true;
        }
    }

    public function setEndDate($endDate)
    {
        if ($endDate == $this->start_date && $this->event_type == 'Event') {
            $this->event_type = 'Conference';
            $this->shenanigans = true;
        }
    }

    public function extractBasicData()
    {
        // set event name
        $answer = $this->answers()->where('question_id', getenv('EVENT_NAME_QUESTION_ID'))->first();
        $this->event_name = $answer->answer;

        // set event type
        $answer = $this->answers()->where('question_id', getenv('EVENT_TYPE_QUESTION_ID'))->first();
        $this->event_type = $answer->answer;

        // set start date and end date
        $s = $this->answers()->where('question_id', getenv('START_DATE_QUESTION_ID'))->first();
        $this->start_date = date('Y-m-d H:i:s', strtotime($s->answer));

        $e = $this->answers()->where('question_id', getenv('END_DATE_QUESTION_ID'))->first();
        $this->end_date = date('Y-m-d H:i:s', strtotime($e->answer));

        // set devangel name & email
        $answer = $this->answers()->where('question_id', getenv('DEVANGEL_QUESTION_ID'))->first();
        $contact = Contacts::where('choice_id', $answer->choice_id)->first();
        $this->devangel_email = $contact->email;
        $this->devangel_name = $contact->name;

        // set speaker count
        $answer = $this->answers()->where('question_id', getenv('SPEAKER_COUNT_QUESTION_ID'))->first();
        $this->speaker_count = $answer->answer;

        // set attendee count
        $answer = $this->answers()->where('question_id', getenv('ATTENDEE_ESTIMATE_QUESTION_ID'))->first();
        if ($answer) {
            $this->attendee_estimate = $answer->answer;
        }
        $this->save();

        return;
    }

    //puts levels at 10%, 20%, 30%, 40%, and 50% of max_score
    public function getLevelMinimums()
    {
        $levelMinimums = [];
        for ($i = 1; $i < 6; $i++) {
            $percent = $i / 10;
            $levelMinimums[$i] = floor($this->max_score * $percent);
        }

        return $levelMinimums;
    }

    public function getRecommendedLevel()
    {
        $score = $this->score;
        $requestCount = $this->requests;
        $levelMinimums = $this->getLevelMinimums();

        $level = 0;
        foreach ($levelMinimums as $minimum) {
            $level++;
            if ($score < $minimum) {
                break;
            }
        }

        if (!$this->minimums) {
            if ($requestCount < $levelMinimums[1] &&
                $score < $levelMinimums[1]) {
                return 0;
            }
            if ($score < $levelMinimums[1]) {
                $level--;
            }
            $level--;
        }

        return $level < 0 ? 0 : $level;
    }

    public function getAttendanceRank()
    {
        $rank = 0;
        $estimate = $this->attendee_estimate;
        $modifiers = $this->eventTypeModifiers;
        if (array_key_exists($this->event_type, $modifiers)) {
            $estimate = $estimate * $modifiers[$this->event_type];
        }

        foreach ($this->attendanceRanks as $each) {
            if ($estimate < $each['minimum']) {
                break;
            }
            $rank++;
        }

        return $rank;
    }

    public function generateRecommendations()
    {
        $attendees = $this->attendee_estimate;
        $this->setAttendeeEstimate($attendees);

        $level = $this->getRecommendedLevel();
        $rank = $this->getAttendanceRank();

        $valid = range(0,5);
        $biasRank = in_array($rank, $valid) ? $rank : 1;
        $attendanceRanks = $this->attendanceRanks;
        $bias = $attendanceRanks[$biasRank]['bias'];
        $mod = $attendees * (1 - $bias);
        $cash = $mod * $level * 5;

        $this->recommended_level = $level;
        $this->recommended_cash = $cash;
        $this->save();

        return;
    }

    public function getMinimumsText()
    {
        $eventType = $this->event_type;
        if (!$this->minimums && $this->recommended_level == 0) {
            return "This $eventType does not meet the minimum diversity and inclusion standards we're hoping for.";
        }

        if (!$this->minimums) {
            return "This $eventType does not meet the minimum diversity and inclusion standards we're hoping for, but does seem to be putting in extra effort.";
        }

        return "This $eventType is already meeting our minimum standards for diversity and inclusion efforts!";
    }

    public function getRecommendationsText()
    {
        if (!$this->minimums && $this->recommended_level == 0) {
            return "It is not recommended we sponsor this event.";
        }

        if (!$this->minimums) {
            return "If this event can up some standards a bit, consider a level " .
                $this->recommended_level .
                " sponsorship of as much as $".
                number_format($this->recommended_cash);
        }

        if ($this->recommended_level > 1) {
            return "This event seems to be planning a wonderfully diverse and inclusive event. It's recommended you consider a level ".$this->recommended_level . " sponsorship for this event, valued around $".number_format($this->recommended_cash).".";
        }

        return "This event seems to be planning a nice event. It's recommended you consider a level ".$this->recommended_level . " sponsorship for this event, valued around $".number_format($this->recommended_cash).".";
    }

    public function getShenanigansText()
    {
        if (!$this->shenanigans) {
            return "";
        }

        return "Shenanigans! This was originally submitted as an Event, but was either too big or over too many days to not be considered a Conference. Since they did not access the Conference advanced D&I efforts options, and so may have a score which fails to accurately reflect their efforts.";
    }

    // return the inline css for the summary recommendation panel
    public function getStyle($type)
    {
        switch ($type) {
            case 'Recommendation':
                // Maybe / Orange
                if (!$this->minimums && $this->recommended_level > 0) {
                    return "border-color:#ff9900 !important; color:#000 !important; background-color:#fff9f0 !important";
                }
                // Yes / Green
                if ($this->minimums) {
                    return "border-color:#00e600 !important; color:#000 !important; background-color:#eeffee !important";
                }
                // Strong Yes / Blue
                if (!$this->minimums && $this->recommended_level > 1) {
                    return "border-color:#00ccff !important; color:#000 !important; background-color:#f0fcff !important";
                }
                // Default / Nope / Red
                return "border-color:#ff0000 !important; color:#000 !important; background-color:#fff0f0 !important";
                break;
            case 'Requests':
                // Green
                return "border-color:#00e600 !important; color:#000 !important; background-color:#eeffee !important";
                break;
            case 'Commitments':
                // Blue
                return "border-color:#00ccff !important; color:#000 !important; background-color:#f0fcff !important";
                break;
        }
        // Default / Red
        return "border-color:#ff0000 !important; color:#000 !important; background-color:#fff0f0 !important";
    }

    public function getMissingMinimums()
    {
        $missingMinimums = [];

        $pages = Page::where('minimum', true)->get();
        $conditional = $this->answers()->where('question_id', getenv('CONDITIONAL_QUESTION_ID'));

        foreach ($pages as $page) {
            if ($page->page_id == getenv('CONDITIONAL_PAGE_ID') &&
                $conditional != 'Yes') {
                continue;
            }
            foreach ($page->questions as $question) {
                $items = [];
                if ($question->prompt_type == 'multiple_choice') {
                    $agreements = $question->choices;
                    foreach ($agreements as $agreement) {
                        $answer = $this->answers()->where('choice_id', $agreement->choice_id)->first();
                        if ($agreement->choice == "none of the above" || $agreement->choice == "No") {
                            continue;
                        }
                        if (!$answer) {
                            $items[] = $agreement->choice;
                            continue;
                        }
                    }
                    if (empty($items)) {
                        continue;
                    }
                    $missingMinimums[] = [
                        'question' => $question->question,
                        'items' => $items
                    ];
                } else {
                    $answer = $this->answers()->where('question_id', $question->question_id)->first();
                    \Log::error("Basic Question, not multiple choice: [" .
                        $question->question_id .
                        "] ".
                        $question->question
                    );
                }
            }
        }

        return $missingMinimums;
    }
}
