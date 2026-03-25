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

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\ModuleModel;
use Contao\System;
use Doctrine\DBAL\Connection;
use numero2\CookieConsentBundle\TagModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;


class ModuleListener {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var Contao\CoreBundle\Routing\ScopeMatcher
     */
    private ScopeMatcher $scopeMatcher;

    /**
     * @var Doctrine\DBAL\Connection
     */
    private Connection $connection;

    /**
     * @var Symfony\Contracts\Translation\TranslatorInterface
     */
    private TranslatorInterface $translator;


    public function __construct( RequestStack $requestStack, ScopeMatcher $scopeMatcher, Connection $connection, TranslatorInterface $translator ) {

        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->connection = $connection;
        $this->translator = $translator;
    }


    /**
     * Add additional fields to palette for tag visibility
     *
     * @param Contao\DataContainer $dc
     */
    #[AsCallback('tl_content', target: 'config.onload')]
    #[AsCallback('tl_module', target: 'config.onload')]
    public function addTagVisibilityFields( DataContainer $dc ): void {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || $this->scopeMatcher->isFrontendRequest($request) ) {
            return;
        }

        $pm = PaletteManipulator::create()
            ->addLegend('cc_tag_visibility_legend', 'expert_legend', PaletteManipulator::POSITION_AFTER)
            ->addField(['cc_tag_visibility'], 'cc_tag_visibility_legend', PaletteManipulator::POSITION_APPEND)
        ;

        foreach( $GLOBALS['TL_DCA'][$dc->table]['palettes'] as $name => $palette ) {

            if( in_array($name, ['__selector__', 'default']) ) {
                continue;
            }
            if( in_array($name, ['cc_cookie_consent', 'alias', 'module']) ) {
                continue;
            }

            $pm->applyToPalette($name, $dc->table);
        }
    }


    /**
     * Gather all tags with a certain type
     *
     * @param Contao\DataContainer $dc
     *
     * @return array
     */
    #[AsCallback('tl_content', target: 'fields.cc_tag.options')]
    #[AsCallback('tl_module', target: 'fields.cc_tag.options')]
    public function getContentElementTags( DataContainer $dc ): array {

        $t = TagModel::getTable();
        $tags = $this->connection->executeQuery(
            "SELECT id, name FROM $t WHERE type=:type"
        ,   ['type'=>'content_module_element']
        )->fetchAllKeyValue();

        return $tags;
    }


    /**
     * Prepends this listener's tag visibility label callback to tl_content label callbacks.
     *
     * @param Contao\DataContainer $dc
     */
    #[AsCallback(table: 'tl_content', target: 'config.onload')]
    public function prependTagVisibilityLabelCallback( DataContainer $dc ): void {

        array_unshift(
            $GLOBALS['TL_DCA'][$dc->table]['list']['label']['label_callback']
        ,   'numero2_cookie_consent.listener.data_container.module'
        ,   'appendTagVisibilityToTypeLabel'
        );
    }


    /**
     * Add info if an element is only visible by optin
     *
     * @param array $arrRow
     * @param string $label
     * @param Contao\DataContainer $dc
     * @param array $labels
     *
     * @return array
     */
    public function appendTagVisibilityToTypeLabel( array $arrRow, string $label, DataContainer $dc, array $labels ): array {

        $t = $dc->table;
        $labelConfig = &$GLOBALS['TL_DCA'][$t]['list']['label'];

        // execute previous child record callbacks
        if( count($labelConfig['label_callback']) > 2 ) {
            $labels = System::importStatic($labelConfig['label_callback'][2])->{$labelConfig['label_callback'][3]}($arrRow, $label, $dc, $labels);
        }

        if( $arrRow['type'] === 'module' ) {

            $module = ModuleModel::findOneById($arrRow['module']);

            if( $module ) {
                $arrRow = $module->row();
            }
        }

        if( $labels && $arrRow['cc_tag_visibility'] ) {

            $labels[0] .= sprintf(
                '<span class="tl_gray cc_optin">%s</span>'
            ,   $this->translator->trans('MSC.cookie_consent.backend.element_optin', [$arrRow['cc_tag']], 'contao_default')
            );
        }

        return $labels;
    }
}