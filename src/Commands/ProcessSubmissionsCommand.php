<?php namespace App\Command;

use App\Object\Answer;
use App\Object\Choice;
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

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:process-submissions';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
    }

    public function sendEmail(Submission $submission, $data, $commitments, $requests)
    {
        if (getenv('MODE') == 'TEST') {
            // return '200'; //uncomment for testing w/o sending emails
        }
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
        } catch (\Exception $e) {
            return 'Caught exception: '. $e->getMessage() ."\n";
        }

        return $response->statusCode();
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (getenv('MODE') == 'TEST') {
            $submissions = Submission::limit(10)->get();
        } else {
            $submissions = Submission::where('state', 'unprocessed')->get();
        }

        foreach ($submissions as $submission)
        {
            // set minimums
            $minimums = $submission->getMissingMinimums();
            if (empty($minimums)) {
                $submission->minimums = true;
            } else {
                $submission->minimums = false;
            }
            $submission->save();

            // get the advanced commitments and questions
            // returns Commitment instance
            $commitments = $submission->getAdvancedCommitments();

            //var_dump($commitments);
            $submission->score = $commitments->score;
            $submission->max_score = $commitments->max;
            $submission->requests = count($commitments->requests);
            $submission->generateRecommendations();
            $submission->save();

            // email developer evangelist
            $data = $submission->getBasicData();
            $status = $this->sendEmail($submission, $data, $commitments->yes, $commitments->requests);
            if (getenv('MODE') != 'TEST') {
                $submission->state = "processed";
            }
            $submission->save();

            $output->writeln("Sent out DevAngel email for: ".$submission->event_name . " ($status)");
            $output->writeln("Event type: ".$submission->event_type);
            $output->writeln("score: ".$submission->score . " of ".$submission->max_score);
            $output->writeln("attendee estimate: ".$submission->attendee_estimate);
        }
    }
}
