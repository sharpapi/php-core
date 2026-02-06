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
