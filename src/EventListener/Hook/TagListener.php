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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;


class TagListener {


    /**
     * Globals an element may contribute to while it is being rendered.
     * TL_HEAD, TL_BODY and TL_STYLE_SHEETS are the targets of Twig's
     * {% add ... to head|body|stylesheets %}, the rest is the classic
     * asset registration.
     */
    private const DOCUMENT_GLOBALS = [
        'TL_HEAD', 'TL_BODY', 'TL_STYLE_SHEETS',
        'TL_CSS', 'TL_JAVASCRIPT', 'TL_JQUERY', 'TL_MOOTOOLS',
        'TL_USER_CSS', 'TL_FRAMEWORK_CSS',
    ];


    /**
     * LIFO stack of [key, snapshot] pairs
     *
     * @var array<int, array{0: string, 1: array<string, array|null>}>
     */
    private array $documentSnapshots = [];


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
     * Resets any leftover snapshots, as this listener is a shared service
     * and may be reused in a long running worker
     *
     * @param Symfony\Component\HttpKernel\Event\RequestEvent $event
     */
    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest( RequestEvent $event ): void {

        if( !$event->isMainRequest() ) {
            return;
        }

        $this->documentSnapshots = [];
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
     * Takes a snapshot of everything an element may add to the document before
     * it is rendered. The getContentElement / getFrontendModule hooks only run
     * after generate(), so by then a template's {% add ... to head %} has
     * already been registered - replacing the buffer alone would leave the
     * external script in the head without any consent.
     *
     * @param Contao\Model $model
     * @param bool $isVisible
     *
     * @return bool
     */
    #[AsHook('isVisibleElement')]
    public function snapshotDocumentContent( Model $model, bool $isVisible ): bool {

        if( !$isVisible ) {
            return $isVisible;
        }

        if( !($model instanceof ContentModel || $model instanceof ModuleModel) ) {
            return $isVisible;
        }

        if( empty($model->cc_tag_visibility) ) {
            return $isVisible;
        }

        $request = $this->requestStack->getCurrentRequest();

        if( !$request || !$this->scopeMatcher->isFrontendRequest($request) ) {
            return $isVisible;
        }

        $snapshot = [];
        foreach( self::DOCUMENT_GLOBALS as $key ) {
            $snapshot[$key] = $GLOBALS[$key] ?? null;
        }

        $this->documentSnapshots[] = [$this->getSnapshotKey($model), $snapshot];

        return $isVisible;
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

        // pop before the alias/module indirection below overwrites $model, so
        // the stack is cleared on every path through this method
        $snapshot = $this->popDocumentSnapshot($model);

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

        // we are about to discard the element's markup, so also discard
        // everything it added to head, body and assets while rendering. Must
        // happen before the fallback template is parsed, as that one adds its
        // own script to the body.
        if( $snapshot !== null ) {
            $this->restoreDocumentContent($snapshot);
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
     * Builds the key a snapshot is stored under. Object identity is of no use
     * here, since Contao may hand a cloneDetached() copy of the model to the
     * getContentElement hook.
     *
     * @param Contao\Model $model
     *
     * @return string
     */
    private function getSnapshotKey( Model $model ): string {

        return $model::class . ':' . ($model->id ?: '') . ':' . ($model->type ?: '');
    }


    /**
     * Returns and removes the snapshot taken for the given model, or null if
     * there is none. Any entry above the match is dropped as well: those either
     * belong to the element's own subtree or are unbalanced, as isVisibleElement
     * also fires for elements Contao bails out on before rendering them.
     *
     * @param Contao\Model $model
     *
     * @return array|null
     */
    private function popDocumentSnapshot( Model $model ): ?array {

        $key = $this->getSnapshotKey($model);

        for( $i = count($this->documentSnapshots) - 1; $i >= 0; $i-- ) {

            if( $this->documentSnapshots[$i][0] !== $key ) {
                continue;
            }

            $snapshot = $this->documentSnapshots[$i][1];

            array_splice($this->documentSnapshots, $i);

            return $snapshot;
        }

        return null;
    }


    /**
     * Restores the given snapshot of the document globals
     *
     * @param array $snapshot
     */
    private function restoreDocumentContent( array $snapshot ): void {

        foreach( $snapshot as $key => $value ) {

            if( $value === null ) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }
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