<?php

require __DIR__.'/vendor/autoload.php';

use App\Command\AssociateDevangelsCommand;
use App\Command\AssociateSurveyPageCommand;
use App\Command\MigrateDatabaseTablesCommand;
use App\Command\ProcessSubmissionsCommand;
use App\Command\PullSurveyQuestionsCommand;
use App\Command\PullSurveySubmissionsCommand;

use App\Command\SendReportCommand;
use App\Command\UpdateHawkeyeCommand;
use App\Command\UpdateSubmissionPercentagesCommand;
use App\Command\UpdateTeamworkCommand;
use App\Command\WeightAdvancedOptionsCommand;
use App\Command\WeightSliderOptionsCommand;

use Symfony\Component\Console\Application;
use GuzzleHttp\Client;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as DB;

$dotenv = new Dotenv(__DIR__);
$dotenv->load();

$db = new DB;
$db->addConnection([
    "driver" => "mysql",
    "host" =>"127.0.0.1",
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    "database" => getenv('DB_NAME'),
    "username" => getenv('DB_USER'),
    "password" => getenv('DB_PASSWORD')
]);

$db->setAsGlobal();
$db->bootEloquent();

$surveyMonkeyClient = new Client([
    'base_uri' => getenv('SURVEYMONKEY_URL'),
    'timeout'  => 8.0,
]);

$qualtricsClient = new Client([
    'base_uri' => getenv('QUALTRICS_URL'),
    'timeout'  => 8.0,
]);

$sendGridClient = new Client([
    'base_uri' => getenv('SENDGRID_URL'),
    'timeout'  => 8.0,
]);

$hawkeyeClient = new Client([
    'base_uri' => getenv('HAWKEYE_URL'),
    'timeout'  => 8.0,
]);

$teamWorkClient = new Client([
    'base_uri' => getenv('TEAMWORK_URL'),
    'timeout'  => 8.0,
    'auth' => [ getenv('TEAMWORK_KEY'), 'dead_lugosi' ]
]);

$application = new Application();

$application->add(new PullSurveyQuestionsCommand($qualtricsClient));
$application->add(new PullSurveySubmissionsCommand($qualtricsClient));
$application->add(new MigrateDatabaseTablesCommand());
$application->add(new AssociateSurveyPageCommand());
$application->add(new AssociateDevangelsCommand());
$application->add(new WeightAdvancedOptionsCommand());
$application->add(new WeightSliderOptionsCommand());
$application->add(new ProcessSubmissionsCommand($sendGridClient));
$application->add(new UpdateSubmissionPercentagesCommand());
$application->add(new UpdateHawkeyeCommand($hawkeyeClient));
$application->add(new UpdateTeamworkCommand($teamWorkClient));
$application->add(new SendReportCommand($sendGridClient));

$application->run();