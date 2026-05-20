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

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;


#[AsEventListener(event: KernelEvents::RESPONSE)]
class ResponseListener {


    public function __invoke( ResponseEvent $event ): void {

        if( !$event->isMainRequest() ) {
            return;
        }

        $request  = $event->getRequest();
        $response = $event->getResponse();

        $saved = $request->cookies->get('cc_cookies_saved', false);
        $choice = $request->cookies->get('cc_cookies', false);

        if( $saved ) {
            $response->headers->set('X-Cookie-Consent', $choice);
        } else {
            $response->headers->set('X-Cookie-Consent', 'none');
        }

        $response->setVary('X-Cookie-Consent', false);
    }
}
