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
    protected $twOwnerId;
    protected $adminId;
    protected $output;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:update-teamwork';

    public function __construct(Client $guzzleClient)
    {
        parent::__construct();
        $this->client = $guzzleClient;
        $this->twOwnerId = getenv('TEAMWORK_OWNER_ID');
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

    protected function createProjectId(Submission $submission)
    {
        $output = $this->output;
        if ($submission->hasTeamworkProject()) {
            $projectId = $submission->teamwork_project_id;
            $output->writeln("Submission with id ". $submission->id ." already has a teamwork project id: ".$projectId);
            return $projectId;
        }
        // create an project
        $response = $this->client->request('POST', 'projects.json', [ 'json' => [
            'project' => [
                'name' => $this->getTeamWorkEventName($submission),
                //'description' => $submission->getTeamworkDescription(),
                "startDate" => date('Ymd', $submission->startDate),
                "endDate" => date('Ymd', $submission->endDate),
                "category-id" => $this->getCategoryId($submission),
                "harvest-timers-enabled" => "true",
                "tags" => $submission->getTags(),
                "replyByEmailEnabled" => "true",
                "privacyEnabled" => "true"
            ]
        ]])->getBody()->getContents();
        $response = json_decode($response, true);
        $submission->teamwork_project_id = $response['id'];
        $submission->save();

        return $response['id'];
    }

    protected function updateProject($project_id, $update)
    {
        return $this->client
            ->request('PUT', 'projects/'.$project_id.'.json', [ 'json' => $update])
            ->getBody()->getContents();
    }

    protected function linkContactsToTeamwork()
    {
        $output = $this->output;
        $output->writeln('link contacts to teamwork');
        // make sure contacts are synced up with their teamwork user
        $people = $this->client->request('GET', 'people.json')->getBody()->getContents();
        $people = json_decode($people, true);
        foreach($people['people'] as $i=>$person) {
            $queryString = '%' . $person['email-address'] . '%';
            $contacts = Contacts::where('email', 'LIKE', $queryString)->get();
            if (empty($contacts)) {
                $output->writeln("No contacts entry found for: " . $person['email-address']);
                continue;
            }
            foreach($contacts as $contact) {
                $output->writeln("set teamwork_id to ". $person['id'] . " for " . $contact->name);
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

    protected function getTeamworkId(Contacts $contact, Submission $submission)
    {
        $teamworkId = $contact->teamwork_id;
        if ($teamworkId == null) {
            $this->linkContactsToTeamwork();
            $contact = Contacts::where('email', $submission->devangel_email)->first();
            $teamworkId = $contact->teamwork_id;
        }

        return $teamworkId;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $twOwnerId = $this->twOwnerId;
        $adminId = $this->adminId;
        $creatorId = getenv('TEAMWORK_CREATOR_ID');
        $incomingRequestsTasklistId = getenv('TEAMWORK_INCOMING_REQUESTS_TASKLIST_ID');
        $twoWeeks = 60 * 60 * 24 * 14;

        $this->output = $output;

        if (getenv('MODE') == 'TEST') {
            $submissionIDs = [ 'R_22tBeb50flRnQl0' ];
            $submissions = Submission::whereIn('respondent_id', $submissionIDs)->get();
        } else {
            $submissions = Submission::where('teamwork_project_id', null)->get();
        }

        foreach ($submissions as $submission) {
            $name = $this->getTeamWorkEventName($submission);
            $projectId = $this->createProjectId($submission);
            $output->writeln("TeamWork Event Name: " . $name . " ($projectId)");

            $contact = Contacts::where('email', $submission->devangel_email)->first();
            $devangelTeamworkId = $this->getTeamworkId($contact, $submission);

            $list = $adminId .',' . $twOwnerId;
            if ($devangelTeamworkId != $adminId && $devangelTeamworkId != $twOwnerId) {
                $list .= ",$devangelTeamworkId";
            }
            $output->writeln("owners: " . $list);

            $content = date("Y-m-d", strtotime($submission->start_date)) .
                ' - ' .
                date("Y-m-d", strtotime($submission->start_date)) .
                ' ' . $submission->event_name;
            $submission->teamwork_content = $content;

            $description = $submission->getTeamworkDescription();
            $submission->teamwork_description = $description;

            $task = [
                'tasklistId' => $incomingRequestsTasklistId,
                'content' => $content,
                'creator-id' => $creatorId,
                'responsible-party-id' => $twOwnerId,
                'comment-follower-ids' => $list,
                'change-follower-ids' => $list,
                'start-date' => date('Ymd'),
                'due-date' => date('Ymd', time() + $twoWeeks),
                'description' => $description
            ];

            $response = $this->client->request('POST', 'tasklists/'.$incomingRequestsTasklistId.'/tasks.json', [
                'json' => [
                    'todo-item' => $task
                ]
            ])->getBody()->getContents();
            $response = json_decode($response, true);
            var_dump($response);
            $output->writeln("create task: " . $response["STATUS"]);
        }
    }
}
