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

class UpdateHawkeyeCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:update-hawkeye';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
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

        $response = $this->client->request('POST', 'events', $headers)->getBody()->getContents();
        $response = json_decode($response, true);

        $submissions = Submission::where('status', 'processed')->get();
        foreach ($submissions as $submission)
        {
            $data = [
                "name" => $submission->event_name,
                "url" => $submission->url,
                "selectedAudienceId" => 0,
                "startDate" => $submission->start_date,
                "endDate" => $submission->end_date,
                "selectedStatusId" => 0,
                "selectedEventTypeId" => 0,
                "addSelfAsDefaultAttendee" => true
            ];

            $submission->status = "recorded";
            $submission->save();

            $output->writeln("Updated Hawkeye for: ".$submission->event_name);
        }
    }
}