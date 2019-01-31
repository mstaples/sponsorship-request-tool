This tool pulls info from SurveyMonkey survey submissions, 
* performs an initial analysis of that data using operator defined weights and 
* delivers a formatted report of that analysis as well as selected raw data to the targeted recipient via SendGrid
* in order to facilitate the evaluation of sponsorship requests based on diversity and inclusion standards.

composer install...

Installation
1. create database
2. add database name, user, password to .env
3. add surveymonkey and sendgrid api info to .env
4. add survey_id to .env
    * if you don't yet have the survey_id, you can retrieve it using curl through your console:
```bash
    curl -i -X GET -H "Authorization:bearer YOUR_BEARER_TOKEN" -H "Content-Type": "application/json" https://api.surveymonkey.com/v3/surveys -d '{"title":"YOUR_SURVEY_TITLE"}'
```

5. create the database structure: php command.php db:migrate

Setup
1. pull in the survey structure, questions, choices: php command.php survey:pull-questions
2. set .env question ids
3. associate each survey page with its targetted event type: php command.php survey:associate-page
4. associate developer evangelist options with names and email addresses: php command.php survey:associate-devangels
5. give multiple choice options weights: php command.php survey:weight-options
6. give various levels on slider responses weights: php command.php survey:weight-sliders

Operation
1. pull completed survey submissions: php command.php survey:pull-submissions
2. process survey submissions and send out developer evangelist emails: php command.php survey:process-submissions
3. WIP! update unified event tracking system: php command.php survey:update-hawkeye

Optionally, if you need to change a submissions' slider answers to match their actual line-up run: php command.php survey:update-percentages {submission_id}