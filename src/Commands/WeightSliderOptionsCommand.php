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

    public function getOptions($questionId, OutputInterface $output)
    {
        $options = [];
        if ($questionId) {
            $question = Question::findOrFail($questionId);
            if ($question->min == NULL) {
                $output->writeln("Specified question does not have a slider answer");
                return [];
            }
            $options[$question->page->page_id] = [
                'page_title' => $question->page->title,
                'questions' => [ $question->question_id => [
                    'question' => $question->question,
                    'levels' => $question->levels,
                    'min' => $question->min,
                    'max' => $question->max
                ]],
            ];
        } else {
            $pages = Page::where('minimum', false)
                ->where('data', false)
                ->get();
            foreach($pages as $page) {
                $questions = [];
                foreach ($page->questions as $question) {
                    if (strpos($question->prompt_type, 'choice') !== false ||
                        strpos($question->prompt_subtype, 'single') === false) {
                        continue;
                    }
                    $questions[$question->question_id] = [
                        'question' => $question->question,
                        'levels' => $question->levels()->orderBy('level', 'ASC')->get(),
                        'min' => $question->min,
                        'max' => $question->max
                    ];
                }
                $options[$page->page_id] = [
                    'page_title' => $page->title,
                    'questions' => $questions
                ];
            }
        }

        return $options;
    }

    public function createFirstLevel($questionId, InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $output->writeln("There are not yet any levels associated with this question.");
        $firstMinimum = 0;
        while($firstMinimum == 0) {
            $prompt = new Prompt("What's the minimum value that counts?", 1);
            $response = $helper->ask($input, $output, $prompt);
            if (is_int($response)) {
                $firstMinimum = $response;
            }
        }
        $level = new Level();
        $level->level = 1;
        $level->minimum = $firstMinimum;
        $level->question()->associate(Question::find($questionId));
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