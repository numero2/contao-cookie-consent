<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


use Contao\PageModel;
use numero2\CookieConsentBundle\TagModel;


$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cc_override_labels';
$GLOBALS['TL_DCA']['tl_module']['palettes']['__selector__'][] = 'cc_tag_visibility';
$GLOBALS['TL_DCA']['tl_module']['palettes']['cc_cookie_consent'] = '{title_legend},name,type;{config_legend:hide},cc_override_labels,cc_exclude_pages,cc_cookie_lifetime,cc_accept_subdomains,cc_hide_copyright;{template_legend:hide},customTpl;{expert_legend:hide},cssID';

$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cc_override_labels'] = 'cc_accept_label,cc_accept_all_label,cc_text';
$GLOBALS['TL_DCA']['tl_module']['subpalettes']['cc_tag_visibility'] = 'cc_tag';


$GLOBALS['TL_DCA']['tl_module']['fields']['cc_override_labels'] = [
    'inputType'         => 'checkbox'
,   'eval'              => ['submitOnChange'=>true]
,   'sql'               => ['type'=>'boolean', 'default'=>false]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_accept_label'] = [
    'inputType'         => 'text'
,   'eval'              => ['mandatory'=>true, 'tl_class'=>'w50']
,   'sql'               => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_accept_all_label'] = [
    'inputType'         => 'text'
,   'eval'              => ['mandatory'=>true, 'tl_class'=>'w50']
,   'sql'               => "varchar(255) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_text'] = [
    'inputType'         => 'textarea'
,   'eval'              => ['mandatory'=>true, 'rte'=>'tinyMCE', 'tl_class'=>'clr', 'helpwizard'=>true, 'allowHtml'=>true]
,   'explanation'       => 'ccTagDescription'
,   'sql'               => "mediumtext NULL"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_exclude_pages'] = [
    'inputType'         => 'pageTree'
,   'foreignKey'        => PageModel::getTable().'.title'
,   'eval'              => ['fieldType'=>'checkbox', 'multiple'=>true, 'tl_class'=>'w50']
,   'sql'               => "blob NULL"
,   'relation'          => ['table'=>PageModel::getTable(), 'type'=>'hasMany', 'load'=>'lazy']
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_cookie_lifetime'] = [
    'inputType'         => 'inputUnit'
,   'options'           => ['days', 'weeks', 'months', 'years']
,   'reference'         => &$GLOBALS['TL_LANG']['tl_module']['cc_tag_cookie_lifetime_units']
,   'eval'              => ['tl_class'=>'clr w50']
,   'sql'               => "varchar(64) NOT NULL default ''"
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_accept_subdomains'] = [
    'inputType'         => 'checkbox'
,   'eval'              => ['tl_class'=>'w50']
,   'sql'               => ['type'=>'boolean', 'default'=>false]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_tag_visibility'] = [
    'inputType'         => 'checkbox'
,   'filter'            => true
,   'eval'              => ['submitOnChange'=>true]
,   'sql'               => ['type'=>'boolean', 'default'=>false]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_hide_copyright'] = [
    'inputType'         => 'checkbox'
,   'sql'               => ['type'=>'boolean', 'default'=>false]
];

$GLOBALS['TL_DCA']['tl_module']['fields']['cc_tag'] = [
    'inputType'         => 'select'
,   'foreignKey'        => TagModel::getTable().'.name'
,   'eval'              => ['mandatory'=>true, 'chosen'=>true, 'includeBlankOption'=>true, 'tl_class'=>'clr w50']
,   'sql'               => "int(10) unsigned NOT NULL default '0'"
,   'relation'          => ['table'=>TagModel::getTable(), 'type'=>'hasOne', 'load'=>'lazy']
];