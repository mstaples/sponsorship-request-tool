<?php namespace App\Command;

use App\Object\Contacts;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question as Prompt;

use App\Object\Question;

class AssociateDevangelsCommand extends Command
{
    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'survey:associate-devangels';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = Question::find(getenv('DEVANGEL_QUESTION_ID'));
        $choices = $question->choices;

        foreach ($choices as $choice) {
            $output->writeln("[".
                $choice->choice_id."] ".
                $choice->choice);
            $associated = Contacts::find($choice->choice_id);
            if ($associated) {
                $question = new Prompt('Keep this association? ([yes]/no) ', 'yes');
                $question->setAutocompleterValues(['yes', 'no']);
                $response = $helper->ask($input, $output, $question);
                if ($response == 'yes') {
                    continue;
                }
            }

            $question = new Prompt('What developer evangelist name should we associate with this choice? ');
            $name = $helper->ask($input, $output, $question);
            $question = new Prompt('What email address should we associate with this developer evangelist? ');
            $email = $helper->ask($input, $output, $question);

            $contact = new Contacts();
            $contact->choice_id = $choice->choice_id;
            $contact->name = $name;
            $contact->email = $email;
            $contact->save();
        }
    }
}