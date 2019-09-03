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

    // Qualtrics uses string ids which contain an ordered numeric piece
    // we can extract that piece to determine questions before and after a given point
    public function getNumericValue($string)
    {
        return preg_replace("/[^0-9]/", "", $string);
    }

    public function getOptions($questionId, OutputInterface $output)
    {
        $options = [];
        if ($questionId) {
            $question = Question::findOrFail($questionId);
            $options[] = $question;
        } else {
            $questions = Question::where('prompt_type', '!=', 'TE')
                    ->where('prompt_type', '!=', 'Slider')->get();
            $firstAdvancedQuestionId = getenv('EVENT_TYPE_QUESTION_ID');
            $firstAdvancedQuestionCount = $this->getNumericValue($firstAdvancedQuestionId);

            foreach ($questions as $question) {
                $qid = $question->question_id;

                // only return advanced standards
                if ($this->getNumericValue($qid) <= $firstAdvancedQuestionCount) {
                    continue;
                }
                $options[] = $question;
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

        foreach ($options as $question) {
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
