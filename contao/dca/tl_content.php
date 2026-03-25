<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


use numero2\CookieConsentBundle\TagModel;


$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'cc_tag_visibility';

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['cc_tag_visibility'] = 'cc_tag';


$GLOBALS['TL_DCA']['tl_content']['fields']['cc_tag_visibility'] = [
    'inputType'         => 'checkbox'
,   'filter'            => true
,   'eval'              => ['submitOnChange'=>true]
,   'sql'               => ['type'=>'boolean', 'default'=>false]
];

$GLOBALS['TL_DCA']['tl_content']['fields']['cc_tag'] = [
    'inputType'         => 'select'
,   'foreignKey'        => TagModel::getTable().'.name'
,   'eval'              => ['mandatory'=>true, 'chosen'=>true, 'includeBlankOption'=>true, 'tl_class'=>'clr w50']
,   'sql'               => "int(10) unsigned NOT NULL default '0'"
,   'relation'          => ['table'=>TagModel::getTable(), 'type'=>'hasOne', 'load'=>'lazy']
];