<?php namespace App\Command;


use App\Object\Contacts;
use App\Object\Submission;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateTeamworkCommand extends Command
{
    protected $client;
    protected $projectOwnerId;
    protected $adminId;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:update-teamwork';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
        $this->projectOwnerId = getenv('TEAMWORK_OWNER_ID');
        $this->adminId = getenv('TEAMWORK_ADMIN_ID');
    }

    // this is hardcoded for our particular TeamWork setup
    // @TODO update this to be more generically useful
    protected function getCategoryId($submission)
    {
        $eventType = $submission->event_type;
        switch($eventType) {
            case 'Conference':
                return 12666;
            case 'Hackathon':
                return 12667;
            default:
                return 14478;
        }
    }

    protected function createProjectId(Submission $submission, OutputInterface $output)
    {
        if ($submission->hasTeamworkProject()) {
            $projectId = $submission->teamwork_project_id;
            $output->writeln("Submission with id ". $submission->id ." already has a teamwork project id: ".$projectId);
            return $projectId;
        }
        // create an project
        $response = $this->client->request('POST', 'projects.json', [ 'json' => [
            'project' => [
                'name' => $this->getTeamWorkEventName($submission),
                'description' => $submission->getDescription(),
                "startDate" => $submission->startDate,
                "endDate" => $submission->endDate,
                "category-id" => $this->getCategoryId($submission),
                "harvest-timers-enabled" => "true",
                // "tags" => "tag1,tag2,tag3",
                "replyByEmailEnabled" => "true",
                "privacyEnabled" => "true"
            ]
        ]])->getBody()->getContents();
        $response = json_decode($response, true);
        $projectId = $response['id'];
        $output->writeln(var_dump($projectId));
        $submission->teamwork_project_id = $projectId;
        $submission->save();

        return $projectId;
    }

    protected function updateProject($project_id, $update)
    {
        return $this->client
            ->request('PUT', 'projects/'.$project_id.'.json', [ 'json' => $update])
            ->getBody()->getContents();
    }

    protected function linkContactsToTeamwork(OutputInterface $output)
    {
        // make sure contacts are synced up with their teamwork user
        $people = $this->client->request('GET', 'people.json')->getBody()->getContents();
        $people = json_decode($people, true);
        foreach($people['people'] as $i=>$person) {
            $contacts = Contacts::where('email', $person['email-address'])->get();
            if (!empty($contacts)) {
                $output->writeln(var_dump($person['email-address']));
                continue;
            }
            foreach($contacts as $contact) {
                $contact->teamwork_id = $person['id'];
                $contact->save();
            }
        }
    }

    protected function getTeamWorkEventStatus(Submission $submission)
    {
        $date = strtotime($submission->end_date);
        $status = "active";
        $year = date("Y", $date);
        if ($year < date("Y")) {
            $status = "inactive";
        }
        $month = date("n", $date);
        if ($month < date("n")) {
            $status = "inactive";
        }
        $day = date("j", $date);
        if ($day < date("j")) {
            $status = "inactive";
        }

        return $status;
    }

    // Month Day: EventName Year
    protected function getTeamWorkEventName(Submission $submission)
    {
        $eventName = $submission->event_name;
        $startDate = strtotime($submission->start_date);
        $endDate = strtotime($submission->end_date);
        $formatDate = date("M j", $startDate);
        if (date("M j", $startDate) != date("M j", $endDate)) {
            if (date("M", $startDate) != date("M", $endDate)) {
                $formatDate .= "-" . date("M j", $endDate);
            } else {
                $formatDate .= "-" . date("j", $endDate);
            }
        }
        $name = $formatDate . ": " . $eventName;
        $currentYear = date("Y");
        if (strpos($name, $currentYear) === false) {
            $name .= " $currentYear";
        }
        return $name;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectOwnerId = $this->projectOwnerId;
        $adminId = $this->adminId;
        if (getenv('MODE') == 'TEST') {
            //$testProjects = [ 567331, 567329, 567326, 567325, 567324, 567323, 567322, 567321, 567320, 567319 ];
            $testProjects = [ 567331, 567329 ];
            foreach ($testProjects as $id) {
                $submission = Submission::where('teamwork_project_id', $id)->first();
                $name = $this->getTeamWorkEventName($submission);

                $contact = Contacts::where('email', $submission->devangel_email)->first();
                $tw_id = $contact->teamwork_id;
                $list = $adminId;
                if ($tw_id != $adminId) {
                    $list .= ",$tw_id";
                }
                $response = $this->client->request('POST', 'projects/'.$id.'/people.json', [
                    'query' => [
                        'id' =>  $id
                    ],
                    'json' => [
                        'add' => [
                            "userIdList"=> $list,
                        ]
                    ]
                ])->getBody()->getContents();
                $response = json_decode($response, true);
                $output->writeln("add users: " . $response["STATUS"]);

                $response = $this->client->request('PUT', 'projects/'.$id.'.json', [
                    'query' => [
                        'id' =>  $id,
                    ],
                    'json' => [
                        'project' => [
                            "projectOwnerId"=> "$projectOwnerId",
                            "name" => $name
                        ]
                    ]
                ])->getBody()->getContents();
                $response = json_decode($response, true);
                $output->writeln("update name and owner: " . $response["STATUS"]);

                $response = $this->client->request('GET', 'projects/'.$id.'.json', [ 'query' => [
                    'includeProjectOwner' => true
                ]])->getBody()->getContents();
                $output->writeln(var_dump($response));
            }
        } else {
            $submissions = Submission::where('teamwork_project_id', null)->get();
            foreach($submissions as $submission) {
                $output->writeln($submission->event_name);
                $project_id = $this->createProjectId($submission, $output);
                $update = [
                    "project" => [
                        "status" => $this->getTeamWorkEventStatus($submission),
                        "projectOwnerId" => $this->projectOwnerId
                    ]
                ];
                $response = $this->updateProject($project_id, $update);
                $output->writeln(var_dump($response));
            }
        }
        /**/
    }
}
