<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\EventListener\KernelResponse;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;


class CookieConsentHeaderListener {


    /**
     * @var Contao\CoreBundle\Routing\ScopeMatcher
     */
    private ScopeMatcher $scopeMatcher;


    public function __construct( ScopeMatcher $scopeMatcher ) {

        $this->scopeMatcher = $scopeMatcher;
    }


    /**
     * Add content of our cookie `cc_cookies` as header 'contao-cookie-consent` so we can remove the cookie from the request.
     * With this an the added vary we can cache the pages according to the tag selection.
     * The priority is specifically set to this value to ensure that `Symfony\Component\HttpKernel\EventListener\RouterListener`
     * already resolved the scope of the request.
     *
     * @param Symfony\Component\HttpKernel\Event\RequestEvent $event
     */
    #[AsEventListener(event: 'kernel.request', priority: 30)]
    public function onKernelRequest( RequestEvent $event ): void {

        $request = $event->getRequest();

        if( !$this->scopeMatcher->isFrontendRequest($request) ) {
            return;
        }

        if( !$request->headers->has('contao-cookie-consent') ) {

            $consent = $request->cookies->get('cc_cookies');
            $request->cookies->remove('cc_cookies');

            $request->headers->set('contao-cookie-consent', $consent);
        }
    }


    /**
     * Always add out vary header field `contao-cookie-consent` if the header is set.
     *
     * @param Symfony\Component\HttpKernel\Event\ResponseEvent $event
     */
    #[AsEventListener(event: 'kernel.response')]
    public function onKernelResponse( ResponseEvent $event ): void {

        if( !$event->isMainRequest() ) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if( $request->headers->has('contao-cookie-consent') ) {
            $response->setVary('Contao-Cookie-Consent', false);
        }
    }
}
