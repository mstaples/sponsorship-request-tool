<?php namespace App\Command;

use App\Object\Answer;
use App\Object\Choice;
use App\Object\Page;
use App\Object\Submission;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;

use App\Object\Question;

class PullSurveySubmissionsCommand extends Command
{
    protected $client;

    protected $questionLibrary = [];

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:pull-submissions';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
    }

    public function getSubmission($apiSubmissionRecord)
    {
        $exists = Submission::find($apiSubmissionRecord['id']);
        if ($exists) {
            return $exists;
        }

        $new = new Submission();
        $new->respondent_id = $apiSubmissionRecord['id'];
        $new->total_time = $apiSubmissionRecord['total_time'];
        $new->url = $apiSubmissionRecord['analyze_url'];
        $new->date_modified = $apiSubmissionRecord['date_modified'];
        $new->survey_id = getenv('SURVEY_ID');
        $new->status = "unprocessed";
        $new->save();

        return Submission::find($apiSubmissionRecord['id']);
    }

    public function getQuestion($questionId)
    {
        if (array_key_exists($questionId, $this->questionLibrary)) {
            return $this->questionLibrary[$questionId];
        }
        $record = Question::find($questionId);
        $this->questionLibrary[$questionId] = $record->question;

        return $this->questionLibrary[$questionId];
    }

    public function getPageTitle($pageId)
    {
        $page = Page::find($pageId);
        return $page->title;
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'Bearer ' . getenv('SURVEYMONKEY_TOKEN'),
                'Accept'        => 'application/json',
            ]
        ];

        $response = $this->client->request('GET', getenv('SURVEY_ID').'/responses/bulk', $headers)->getBody()->getContents();
        $response = json_decode($response, true);

        $submissions = $response['data'];
        $output->writeln([
            'Pull Survey Submissions',
            '============'
        ]);
        $submissionCount = 0;
        foreach ($submissions as $each) {
            if ($each['response_status'] != 'completed') {
                continue;
            }
            $submissionCount++;
            $submission = $this->getSubmission($each);
            foreach ($each['pages'] as $page) {
                foreach ($page['questions'] as $question) {
                    $questionId = $question['id'];
                    $questionText = $this->getQuestion($questionId);

                    $answers = $question['answers'];
                    foreach ($answers as $what) {
                        foreach ($what as $answer_type => $answer) {
                            $new = new Answer();
                            $new->question_id = $question['id'];
                            $new->question = $questionText;

                            if ($answer_type == 'text') {
                                $exists = $submission->answers()
                                    ->where('question_id', $question['id'])
                                    ->first();
                                if ($exists) {
                                    continue;
                                }
                                $new->answer = $answer;
                            } else {
                                $choice = Choice::find($answer);
                                if (!$choice) {
                                    $output->writeln("No such choice found! ".$answer);
                                    break;
                                }
                                $exists = $submission->answers()
                                    ->where('question_id', $question['id'])
                                    ->where('choice_id', $choice->choice_id)
                                    ->first();
                                if ($exists) {
                                    continue;
                                }
                                $new->answer = $choice->choice;
                                $new->choice_id = $choice->choice_id;
                            }
                            $new->submission()->associate($submission);
                            $new->save();
                        }
                    }
                }
            }
        }

        $output->writeln("Pulled in data from $submissionCount completed submissions");
    }
}