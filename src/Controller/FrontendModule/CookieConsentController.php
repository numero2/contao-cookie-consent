<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use numero2\CookieConsentBundle\TagModel;
use numero2\CookieConsentBundle\Util\CookieConsentUtil;
use Pdp\Domain;
use Pdp\Rules;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


#[AsFrontendModule('cc_cookie_consent', category: 'cookie_consent')]
class CookieConsentController extends AbstractFrontendModuleController {


    /**
     * @var numero2\CookieConsentBundle\Util\CookieConsentUtil
     */
    private CookieConsentUtil $cookieConsentUtil;


    public function __construct( CookieConsentUtil $cookieConsentUtil ) {

        $this->cookieConsentUtil = $cookieConsentUtil;
    }


    /**
     * {@inheritdoc}
     */
    protected function getResponse( FragmentTemplate $template, ModuleModel $model, Request $request ): Response {

        $page = $this->getPageModel();

        $actionHref = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        $actionHref = preg_replace('|_ccscb=[0-9]+[&]?|', '', $actionHref);
        $actionHref = preg_replace('|_ccelid=[\w]+[&]?|', '', $actionHref);
        $actionHref = !empty($request->query->get('_ccelid')) ? $actionHref.'#'.$request->query->get('_ccelid') : $actionHref;

        $formSubmit = $model->type;

        // handle form data
        $this->handleFormData($actionHref, $formSubmit, $model, $request);

        // check if it should be shown at all
        if( !$this->shouldBeShown($model, $request) ) {
            return new Response('');
        }

        Controller::loadDataContainer(TagModel::getTable());

        $accepted = [];
        if( $request->headers->get('contao-cookie-consent') !== null ) {
            $accepted = explode('-', $request->headers->get('contao-cookie-consent'));
        }

        $oTags = TagModel::findBy(['type!=? AND active=?'], ['group', 1], ['order'=>'pid ASC, sorting ASC']);
        $oTagGroups = TagModel::findGroupsWithRootOverride($page->trail[0]);

        if( !$oTags ) {
            return new Response('');
        }

        $tagGroups = [];
        foreach( $oTagGroups as $oGroup ) {

            $group = $oGroup->row();

            $group['show'] = false;
            $group['accepted'] = in_array($group['id'], $accepted);
            $group['required'] = false;
            $group['tags'] = [];
            $group['tagPages'] = [];

            $tagGroups[$group['id']] = $group;
        }

        $tags = [];

        foreach( $oTags as $oTag ) {

            if( !array_key_exists($oTag->pid, $tagGroups) ) {
                continue;
            }

            $tag = $oTag->row();

            $palette = $GLOBALS['TL_DCA']['tl_cc_tag']['palettes'][$tag['type']] ?? '';

            if( $this->isFieldInPalette('pages_root', $palette) ) {

                $pagesRoot = array_map(\intval(...), StringUtil::deserialize($tag['pages_root'], true));

                if( !empty($pagesRoot) && !in_array($page->trail[0], $pagesRoot) ) {
                    continue;
                }
                \array_push($tagGroups[$tag['pid']]['tagPages'], $page->trail[0]);
            }

            $processFields = true;
            if( empty($tag['enable_on_cookie_accept']) ) {
                $processFields = false;
            } else {
                $tagGroups[$tag['pid']]['required'] = true;
            }

            if( $this->isFieldInPalette('pages', $palette) ) {
                if( !empty($tag['pages']) ) {

                    $pages = StringUtil::deserialize($tag['pages'], true);
                    if( !$this->cookieConsentUtil->isPageInRoot($page->trail[0], ...$pages) ) {
                        continue;
                    }

                    \array_push($tagGroups[$tag['pid']]['tagPages'], ...$pages);
                }
            } else {
                \array_push($tagGroups[$tag['pid']]['tagPages'], $page->trail[0]);
            }

            $tagGroups[$tag['pid']]['tags'][] = $tag;
        }

        foreach( $tagGroups as $id => &$tagGroup ) {

            // check if group should be shown based on configured pages
            if( !empty($tagGroup['tagPages']) ) {
                $tagGroup['show'] = true;
            }
        }

        $template->set('action', $actionHref);
        $template->set('formSubmit', $formSubmit);
        $template->set('tagGroups', $tagGroups);
        $template->set('hideCopyright', $model->cc_hide_copyright);

        $template->set('cc_override_labels', $model->cc_override_labels);
        $template->set('cc_text', $model->cc_text);
        $template->set('cc_accept_label', $model->cc_accept_label);
        $template->set('cc_accept_all_label', $model->cc_accept_all_label);

        $this->tagResponse([
            'contao.db.tl_cc_tag'
        ,   ...array_map(static fn ($id): string => 'contao.db.tl_cc_tag.'.$id, array_keys($tagGroups))
        ]);

        return $template->getResponse();
    }


    /**
     * Handles the data submitted by the consent form
     *
     * @param string $href
     * @param string $formSubmit
     * @param Contao\ModuleModel $model
     * @param Symfony\Component\HttpFoundation\Request $request
     *
     * @throws ResponseException
     */
    protected function handleFormData( string $href, string $formSubmit, ModuleModel $model, Request $request ): void {

        $page = $this->getPageModel();

        if( $request->request->get('FORM_SUBMIT') === $formSubmit ) {

            $oTags = NULL;
            $oTags = TagModel::findBy(['type=?'], ['group'], ['order'=>'sorting ASC']);

            $aTagIds = [];

            if( $oTags ) {
                $aTagIds = array_map(\intval(...), $oTags->fetchEach('id'));
            }

            $accepted = [];
            foreach( array_keys($_POST) as $value) {

                if( strpos($value, 'cookie_') === 0 ) {

                    $val = intval(str_replace('cookie_', '', $value));

                    if( $val && \in_array($val, $aTagIds, true) ) {
                        $accepted[] = $val;
                    }
                }
            }

            $iCookieExpires = strtotime('+7 days');

            $aCookieConfig = StringUtil::deserialize($model->cc_cookie_lifetime, true);
            if( !empty($aCookieConfig['value']) && !empty($aCookieConfig['unit']) ) {
                $iCookieExpires = strtotime('+'.(int)$aCookieConfig['value'].' '.$aCookieConfig['unit']);
            }

            $sDomain = null;

            // set cookies for all subdomains
            if( !empty($model->cc_accept_subdomains) ) {

                $rootPage = PageModel::findById($page->rootId);

                $sDomain = $rootPage->dns?:$request->getHttpHost();
                $sDomain = $this->getRegisterableDomain($sDomain);
            }

            $response = new RedirectResponse($href);

            // store decision in cookie with secure flag and SameSite=Lax
            $response->headers->setCookie(new Cookie(
                'cc_cookies',
                implode('-', $accepted),
                $iCookieExpires,
                '/',
                $sDomain,
                true, // secure
                true, // httpOnly
                false, // raw
                'lax' // sameSite
            ));

            throw new ResponseException($response);
        }
    }


    /**
     * Determines if the module should be visible
     *
     * @return boolean
     */
    protected function shouldBeShown( ModuleModel $model, Request $request ): bool {

        $page = $this->getPageModel();

        // prevent as form handling not possible
        if( in_array($page->type, ['error_503']) ) {
            return false;
        }

        // check if forced to show up
        if( $request->query->get('_ccscb') ) {
            return true;
        }

        // check for page type
        if( in_array($page->type, ['error_401', 'error_403', 'error_404', 'error_410']) ) {
            return false;
        }

        // check if cookie consent is excluded from current page
        if( !empty($model->cc_exclude_pages) ) {

            $excludePages = StringUtil::deserialize($model->cc_exclude_pages, true);

            // page excluded
            if( in_array($page->id, $excludePages) ) {
                return false;
            }
        }

        // check if cookies not already set
        if( $request->headers->get('contao-cookie-consent') !== null ) {
            return false;
        }

        return true;
    }


    /**
     * Check if a field is in the given palette
     *
     * @param string $field
     * @param string $palette
     *
     * @return bool
     */
    protected function isFieldInPalette( string $field, string $palette ): bool {

        return (bool) preg_match("/,$field(;|,|$)/", $palette);
    }


    /**
     * Resolve the registerable domain
     *
     * @param string $domain
     *
     * @return string|null
     */
    protected function getRegisterableDomain( string $domain ): ?string {

        $publicSuffixList = Rules::fromPath(__DIR__ . '/../../../assets/publicsuffix/public_suffix_list.dat');

        $result = $publicSuffixList->resolve(Domain::fromIDNA2008($domain));

        return $result->registrableDomain()?->toString();
    }
}
