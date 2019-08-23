<?php namespace App\Command;

use App\Object\Choice;
use App\Object\Condition;
use App\Object\Page;
use App\Object\Question;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;

/**
 * A SurveyMonkey survey is broken up into Pages of Questions.
 * Defining the Logic of movement between pages allows multiple paths through the form.
 * This has been used to allow some different questions depending on whether the submission is for a Conference, Hackathon, or Event.
 * To assist in analysis, this tool also optionally associates pages as defining Minimum Standards or Basic Information.
 *
 * This command shows the operator the title of each Page and
 * 1) the current settings for the page as being for Conferences, Hackathons, and/or Events
 * 2) as well as whether the Page defines Minimum Standards or Basic Information
 * 3) the operator may choose to keep those settings and move on to the next Question or change them.
 *
 * This command only needs to be run during initial setup or when the survey Pages have been changed
 */
class PullSurveyQuestionsCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:pull-questions';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
    }

    public function createConditions($conditions)
    {
        $cond = 0;
        foreach ($conditions as $questionId => $each) {
            $each = $each[0][0];
            $condition = new Condition();
            $condition->question_question_id = $questionId;
            $condition->condition_question_id = $each['QuestionID'];
            $condition->condition_state = $each["Operator"];

            $choice = $each['LeftOperand'];
            $choiceBlocks = explode('/', $choice);
            $choiceId = array_pop($choiceBlocks);

            $condition->condition_choice = $questionId . "c" . $choiceId;
            $condition->save();
            $cond++;
        }

        return $cond;
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $headers = [
            'headers' => [
                'X-API-TOKEN' => getenv('QUALTRICS_TOKEN'),
                'Accept'        => 'application/json',
            ]
        ];

        $url = 'survey-definitions/'.getenv('SURVEY_ID').'/questions';
        $response = $this->client->request('GET', $url, $headers)->getBody()->getContents();
        $response = json_decode($response, true);

        $questions = $response['result']['elements'];
        $output->writeln([
            'Pull Survey Questions',
            '============'
        ]);
        $questionCount = 0;
        $conditions = [];

        foreach ($questions as $key=>$each) {
            $questionId = $each['QuestionID'];
            $questionType = $each['QuestionType'];

            $questionCount++;
            $record = Question::find($questionId);
            if (!$record) {
                $record = new Question();
                $record->question_id = $questionId;
                $record->question = $each['QuestionDescription'];
                $record->prompt_type = $questionType;
                $record->prompt_subtype = $each['Selector'];
                $record->save();
            }

            if (array_key_exists('DisplayLogic', $each)) {
                $record->conditional = true;
                $conditions[$questionId] = $each['DisplayLogic'];
            }

            if ($questionType == 'MC') {
                $choices = $each['Choices'];
                foreach ($choices as $id => $choice) {
                    $choiceId = $questionId . "c" . $id;
                    $exists = Choice::find($choiceId);
                    if ($exists) {
                        continue;
                    }
                    var_dump($questionId);
                    //var_dump($record == Question::find($questionId));
                    $add = new Choice();
                    $add->choice_id = $choiceId;
                    $add->choice = $choice['Display'];
                    $add->question_question_id = $questionId;
                    $add->save();
                }
            }
        }

        $conditions = $this->createConditions($conditions);
        $output->writeln("Pulled a total of $questionCount questions, $conditions with conditions.");
    }
}
