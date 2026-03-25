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

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;


#[AsHook('getSystemMessages')]
class MessagesListener {


    /**
     * @var Doctrine\DBAL\Connection
     */
    private Connection $connection;


    public function __construct( Connection $connection, TranslatorInterface $translator ) {

        $this->connection = $connection;
        $this->translator = $translator;
    }


    public function __invoke(): string {

        $result = $this->connection->executeQuery(
            "SELECT id FROM tl_log WHERE action = ? AND func = ? AND tstamp >= ? LIMIT 1"
        ,   ['ERROR', CookieConsentInsertTag::class . '::__invoke', (time() - 86400)]
        );

        if( $result->rowCount() > 0 ) {
            return '<p class="tl_error">' . $this->translator->trans('ERR.cookie_consent.cms_optinlink_deprecation', [], 'contao_default') . '</p>';
        }


        return '';
    }
}
