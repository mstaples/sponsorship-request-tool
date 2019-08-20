<?php namespace App\Command;

use App\Object\Answer;
use App\Object\Choice;
use App\Object\Page;
use App\Object\Submission;
use Carbon\Carbon;
use SendGrid\Mail\Mail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;

use App\Object\Question;
use Twig_Environment;
use Twig_Loader_Filesystem;

class SendReportCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:report {email}';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
    }

    public function emailReport($emailTo, $reportData)
    {
        $loader = new Twig_Loader_Filesystem('src/Templates');
        $twig = new Twig_Environment($loader, array(
            'cache' => 'src/Templates/cache',
        ));

        $htmlContent = $twig->render('ReportEmail.twig.html', ['data' => $reportData]);

        $email = new Mail();
        $email->setFrom("bot@sponsorship-requests.twilio", "Sponsorship Request Bot");
        $email->setSubject("Sponsorship Request Report");
        if (getenv('MODE') == 'TEST') {
            $email->addTo("mstaples@twilio.com", "Margaret");
        } else {
            $email->addTo($emailTo, $emailTo);
        }
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
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address required');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        $submissions = Submission::where('state', 'processed')->orderBy('start_date', 'ASC')->get();
        $reportData = [];
        foreach ($submissions as $submission) {
            $last_change = strtotime($submission->date_modified);
            if (date("Y", $last_change) < date("Y")) {
                continue;
            }
            if (date("n") - 4 > date("n", $last_change)) {
                continue;
            }
            $reportData[] = [
                "name" => $submission->event_name,
                'basics' => $submission->getBasicData()
            ];
        }

        $this->emailReport($email, $reportData);
    }
}
