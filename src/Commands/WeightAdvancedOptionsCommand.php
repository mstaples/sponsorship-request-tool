<?php namespace App\Command;

use App\Object\Page;
use App\Object\Question;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question as Prompt;

/**
 * This command allows you to set the point value for each answer option for multiple choice questions.
 */
class WeightAdvancedOptionsCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:weight-options {question_id?}';

    public function __construct()
    {
        parent::__construct();
    }

    public function printBool($bool)
    {
        return ($bool) ? 'true' : 'false';
    }

    public function getOptions($questionId, OutputInterface $output)
    {
        $options = [];
        if ($questionId) {
            $question = Question::findOrFail($questionId);
            if (strpos($question->prompt_type, 'choice') === false) {
                $output->writeln("Specified question does not have weighted answer options");
                return;
            }
            $options[$question->page->page_id] = [
                'page_title' => $question->page->title,
                'questions' => [ $question->question_id => [
                    'question' => $question->question,
                    'choices' => $question->choices
                ]],
            ];
        } else {
            $pages = Page::where('minimum', false)
                ->where('data', false)
                ->get();
            foreach($pages as $page) {
                $questions = [];
                foreach ($page->questions as $question) {
                    if (strpos($question->prompt_type, 'choice') === false) {
                        continue;
                    }
                    $questions[$question->question_id] = [
                        'question' => $question->question,
                        'choices' => $question->choices
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
            $output->writeln("**************************");
            $output->writeln("[ ".$option['page_title']." ]");
            $output->writeln("**************************");
            foreach ($option['questions'] as $question) {
                $output->writeln("-------------------");
                $output->writeln($question['question']);
                $choiceCount = count($question['choices']);
                $output->writeln("[ weight ] [ choice ]");
                foreach ($question['choices'] as $choice) {
                    $output->writeln("[". $choice->weight ."] " . $choice->choice);
                }
                $prompt = new Prompt("Keep this weighting? ([yes]/no) ", 'yes');
                $prompt->setAutocompleterValues(['yes', 'no']);
                $response = $helper->ask($input, $output, $prompt);
                if ($response == 'yes') {
                    continue;
                }
                foreach ($question['choices'] as $choice) {
                    $setting = "unset";
                    $range = range(0, $choiceCount - 1);
                    while (!in_array($setting, $range, true)) {
                        $output->writeln("[". $choice->weight ."] " . $choice->choice);

                        $max = $choiceCount - 1;
                        $message = "Weighting? (0 - $max) [" . $choice->weight . "] ";

                        $prompt = new Prompt($message, $choice->weight);
                        $setting = (int) $helper->ask($input, $output, $prompt);
                    }
                    $choice->weight = $setting;
                    $choice->save();
                }
            }
        }
    }
}
