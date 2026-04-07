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

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Exception;
use numero2\CookieConsentBundle\TagModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;


class TagListener {


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
     * @var Symfony\Bundle\SecurityBundle\Security
     */
    private Security $security;

    /**
     * @var Symfony\Contracts\Translation\TranslatorInterface
     */
    private TranslatorInterface $translator;

    /**
     * @var array
     */
    private array|null $labelsPageCache;


    public function __construct( RequestStack $requestStack, ScopeMatcher $scopeMatcher, Connection $connection, Security $security, TranslatorInterface $translator ) {

        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->connection = $connection;
        $this->security = $security;
        $this->translator = $translator;
        $this->labelsPageCache = null;
    }


    /**
     * Make new root level tags group
     *
     * @param Contao\DataContainer $dc
     */
    #[AsCallback('tl_cc_tag', target: 'config.onload')]
    public function setRootType( DataContainer $dc ): void {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || $this->scopeMatcher->isFrontendRequest($request) ) {
            return;
        }

        if( Input::get('act') != 'create' ) {
            return;
        }

        // Insert into
        if( Input::get('pid') == 0 ) {

            $GLOBALS['TL_DCA']['tl_cc_tag']['fields']['type']['default'] = 'group';

        } elseif( Input::get('mode') == 1 ) {

            $t = $dc->table;
            $page = $this->connection->executeQuery(
                "SELECT * FROM $t WHERE id=:id LIMIT 1"
            ,   ['id'=>Input::get('pid')]
            )->fetchAssociative();

            if( $page['pid'] === 0 ) {
                $GLOBALS['TL_DCA']['tl_cc_tag']['fields']['type']['default'] = 'group';
            }
        }
    }


    /**
     * Adjust the cut tag button
     *
     * @param Contao\CoreBundle\DataContainer\DataContainerOperation $operation
     */
    #[AsCallback('tl_cc_tag', target: 'list.operations.cut.button')]
    public function cutTagButton( DataContainerOperation $operation ): void {

        $row = $operation->getRecord();
        if( !empty($row['root']) ) {
            $operation->disable();
        }
    }


    /**
     * Return the paste page button
     *
     * @param Contao\DataContainer $dc
     * @param array $row
     * @param string $table
     * @param boolean $cr
     * @param array $clipboard
     *
     * @return string
     */
    #[AsCallback('tl_cc_tag', target: 'list.sorting.paste_button')]
    public function pasteTagButton( DataContainer $dc, $row, $table, $cr, $clipboard=null ): string {

        $disableAfter = false;
        $disableInto = false;

        // disable all buttons if there is a circular reference
        if( $clipboard !== false && ($clipboard['mode'] == 'cut' && ($cr == 1 || $clipboard['id'] == $row['id']) || $clipboard['mode'] == 'cutAll' && ($cr == 1 || \in_array($row['id'], $clipboard['id']))) ) {
            $disableAfter = true;
            $disableInto = true;
        }

        // only support root level and level 1
        if( ($clipboard['mode']??null) === 'create' && !empty($row['pid']) ) {
            $disableInto = true;
        }

        // only support past in same level
        if( ($clipboard['mode']??null) !== 'create' ) {

            $tag = $this->connection->executeQuery(
                "SELECT * FROM $table WHERE id=:id LIMIT 1"
            ,   ['id'=>$clipboard['id']]
            )->fetchAssociative();

            if( $tag['pid'] === 0 ) {

                if( !array_key_exists('pid', $row) ) {

                    $disableInto = false;
                    $disableAfter = true;

                } else if( array_key_exists('pid', $row) && $row['pid'] == '0' ) {

                    $disableInto = true;

                } else {
                    $disableInto = true;
                    $disableAfter = true;
                }

            } else {

                if( array_key_exists('pid', $row) && $row['pid'] == '0' ) {
                    $disableAfter = true;
                } else {
                    $disableInto = true;
                }
            }
        }

        // prevent interacting with root-specific groups
        if( !empty($row['root']) ) {
            $disableAfter = true;
            $disableInto = true;
        }

        $return = '';

        // return the buttons
        $imagePasteAfter = Image::getHtml('pasteafter.svg', $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default'));
        $imagePasteInto = Image::getHtml('pasteinto.svg', $this->translator->trans('DCA.pasteinto.1', [$row['id']], 'contao_default'));

        $disableSuffix = '--disabled';

        if( !empty($row['id']) && $row['id'] > 0 ) {
            $return = $disableAfter ? Image::getHtml('pasteafter'.$disableSuffix.'.svg').' ' : '<a href="'.Backend::addToUrl('act='.$clipboard['mode'].'&amp;mode=1&amp;pid='.$row['id'].(!\is_array($clipboard['id']) ? '&amp;id='.$clipboard['id'] : '')).'" title="'.StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['DCA']['pasteafter'][1], $row['id'])).'" data-action="contao--scroll-offset#store">'.$imagePasteAfter.'</a> ';
        }

        return $return.($disableInto ? Image::getHtml('pasteinto'.$disableSuffix.'.svg').' ' : '<a href="'.Backend::addToUrl('act='.$clipboard['mode'].'&amp;mode=2&amp;pid='.$row['id'].(!\is_array($clipboard['id']) ? '&amp;id='.$clipboard['id'] : '')).'" title="'.StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['DCA']['pasteinto'][1], $row['id'])).'" data-action="contao--scroll-offset#store">'.$imagePasteInto.'</a> ');
    }


    /**
     * Adjust the toggle tag button
     *
     * @param Contao\CoreBundle\DataContainer\DataContainerOperation $operation
     */
    #[AsCallback('tl_cc_tag', target: 'list.operations.toggle.button')]
    public function toggleTagButton( DataContainerOperation $operation ): void {

        $row = $operation->getRecord();
        if( $row['type'] === 'group' ) {
            $operation->hide();
        }
    }


    /**
     * Genrates an alias if none is given
     *
     * @param mixed $value
     * @param Contao\DataContainer $dc
     *
     * @return string
     *
     * @throws Exception
     */
    #[AsCallback('tl_cc_tag', target: 'fields.alias.save')]
    public function generateAlias( $value, DataContainer $dc ): string {

        if( !strlen($value) ) {
            $value = 't'.bin2hex(random_bytes(4));
        }

        $activeRecord = $dc->getActiveRecord();

        $t = $dc->table;
        $result = $this->connection->executeQuery(
            "SELECT count(1) FROM $t WHERE id!=:id AND alias=:alias"
        ,   ['id'=>$activeRecord['id'], 'alias'=>$value]
        )->fetchOne();

        if( intval($result) > 0 ) {
            throw new Exception($this->translator->trans('ERR.aliasExists', [$value], 'contao_default'));
        }

        return $value;
    }


    /**
     * Generates the labels for the table view
     *
     * @param array $row
     * @param string $label
     * @param Contao\DataContainer $dc
     * @param array $args
     *
     * @return string
     */
    #[AsCallback('tl_cc_tag', target: 'list.label.label')]
    public function getLabel( $row, $label, DataContainer $dc, $imageAttribute ): string {

        $image = 'bundles/cookieconsent/backend/img/tags/';
        $attributes = $imageAttribute;

        // groups and translations
        if( $row['pid'] === 0 || $row['root'] ) {

            // translations
            if( $row['root'] ) {

                if( !is_array($this->labelsPageCache) ) {
                    $this->labelsPageCache = $this->getRootPages();
                }

                if( $this->labelsPageCache[$row['root']] ?? null ) {
                    $label .= '<span>'.$this->labelsPageCache[$row['root']].'</span>';
                }

                $image .= 'icon_tag_group_translation';

            // normal groups
            } else {

                $image .= 'icon_tag_group';
            }

        // normal tags
        } else {

            $image .= 'icon_tag_'.$row['type'];
        }

        $image .= '.svg';

        $attributes .= ' data-icon="'.$image.'" data-icon-disabled="'.$image.'"';

        if( $row['type'] != 'group' ) {

            $label .= '<span class="label-info">['.$this->translator->trans('tl_cc_tag.types.'.$row['type'], [], 'contao_tl_cc_tag').']</span>';
        }

        return Image::getHtml($image, '', $attributes).' '.$label;
    }


    /**
     * Return all tag types as array, based on available palettes and structure
     *
     * @param Contao\DataContainer $dc
     *
     * @return array
     */
    #[AsCallback('tl_cc_tag', target: 'fields.type.options')]
    public function getTagTypes( DataContainer $dc ): array {

        $types = [];

        $aRootTypes = ['group'];
        $activeRecord = $dc->getActiveRecord();

        foreach( $GLOBALS['TL_DCA']['tl_cc_tag']['palettes'] as $type => $palette ) {

            if( $type == '__selector__' ) {
                continue;
            }

            if( $type == 'default' || empty($activeRecord) ) {

                $types[$type] = $type;

            } else if( $activeRecord['pid'] === 0 || !empty($activeRecord['root']) ) {

                if( in_array($type, $aRootTypes) ) {
                    $types[$type] = $type;
                }
            } else {

                if( !in_array($type, $aRootTypes) ) {
                    $types[$type] = $type;
                }
            }
        }

        return $types;
    }


    /**
     * Add our default data to this table, if this is fresh
     */
    #[AsCallback('tl_cc_tag', target: 'config.onload')]
    public function addDefaults() {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || $this->scopeMatcher->isFrontendRequest($request) ) {
            return;
        }

        $t = TagModel::getTable();
        $count = $this->connection->executeQuery(
            "SELECT count(1) FROM $t"
        )->fetchOne();

        $user = $this->security->getUser();

        $autoincrement = $this->connection->executeQuery(
            "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table LIMIT 1"
        ,   ['table'=>$t]
        )->fetchOne();

        $defaultGroups = $GLOBALS['TL_LANG']['tl_cc_tag']['default'] ?? [];

        // create initial tag groups
        if( !(intval($count) || intval($autoincrement) > 1) ) {

            $tag = new TagModel();
            $tag->tstamp = time();
            $tag->sorting = 32;
            $tag->enable_on_cookie_accept = 1;
            $tag->pid = 0;
            $tag->type = 'group';
            $tag->initial_language = $user->language;

            if( is_array($defaultGroups) ) {

                foreach( $defaultGroups as $dataKey => $data ) {

                    $current = clone $tag;

                    foreach( $data as $key => $value ) {
                        $current->{$key} = $value;
                    }

                    $current->save();

                    if( $dataKey == 0 ) {

                        $sessionCookie = clone $tag;

                        $sessionCookie->name = 'Session-Cookie';
                        $sessionCookie->enable_on_cookie_accept = 0;
                        $sessionCookie->pid = $current->id;
                        $sessionCookie->type = 'session';

                        $sessionCookie->save();
                    }

                    $tag->sorting *= 2;
                }

                self::addDefaults();
            }

        // add missing translations for each default group
        } else {

            $rootPages = $this->getRootPages(true);

            $groups = $this->connection->executeQuery(
                "SELECT id, name, initial_language FROM $t WHERE type=:type AND root=:root AND initial_language != ''"
            ,   ['type'=>'group', 'root'=>0]
            )->fetchAllAssociative();

            if( is_array($groups) && is_array($defaultGroups) ) {

                foreach( $rootPages as $rootId => $rootLanguage ) {

                    $count = $this->connection->executeQuery(
                        "SELECT count(1) FROM $t WHERE root=:root"
                        ,   ['root'=>$rootId]
                        )->fetchOne();

                    // make sure we don't have any translations for this root already
                    if( intval($count) > 0 ) {
                        continue;
                    }

                    foreach( $groups as $i => $group ) {

                        $translationIndex = array_search($group['name'], array_column($defaultGroups, 'name'));

                        if( $translationIndex === false ) {
                            continue;
                        }

                        $tag = new TagModel();
                        $tag->tstamp = time();
                        $tag->sorting = $i*32;
                        $tag->pid = $group['id'];
                        $tag->type = 'group';
                        $tag->root = $rootId;

                        $fallbackName = $this->translator->trans('tl_cc_tag.default.' . $translationIndex . '.name', [], 'contao_tl_cc_tag', 'en');
                        $fallbackDescription = $this->translator->trans('tl_cc_tag.default.' . $translationIndex . '.description', [], 'contao_tl_cc_tag', 'en');

                        $tag->name = $this->translator->trans('tl_cc_tag.default.' . $translationIndex . '.name', [], 'contao_tl_cc_tag', $rootLanguage);
                        $tag->description = $this->translator->trans('tl_cc_tag.default.' . $translationIndex . '.description', [], 'contao_tl_cc_tag', $rootLanguage);

                        // only save te translation if we're not falling back onto the english one (except for english roots)
                        if( ($tag->name !== $fallbackName && $tag->description !== $fallbackDescription) || $rootLanguage == 'en' ) {
                            $tag->save();
                        }
                    }
                }
            }
        }
    }


    /**
     * Unset enable_on_cookie_accept for session tags and set groups to not activ
     */
    #[AsCallback('tl_cc_tag', target: 'config.onload')]
    public function cleanDatabase(): void {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || $this->scopeMatcher->isFrontendRequest($request) ) {
            return;
        }

        $t = TagModel::getTable();

        // set all session to enable_on_cookie_accept = 0
        $this->connection->executeStatement(
            "UPDATE $t SET enable_on_cookie_accept=:empty WHERE type=:type AND enable_on_cookie_accept!=:empty"
        ,   ['type'=>'session', 'empty'=>0]
        );

        // set all content elements to enable_on_cookie_accept = 1
        $this->connection->executeStatement(
            "UPDATE $t SET enable_on_cookie_accept=:one WHERE type=:type AND enable_on_cookie_accept!=:one"
        ,   ['type'=>'content_module_element', 'one'=>1]
        );

        // set all groups active = 0
        $this->connection->executeStatement(
            "UPDATE $t SET active=:empty WHERE type=:type AND active!=:empty"
        ,   ['type'=>'group', 'empty'=>0]
        );
    }


    /**
     * Performs a sanity chack for the field pages_scope and pages
     *
     * @param string $varValue
     * @param Contao\DataContainer $dc
     *
     * @return string
     */
    #[AsCallback('tl_cc_tag', target: 'fields.pages.save')]
    public function sanityCheckPageScopeWithPages( $varValue, DataContainer $dc ) {

        if( Input::post('pages_scope') === 'current_page' ) {

            $oPages = PageModel::findMultipleByIds(StringUtil::deserialize($varValue, true));

            if( $oPages ) {
                foreach( $oPages as $oPage ) {
                    if( $oPage->type == 'root' ) {
                        throw new Exception($this->translator->trans('ERR.cookie_consent.no_root_pages_for_pagescope_current', [], 'contao_default'));
                    }
                    if( in_array($oPage->type, ['forward', 'redirect']) ) {
                        throw new Exception($this->translator->trans('ERR.cookie_consent.no_forward_redirect_pages_for_pagescope_current', [], 'contao_default'));
                    }
                }
            }
        }

        return $varValue;
    }


    /**
     * Get all page root for the language override
     *
     * @return array
     */
    #[AsCallback('tl_cc_tag', target: 'fields.root.options')]
    public function getRootPagesForLanguage( DataContainer $dc ): array {

        // we're in editing mode and just creating the fallback case
        // do no let the user choose any other root page until
        // the fallback has been saved
        $activeRecord = $dc->getActiveRecord();
        if( empty($activeRecord['tstamp']) && (empty($activeRecord['root']) || empty($activeRecord['name'])) ) {
            return [
                '' => $this->translator->trans('tl_cc_tag.roots.default_initial', [], 'contao_tl_cc_tag')
            ];
        }

        $roots = [
            '' => $this->translator->trans('tl_cc_tag.roots.default', [], 'contao_tl_cc_tag')
        ];

        $rootPages = $this->getRootPages();

        foreach( $rootPages as $id => $label ) {

            $roots[$id] = $this->translator->trans('tl_cc_tag.roots.specific', [$label], 'contao_tl_cmctag');
        }

        return $roots;
    }


    /**
     * Get all root pages
     *
     * @return array
     */
    #[AsCallback('tl_cc_tag', target: 'fields.pages_root.options')]
    public function getRootPages( $langOnly=false ): array {

        $roots = [];

        $t = PageModel::getTable();
        $rootPages = $this->connection->executeQuery(
            "SELECT id, title, language FROM $t WHERE type=:type ORDER BY sorting ASC"
        ,   ['type'=>'root']
        )->fetchAllAssociative();

        foreach( $rootPages as $page ) {

            if( $langOnly ) {

                $roots[$page['id']] = $page['language'];

            } else {

                $roots[$page['id']] = $page['title'] . ' ('.$page['language'].')';
            }
        }

        return $roots;
    }


    /**
     * Adjust sorting base on root and redirect if this field is changed
     */
    #[AsCallback('tl_cc_tag', target: 'config.onload')]
    public function changeIdWithRoot( DataContainer $dc ): void {

        $request = $this->requestStack->getCurrentRequest();
        if( !$request || $this->scopeMatcher->isFrontendRequest($request) ) {
            return;
        }

        $t = TagModel::getTable();

        $roots = array_keys(self::getRootPages());

        if( !empty($roots) ) {

            // increase sorting to be higher as group with root sorting if needed
            $this->connection->executeStatement(
                "UPDATE $t SET sorting=sorting+:sorting WHERE type!=:type AND pid in (
                    SELECT DISTINCT pid FROM $t WHERE type!=:type AND sorting<=:sorting
                )"
            ,   ['type'=>'group', 'sorting'=>2*count($roots)]
            );

            // update group wiht root sorting to have same order as in tl_page
            foreach( $roots as $index => $id ) {

                $this->connection->executeStatement(
                    "UPDATE $t SET sorting=:sorting WHERE type=:type AND pid!=:pid AND root=:root"
                ,   ['type'=>'group', 'pid'=>0, 'root'=>$id, 'sorting'=>$index]
                );
            }
        }


        // either switch to entry based on selected root or copy current and redirect there
        if( Input::post('SUBMIT_TYPE') == 'auto' ) {


            $id = Input::get('id');
            $rootId = Input::post('root');

            $activeRecord = $dc->getActiveRecord();

            if( ($activeRecord['type'] ?? null) === 'group' ) {

                if( !empty($rootId) ) {
                    if( empty($activeRecord['pid']) ) {
                        $existingGroup = TagModel::findOneBy(['root=? AND pid=?'], [$rootId, $activeRecord['id']]);
                    } else {
                        $existingGroup = TagModel::findOneBy(['root=? AND pid=?'], [$rootId, $activeRecord['pid']]);
                    }
                } else {
                    $existingGroup = TagModel::findOneBy(['id=?'], [$activeRecord['pid']]);
                }

                $redirectId = 0;

                if( $existingGroup ) {

                    $redirectId = $existingGroup->id;

                } else {

                    $oGroup = TagModel::findOneById($activeRecord['id']);
                    $oNewGroup = clone $oGroup;

                    $oNewGroup->pid = $oGroup->pid?:$oGroup->id;
                    $oNewGroup->root = $rootId;
                    $oNewGroup->save();

                    $redirectId = $oNewGroup->id;
                }

                if( $redirectId ) {
                    Controller::redirect(Backend::addToUrl('id='.$redirectId));
                }
            }
        }
    }
}

