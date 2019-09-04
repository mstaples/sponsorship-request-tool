<?php namespace App\Command;

use Illuminate\Database\Schema\Blueprint;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Illuminate\Database\Capsule\Manager as Db;

class MigrateDatabaseTablesCommand extends Command
{
    protected $client;

    // the name of the command (the part after "php command.php")
    protected static $defaultName = 'db:migrate';

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
        // Create the "conditions" table
        if (!Db::schema()->hasTable('conditions')) {
            Db::schema()->create('conditions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('question_question_id');
                $table->string('condition_question_id');
                $table->string('condition_state');
                $table->string('condition_choice');
                $table->timestamps();
            });
        }

        // Create the "questions" table
        if (!Db::schema()->hasTable('questions')) {
            Db::schema()->create('questions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('question');
                $table->string('question_id')->unique();
                $table->string('prompt_type');
                $table->string('prompt_subtype');
                $table->boolean('conditional');
                $table->integer('min')->nullable();
                $table->integer('max')->nullable();
                $table->timestamps();
            });
        }

        // Create the "choices" table
        if (!Db::schema()->hasTable('choices')) {
            Db::schema()->create('choices', function (Blueprint $table) {
                $table->increments('id');
                $table->string('question_question_id');
                $table->string('choice_id')->unique();
                $table->string('choice');
                $table->integer('weight')->nullable();
                $table->timestamps();

                $table->foreign('question_question_id')
                    ->references('question_id')->on('questions')
                    ->onDelete('cascade');
            });
        }

        // Create the "levels" table
        if (!Db::schema()->hasTable('levels')) {
            Db::schema()->create('levels', function (Blueprint $table) {
                $table->increments('id');
                $table->string('question_question_id');
                $table->integer('level');
                $table->integer('minimum');
                $table->timestamps();

                $table->foreign('question_question_id')
                    ->references('question_id')->on('questions')
                    ->onDelete('cascade');
            });
        }

        // Create the "submissions" table
        if (!Db::schema()->hasTable('submissions')) {
            Db::schema()->create('submissions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('respondent_id')->unique();
                $table->string('teamwork_project_id')->nullable();
                $table->string('teamwork_content')->nullable();
                $table->longText('teamwork_description')->nullable();
                $table->string('event_type')->nullable();
                $table->string('event_name')->nullable();
                $table->string('total_time');
                $table->string('date_modified');
                $table->string('state');
                $table->string('devangel_email')->nullable();
                $table->string('devangel_name')->nullable();
                $table->string('last_email')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('contact_phone')->nullable();
                $table->boolean('minimums');
                $table->boolean('shenanigans');
                $table->integer('commitments');
                $table->integer('speaker_count');
                $table->integer('attendee_estimate');
                $table->integer('developer_estimate');
                $table->integer('score');
                $table->integer('max_score');
                $table->integer('recommended_level');
                $table->integer('recommended_cash');
                $table->integer('requests');
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();
                $table->timestamps();
            });
        }

        // Create the "answers" table
        if (!Db::schema()->hasTable('answers')) {
            Db::schema()->create('answers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('submission_respondent_id');
                $table->string('question');
                $table->string('question_id');
                $table->string('choice_id')->nullable();
                $table->text('answer');
                $table->timestamps();

                $table->foreign('submission_respondent_id')
                    ->references('respondent_id')->on('submissions')
                    ->onDelete('cascade');
            });
        }

        // Create the "contacts" table
        if (!Db::schema()->hasTable('contacts')) {
            Db::schema()->create('contacts', function (Blueprint $table) {
                $table->increments('id');
                $table->string('choice_id')->unique();
                $table->string('name');
                $table->string('email');
                $table->string('teamwork_id')->nullable();
                $table->timestamps();
            });
        }
    }
}
