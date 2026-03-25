<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\EventListener\Hook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsBlockInsertTag;
use Contao\CoreBundle\InsertTag\Exception\InvalidInsertTagException;
use Contao\CoreBundle\InsertTag\ParsedSequence;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\CoreBundle\InsertTag\Resolver\BlockInsertTagResolverNestedResolvedInterface;
use numero2\CookieConsentBundle\Util\CookieConsentUtil;
use Symfony\Component\HttpFoundation\RequestStack;


#[AsBlockInsertTag('ifoptin', endTag: 'ifoptin')]
#[AsBlockInsertTag('ifnoptin', endTag: 'ifnoptin')]
class IfOptinInsertTag implements BlockInsertTagResolverNestedResolvedInterface {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var numero2\CookieConsentBundle\Util\CookieConsentUtil
     */
    private CookieConsentUtil $cookieConsentUtil;


    public function __construct( RequestStack $requestStack, CookieConsentUtil $cookieConsentUtil ) {

        $this->requestStack = $requestStack;
        $this->cookieConsentUtil = $cookieConsentUtil;
    }


    public function __invoke( ResolvedInsertTag $insertTag, ParsedSequence $wrappedContent ): ParsedSequence {

        $inverse = 'ifoptin' !== $insertTag->getName();

        $tagId = intval($insertTag->getParameters()->get(0));
        if( empty($tagId) ) {
            throw new InvalidInsertTagException(\sprintf('Missing tag id parameter in %s insert tag', $insertTag->getName()));
        }

        if( $this->cookieConsentUtil->isTagAccepted($tagId) ) {
            return $inverse ? new ParsedSequence([]) : $wrappedContent;
        }

        return $inverse ? $wrappedContent : new ParsedSequence([]);
    }
}
