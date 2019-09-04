<?php namespace App\Object;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Description extends Eloquent
{
    public $dateOfRequest = '';
    public $eventName = '';
    public $startDate = '';
    public $endDate = '';
    public $url = '';
    public $expectedAttendees = 0;
    public $expectedDeveloperPercentage = 0;
    public $socialMediaLinks = '';
    public $eventType = '';
    public $desc = '';
    public $organizer = '';
    public $organzierEmail = '';
    public $phoneNumber = '';
    public $pastOrganizer = '';
    public $pastPartner = '';
    public $prospectusLink = '';
    public $sponsorshipRequest = '';
    public $venue = '';
    public $location = '';

    public function assembleDescription()
    {
        $description = <<<EOT
Date Request came in: $this->dateOfRequest
Event Name: $this->eventName
Event Start Date: $this->startDate
Event End Date: $this->endDate
Event URL: $this->url
Expected Attendees: $this->expectedAttendees
% Devs: $this->expectedDeveloperPercentage
Social Media Links: $this->socialMediaLinks

About the event: $this->eventType
How would you describe your event?: $this->desc

Organizer -
Name: $this->organizer
Email: $this->organzierEmail
phone number: $this->phoneNumber
Have you organized this event in the past? $this->pastOrganizer
Have you partnered with us in the past? $this->pastPartner

Sponsorship Prospectus:
Can you share a copy of your sponsorship prospectus? $this->prospectusLink
What type of sponsorship are you looking for? $this->sponsorshipRequest

Where is the event:
Venue: $this->venue
Location: $this->location

Other Details here:
Project Data ----------------------------------
Attending Team:  TBD
PO# (if applicable): TBD
City, State: Minneapolis, MN
Org Name and Email:  $this->organizer:$this->organzierEmail
Sponsorship Amount: TBD
Contract Terms: TBD

EOT;
        return $description;
    }
}
