<?php

/**
 * Cookie Consent Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\CookieConsentBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use numero2\CookieConsentBundle\TagModel;


class CmsTagMigration extends AbstractMigration {


    /**
     * @var Doctrine\DBAL\Connection
     */
    private Connection $connection;


    public function __construct( Connection $connection ) {

        $this->connection = $connection;
    }


    public function shouldRun(): bool {

        $tCmsTag = 'tl_cms_tag';
        $tTag = TagModel::getTable();

        $schemaManager = $this->connection->createSchemaManager();

        if( $schemaManager->tablesExist([$tCmsTag]) && !$schemaManager->tablesExist([$tTag]) ) {

            $count = $this->connection->fetchOne("SELECT COUNT(1) FROM $tCmsTag");

            if( intval($count) ) {
                return true;
            }
        }

        return false;
    }


    public function run(): MigrationResult {

        $tCmsTag = 'tl_cms_tag';
        $tTag = TagModel::getTable();

        $rows = $this->connection->executeQuery(
            "SELECT * FROM $tCmsTag ORDER BY id ASC"
        )->fetchAllAssociative();

        $this->connection->executeStatement(
            "CREATE TABLE $tTag (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                pid INT UNSIGNED DEFAULT 0 NOT NULL,
                sorting INT UNSIGNED DEFAULT 0 NOT NULL,
                tstamp INT UNSIGNED DEFAULT 0 NOT NULL,
                type VARCHAR(64) CHARACTER SET ascii DEFAULT '' NOT NULL COLLATE `ascii_bin`,
                name VARCHAR(64) DEFAULT '' NOT NULL,
                root INT UNSIGNED DEFAULT 0 NOT NULL,
                description TEXT DEFAULT NULL,
                html TEXT DEFAULT NULL,
                section VARCHAR(16) DEFAULT 'body' NOT NULL,
                fallbackTpl VARCHAR(64) DEFAULT '' NOT NULL,
                fallback_text TEXT DEFAULT NULL,
                customTpl VARCHAR(64) DEFAULT '' NOT NULL,
                pages_scope VARCHAR(64) DEFAULT 'current_and_all_children' NOT NULL,
                pages TEXT DEFAULT NULL,
                pages_root TEXT DEFAULT NULL,
                active TINYINT DEFAULT 0 NOT NULL,
                enable_on_cookie_accept TINYINT DEFAULT 1 NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC"
        );

        foreach( $rows as $row ) {

            if( $row['type'] === 'google_analytics' || $row['type'] === 'google_analytics4' ) {
                $this->importGoogleAnalyticsTag($row);
            } else if( $row['type'] === 'google_tag_manager' ) {
                $this->importGoogleTagManagerTag($row);
            } else if( $row['type'] === 'facebook_pixel' ) {
                $this->importFacebookPixelTag($row);
            } else if( $row['type'] === 'matomo' ) {
                $this->importMatomoTag($row);
            } else {
                $this->importRow($row);
            }
        }

        return $this->createResult(true);
    }


    private function importGoogleAnalyticsTag( array $row ) : void {

        $row['type'] = 'google_analytics';

        $row['html'] = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $row['tag'] . '"></script>' ."\n";
        $row['html'] .= '<script>' ."\n";
        $row['html'] .= 'window.dataLayer = window.dataLayer || [];' ."\n";
        $row['html'] .= 'function gtag(){dataLayer.push(arguments);}' ."\n";
        $row['html'] .= "gtag('js', new Date());" ."\n";
        if( $row['anonymize_ip'] == '1' ) {
            $row['html'] .= "gtag('config', '" . $row['tag'] . "', { 'anonymize_ip': true });" ."\n";
        } else {
            $row['html'] .= "gtag('config', '" . $row['tag'] . "');" ."\n";
        }
        $row['html'] .= '</script>';

        $this->importRow($row);
    }


    private function importGoogleTagManagerTag( array $row ) : void {

        $row['html'] = '<script>' ."\n";
        $row['html'] .= "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':" ."\n";
        $row['html'] .= "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0]," ."\n";
        $row['html'] .= "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=" ."\n";
        $row['html'] .= "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);" ."\n";
        $row['html'] .= "})(window,document,'script','dataLayer','" . $row['tag']. "');" ."\n";
        $row['html'] .= '</script>';

        $this->importRow($row);
    }


    private function importFacebookPixelTag( array $row ) : void {

        $row['html'] = '<script>' ."\n";
        $row['html'] .= "!function(f,b,e,v,n,t,s)" ."\n";
        $row['html'] .= "{if(f.fbq)return;n=f.fbq=function(){n.callMethod?" ."\n";
        $row['html'] .= "n.callMethod.apply(n,arguments):n.queue.push(arguments)};" ."\n";
        $row['html'] .= "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';" ."\n";
        $row['html'] .= "n.queue=[];t=b.createElement(e);t.async=!0;" ."\n";
        $row['html'] .= "t.src=v;s=b.getElementsByTagName(e)[0];" ."\n";
        $row['html'] .= "s.parentNode.insertBefore(t,s)}(window,document,'script'," ."\n";
        $row['html'] .= "'https://connect.facebook.net/en_US/fbevents.js');" ."\n";
        $row['html'] .= "\n";

        $row['html'] .= "fbq('init', '" . $row['tag']. "');" ."\n";
        $row['html'] .= "\n";

        $row['html'] .= "document.addEventListener('DOMContentLoaded', function() {" ."\n";
        $row['html'] .= "    var fbpxevt;" ."\n";
        $row['html'] .= "    if( typeof(Event) === 'function' ) {" ."\n";
        $row['html'] .= "        fbpxevt = new Event('fbPixelInit');" ."\n";
        $row['html'] .= "    } else {" ."\n";
        $row['html'] .= "        fbpxevt = document.createEvent('Event');" ."\n";
        $row['html'] .= "        fbpxevt.initEvent('fbPixelInit', true, true);" ."\n";
        $row['html'] .= "    }" ."\n";
        $row['html'] .= "    fbpxevt.pixelId = '" . $row['tag']. "';" ."\n";
        $row['html'] .= "    document.dispatchEvent(fbpxevt);" ."\n";
        $row['html'] .= "}, false);" ."\n";
        $row['html'] .= "\n";

        $row['html'] .= "fbq('track', 'PageView');" ."\n";
        $row['html'] .= '</script>' ."\n";
        $row['html'] .= "\n";

        $row['html'] .= '<noscript>' ."\n";
        $row['html'] .= '<img height="1" width="1" src="https://www.facebook.com/tr?id=' . $row['tag']. '&ev=PageView&noscript=1" alt="" style="display:none !important;"/>' ."\n";
        $row['html'] .= '</noscript>';

        $this->importRow($row);
    }


    private function importMatomoTag( array $row ) : void {

        $row['html'] = '<script>' ."\n";
        $row['html'] .= 'var _paq = window._paq = window._paq || [];' ."\n";
        $row['html'] .= "_paq.push(['trackPageView']);" ."\n";
        $row['html'] .= "_paq.push(['enableLinkTracking']);" ."\n";
        $row['html'] .= '(function() {' ."\n";
        $row['html'] .= '    var u="//' . $row['matomo_url'] . '/";' ."\n";
        $row['html'] .= "    _paq.push(['setTrackerUrl', u+'matomo.php']);" ."\n";
        $row['html'] .= "    _paq.push(['setSiteId', '" . $row['matomo_siteid'] . "']);" ."\n";
        $row['html'] .= "    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];" ."\n";
        $row['html'] .= "    g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);" ."\n";
        $row['html'] .= '})();' ."\n";
        $row['html'] .= '</script>';

        $this->importRow($row);
    }


    private function importRow( array $row ) : void {

        // remove unknown fields
        $fields = [
            'id', 'pid', 'sorting', 'tstamp', 'type', 'name', 'root', 'description',
            'html', 'fallbackTpl', 'fallback_text', 'customTpl',
            'pages_scope', 'pages', 'pages_root', 'active', 'enable_on_cookie_accept'
        ];
        $row = array_intersect_key($row, array_combine($fields, $fields));

        // cast boolean
        $booleans = ['active', 'enable_on_cookie_accept'];
        foreach( $booleans as $field ) {
            $row[$field] = empty($row[$field]) ? 0 : 1;
        }

        $tTag = TagModel::getTable();
        $this->connection->insert($tTag, $row);
    }
}
