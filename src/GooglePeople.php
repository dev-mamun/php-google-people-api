<?php

namespace RapidWeb\GooglePeopleAPI;

use Exception;

class GooglePeople
{
    private $googleOAuth2Handler;

    const PERSON_FIELDS = ['addresses', 'ageRanges', 'biographies', 'birthdays', 'braggingRights', 'coverPhotos', 'emailAddresses', 'events', 'genders', 'imClients', 'interests', 'locales', 'memberships', 'metadata', 'names', 'nicknames', 'occupations', 'organizations', 'phoneNumbers', 'photos', 'relations', 'relationshipInterests', 'relationshipStatuses', 'residences', 'skills', 'taglines', 'urls'];
    const UPDATE_PERSON_FIELDS = ['addresses', 'biographies', 'birthdays', 'braggingRights', 'emailAddresses', 'events', 'genders', 'imClients', 'interests', 'locales', 'names', 'nicknames', 'occupations', 'organizations', 'phoneNumbers', 'relations', 'residences', 'skills', 'urls'];
    const PEOPLE_BASE_URL = 'https://people.googleapis.com/v1/';

    public function __construct(GoogleOAuth2Handler $googleOAuth2Handler)
    {
        $this->googleOAuth2Handler = $googleOAuth2Handler;
    }

    private function convertResponseConnectionToContact($connection)
    {
        $contact = new Contact($this);
        $contact->resourceName = $connection->resourceName;
        $contact->etag = $connection->etag;
        $contact->metadata = $connection->metadata;

        foreach(self::PERSON_FIELDS as $personField) {
            if (isset($connection->$personField)) {
                $contact->$personField = $connection->$personField;
            } else {
                $contact->$personField = [];
            }
        }

        return $contact;
    }

    public function get($resourceName)
    {
        $url = self::PEOPLE_BASE_URL.$resourceName.'?personFields='.implode(',', self::PERSON_FIELDS);

        $response = $this->googleOAuth2Handler->performRequest('GET', $url);
        $body = (string) $response->getBody();

        if ($response->getStatusCode()!=200) {
            throw new Exception($body);
        }

        $contact = json_decode($body);

        return $this->convertResponseConnectionToContact($contact);
    }

    public function all()
    {
        $url = self::PEOPLE_BASE_URL.'people/me/connections?personFields='.implode(',', self::PERSON_FIELDS).'&pageSize=2000';

        $response = $this->googleOAuth2Handler->performRequest('GET', $url);
        $body = (string) $response->getBody();

        if ($response->getStatusCode()!=200) {
            throw new Exception($body);
        }

        $responseObj = json_decode($body);

        $contacts = [];

        foreach($responseObj->connections as $connection) {
            $contacts[] = $this->convertResponseConnectionToContact($connection);
        }

        while(isset($responseObj->nextPageToken)) {

            $url = self::PEOPLE_BASE_URL.'people/me/connections?personFields='.implode(',', self::PERSON_FIELDS).'&pageSize=2000&pageToken='.$responseObj->nextPageToken;

            $response = $this->googleOAuth2Handler->performRequest('GET', $url);
            $body = (string) $response->getBody();

            if ($response->getStatusCode()!=200) {
                throw new Exception($body);
            }

            $responseObj = json_decode($body);

            foreach($responseObj->connections as $connection) {
                $contacts[] = $this->convertResponseConnectionToContact($connection);
            }
        }

        return $contacts;
    }

    public function me()
    {
        return $this->getByResourceName('people/me');
    }

    public function save(Contact $contact)
    {
        $url = self::PEOPLE_BASE_URL.$contact->resourceName.':updateContact?updatePersonFields='.implode(',', self::UPDATE_PERSON_FIELDS);

        $patchObj = new \stdClass();
        $patchObj->etag = $contact->etag;
        $patchObj->metadata = $contact->metadata;

        foreach(self::UPDATE_PERSON_FIELDS as $personField) {
            $patchObj->$personField = $contact->$personField;
        }

        $patchBody = json_encode($patchObj);

        $response = $this->googleOAuth2Handler->performRequest('PATCH', $url, $patchBody);
        $body = (string) $response->getBody();

        if ($response->getStatusCode()!=200) {
            throw new Exception($body);
        }

        $responseObj = json_decode($body);

        return $this->convertResponseConnectionToContact($responseObj);
    }
}