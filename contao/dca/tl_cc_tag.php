<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


use Contao\Config;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\PageModel;


$GLOBALS['TL_DCA']['tl_cc_tag'] = [

    'config' => [
        'label'                     => Config::get('websiteTitle')
    ,   'dataContainer'             => DC_Table::class
    ,   'notCopyable'               => true
    ,   'sql' => [
            'keys' => [
                'id' => 'primary'
            ]
        ]
    ]
,   'list' => [
        'sorting' => [
            'mode'                  => DataContainer::MODE_TREE
        ,   'rootPaste'             => true
        ,   'showRootTrails'        => true
        ,   'icon'                  => 'pagemounts.svg'
        ,   'panelLayout'           => 'search,limit;filter'
        ]
    ,   'label' => [
            'fields'                => ['name']
        ,   'format'                => '%s'
        ]
    ,   'global_operations' => [
            'toggleNodes'
        ,   'all'
        ]
    ,   'operations' => [
            'edit'
        ,   'cut'
        ,   'delete'
        ,   'toggle' =>[
                'href'              => 'act=toggle&amp;field=active'
            ,   'icon'              => 'visible.svg'
            ,   'primary'           => true
            ]
        ]
        ,   'show'
    ]
,   'palettes' => [
        '__selector__'              => ['type']
    ,   'default'                   => '{common_legend},type,name'
    ,   'group'                     => '{common_legend},type,root,name;{description_legend},description'
    ,   'session'                   => '{common_legend},type,name;{publish_legend},active'
    ,   'html'                      => '{common_legend},type,name;{tag_legend},html,section;{expert_legend:hide},customTpl;{publish_legend},pages_scope,pages,active,enable_on_cookie_accept'
    ,   'google_analytics'          => '{common_legend},type,name;{tag_legend},html,section;{expert_legend:hide},customTpl;{publish_legend},pages_scope,pages,active,enable_on_cookie_accept'
    ,   'google_tag_manager'        => '{common_legend},type,name;{tag_legend},html,section;{expert_legend:hide},customTpl;{publish_legend},pages_scope,pages,active,enable_on_cookie_accept'
    ,   'facebook_pixel'            => '{common_legend},type,name;{tag_legend},html,section;{expert_legend:hide},customTpl;{publish_legend},pages_scope,pages,active,enable_on_cookie_accept'
    ,   'matomo'                    => '{common_legend},type,name;{tag_legend},html,section;{expert_legend:hide},customTpl;{publish_legend},pages_scope,pages,active,enable_on_cookie_accept'
    ,   'content_module_element'    => '{common_legend},type,name;{tag_legend},fallbackTpl,fallback_text;{publish_legend},pages_root,active'
    ]
,   'fields' => [
        'id' => [
            'sql'         => "int(10) unsigned NOT NULL auto_increment"
        ]
    ,   'pid' => [
            'sql'         => "int(10) unsigned NOT NULL default '0'"
        ]
    ,   'sorting' => [
            'sql'         => "int(10) unsigned NOT NULL default '0'"
        ]
    ,   'tstamp' => [
            'sql'         => "int(10) unsigned NOT NULL default '0'"
        ]
    ,   'type' => [
            'inputType'             => 'select'
        ,   'filter'                => true
        ,   'reference'             => &$GLOBALS['TL_LANG']['tl_cc_tag']['types']
        ,   'eval'                  => ['mandatory'=>true, 'maxlength'=>64, 'chosen'=>true, 'submitOnChange'=>true, 'tl_class'=>'w50']
        ,   'sql'                   => ['name'=>'type', 'type'=>'string', 'length'=>64, 'default'=>'', 'customSchemaOptions'=>['collation'=>'ascii_bin']]

        ]
    ,   'name' => [
            'inputType'             => 'text'
        ,   'search'                => true
        ,   'eval'                  => ['mandatory'=>true, 'maxlength'=>64, 'tl_class'=>'w50']
        ,   'sql'                   => "varchar(64) NOT NULL default ''"
        ]
    ,   'root' => [
            'inputType'             => 'select'
        ,   'eval'                  => ['submitOnChange'=>true, 'tl_class'=>'w50']
        ,   'sql'                   => "int(10) unsigned NOT NULL default '0'"
        ]
    ,   'description' => [
            'inputType'             => 'textarea'
        ,   'eval'                  => ['rte'=>'tinyMCE', 'doNotSaveEmpty'=>true, 'allowHtml'=>true]
        ,   'sql'                   => "text NULL"
        ]
    ,   'html' => [
            'inputType'             => 'textarea'
        ,   'eval'                  => ['mandatory'=>true, 'preserveTags'=>true, 'class'=>'monospace', 'rte'=>'ace|html', 'tl_class'=>'clr']
        ,   'sql'                   => "text NULL"
        ]
    ,   'section' => [
            'inputType'             => 'select'
        ,   'options'               => ['head', 'body']
        ,   'reference'             => &$GLOBALS['TL_LANG']['tl_cc_tag']['sections']
        ,   'eval'                  => ['mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'clr w50']
        ,   'sql'                   => ['name'=>'section', 'type'=>'string', 'length'=>16, 'default'=>'body', 'customSchemaOptions'=>['collation'=>'ascii_bin']]
        ]
    ,   'fallbackTpl' => [
            'inputType'             => 'select'
        ,   'eval'                  => ['chosen'=>true, 'tl_class'=>'w50']
        ,   'sql'                   => "varchar(64) NOT NULL default ''"
        ]
    ,   'fallback_text' => [
            'inputType'             => 'textarea'
        ,   'explanation'           => 'ccOptinFallback'
        ,   'eval'                  => ['rte'=>'tinyMCE', 'helpwizard'=>true, 'tl_class'=>'clr', 'allowHtml'=>true]
        ,   'sql'                   => "text NULL"
        ]
    ,   'customTpl' => [
            'inputType'             => 'select'
        ,   'eval'                  => ['chosen'=>true, 'tl_class'=>'w50']
        ,   'sql'                   => "varchar(64) NOT NULL default ''"
        ]
    ,   'pages_scope' => [
            'inputType'             => 'radio'
        ,   'options'               => ['current_and_all_children', 'current_and_direct_children', 'current_page']
        ,   'reference'             => &$GLOBALS['TL_LANG']['tl_cc_tag']['page_scopes']
        ,   'eval'                  => ['tl_class'=>'clr w50 no-height']
        ,   'sql'                   => "varchar(64) NOT NULL default 'current_and_all_children'"
        ]
    ,   'pages' => [
            'inputType'             => 'pageTree'
        ,   'foreignKey'            => PageModel::getTable().'.title'
        ,   'eval'                  => ['mandatory'=>true, 'multiple'=>true, 'fieldType'=>'checkbox', 'tl_class'=>'clr']
        ,   'relation'              => ['table'=>PageModel::getTable(), 'type'=>'hasMany', 'load'=>'lazy']
        ,   'sql'                   => "text NULL"
        ]
    ,   'pages_root' => [
            'inputType'             => 'checkboxWizard'
        ,   'eval'                  => ['multiple'=>true, 'tl_class'=>'w50 clr']
        ,   'sql'                   => "text NULL"
        ]
    ,   'active' => [
            'inputType'             => 'checkbox'
        ,   'toggle'                => true
        ,   'eval'                  => ['tl_class'=>'clr w50']
        ,   'sql'                   => ['type'=>'boolean', 'default'=>false]
        ]
    ,   'enable_on_cookie_accept' => [
            'inputType'             => 'checkbox'
        ,   'eval'                  => ['tl_class'=>'w50']
        ,   'sql'                   => ['type'=>'boolean', 'default'=>true]
        ]
    ]
];
