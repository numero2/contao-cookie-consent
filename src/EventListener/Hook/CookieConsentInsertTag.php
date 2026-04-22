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

use Contao\CoreBundle\DependencyInjection\Attribute\AsInsertTag;
use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use Contao\CoreBundle\InsertTag\Resolver\InsertTagResolverNestedResolvedInterface;
use numero2\CookieConsentBundle\Util\CookieConsentUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;


#[AsInsertTag('cc_optinlink')]
#[AsInsertTag('cms_optinlink')]
class CookieConsentInsertTag implements InsertTagResolverNestedResolvedInterface {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var numero2\CookieConsentBundle\Util\CookieConsentUtil
     */
    private CookieConsentUtil $cookieConsentUtil;

    /**
     * @var Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;


    public function __construct( RequestStack $requestStack, CookieConsentUtil $cookieConsentUtil, LoggerInterface $logger ) {

        $this->requestStack = $requestStack;
        $this->cookieConsentUtil = $cookieConsentUtil;
        $this->logger = $logger;
    }


    public function __invoke( ResolvedInsertTag $insertTag ): InsertTagResult {

        if( $insertTag->getName() === 'cms_optinlink' ) {

            $request = $this->requestStack->getCurrentRequest();
            $this->logger->error('Unknown insert tag "{{'.$insertTag->getName().'}}" on page '.$request->getUri());
        }

        return new InsertTagResult($this->cookieConsentUtil->generateConsentForceLink($insertTag->getParameters()->get(0) ?? ''));
    }
}
