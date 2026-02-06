<?php

declare(strict_types=1);

namespace SharpAPI\Core\DTO;

use Carbon\Carbon;

class SubscriptionInfo
{
    /**
     * Initializes a new instance with details regarding the subscription's current period,
     * usage, and status.
     *
     * @param Carbon $timestamp Timestamp of the subscription check.
     * @param bool $on_trial Indicates if the account is on a trial period.
     * @param Carbon $trial_ends Timestamp for the end of the trial period.
     * @param bool $subscribed Indicates if the user is currently subscribed.
     * @param Carbon $current_subscription_start Start timestamp of the current subscription period.
     * @param Carbon $current_subscription_end End timestamp of the current subscription period.
     * @param Carbon $current_subscription_reset Quota reset timestamp of the current subscription period.
     * @param int $subscription_words_quota Total word quota for the current subscription period.
     * @param int $subscription_words_used Number of words used in the current subscription period.
     * @param float $subscription_words_used_percentage Percentage of the word quota used
     *                                                     in the current subscription period.
     * @param int $requests_per_minute Maximum number of API requests allowed per minute.
     */
    public function __construct(
        public Carbon $timestamp,
        public bool   $on_trial,
        public Carbon $trial_ends,
        public bool   $subscribed,
        public Carbon $current_subscription_start,
        public Carbon $current_subscription_end,
        public Carbon $current_subscription_reset,
        public int    $subscription_words_quota,
        public int    $subscription_words_used,
        public float  $subscription_words_used_percentage,
        public int    $requests_per_minute
    )
    {
    }

    /**
     * Converts the subscription information to an associative array.
     *
     * @return array An array containing all subscription details.
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->toDateTimeString(),
            'on_trial' => $this->on_trial,
            'trial_ends' => $this->trial_ends->toDateTimeString(),
            'subscribed' => $this->subscribed,
            'current_subscription_start' => $this->current_subscription_start->toDateTimeString(),
            'current_subscription_end' => $this->current_subscription_end->toDateTimeString(),
            'current_subscription_reset' => $this->current_subscription_reset->toDateTimeString(),
            'subscription_words_quota' => $this->subscription_words_quota,
            'subscription_words_used' => $this->subscription_words_used,
            'subscription_words_used_percentage' => $this->subscription_words_used_percentage,
            'requests_per_minute' => $this->requests_per_minute,
        ];
    }
}
