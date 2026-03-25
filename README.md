# Contao Cookie Consent Bundle

[![Packagist Version](https://img.shields.io/packagist/v/numero2/contao-cookie-consent.svg?style=flat-square)](https://packagist.org/packages/numero2/contao-cookie-consent)
[![License: LGPL v3](https://img.shields.io/badge/License-LGPL%20v3-blue.svg?style=flat-square)](http://www.gnu.org/licenses/lgpl-3.0)

A cookie consent solution for Contao CMS - the drop-in replacement for the cookie consent functionality of [Contao Marketing Suite](https://github.com/numero2/contao-marketing-suite).

---

## Screenshot

![Cookie Consent in Light and Dark mode](./docs/screenshot-consent.png)

---

## Requirements

- [Contao](https://github.com/contao/contao) **5.7 or newer**

---

## Installation

Via **Contao Manager** or **Composer**:

```bash
composer require numero2/contao-cookie-consent
```

---

## Insert Tags

Use these insert tags directly in your Contao templates and content elements to control cookie consent dependent output.

| Tag                | Description                                                                                                                                                                      |
|--------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `{{cc_optinlink}}` | Renders a link that reopens the cookie consent dialog, allowing the user to adjust their consent. After confirming, the page scrolls back to the original element automatically. |
| `{{ifoptin::*}}`   | Wraps content that is only shown if the user **has accepted** the specified tag. Replace `*` with the tag ID.                                                                    |
| `{{ifnoptin::*}}`  | Wraps content that is only shown if the user **has not accepted** the specified tag. Replace `*` with the tag ID.                                                                |

### Examples

```
{{ifoptin::6}}<p>You have accepted ACME cookies.</p>{{ifoptin}}

{{ifnoptin::6}}<p>Please accept ACME cookies to see this content.</p>{{ifnoptin}}
```

---

## Styling

### Colors

Colors can be customized via CSS custom properties. Example:

```css
cc-cookie-consent, cc-cookie-optin {
    --cc-accent: #f47c00;
}
```

> A full list of available CSS custom properties can be found in [contao/templates/styles/cc_default.html.twig](contao/templates/styles/cc_default.html.twig).

### Color scheme

By default, both elements follow the color scheme defined by the user's operating system or browser.
If your page layout requires a specific scheme, it can be enforced via a custom template:

```twig
{% extends '@Contao/frontend_module/cc_cookie_consent.html.twig' %}

{% set attributes = attrs(attributes|default)
    .set('color-scheme', 'light')
%}
```

---

## Developer API

This bundle exposes helper utilities to check cookie consent status programmatically — both in PHP/Symfony and in Twig templates.

### PHP / Symfony

Inject the `numero2_cookie_consent.util.cookie_consent` service into your controller or service:

```php
// src/Controller/MyCustomController.php
namespace App\Controller;

use numero2\CookieConsentBundle\Util\CookieConsentUtil;

class MyCustomController
{
    public function __construct(
        private readonly CookieConsentUtil $cookieConsentUtil
    ) {}

    public function __invoke(): void
    {
        // Returns true if the tag with ID 6 has been accepted
        $this->cookieConsentUtil->isTagAccepted(6);

        // Returns true if the tag with ID 6 has NOT been accepted
        $this->cookieConsentUtil->isTagNotAccepted(6);

        // Generates a link that triggers the consent dialog
        $this->cookieConsentUtil->generateConsentForceLink();
    }
}
```

### Twig

Three global Twig functions are available:

```twig
{# Show content only if tag ID 6 was accepted #}
{% if cc_tag_accepted(6) %}
    <p>Analytics is enabled.</p>
{% endif %}

{# Show content only if tag ID 6 was NOT accepted #}
{% if cc_tag_not_accepted(6) %}
    <p>Please enable Analytics to see this content.</p>
{% endif %}

{# Render a link that opens the consent dialog (optional: custom CSS ID) #}
{{ cc_consent_force_link('customCSSId') }}
```

---

## Disclaimer

### Cookie storage 🍪

This bundle manages cookie consent on behalf of your Contao installation. Any cookies set during the consent process belong to **your website** — no data is transmitted to or stored by us.

The following cookies are set by this bundle:

| Cookie             | Value                 | Purpose                                              |
|--------------------|-----------------------|------------------------------------------------------|
| `cc_cookies_saved` | `true` / `false`      | Stores whether the user has made a consent decision. |
| `cc_cookies`       | List of tag group IDs | Stores which tag groups the user has accepted.       |

### Legal stuff ⚖️

We cannot guarantee complete legal compliance for your specific use case. Use this bundle at your own risk, or consult a lawyer if you are unsure about your configuration. We are happy to help with **technical questions**, but please understand that we cannot provide legal advice.