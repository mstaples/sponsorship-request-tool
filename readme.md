1. create database
2. add database name, user, password to .env
3. add surveymonkey and sendgrid api info to .env
4. add survey_id to .env
5. create the database structure: php command.php db:migrate
6. pull in the survey structure, questions, choices: php command.php survey:pull-questions
7. set .env question ids
8. associate each survey page with its targetted event type: php command.php survey:associate-page
9. associate developer evangelist options with names and email addresses: php command.php survey:associate-devangels
10. give multiple choice options weights: php command.php survey:weight-options
11. give various levels on slider responses weights: php command.php survey:weight-sliders
12. pull completed survey submissions: php command.php survey:pull-submissions
13: process survey submissions and send out developer evangelist emails: php command.php survey:process-submissions
14. WIP! update unified event tracking system: php command.php survey:update-hawkeye

Optionally, if you need to change a submissions' slider answers to match their actual line-up run: php command.php survey:update-percentages {submission_id}