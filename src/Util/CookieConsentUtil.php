<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\Util;

use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use numero2\CookieConsentBundle\TagModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;


class CookieConsentUtil {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var Doctrine\DBAL\Connection
     */
    private Connection $connection;


    public function __construct( RequestStack $requestStack, Connection $connection ) {

        $this->requestStack = $requestStack;
        $this->connection = $connection;
    }


    /**
     * Checks if the given tag is accepted by the user, check if the tag itself is active, can be set in with $checkActive.
     * Third paramter will be set to the found tag.
     *
     * @param string|int $tagId
     * @param bool $checkActive
     * @param array|null $aTag
     *
     * @return bool
     */
    public function isTagAccepted( string|int $tagId, bool $checkActive=true, ?array &$aTag=null ): bool {

        $tagId = intval($tagId);

        $request = $this->requestStack->getMainRequest();
        $pageModel = $request->get('pageModel');

        if( !($pageModel instanceof PageModel) ) {
            return false;
        }

        $t = TagModel::getTable();
        $tag = $this->connection->executeQuery(
            "SELECT * FROM $t WHERE id=:id LIMIT 1"
        ,   ['id'=>$tagId]
        )->fetchAssociative();

        if( empty($tag) ) {
            return false;
        }

        if( $aTag !== null ) {
            $aTag = $tag;
        }

        if( $checkActive && empty($tag['active']) ) {
            return false;
        }

        if( $request->headers->get('contao-cookie-consent') !== null ) {
            return in_array($tag['pid'], explode('-', $request->headers->get('contao-cookie-consent')));
        }

        return false;
    }


    /**
     * Checks if the given tag is not accepted by the user, therefore the tag itself must be active.
     *
     * @param string|int $tagId
     *
     * @return boolean
     */
    public function isTagNotAccepted( string|int $tagId ) {

        $aTag = [];
        if( !$this->isTagAccepted($tagId, false, $aTag) && !empty($aTag['active']) ) {
            return true;
        }

        return false;
    }


    /**
     * Get all allowed tags for the current page in request grouped by groupId
     *
     * @return array
     */
    public function getAllowedTags(): array {

        $request = $this->requestStack->getMainRequest();
        $pageModel = $request->get('pageModel');

        if( !($pageModel instanceof PageModel) ) {
            return [];
        }

        $tagsAllowed = [];
        $oTags = TagModel::findAllActiveByPage($pageModel->id);

        if( $oTags && count($oTags) ) {

            // prepare which page is allowed on which scope
            $allowed = [
                'current_page' => []
            ,   'current_and_direct_children' => []
            ,   'current_and_all_children' => []
            ];

            foreach( array_reverse($pageModel->trail) as $value ) {

                if( $value == $pageModel->id ) {
                    $allowed['current_page'][] = $value;
                }
                if( count($allowed['current_page']) && count($allowed['current_and_direct_children'] ) < 2 ) {
                    $allowed['current_and_direct_children'][] = $value;
                }
                if( count($allowed['current_page']) ) {
                    $allowed['current_and_all_children'][] = $value;
                }
            }

            foreach( $oTags as $key => $tag ) {

                // skip on not enough data
                if( empty($tag->pages) || empty($allowed[$tag->pages_scope]) ) {
                    continue;
                }

                // skip if cookie needed but not cookie_accepted
                if( $tag->enable_on_cookie_accept && !$this->isTagAccepted($tag->id) ) {
                    continue;
                }

                $tagPages = StringUtil::deserialize($tag->pages, true);

                // check all pages if one is allowed
                foreach( $allowed[$tag->pages_scope] as $key => $value ) {

                    if( in_array($value, $tagPages) ) {
                        $tagsAllowed[$tag->pid][] = $tag->row();
                        break;
                    }
                }
            }
        }

        return $tagsAllowed;
    }


    /**
     * Check if at least one page is within the given root
     *
     * @param int $root
     * @param string|int ...$aPages
     *
     * @return boolean
     */
    public function isPageInRoot( int $root, string|int ...$pages ) {

        $pages = array_map(\intval(...), $pages);
        $pages = array_unique(array_filter($pages));

        if( empty($pages) ) {
            return false;
        }

        if( in_array($root, $pages) ) {
            return true;
        }

        while( count($pages) ) {

            // build trail for all given page ids upwards up to 8 levels
            $rows = $this->connection->executeQuery(
                "SELECT DISTINCT p.pid, p1.pid, p2.pid, p3.pid, p4.pid, p5.pid, p6.pid, p7.pid
                FROM tl_page AS p
                    LEFT JOIN tl_page AS p1 ON p1.id = p.pid
                    LEFT JOIN tl_page AS p2 ON p2.id = p1.pid
                    LEFT JOIN tl_page AS p3 ON p3.id = p2.pid
                    LEFT JOIN tl_page AS p4 ON p4.id = p3.pid
                    LEFT JOIN tl_page AS p5 ON p5.id = p4.pid
                    LEFT JOIN tl_page AS p6 ON p6.id = p5.pid
                    LEFT JOIN tl_page AS p7 ON p7.id = p6.pid
                WHERE p.id in (:pages)"
            ,   ['pages'=>$pages]
            ,   ['pages'=>ArrayParameterType::INTEGER]
            )->fetchAllNumeric();

            $pages = [];
            foreach( $rows as $row ) {
                if( in_array($root, $row) ) {
                    return true;
                }

                $last = array_pop($row);
                if( $last !== 0 && $last !== null ) {
                    $pages[] = $last;
                }
            }
        }

        return false;
    }


    /**
     * Generates a link to the current page with a parameter that forces the cookie bar to show up again
     *
     * @param string Optional id (cssID) of the original element
     *
     * @return string
     */
    public function generateConsentForceLink( string $strElementId='' ): string {

        $href = $this->requestStack->getMainRequest()->getRequestUri();

        $index = strpos($href, '?');
        if( $index !== false ) {
            $href = substr($href, 0, $index);
        }

        $href = $href . '?_ccscb=1';

        if( strlen($strElementId) ) {
            $href .= '&amp;_ccelid='.$strElementId;
        }

        return $href;
    }
}