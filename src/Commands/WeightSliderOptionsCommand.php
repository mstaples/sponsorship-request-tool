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
 * This command allows you to set minimums for levels of slider answer.
 * For example, if you gave a slider 3 levels with minimums of 10, 20, and 80, an answer of 45 would fall between level 2 and level 3 so it would be a 2 point answer.
 */
class WeightSliderOptionsCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:weight-sliders {question_id?}';

    public function __construct()
    {
        parent::__construct();
    }

    public function getSliders($questionId, OutputInterface $output)
    {
        $sliders = [];
        if ($questionId) {
            $question = Question::findOrFail($questionId);
            if ($question->prompt_type != 'Slider') {
                $output->writeln("Specified question does not have a slider answer");
                return [];
            }
            $sliders[] = $question;
        } else {
            $sliders = Question::where('prompt_type', 'Slider')->get();
        }

        return $sliders;
    }

    public function createFirstLevel(Question $question, InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $output->writeln("There are not yet any levels associated with this question.");
        $firstMinimum = 0;
        while ($firstMinimum == 0) {
            $prompt = new Prompt("What's the minimum value that counts?", 1);
            $response = $helper->ask($input, $output, $prompt);
            if (is_int($response)) {
                $firstMinimum = $response;
            }
        }
        $level = new Level();
        $level->level = 1;
        $level->minimum = $firstMinimum;
        $level->question()->associate($question);
        $level->save();

        return $level;
    }

    protected function configure()
    {
        $this->addArgument('question_id', InputArgument::OPTIONAL, 'ID for a specific question?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $questionId = $input->getArgument('question_id');
        $sliders = $this->getSliders($questionId, $output);

        foreach ($sliders as $question) {
            $questionId = $question->question_id;
            $output->writeln("-------------------");
            $output->writeln($question->question .
                "(" .
                $question->min .
                " - " .
                $question->max .
                ")");

            if (empty($question->levels())) {
                $this->createFirstLevel($question, $input, $output);
            }

            $output->writeln("[ level ] [ minimum ]");
            foreach ($question->levels() as $level) {
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
            $low = $question->min + 1;
            $high = $question->max;
            for ($i = 1; $i <= $response; $i++) {
                $setting = $question->min - 1;
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
