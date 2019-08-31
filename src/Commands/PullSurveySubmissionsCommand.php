<?php namespace App\Command;

use App\Object\Answer;
use App\Object\Choice;
use App\Object\Page;
use App\Object\Submission;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;

use App\Object\Question;
use ZipArchive;

/**
 * This command
 * 1) pulls submissions to the survey monkey form,
 * 2) saves completed submissions + answers to the db
 */
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

    public function getSubmission($apiSubmissionRecordId, $values)
    {
        $exists = Submission::find($apiSubmissionRecordId);
        if ($exists) {
            return $exists;
        }
        //YYYY-MM-DD HH:MM:SS
        $new = new Submission();
        $new->respondent_id = $apiSubmissionRecordId;
        $new->total_time = $values['duration'];
        $new->date_modified = date("Y-m-d H:i:s", strtotime($values['recordedDate']));
        $new->start_date = date("Y-m-d H:i:s", strtotime($values['startDate']));
        $new->end_date = date("Y-m-d H:i:s", strtotime($values['endDate']));
        $new->state = "unprocessed";
        $new->save();

        return Submission::find($apiSubmissionRecordId);
    }

    public function getQuestion($questionId)
    {
        if (array_key_exists($questionId, $this->questionLibrary)) {
            return $this->questionLibrary[$questionId];
        }
        $record = Question::find($questionId);
        $this->questionLibrary[$questionId] = $record;

        return $this->questionLibrary[$questionId];
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$last = Submission::orderBy('date_modified', 'desc')->first();

        $headers =  [
        'X-API-TOKEN' => getenv('QUALTRICS_TOKEN'),
        'Accept'      => 'application/json',
        ];

        $setup = [
            'headers' => $headers,
            'json' => [
                'format' => 'json',
                'compress' => false,
                //'seenUnansweredRecode' => -1,
                //'newlineReplacement' => "%%%"
            ]
        ];

        $url = 'surveys/' . getenv('SURVEY_ID') . '/export-responses/';

        $output->writeln([
            'Pull Survey Submissions',
            '============'
        ]);
        try {
            $response = $this->client->request('POST', $url, $setup)->getBody()->getContents();
            $response = json_decode($response, true);
            $output->writeln($response['result']['status']);

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $output->writeln($response->getBody()->getContents());

            return;
        }

        $updateUrl = $url . 'ES_3HPVvXMp0lbtZ53';//$response['result']['progressId'];
        $response = ['result' => ['status' => 'inProgress']];
        while ($response['result']['status'] != 'complete'
            && $response['result']['status'] != 'fail') {
            try {
                $response = $this->client->request('GET', $updateUrl, ['headers' => $headers])->getBody()->getContents();
                $response = json_decode($response, true);
                $output->writeln($response['result']['status']);

            } catch (ClientException $e) {
                $response = $e->getResponse();
                $output->writeln($response->getBody()->getContents());

                return;
            }
        }

        if ($response['result']['status'] == 'fail') {
            $output->writeln("Export failed.");
            var_dump($response);

            return;
        }

        $fileId = $response["result"]["fileId"];
        $downloadUrl = $url . $fileId . '/file';

        try {
            $response = $this->client->request('GET', $downloadUrl, [
                'headers' => $headers,
                //'sink' => getenv('LOCAL_PATH') . '/responses.zip'
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $output->writeln($response->getBody()->getContents());

            return;
        }
        $responses = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        $responses = $responses['responses'];
        $hold = [];
        foreach ($responses as $response) {
            $hold[$response['responseId']] = $response['values'];
        }

        $submissionCount = 0;

        foreach ($hold as $responseId => $each) {
            $submissionCount++;
            $submission = $this->getSubmission($responseId, $each);
            $attributes = $submission->getAttributes();
            $respondentId = $attributes['respondent_id'];
            foreach ($each as $questionId => $answers) {
                // skip values that aren't submission answers
                if (strpos($questionId, "QI") !== 0) continue;

                $question = $this->getQuestion($questionId);
                $new = new Answer();
                $new->question_id = $questionId;
                $new->question = $question->question;
                $new->submission_respondent_id = $respondentId;

                $exists = $submission->answers()
                    ->where('question_id', $questionId)
                    ->first();

                if ($exists) continue;

                if ($question->prompt_type !== 'MC') {
                    $new->answer = $answers;
                    $new->save();

                    continue;
                }

                if ($question->prompt_subtype === 'MAVR') {
                    $output->writeln($question->question);
                    foreach ($answers as $answer) {
                        $exists = $submission->answers()
                            ->where('question_id', $questionId)
                            ->first();

                        if ($exists) continue;

                        $choiceId = $questionId . 'c' . $answer;
                        $choice = Choice::find($choiceId);
                        if (!$choice) {
                            $output->writeln("No such choice found! " . $choiceId);
                            break;
                        }

                        $new = new Answer();
                        $new->question_id = $questionId;
                        $new->question = $question->question;
                        $new->choice_id = $choiceId;
                        $new->answer = $answer;
                        $new->submission_respondent_id = $respondentId;
                        $new->save();
                    }

                    continue;
                }

                if ($question->prompt_subtype !== 'SAVR') {
                    $output->writeln("Unknown prompt subtype: ". $question->prompt_subtype);
                }

                $choiceId = $questionId . 'c' . $answers;
                $choice = Choice::find($choiceId);
                if (!$choice) {
                    $output->writeln("No such choice found! " . $choiceId);
                    break;
                }
                $exists = $submission->answers()
                    ->where('question_id', $questionId)
                    ->where('choice_id', $choiceId)
                    ->first();
                if ($exists) continue;

                $new->answer = $choice->choice;
                $new->choice_id = $choiceId;
                $new->save();
            }
            $submission->extractBasicData();
        }

        $output->writeln("Pulled in data from $submissionCount completed submissions");
    }
}
