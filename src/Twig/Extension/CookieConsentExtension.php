<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\Twig\Extension;

use numero2\CookieConsentBundle\Twig\Runtime\CookieConsentRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


final class CookieConsentExtension extends AbstractExtension {


    public function getFunctions(): array {

        return [
            new TwigFunction(
                'cc_tag_accepted',
                [CookieConsentRuntime::class, 'isTagAccepted'],
            ),
            new TwigFunction(
                'cc_tag_not_accepted',
                [CookieConsentRuntime::class, 'isTagNotAccepted'],
            ),
            new TwigFunction(
                'cc_consent_force_link',
                [CookieConsentRuntime::class, 'generateConsentForceLink'],
            ),
        ];
    }
}