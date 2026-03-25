<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\DataContainer;
use Contao\DC_Table;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;


#[AsCallback(table: 'tl_cc_tag', target: 'fields.customTpl.options')]
#[AsCallback(table: 'tl_cc_tag', target: 'fields.fallbackTpl.options')]
class TemplateOptionsListener {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var Doctrine\DBAL\Connection
     */
    private Connection $connection;

    /**
     * @var Contao\CoreBundle\Twig\Finder\FinderFactory
     */
    private FinderFactory $finderFactory;


    public function __construct( RequestStack $requestStack, Connection $connection, FinderFactory $finderFactory ) {

        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->finderFactory = $finderFactory;
    }


    /**
     * Find the available twig templates
     *
     * @param Contao\DC_Table $dc
     *
     * @return array
     */
    public function __invoke( DC_Table $dc ): array {

        $overrideAll = $this->isOverrideAll();

        $type = $overrideAll
            ? $this->getCommonOverrideAllType($dc)
            : $dc->getActiveRecord()['type'] ?? null;

        if( $type === null ) {
            // Add a blank option that allows to reset all custom templates to the default
            // one when in "overrideAll" mode
            return $overrideAll ? ['' => '-'] : [];
        }

        $identifier = null;
        if( $dc->table === 'tl_cc_tag' && $dc->field === 'customTpl' ) {
            $identifier = 'cc_tag/'.$type;
        } else if( $dc->table === 'tl_cc_tag' && $dc->field === 'fallbackTpl' ) {
            $identifier = 'content_element/cc_optin';
        }

        if( $identifier === null ) {
            return [];
        }

        $templateOptions = $this->finderFactory
            ->create()
            ->identifier((string) $identifier)
            ->extension('html.twig')
            ->withVariants()
            ->excludePartials()
            ->asTemplateOptions()
        ;

        return $templateOptions;
    }


    /**
     * Check if current request is for overrideAll
     *
     * @return bool
     */
    private function isOverrideAll(): bool {

        $request = $this->requestStack->getCurrentRequest();

        if( !$request?->query->has('act') ) {
            return false;
        }

        return 'overrideAll' === $request->query->get('act');
    }


    /**
     * Returns the type that all currently edited items are sharing or null if there
     * is no common type.
     *
     * @param Contao\DataContainer $dc
     *
     * @return string|null
     */
    private function getCommonOverrideAllType( DataContainer $dc ): string|null {

        $affectedIds = $this->requestStack->getSession()->all()['CURRENT']['IDS'] ?? [];
        $table = $this->connection->quoteIdentifier($dc->table);

        $result = $this->connection->executeQuery(
            "SELECT type FROM $table WHERE id IN (?) GROUP BY type LIMIT 2"
        ,   [$affectedIds]
        ,   [ArrayParameterType::STRING]
        );

        if( $result->rowCount() !== 1 ) {
            return null;
        }

        return $result->fetchOne();
    }
}
