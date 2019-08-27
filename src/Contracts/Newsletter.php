<?php

namespace Leeovery\LaravelNewsletter\Contracts;

use Illuminate\Support\Carbon;

interface Newsletter
{
    /**
     * @param  string             $email
     * @param  array|string|null  $listNames
     * @param  array              $attributes
     * @return bool
     */
    public function subscribe(string $email, $listNames = null, array $attributes = []);

    /**
     * @param  string  $email
     * @return bool
     */
    public function unsubscribe(string $email);

    /**
     * @param  string             $email
     * @param  array|string|null  $listNames
     * @return bool
     */
    public function addToLists(string $email, $listNames = null);

    /**
     * @param  string             $email
     * @param  array|string|null  $listNames
     * @return bool
     */
    public function removeFromLists(string $email, $listNames = null);

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
    );

    /**
     * @param  string  $oldEmail
     * @param  string  $newEmail
     * @return bool
     */
    public function updateEmailAddress(string $oldEmail, string $newEmail);

    public function getApi();
}