<?php namespace App\Command;

use App\Object\Page;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question as Prompt;

class AssociateSurveyPageCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:associate-page {page_id?}';

    public function __construct()
    {
        parent::__construct();
    }

    public function printBool($bool)
    {
        return ($bool) ? 'true' : 'false';
    }

    protected function configure()
    {
        $this->addArgument('page_id', InputArgument::OPTIONAL, 'ID for a specific page?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $pageId = $input->getArgument('page_id');
        if ($pageId) {
            $pages = [ Page::findOrFail($pageId) ];
        } else {
            $pages = Page::all();
        }

        foreach ($pages as $page) {
            $output->writeln("[".
                $page->page_id."] ".
                $page->title);
            do {
                $output->writeln("for conferences: [" .
                    $this->printBool($page->conference).
                    "], hackathons: [" .
                    $this->printBool($page->hackathon).
                    "], events: [" .
                    $this->printBool($page->event).
                    "], minimum: [" .
                    $this->printBool($page->minimum).
                    "], data: [" .
                    $this->printBool($page->data).
                    "]"
                );
                $question = new Prompt('Keep these settings? ([yes]/no) ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $response = $helper->ask($input, $output, $question);
                if ($response == 'yes') {
                    continue;
                }

                $question = new Prompt('Will conferences see this page? ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $setting = $helper->ask($input, $output, $question);
                $page->conference = $setting == 'yes' ? true : false;

                $question = new Prompt('Will hackathons see this page? ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $setting = $helper->ask($input, $output, $question);
                $page->hackathon = $setting == 'yes' ? true : false;

                $question = new Prompt('Will events see this page? ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $setting = $helper->ask($input, $output, $question);
                $page->event = $setting == 'yes' ? true : false;

                $question = new Prompt('Is this page about minimum standards? ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $setting = $helper->ask($input, $output, $question);
                $page->minimum = $setting == 'yes' ? true : false;

                $question = new Prompt('Is this page basic event info? ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $setting = $helper->ask($input, $output, $question);
                $page->data = $setting == 'yes' ? true : false;
                $page->save();
            } while ($response == 'no');
        }
    }
}