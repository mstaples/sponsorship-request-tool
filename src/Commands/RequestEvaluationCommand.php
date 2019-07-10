<?php namespace App\Command;

use App\Object\Level;
use App\Object\Page;
use App\Object\Question;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question as Prompt;

/**
 * This command messages the developer evangelist associated with each event and
 * requests feedback on specific aspects of that event
 * based on the original form submission
 */
class RequestEvaluationCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'devangel:request-evaluation';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        //
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $questionId = $input->getArgument('question_id');
        $options = $this->getOptions($questionId, $output);

        foreach ($options as $option) {
            if (!empty($option['questions'])) {
                $output->writeln("**************************");
                $output->writeln("[ ".$option['page_title']." ]");
                $output->writeln("**************************");
            }
            foreach ($option['questions'] as $questionId => $question) {
                $output->writeln("-------------------");
                $output->writeln($question['question'] .
                    "(" .
                    $question['min'] .
                    " - " .
                    $question['max'] .
                    ")");

                if (empty($question['levels'])) {
                    $question['levels'][] = $this->createFirstLevel($questionId, $input, $output);
                }

                $output->writeln("[ level ] [ minimum ]");
                foreach ($question['levels'] as $level) {
                    $output->writeln("[". $level->level ."] " . $level->minimum);
                }
                $prompt = new Prompt("Keep these levels? ([yes]/no) ", 'yes');
                $prompt->setAutocompleterValues(['yes', 'no']);
                $response = $helper->ask($input, $output, $prompt);
                if ($response == 'yes') {
                    continue;
                }
                Level::where('question_question_id', $questionId)->delete();
                $prompt = new Prompt("How many levels should this slider have? (1 - 5) ");
                $prompt->setAutocompleterValues([1, 2, 3, 4, 5]);
                $response = $helper->ask($input, $output, $prompt);
                $low = $question['min'] + 1;
                $high = $question['max'];
                for ($i = 1; $i <= $response; $i++) {
                    $setting = $question['min'] - 1;
                    while ($setting > $high || $setting < $low) {
                        $prompt = new Prompt("Minimum for level $i? ($low - $high) ");
                        $setting = $helper->ask($input, $output, $prompt);
                    }
                    $low = $setting + 1;
                    $level = Level::create([
                        'question_question_id' => $questionId,
                        'level' => $i
                    ]);
                    $level->minimum = $setting;
                    $level->save();
                }
            }
        }
    }
}
