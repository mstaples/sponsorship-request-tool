<?php namespace App\Command;

use App\Object\Answer;
use App\Object\Choice;
use App\Object\Page;
use App\Object\Submission;
use SendGrid\Mail\Mail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;

use App\Object\Question;
use Twig_Environment;
use Twig_Loader_Filesystem;
/**
 * This command
 * 1) determines if submission has met minimums,
 * 2) determines advanced D&I commitments indicated by submission,
 * 3) determines the max possible score, the submissions actual score, and pulls questions submission wanted more info on
 * 4) requests recommendation info from submission object
 * 5) bundles and formats this data into a summary info and recommendation email to the specified developer evangelist
 */
class ProcessSubmissionsCommand extends Command
{
    protected $client;

    protected $optOutAnswerText = "Not this time";

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:process-submissions';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
    }

    public function sendEmail(Submission $submission, $data, $commitments, $requests)
    {
        $loader = new Twig_Loader_Filesystem('src/Templates');
        $twig = new Twig_Environment($loader, array(
            'cache' => 'src/Templates/cache',
        ));

        $htmlContent = $twig->render('SubmissionEmail.twig.html', [
            'submission' => $submission,
            'data' => $data,
            'commitments' => $commitments,
            'requests' => $requests
        ]);

        $textContent = $twig->render('SubmissionEmail.twig.txt', [
            'submission' => $submission,
            'data' => $data
        ]);

        $email = new Mail();
        $email->setFrom("bot@sponsorship-requests.twilio", "Sponsorship Request Bot");
        $email->setSubject("New Sponsorship Request! " . $submission->event_name);
        if (getenv('MODE') == 'TEST') {
            $email->addTo("mstaples@twilio.com", $submission->devangel_name);
        } else {
            $email->addTo($submission->devangel_email, $submission->devangel_name);
        }
        $email->addContent("text/plain", $textContent);
        $email->addContent("text/html", $htmlContent);
        $sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));
        try {
            $response = $sendgrid->send($email);
            //print $response->statusCode() . "\n";
            //print $response->body() . "\n";
        } catch (\Exception $e) {
            return 'Caught exception: '. $e->getMessage() ."\n";
        }

        return $response->statusCode();
    }

    public function getBasicData(Submission $submission)
    {
        $data = ["short" => [], "long" => []];
        $pages = Page::where('data', true)->get();
        foreach($pages as $page) {
            foreach ($page->questions as $question) {
                $answer = $submission->answers()->where('question_id', $question->question_id)->first();
                if (empty($answer)) {
                    continue;
                }
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

    public function processMinimums(Submission $submission, OutputInterface $output)
    {
        $pages = Page::where('minimum', true)->get();
        $minimums = [
            'yes' => [],
            'no' => []
        ];
        $conditional = $submission->answers()->where('question_id', getenv('CONDITIONAL_QUESTION_ID'));
        foreach ($pages as $page) {
            if ($page->page_id == getenv('CONDITIONAL_PAGE_ID') &&
                $conditional != 'Yes') {
                continue;
            }
            $questions = $page->questions;
            foreach ($questions as $question) {
                if ($question->prompt_type == 'multiple_choice') {
                    $agreements = $question->choices;
                    foreach ($agreements as $agreement) {
                        $answer = $submission->answers()->where('choice_id', $agreement->choice_id)->first();
                        if (!$answer) {
                            $minimums['no'][$question->question_id][$agreement->choice_id] = $agreement->choice;
                            continue;
                        }
                        $minimums['yes'][$question->question_id][$agreement->choice_id] = $agreement->choice;
                    }
                    continue;
                }
                $answer = $submission->answers()->where('question_id', $question->question_id)->first();
                $output->writeln("Basic Question, not multiple choice: [" .
                    $question->question_id .
                    "] ".
                    $question->question
                );
                $output->writeln("Answer: ".$answer->answer);
            }
        }
        return $minimums;
    }

    public function processSliderAnswer(Question $question, Answer $answer)
    {
        $levels = $question->levels()->orderBy('level', 'desc')->get();
        $answerValue = $answer->answer;
        $score = 0;
        foreach ($levels as $level) {
            if ($answerValue >= $level->minimum) {
                $score = $level->level;
                break;
            }
        }

        return $score;
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (false){//getenv('MODE') == 'TEST') {
            $submissions = Submission::all();
        } else {
            $submissions = Submission::where('state', 'unprocessed')->get();
        }
        foreach ($submissions as $submission)
        {
            // set minimums
            $minimums = $this->processMinimums($submission, $output);
            if (empty($minimums['no'])) {
                $submission->minimums = true;
            }
            $submission->save();

            // set commitments
            $advancedPages = Page::where($submission->event_type, true)
                ->where('minimum', false)
                ->where('data', false)
                ->get();

            $max = 0;
            $responses = [
                'maxScore' => 0,
                'score' => 0,
                'yes' => [],
                'no' => [],
                'requests' => []
            ];
            foreach ($advancedPages as $page) {
                $questions = $page->questions;
                foreach ($questions as $question) {
                    $max += $question->getMaxValue();
                    $answer = $submission->answers()->where('question_id', $question->question_id)->first();
                    if (!$answer) {
                        continue;
                    }
                    if (strpos($question->prompt_type, 'choice') === false &&
                        strpos($question->prompt_subtype, 'single') !== false) {
                        $score = $this->processSliderAnswer($question, $answer);
                        if ($score == 0) {

                            $responses['no'][] = $question->question;
                            continue;
                        }
                        $responses['score'] += $score;
                        $responses['yes'][] = [
                            'question' => $question->question,
                            'answer' => $answer->answer
                        ];
                    }
                    if (strpos($question->prompt_type, 'choice') !== false) {
                        try {
                            $choice = Choice::findOrFail($answer->choice_id);
                        } catch (\Exception $e) {
                            $output->writeln("choice id = ".$answer->choice_id);
                            $output->writeln("prompt type = ".$question->prompt_type);
                            $output->writeln("no matching choice record found");
                            $output->writeln(var_dump($e));
                            break 3;
                        }

                        if ($choice->weight == 0) {
                            // no or request?
                            if ($answer->answer == $this->optOutAnswerText) {
                                $responses['no'][] = $question->question;
                            } else {
                                $responses['requests'][] = $question->question;
                            }
                            continue;
                        }
                        $responses['score'] += $choice->weight;
                        $responses['yes'][] = [
                            'question' => $question->question,
                            'answer' => $answer->answer
                        ];
                    }
                }
            }

            $submission->score = $responses['score'];
            $submission->max_score = $max;
            $submission->requests = count($responses['requests']);
            $submission->generateRecommendations();
            $submission->save();

            // email developer evangelist
            $data = $this->getBasicData($submission);
            $status = $this->sendEmail($submission, $data, $responses['yes'], $responses['requests']);
            $submission->state = "processed";
            $submission->save();

            $output->writeln("Sent out DevAngel email for: ".$submission->event_name . " ($status)");
            $output->writeln("Event type: ".$submission->event_type);
            $output->writeln("score: ".$submission->score . " of ".$submission->max_score);
            $output->writeln("attendee estimate: ".$submission->attendee_estimate);
        }
    }
}