<?php

namespace Leeovery\LaravelNewsletter;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use GuzzleHttp\Client as HttpClient;
use SendinBlue\Client\Configuration;
use SendinBlue\Client\Api\ContactsApi;
use SendinBlue\Client\Model\CreateContact;
use SendinBlue\Client\Model\UpdateContact;
use SendinBlue\Client\Api\EmailCampaignsApi;
use SendinBlue\Client\Model\AddContactToList;
use SendinBlue\Client\Model\CreateEmailCampaign;
use SendinBlue\Client\Model\RemoveContactFromList;
use Leeovery\LaravelNewsletter\Contracts\Newsletter;
use Leeovery\LaravelNewsletter\Exceptions\LaravelNewsletterException;

class SendInBlueProvider implements Newsletter
{
    /**
     * @var NewsletterListCollection
     */
    private $lists;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var HttpClient
     */
    private $guzzle;

    /**
     * @var array
     */
    private $credentials;

    /**
     * SendInBlueProvider constructor.
     *
     * @param  HttpClient                $guzzle
     * @param  NewsletterListCollection  $lists
     * @param  array                     $credentials
     */
    public function __construct(HttpClient $guzzle, NewsletterListCollection $lists, array $credentials)
    {
        $this->guzzle = $guzzle;
        $this->lists = $lists;
        $this->credentials = $credentials;
        $this->config = Configuration::getDefaultConfiguration()
                                     ->setApiKey('api-key', $credentials['api_key']);
    }

    /**
     * @param  string  $email
     * @return bool
     */
    public function unsubscribe(string $email)
    {
        try {
            $this->getContactsAPIInstance()->updateContact($email,
                new UpdateContact(['emailBlacklisted' => true])
            );

            return true;
        } catch (Exception $e) {
            LaravelNewsletterException::unsubscribeFailed($e->getMessage());
        }

        return false;
    }

    /**
     * @return ContactsApi
     */
    private function getContactsAPIInstance(): ContactsApi
    {
        return new ContactsApi($this->guzzle, $this->config);
    }

    /**
     * @param  string             $email
     * @param  array|string|null  $listNames
     * @return bool
     */
    public function addToLists(string $email, $listNames = null)
    {
        try {

            $this->getListIdsFromNames($listNames)->each(function ($listId) use ($email) {
                $this->getContactsAPIInstance()->addContactToList(
                    $listId,
                    new AddContactToList(['emails' => [$email]])
                );
            });

            return true;
        } catch (Exception $e) {
            LaravelNewsletterException::addToListsFailed($e->getMessage());
        }

        return false;
    }

    /**
     * @param $listNames
     * @return Collection
     */
    private function getListIdsFromNames($listNames): Collection
    {
        return collect(Arr::wrap($listNames ?: ''))->map(function ($name) {
            return $this->lists->findByName($name);
        })->map(function (NewsletterList $list) {
            return (int) $list->getId();
        });
    }

    /**
     * @param  string             $email
     * @param  array|string|null  $listNames
     * @param  array              $attributes
     * @return bool
     */
    public function subscribe(string $email, $listNames = null, array $attributes = [])
    {
        $createContact = new CreateContact([
            'email'            => $email,
            'listIds'          => $this->getListIdsFromNames($listNames)->all(),
            'emailBlacklisted' => false,
            'updateEnabled'    => true,
        ]);

        if (!empty($attributes)) {
            $createContact->setAttributes($attributes);
        }

        try {
            return optional(
                $this->getContactsAPIInstance()->createContact($createContact)
            )->getId() ?: true;
        } catch (Exception $e) {
            LaravelNewsletterException::subscribeFailed($e->getMessage());
        }

        return false;
    }

    /**
     * @param  string             $email
     * @param  array|string|null  $listNames
     * @return bool
     */
    public function removeFromLists(string $email, $listNames = null)
    {
        try {

            $this->getListIdsFromNames($listNames)->each(function ($listId) use ($email) {
                $this->getContactsAPIInstance()->removeContactFromList(
                    $listId,
                    new RemoveContactFromList(['emails' => [$email]])
                );
            });

            return true;
        } catch (Exception $e) {
            LaravelNewsletterException::removeFromListsFailed($e->getMessage());
        }

        return false;
    }

    /**
     * @param  string             $campaignName
     * @param  string             $fromEmail
     * @param  string             $fromName
     * @param  string             $htmlContent
     * @param  string             $subject
     * @param  string             $replyTo
     * @param  string|array|null  $listNames
     * @param  Carbon|null        $scheduledAt
     * @return bool
     */
    public function sendCampaign(
        string $campaignName,
        string $fromEmail,
        string $fromName,
        string $htmlContent,
        string $subject,
        string $replyTo,
        $listNames = null,
        Carbon $scheduledAt = null
    ) {
        try {
            $this->getCampaignAPIInstance()->createEmailCampaign(
                new CreateEmailCampaign([
                    'name'        => $campaignName,
                    'sender'      => [
                        'email' => $fromEmail,
                        'name'  => $fromName,
                    ],
                    'recipients'  => [
                        'listIds' => $this->getListIdsFromNames($listNames)->all(),
                    ],
                    'htmlContent' => $htmlContent,
                    'subject'     => $subject,
                    'replyTo'     => $replyTo,
                    'scheduledAt' => value($scheduledAt ?? now()->addMinutes(10))->toISOString(),
                ])
            );

            return true;
        } catch (Exception $e) {
            LaravelNewsletterException::sendCampaignFailed($e->getMessage());
        }

        return false;
    }

    /**
     * @return EmailCampaignsApi
     */
    private function getCampaignAPIInstance(): EmailCampaignsApi
    {
        return new EmailCampaignsApi($this->guzzle, $this->config);
    }

    /**
     * @param  string  $oldEmail
     * @param  string  $newEmail
     * @return bool
     */
    public function updateEmailAddress(string $oldEmail, string $newEmail)
    {
        try {
            $this->getContactsAPIInstance()->updateContact($oldEmail,
                new UpdateContact([
                    'attributes' => [
                        'EMAIL' => $newEmail,
                    ],
                ])
            );

            return true;
        } catch (Exception $e) {
            LaravelNewsletterException::updateEmailAddressFailed($e->getMessage());
        }

        return false;
    }

    public function getApi()
    {
        return [
            'ContactsApi'       => $this->getContactsAPIInstance(),
            'EmailCampaignsApi' => $this->getCampaignAPIInstance(),
        ];
    }
}