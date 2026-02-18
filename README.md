![SharpAPI GitHub cover](https://sharpapi.com/sharpapi-github-laravel-bg.jpg "SharpAPI Laravel Client")

# SharpAPI.com PHP Core functionalities & communication

## 🚀 Automate workflows with AI-powered API

### Leverage AI API to streamline workflows in E-Commerce, Marketing, Content Management, HR Tech, Travel, and more.

See more at [SharpAPI.com Website &raquo;](https://sharpapi.com/)

---

## Quota Method

The `quota()` method returns a `SubscriptionInfo` DTO with the following fields:

| Field | Type | Description |
|---|---|---|
| `timestamp` | `Carbon` | Timestamp of the subscription check |
| `on_trial` | `bool` | Whether the account is on a trial period |
| `trial_ends` | `Carbon` | End date of the trial period |
| `subscribed` | `bool` | Whether the user is currently subscribed |
| `current_subscription_start` | `Carbon` | Start of the current subscription period |
| `current_subscription_end` | `Carbon` | End of the current subscription period |
| `current_subscription_reset` | `Carbon` | Quota reset timestamp |
| `subscription_words_quota` | `int` | Total word quota for the period |
| `subscription_words_used` | `int` | Words used in the current period |
| `subscription_words_used_percentage` | `float` | Percentage of word quota used |
| `requests_per_minute` | `int` | Maximum API requests allowed per minute |

```php
$client = new SharpApiClient('your-api-key');
$quota = $client->quota();

echo $quota->subscription_words_quota;
echo $quota->requests_per_minute;
```

---

## Two-Layer Rate Limiting Architecture

The SDK uses a two-layer approach to keep your requests within API limits:

| Layer | Type | How it works |
|---|---|---|
| **Native Throttling** | Proactive (client-side) | Sliding window rate limiter blocks outgoing requests *before* they hit the server |
| **429 Retry & Adaptive Polling** | Reactive (server-side) | Catches HTTP 429 responses, retries with backoff, and slows polling when limits are low |

The proactive layer prevents most 429 errors from ever occurring. The reactive layer acts as a safety net for edge cases (clock drift, concurrent processes, plan changes).

---

## Native Throttling

Every `SharpApiClient` instance includes an in-memory **sliding window rate limiter** (`SlidingWindowRateLimiter`) that tracks request timestamps in a 60-second rolling window.

Before each API call, `waitIfNeeded()` checks the window. If capacity is available the request proceeds immediately; if not, the call sleeps until the oldest timestamp expires out of the window.

- **Default limit:** 60 requests/minute (matches most subscription plans)
- **Per-process, in-memory** — no external dependencies (Redis, database, etc.)
- **Server-adaptive** — when the server returns a higher `X-RateLimit-Limit` header (e.g. after a plan upgrade), the limiter automatically ratchets up to match. It never ratchets *down*, so your configured default is always the floor.
- **Metadata bypass** — `ping()` and `quota()` skip throttling to avoid deadlocks when checking connectivity or subscription status.

### Configuration

```php
$client = new SharpApiClient('your-api-key');

// Change the throttle limit (default: 60)
$client->setRequestsPerMinute(120);

// Disable throttling entirely (pass 0)
$client->setRequestsPerMinute(0);
```

### Advanced Throttling Controls

You can access the underlying `SlidingWindowRateLimiter` instance directly for fine-grained control:

```php
$limiter = $client->getRateLimiter();

// Non-blocking check — returns true if a request can proceed without waiting
$limiter->canProceed();

// How many requests are available in the current window
$limiter->remaining();       // e.g. 42
```

**Cross-process state caching** — save and restore the server-reported rate limit state (e.g. between HTTP requests in a web app):

```php
// After an API call, cache the server-reported state
$state = $client->getRateLimitState();
// $state = ['limit' => 60, 'remaining' => 58]
cache()->put('sharpapi_rate_state', $state, 60);

// In the next process/request, restore it
$client->setRateLimitState(cache()->get('sharpapi_rate_state'));
```

**Quick quota check** — verify the server still reports available capacity before making a request:

```php
if ($client->canMakeRequest()) {
    // Server-reported remaining > 0 (or no server data yet)
}
```

---

## Automatic 429 Retry & Adaptive Polling

If a request does hit the server rate limit, the SDK handles it automatically:

1. **Retry automatically** — reads the `Retry-After` header, sleeps for the specified duration, and retries the request (up to 3 times by default).
2. **Slow down polling** — during `fetchResults()`, when `X-RateLimit-Remaining` drops below the low threshold, polling intervals are automatically increased to avoid hitting the limit.

### Inspecting Rate-Limit State

After any API call, you can check the current rate-limit values:

```php
$client = new SharpApiClient('your-api-key');
$client->ping();

echo $client->getRateLimitLimit();     // e.g. 60 (requests per window)
echo $client->getRateLimitRemaining(); // e.g. 58 (remaining in current window)
```

> **Note:** `getRateLimitLimit()` and `getRateLimitRemaining()` return `null` before the first API call or after endpoints that don't return rate-limit headers (e.g. `/ping`, `/quota`).

### Configuration

```php
// Max automatic retries on HTTP 429 (default: 3)
$client->setMaxRetryOnRateLimit(5);

// Threshold below which polling intervals are increased (default: 3)
$client->setRateLimitLowThreshold(5);
```

When `rateLimitRemaining` is at or below the threshold, polling intervals in `fetchResults()` are multiplied by an increasing factor (2x at threshold, growing as remaining approaches 0). This helps avoid 429 errors during long-running job polling.

---

## Credits

- [A2Z WEB LTD](https://github.com/a2zwebltd)
- [Dawid Makowski](https://github.com/makowskid)
- Boost your [Laravel AI](https://sharpapi.com/) capabilities!

---

## License

The MIT License (MIT).

---
## Social Media

🚀 For the latest news, tutorials, and case studies, don't forget to follow us on:
- [SharpAPI X (formerly Twitter)](https://x.com/SharpAPI)
- [SharpAPI YouTube](https://www.youtube.com/@SharpAPI)
- [SharpAPI Vimeo](https://vimeo.com/SharpAPI)
- [SharpAPI LinkedIn](https://www.linkedin.com/products/a2z-web-ltd-sharpapicom-automate-with-aipowered-api/)
- [SharpAPI Facebook](https://www.facebook.com/profile.php?id=61554115896974)
