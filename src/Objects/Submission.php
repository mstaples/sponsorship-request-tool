<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;
use App\Object\Description as Description;

class Submission extends Eloquent
{
    protected $optOutAnswerTexts = [ "Notthistime", "No", "noneoftheabove"];

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

        'respondent_id', 'date_modified', 'total_time', 'event_type', 'minimums', 'commitments', 'speaker_count', 'attendee_estimate', 'developer_estimate', 'score', 'max_score', 'recommended_level', 'recommended_cash', 'devangel_email', 'last_email', 'state', 'event_name', 'requests', 'shenanigans', 'start_date', 'end_date', 'teamwork_project_id', 'teamwork_content', 'teamwork_description', 'contact_name', 'contact_email', 'contact_phone'

    ];

    protected $attributes = [
        'minimums' => false,
        'commitments' => 0,
        'speaker_count' => 0,
        'attendee_estimate' => 0,
        'developer_estimate' => 0,
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
        'contact_name' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'state' => 'unprocessed',
        'shenanigans' => false,
        'teamwork_project_id' => null,
        'teamwork_content' => null,
        'teamwork_description' => null
    ];

    // Eloquent will auto-cast keys as ints if this is not definedv
    protected $casts = [
        'respondent_id' => 'string'
    ];

    public $primaryKey = 'respondent_id';

    public function answers()
    {
        return $this->hasMany(Answer::class);
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

    public function getDeveloperAttendeePercent()
    {
        $total = $this->attendee_estimate;
        $devs = $this->developer_estimate;

        $percent = $total/100;
        $devPercent = $devs / $percent;

        return round($devPercent);
    }

    public function getAnswer($queryText)
    {
        $question = Question::where('question', 'LIKE', "%$queryText%")->firstOrFail();
        $answer = $this->answers()->where('question_id', $question->question_id)->first();

        if ($answer) {
            return $answer->answer;
        }

        return "None";
    }

    public function getTeamworkDescription()
    {
        if ($this->teamwork_description != null) {
            return $this->teamwork_description;
        }

        $description = new Description();
        $description->dateOfRequest = $this->date_modified;
        $description->eventName = $this->event_name;
        $description->startDate = date('Y/m/d', $this->start_date);
        $description->endDate = date('Y/m/d', $this->end_date);
        $description->url = $this->url;
        $description->expectedAttendees = $this->attendee_estimate;
        $description->expectedDeveloperPercentage = $this->getDeveloperAttendeePercent();
        $description->socialMediaLinks = $this->getAnswer('Social media');
        $description->eventType = $this->event_type;
        $description->desc = $this->getAnswer('short description');
        $description->organizer = $this->contact_name;
        $description->organzierEmail = $this->contact_email;
        $description->phoneNumber = $this->contact_phone;
        $description->pastOrganizer = $this->getAnswer('event in the past');
        $description->pastPartner = $this->getAnswer('Twilio in the past');
        $description->prospectusLink = $this->getAnswer('sponsorship prospectus');
        $description->sponsorshipRequest = $this->getAnswer('kind of sponsorship');
        $description->venue = $this->getAnswer('Venue (you');
        $description->location = $this->getAnswer('Where will the event take place');

        $this->teamwork_description = $description->assembleDescription();
        $this->save();

        return $description;
    }

    public function extractBasicData()
    {
        // set event name
        $answer = $this->answers()->where('question_id', getenv('EVENT_NAME_QUESTION_ID'))->first();
        $this->event_name = $answer->answer;

        // set event type
        $answer = $this->answers()->where('question_id', getenv('EVENT_TYPE_QUESTION_ID'))->first();
        $this->event_type = $answer->answer;

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

        // set attendee count
        $answer = $this->answers()->where('question_id', getenv('DEVELOPER_ESTIMATE_QUESTION_ID'))->first();
        if ($answer) {
            $this->developer_estimate = $answer->answer;
        }

        // set contact info
        $this->contact_name = $this->getAnswer("Who should we");
        $this->contact_email = $this->getAnswer("What email address");
        $this->contact_phone = $this->getAnswer("What phone number");

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

    public function hasTeamworkProject()
    {
        return $this->teamwork_project_id == null ? false : true;
    }

    public function getTags()
    {
        $question = Question::where('question', 'LIKE', "%tags%")->firstOrFail();
        $answers = $this->answers()->where('question_id', $question->question_id)->get();

        if (empty($answer)) {
            return "";
        }

        $selected = $this->event_type;
        foreach ($answers as $answer) {
            try {
                $choice = Choice::findOrFail($answer->choice_id);
            } catch (\Exception $e) {
                error_log("choice id = ".$answer->choice_id);
                error_log("prompt type = ".$question->prompt_type);
                error_log("no matching choice record found");
                error_log(var_dump($e));
                continue;
            }
            
            $selected .= ', ' . $choice->choice;
        }

        return $selected;
    }

    public function getBasicData()
    {
        $data = ["short" => [], "long" => []];
        $infoIds = explode(',', getenv('INFO_IDS'));
        $questions = Question::all();
        foreach ($questions as $question) {
            $qid = $question->question_id;
            $numVal = $this->getNumericValue($qid);
            if (!in_array($numVal, $infoIds)) {
                continue;
            }
            $answers = $this->answers()->where('question_id', $question->question_id)->get();
            if (empty($answers)) {
                continue;
            }

            if ($question->prompt_type == 'MC') {
                $selected = null;
                foreach ($answers as $answer) {
                    try {
                        $choice = Choice::findOrFail($answer->choice_id);
                    } catch (\Exception $e) {
                        error_log("choice id = ".$answer->choice_id);
                        error_log("prompt type = ".$question->prompt_type);
                        error_log("no matching choice record found");
                        error_log(var_dump($e));
                        continue;
                    }

                    if ($selected == null) {
                        $selected = $choice->choice;
                        continue;
                    }
                    $selected .= ', ' . $choice->choice;
                }

                $data['short'][$question->question_id] = [
                    'question' => $question->question,
                    'answer' => $selected
                ];
                continue;
            }

            foreach ($answers as $answer) {
                if (strlen($question->question) + strlen($answer->answer) < 100) {
                    $designate = "short";
                } else {
                    $designate = "long";
                }

                $data[$designate][$question->question_id] = [
                    'question' => $question->question,
                    'answer' => $answer->answer
                ];
            }
        }

        return $data;
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

    // Qualtrics uses string ids which contain an ordered numeric piece
    // we can extract that piece to determine questions before and after a given point
    public function getNumericValue($string)
    {
        return preg_replace("/[^0-9]/", "", $string);
    }

    public function getAdvancedCommitments()
    {
        $infoIds = getenv('INFO_IDS');
        $basicIds = getenv('BASICS_IDS');
        $skips = $infoIds . ',' . $basicIds;
        $skips = explode(',', $skips);

        $max = 0;
        $commitments = new Commitment();
        $questions = Question::all();
        foreach ($questions as $question) {
            $qid = $question->question_id;
            $numVal = $this->getNumericValue($qid);

            // only evaluate advanced standards
            if (in_array($numVal, $skips)) {
                continue;
            }

            // submitter didn't see this question
            if ($question->conditional && !$this->hasCondition($question)) {
                continue;
            }

            $max += $question->getMaxValue();

            $answer = $this->answers()->where('question_id', $qid)->first();
            if (!$answer) {
                continue;
            }

            if ($question->prompt_type == 'Slider') {
                $score = $answer->processSliderAnswer($question);
                if ($score == 0) {
                    $commitments->no[] = $question->question;
                    continue;
                }
                $commitments->score += $score;
                $commitments->appendYes($answer);
            }

            if ($question->prompt_type == 'MC') {
                try {
                    $choice = Choice::findOrFail($answer->choice_id);
                } catch (\Exception $e) {
                    error_log("choice id = ".$answer->choice_id);
                    error_log("prompt type = ".$question->prompt_type);
                    error_log("no matching choice record found");
                    error_log(var_dump($e));
                    continue;
                }

                if ($choice->weight == 0) {
                    // SurveyMonkey seemed to inject special chars for spaces sometimes so we remove spaces & special chars for matching
                    $checkText = preg_replace('/[^A-Za-z0-9]/', '', $answer->answer);
                    if (in_array($checkText, $this->optOutAnswerTexts)) {
                        $commitments->no[] = $question->question;
                    } else {
                        $commitments->requests[] = $question->question;
                    }
                    continue;
                }

                $commitments->score += $choice->weight;
                $commitments->appendYes($answer);
            }
            // currently advanced questions are all Slider or MC (multiple choice)
        }

        $commitments->max = $max;

        return $commitments;
    }

    public function getMissingMinimums()
    {
        $missingMinimums = [];

        $basicIds = explode(',', getenv('BASIC_IDS'));
        $questions = Question::all();
        foreach ($questions as $question) {
            $qid = $question->question_id;
            $numVal = $this->getNumericValue($qid);
            if (!in_array($numVal, $basicIds)) {
                continue;
            }
            $check = Answer::where('question_id', $qid)->first();
            if (!$check) {
                continue;
            }

            $agreements = $question->choices;
            if (empty($agreements)) {
                error_log('No choices found for question: ' . $question->question . "($qid)");
            }

            $items = [];
            foreach ($agreements as $agreement) {
                if ($agreement->choice == "none of the above" || $agreement->choice == "No") {
                    continue;
                }

                $answer = $this->answers()->where('choice_id', $agreement->choice_id)->first();
                if (!$answer) {
                    // submitter did not agree with this statement
                    $items[] = $agreement->choice;
                    //var_dump("no agreement: ".$agreement->choice);
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
        }
        return $missingMinimums;
    }
}
