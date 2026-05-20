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

use Contao\ContentModel;
use Contao\CoreBundle\Cache\CacheTagManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Event\LayoutEvent;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\FragmentTemplate;
use Contao\LayoutModel;
use Contao\Model;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\StringUtil;
use numero2\CookieConsentBundle\Util\CookieConsentUtil;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;


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
     * @var Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor
     */
    private ResponseContextAccessor $responseContextAccessor;

    /**
     * @var Contao\CoreBundle\Cache\CacheTagManager
     */
    private CacheTagManager $cacheTagManager;

    /**
     * @var numero2\CookieConsentBundle\Util\CookieConsentUtil
     */
    private CookieConsentUtil $cookieConsentUtil;


    public function __construct( RequestStack $requestStack, ScopeMatcher $scopeMatcher, ResponseContextAccessor $responseContextAccessor, CacheTagManager $cacheTagManager, CookieConsentUtil $cookieConsentUtil ) {

        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->responseContextAccessor = $responseContextAccessor;
        $this->cacheTagManager = $cacheTagManager;
        $this->cookieConsentUtil = $cookieConsentUtil;
    }


    /**
     * Generates and adds tags on generate page
     *
     * @param Contao\PageModel $pageModel
     * @param Contao\LayoutModel $layout
     * @param Contao\PageRegular $pageRegular
    */
    #[AsHook('generatePage')]
    public function onGeneratePage( PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular ): void {

        $this->generateScripts();
    }


    /**
     *  Generates and adds tags on layout event
     *
     * @param Contao\CoreBundle\Event\LayoutEvent $event
     */
    #[AsEventListener]
    public function onLayoutEvent( LayoutEvent $event ): void {

        $this->generateScripts();
    }


    /**
     * Generates and adds all script tags to the page
     */
    private function generateScripts(): void {

        $tagsRendered = [];
        $tagGroups = $this->cookieConsentUtil->getAllowedTags();
        $ids = [];

        if( $tagGroups && count($tagGroups) ) {

            // render script tags
            foreach( $tagGroups as $pid => $tags ) {

                $ids[] = $pid;

                foreach( $tags as $key => $tag ) {

                    $ids[] = $tag['id'];

                    if( in_array($tag['type'], ['session', 'content_module_element']) ) {
                        continue;
                    }

                    $template = new FragmentTemplate($tag['customTpl']?:'cc_tag/'.$tag['type']);

                    $template->tag = $tag;

                    $tagsRendered[] = $template->parse();
                }
            }
        }

        // make sure we don't index the page if we force showing the consent
        if( $this->requestStack->getMainRequest()?->query->get('_ccscb') ) {

            if( $this->responseContextAccessor->getResponseContext()?->has(HtmlHeadBag::class) ) {

                /** @var HtmlHeadBag $htmlHeadBag */
                $htmlHeadBag = $this->responseContextAccessor->getResponseContext()->get(HtmlHeadBag::class);
                $htmlHeadBag->setMetaRobots('noindex,nofollow');
            }
        }

        if( !empty($ids) ) {
            $this->cacheTagManager->tagWith(array_map(static fn ($id): string => 'contao.db.tl_cc_tag.'.$id, $ids));
        }

        $template = new FragmentTemplate('frontend_module/cc_tags');

        $template->tags = $tagsRendered;

        $template->parse();
    }


    /**
     * Replace a rendered content element or frontend module with a fallback
     * template if configured to be only visible on cookie accept
     *
     * @param Contao\ContentModel|Contao\ModuleModel $model
     * @param string $strBuffer
     * @param Contao\ContentElement|Contao\Module $oElement
     *
     * @return string
    */
    #[AsHook('getContentElement')]
    #[AsHook('getFrontendModule')]
    public function replaceTagContentModuleElement( Model $model, string $buffer, $element ): string {

        if( !($model instanceof ContentModel || $model instanceof ModuleModel) ) {
            return $buffer;
        }

        $request = $this->requestStack->getCurrentRequest();

        if( !$request || !$this->scopeMatcher->isFrontendRequest($request) ) {
            return $buffer;
        }

        $cssClass = '';

        // we may have
        // - a frontend module referenced by a content element
        // - a content element referenced by a content element
        // in this case make sure to check the settings of the referenced element
        if( empty($model->cc_tag_visibility) ) {
            if( $model instanceof ContentModel && $element->type === 'alias' ) {
                $cssClass .= ' ' . (StringUtil::deserialize($model->cssID, true)[1] ?? '');
                $model = ContentModel::findOneById($model->cteAlias);
            } else if( $model instanceof ContentModel && $element->type === 'module' ) {
                $cssClass .= ' ' . (StringUtil::deserialize($model->cssID, true)[1] ?? '');
                $model = ModuleModel::findOneById($model->module);
            }
        }

        if( !empty($model->cc_tag_visibility) ) {
            $cssID = $this->addIdAttribute($buffer, !empty($element->id)?$element:$model);
        }

        $tag = [];
        // only replace buffer if cc_tag_visibility is set and selected tag is not accepted
        if( empty($model->cc_tag_visibility) || $this->cookieConsentUtil->isTagAccepted($model->cc_tag, true, $tag) ) {
            $this->cacheTagManager->tagWith('contao.db.tl_cc_tag.'.$model->cc_tag);
            return $buffer;
        }

        // return original if referenced tag does not exist anymore
        if( empty($tag) ) {
            return $buffer;
        }

        if( $model instanceof ContentModel ) {

            if( $this->isFieldInPalette('cssID', $GLOBALS['TL_DCA']['tl_content']['palettes'][$model->type] ?? '') ) {
                $cssClass .= ' ' . (StringUtil::deserialize($model->cssID, true)[1] ?? '');
            }
        }

        $template = new FragmentTemplate($tag['fallbackTpl']?:'content_element/cc_optin');

        $template->setData($model->row());

        $template->headline = null;
        $template->element_css_classes = 'content-cc-optin '. $cssClass;
        $template->element_html_id = $cssID;

        $template->fallback_text = $tag['fallback_text'];
        $template->origin = $element;
        $template->originType = '';
        if( $model instanceof ContentModel ) {
            $template->originType = 'content_element';
        } else if( $model instanceof ModuleModel ) {
            $template->originType = 'frontend_module';
        }

        if( empty($template->fallback_text) ) {
            $template->element_css_classes .= ' cc-default-fallback';
        }

        $this->cacheTagManager->tagWith('contao.db.tl_cc_tag.'.$tag['id']);

        return $template->parse();
    }


    /**
     * Adds an id attribute to the given element markup if necessary
     * and returns the found / generated id
     *
     * @param string $buffer
     * @param Contao\ContentElement|Contao\Module|Contao\ContentModel|Contao\ModuleModel $element
     *
     * @return string
     */
    private function addIdAttribute( string &$buffer, $element ) {

        $id = '';
        $firstTag = [];

        if( preg_match('/<[^\!][^>]*?>/m', $buffer, $firstTag) ) {

            $firstTag = $firstTag[0];
            $arrExistingID = [];
            if( preg_match('/id="(.*?)"/', $firstTag, $arrExistingID) ) {

                $id = $arrExistingID[1];

            } else {

                $id = 'cc_' . $element->type . $element->id;
                $buffer = str_replace($firstTag, substr($firstTag, 0, -1).' id="'.$id.'">', $buffer);
            }
        }

        return $id;
    }


    /**
     * Check if a field is in the given palette
     *
     * @param string $field
     * @param string $palette
     *
     * @return bool
     */
    private function isFieldInPalette( string $field, string $palette ): bool {

        return (bool) preg_match("/,$field(;|,|$)/", $palette);
    }
}