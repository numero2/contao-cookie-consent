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

use Contao\ContentModel;
use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\ModuleModel;
use Doctrine\DBAL\Connection;


class CmsContentModuleFieldsMigration extends AbstractMigration {


    /**
     * @var Doctrine\DBAL\Connection
     */
    private Connection $connection;

    /**
     * @var array
     */
    private array $tables;

    /**
     * @var array
     */
    private array $fields;


    public function __construct( Connection $connection ) {

        $this->connection = $connection;

        $this->tables = [ContentModel::getTable(), ModuleModel::getTable()];
        $this->fields = [
            'cms_tag_visibility' => 'cc_tag_visibility',
            'cms_tag' => 'cc_tag',
        ];
    }


    public function shouldRun(): bool {

        $schemaManager = $this->connection->createSchemaManager();

        foreach( $this->tables as $table ) {

            if( !$schemaManager->tablesExist([$table]) ) {
                continue;
            }

            $columns = array_map(function( $column ) {
                return $column->getName();
            }, $schemaManager->listTableColumns($table));

            foreach( $this->fields as $cmsField => $field ) {
                if( in_array($cmsField, $columns) && !in_array($field, $columns) ) {
                    return true;
                }
            }
        }

        return false;
    }


    public function run(): MigrationResult {

        $schemaManager = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();

        foreach( $this->tables as $table ) {

            if( !$schemaManager->tablesExist([$table]) ) {
                continue;
            }

            $columns = $schemaManager->listTableColumns($table);
            $columnNames = array_map(function( $column ) {
                return $column->getName();
            }, $columns);

            foreach( $columns as $column ) {

                $name = $column->getName();

                if( !array_key_exists($name, $this->fields) ) {
                    continue;
                }

                $nameNew = $this->fields[$name];

                if( in_array($nameNew, $columnNames) ) {
                    continue;
                }

                $sqlDefinition = $platform->getColumnDeclarationSQL(
                    $column->getName(),
                    $column->toArray()
                );

                $sqlDefinition = str_replace($name, $nameNew, $sqlDefinition);

                $this->connection->executeStatement("ALTER TABLE $table ADD $sqlDefinition");
                $this->connection->executeStatement("UPDATE $table SET $nameNew=$name");
            }
        }

        return $this->createResult(true);
    }
}
