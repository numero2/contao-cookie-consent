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


/**
 * MODELS
 */
$GLOBALS['TL_MODELS'][TagModel::getTable()] = TagModel::class;


/**
 * BACK END MODULES
 */
$GLOBALS['BE_MOD']['system']['cc_tags'] = [
    'tables'      => [TagModel::getTable()]
];
