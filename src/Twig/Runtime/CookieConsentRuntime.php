<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\Twig\Runtime;

use numero2\CookieConsentBundle\Util\CookieConsentUtil;
use Twig\Extension\RuntimeExtensionInterface;


final class CookieConsentRuntime implements RuntimeExtensionInterface {


    /**
     * @var numero2\CookieConsentBundle\Util\CookieConsentUtil
     */
    private CookieConsentUtil $cookieConsentUtil;


    public function __construct( CookieConsentUtil $cookieConsentUtil ) {

        $this->cookieConsentUtil = $cookieConsentUtil;
    }


    public function isTagAccepted( string|int $tagId ): bool {

        return $this->cookieConsentUtil->isTagAccepted($tagId);
    }


    public function isTagNotAccepted( string|int $tagId ): bool {

        return $this->cookieConsentUtil->isTagNotAccepted($tagId);
    }

    public function generateConsentForceLink( string $id='' ): string {

        return $this->cookieConsentUtil->generateConsentForceLink($id);
    }


}
