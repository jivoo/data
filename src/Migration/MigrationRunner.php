<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Migration;

use Jivoo\Models\DataType;
use Jivoo\Utilities;
use Jivoo\Data\Database\MigratableDatabase;
use Jivoo\Autoloader;
use Jivoo\Data\DefinitionBuilder;

/**
 * Schema and data migration runner.
 */
class MigrationRunner
{
    
    private $config;

    /**
     * @var MigratableDatabase[] Database connections.
     */
    private $connections = array();

    /**
     * @var Schema Table schema for SchemaRevision-table.
     */
    private $definition;

    /**
     * @var MigrationDefinition[] Array of definitions.
     */
    private $migrationDefinitions = array();

    /**
     * @var string[] Associative array of database names and migration dirs.
     */
    private $migrationDirs = array();

    /**
     * Construct migration runner.
     */
    public function __construct(\Jivoo\Store\Document $config)
    {
        $this->config = $config;
        
        $this->config->defaults = array(
            'automigrate' => false,
            'silent' => false,
            'mtimes' => array()
        );
        
        // Initialize SchemaRevision schema
        $this->definition = new \Jivoo\Data\DefinitionBuilder();
        $this->definition->revision = DataType::string(255);
        $this->definition->setPrimaryKey('revision');
    }

    /**
     * Get an attached database.
     *
     * @param string $name
     *            Database name.
     * @throws MigrationException If the database is not attached.
     * @return MigratableDatabase Database/
     */
    public function getDatabase($name)
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        throw new MigrationException('"' . $name . '" is not a migratable database');
    }

    /**
     * Attach a database for migrations.
     *
     * @param string $name Database name.
     * @param MigratableDatabase $db
     *            Database.
     * @param string $migrationDir
     *            Location of migrations.
     */
    public function attachDatabase($name, MigratableDatabase $db, $migrationDir)
    {
        $this->migrationDirs[$name] = $migrationDir;
        $this->connections[$name] = $db;
//        TODO:
//        if ($this->config['automigrate'] and isset($this->m->Setup)) {
//            if (! $this->m->Setup->isActive() and is_dir($this->migrationDirs[$name])) {
//                $mtime = filemtime($this->migrationDirs[$name] . '/.');
//                if (! isset($this->config['mtimes'][$name]) or $this->config['mtimes'][$name] != $mtime) {
//                    if ($this->config['silent']) {
//                        $missing = $this->check($name);
//                        foreach ($missing as $migration) {
//                            $this->run($name, $migration);
//                        }
//                        $this->finalize($name);
//                    } else {
//                        $this->m->Setup->trigger('Jivoo\Data\Migration\MigrationUpdater');
//                    }
//                }
//            }
//        }
    }

    /**
     * Whether or not the SchemaRevision table has been created.
     *
     * @param string $name
     *            Database name.
     * @return bool True if initialized, false otherwise.
     */
    public function isInitialized($name)
    {
        return isset($this->getDatabase($name)->SchemaRevision);
    }

    /**
     * Whether or not a database contains (conflicting) tables already.
     *
     * @param string $name
     *            Database name.
     * @return bool True if conflicting tables found.
     */
    public function isClean($name)
    {
        $db = $this->getDatabase($name);
        if (isset($db->SchemaRevision)) {
            return false;
        }
        $schema = $db->getSchema();
        foreach ($schema->getTables() as $table) {
            if (isset($db->$table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove all tables of a database including the SchemaRevision table.
     *
     * @param string $name
     *            Database name.
     */
    public function clean($name)
    {
        $db = $this->getDatabase($name);
        if (isset($db->SchemaRevision)) {
            $db->dropTable('SchemaRevision');
        }
        $schema = $db->getSchema();
        foreach ($schema->getTables() as $table) {
            if (isset($db->$table)) {
                $db->dropTable($table);
            }
        }
    }

    /**
     * Initialize a database for migrations by creating the SChemaRevision table.
     *
     * @param string $name
     *            Database name.
     */
    public function initialize($name)
    {
        $db = $this->getDatabase($name);
        $this->logger->info('Creating SchemaRevision table for ' . $name);
        $db->createTable('SchemaRevision', $this->definition);
        $records = array();
        foreach ($this->getMigrations($name) as $migration) {
            $records[] = array(
                'revision' => $migration
            );
        }
        $db->SchemaRevision->insertMultiple($records);
    }

    /**
     * Find migrations for a database.
     *
     * @param string $name
     *            Database name.
     * @return string[] List of migration class names.
     */
    public function getMigrations($name)
    {
        $migrationDir = $this->migrationDirs[$name];
        $migrations = array();
        if (is_dir($migrationDir)) {
            Autoloader::getInstance()->addPath('', $migrationDir);
            $files = scandir($migrationDir);
            if ($files !== false) {
                foreach ($files as $file) {
                    $split = explode('.', $file);
                    if (isset($split[1]) and $split[1] == 'php') {
                        $migrations[] = $split[0];
                    }
                }
            }
        }
        sort($migrations);
        return $migrations;
    }

    /**
     * Check a database for schema changes and initialize neccessary migrations.
     *
     * @param string $name
     *            Database name.
     * @return string[] Names of migrations that need to run.
     */
    public function check($name)
    {
        $db = $this->getDatabase($name);
        $db->SchemaRevision->setSchema($this->definition);
        // Schedule necessary migrations
        $currentState = array();
        foreach ($db->SchemaRevision->select('revision') as $row) {
            $currentState[$row['revision']] = true;
        }
        $migrations = $this->getMigrations($name);
        $missing = array();
        foreach ($migrations as $migration) {
            if (! isset($currentState[$migration])) {
                $missing[] = $migration;
            }
        }
        return $missing;
    }

    /**
     * Run a migration on a database.
     * Will attempt to revert if migration fails.
     *
     * @param string $dbName
     *            Name of database.
     * @param string $migrationName
     *            Name of migration.
     * @throws MigrationException If migration fails.
     */
    public function run($dbName, $migrationName)
    {
        $db = $this->getDatabase($dbName);
        $this->logger->info('Initializing migration ' . $migrationName);
        Utilities::assumeSubclassOf($migrationName, 'Jivoo\Data\Migration\Migration');
        
        // The migration definition keeps track of the state of the database
        if (! isset($this->migrationDefinitions[$dbName])) {
            $this->migrationDefinitions[$dbName] = new MigrationDefinition($db);
        }
        $migrationSchema = $this->migrationDefinitions[$dbName];
        
        $migration = new $migrationName($db, $migrationSchema);
        try {
            $migration->up();
            $db->SchemaRevision->insert(array(
                'revision' => $migrationName
            ));
        } catch (\Exception $e) {
            $migration->revert();
            throw new MigrationException('Migration failed: ' . $migrationName, null, $e);
        }
    }

    /**
     * Finalize the migration of a database.
     *
     * @param string $name
     *            Name of database.
     */
    public function finalize($name)
    {
        if (isset($this->migrationDefinitions[$name])) {
            $this->migrationDefinitions[$name]->finalize();
        }
        $mtime = filemtime($this->migrationDirs[$name] . '/.');
        $this->config['mtimes'][$name] = $mtime;
        $this->config->save();
    }
}
