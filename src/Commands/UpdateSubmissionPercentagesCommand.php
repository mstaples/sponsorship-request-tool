<?php namespace App\Command;

use App\Object\Answer;
use App\Object\Level;
use App\Object\Page;
use App\Object\Question;
use App\Object\Submission;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question as Prompt;
/**
 * This command allows you to update the speaker line up percentages on a submission to match their actual line up.
 * This is useful when an organizer misunderstood the form (entering head count instead of percentage) or in any situation
 * in which it's determined that the submitted data insufficiently corresponds with reality.
 *
 * speaker line up percentages are factored into calculated recommendations.
 */
class UpdateSubmissionPercentagesCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:update-percentages {submission_id}';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('submission_id', InputArgument::REQUIRED, 'ID for the submission to update?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $submissionId = $input->getArgument('submission_id');

        $submission = Submission::find($submissionId);

        // find appropriate speaker representation page
        $speakerPages = Page::where('description', 'LIKE', '%' . getenv('SPEAKER_PAGE_DESC_TEXT') .'%')->get();
        $selectedPage = null;
        foreach ($speakerPages as $page) {
            $questions = $page->questions;
            foreach ($questions as $question) {
                $questionId = $question->question_id;
                $answer = $submission->answers()->where('question_id', $questionId)->first();
                if ($answer) {
                    $selectedPage = $page;
                    break 2;
                }
            }
        }
        if ($selectedPage === null) {
            $output->writeln("This submission didn't include any speaker representation information.");
            return;
        }

        // show existing submission info
        $output->writeln("Current Submission:");
        $output->writeln("Event name: ".$submission->event_name);
        $output->writeln("Total Speakers: ".$submission->speaker_count);
        $questions = $selectedPage->questions;
        foreach ($questions as $question) {
            $output->writeln($question->question);
            $questionId = $question->question_id;
            $answer = $submission->answers()->where('question_id', $questionId)->first();

            if (!$answer) {
                continue;
            }

            $output->writeln("Answer: " . $answer->answer);
        }
        $prompt = new Prompt("Keep these percentages? ([yes]/no) ", 'yes');
        $prompt->setAutocompleterValues(['yes', 'no']);
        $response = $helper->ask($input, $output, $prompt);
        if ($response == 'yes') {
            return;
        }

        // set new answer values for submission
        foreach ($questions as $question) {
            $output->writeln($question->question);
            $questionId = $question->question_id;
            $answer = $submission->answers()->where('question_id', $questionId)->first();

            if (!$answer) {
                $answer = new Answer();
                $answer->question_id = $question->question_id;
                $answer->question = $question->question;
                $answer->answer = 0;
            }
            $newAnswer = -1;
            while ($newAnswer < 0 || $newAnswer > 100) {
                $output->writeln("Answer: " . $answer->answer);
                $prompt = new Prompt("New answer? (0 - 100) ", 0);
                $newAnswer = $helper->ask($input, $output, $prompt);
            }
            $answer->answer = $newAnswer;
            $answer->save();
        }
    }
}