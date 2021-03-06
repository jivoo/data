<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Database;

use Jivoo\Assume;
use Jivoo\Store\Document;
use Jivoo\InvalidPropertyException;
use Jivoo\Json;
use Psr\Log\LoggerAwareInterface as LoggerAware;
use Psr\Log\LoggerInterface as Logger;
use Jivoo\Log\NullLogger;

/**
 * Connects to databases.
 */
class Loader implements LoggerAware
{

    /**
     * @var Document
     */
    private $config;

    /**
     * @var string
     */
    private $drivers;

    /**
     * @var LodableDatabase[] Named database connections.
     */
    private $connections = array();

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Construct database loader.
     */
    public function __construct(Document $config = null)
    {
        $this->logger = new NullLogger();
        if (! isset($config)) {
            $config = new Document();
        }
        $this->config = $config;
        $this->drivers = dirname(__FILE__) . '/Drivers';
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get a database connection.
     *
     * @param string $name
     *            Connection name.
     * @return LoadableDatabase Database.
     */
    public function __get($name)
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        throw new InvalidPropertyException('No connection named: ' . $name);
    }

    /**
     * {@inheritdoc}
     */
    public function __isset($name)
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get all database connections.
     *
     * @return LoadableDatabase[] Associative array of database names and
     *         connections.
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Get information about a database driver.
     *
     * The returned information array is of the format:
     * <code>
     * array(
     * 'driver' => ..., // Driver name (string)
     * 'name' => ..., // Formal name, e.g. 'MySQL' instead of 'MySql' (string)
     * 'requiredOptions' => array(...), // List of required options (string[])
     * 'optionalOptions' => array(...), // List of optional options (string[])
     * 'isAvailable' => ..., // Whether or not driver is available (bool)
     * 'missingExtensions => array(...) // List of missing extensions (string[])
     * )
     * </code>
     *
     * @param string $driver
     *            Driver name
     * @return array Driver information as an associative array.
     * @throws InvalidDriverException If driver is missing or invalid.
     */
    public function checkDriver($driver)
    {
        if (! file_exists($this->drivers . '/' . $driver . '/' . $driver . 'Database.php')) {
            throw new InvalidDriverException('Driver class not found: ' . $driver);
        }
        if (! file_exists($this->drivers . '/' . $driver . '/driver.json')) {
            throw new InvalidDriverException('Driver manifest not found: ' . $driver);
        }
        try {
            $info = Json::decodeFile($this->drivers . '/' . $driver . '/driver.json');
        } catch (JsonException $e) {
            throw new InvalidDriverException(
                'Invalid driver manifest: ' . $driver . ' (' . $e->getMessage() . ')',
                0,
                $e
            );
        }
        if (! isset($info['required'])) {
            $info['required'] = array();
        }
        if (! isset($info['optional'])) {
            $info['optional'] = array();
        }
        if (! isset($info['phpExtensions'])) {
            $info['phpExtensions'] = array();
        }
        $missing = array();
        foreach ($info['phpExtensions'] as $dependency) {
            if (! extension_loaded($dependency)) {
                $missing[] = $dependency;
            }
        }
        return array(
            'driver' => $driver,
            'name' => $info['name'],
            'requiredOptions' => $info['required'],
            'optionalOptions' => $info['optional'],
            'isAvailable' => count($missing) < 1,
            'missingExtensions' => $missing
        );
    }

    /**
     * Get an array of all drivers and their information.
     *
     * @return array An associative array of driver names and driver information
     *         as returned by {@see Database::checkDriver()}.
     */
    public function listDrivers()
    {
        $drivers = array();
        $files = scandir($this->drivers);
        if ($files !== false) {
            foreach ($files as $driver) {
                if (is_dir($this->drivers . '/' . $driver)) {
                    try {
                        $drivers[$driver] = $this->checkDriver($driver);
                    } catch (InvalidDriverException $e) {
                        $this->logger->warning($e->getMessage(), array(
                            'exception' => $e
                        ));
                    }
                }
            }
        }
        return $drivers;
    }

    /**
     * Read definitions from a namespace.
     *
     * @param string $namespace
     *            Namespace of schema classes.
     * @param string $dir
     *            Location of schema classes.
     * @return DatabaseDefinitionBuilder Database schema.
     */
    public function readDefinition($namespace, $dir)
    {
        $definition = new DatabaseDefinitionBuilder();
        Assume::that(is_dir($dir));
        $files = scandir($dir);
        if ($files !== false) {
            foreach ($files as $file) {
                $split = explode('.', $file);
                if (isset($split[1]) and $split[1] == 'php') {
                    $class = rtrim($namespace, '\\') . '\\' . $split[0];
                    $definition->addDefinition($split[0], $class::getDefinition());
                }
            }
        }
        return $definition;
    }

    /**
     * Make a database connection.
     *
     * @param string|Document|array $name
     *            Name of database connection.
     * @param DatabaseDefinition $definition
     *            Database definition (collecton of table definitions).
     * @throws ConfigurationException If the $options-array does not
     *         contain the necessary information for a connection to be made.
     * @throws InvalidSchemaException If one of the schema names listed
     *         in the $schemas-parameter is unknown.
     * @throws ConnectionException If the connection fails.
     * @return LoadableDatabase A database object.
     */
    public function connect($name, DatabaseDefinition $definition = null)
    {
        if (is_string($name)) {
            if (! isset($this->config[$name])) {
                throw new ConfigurationException('Database "' . $name . '" not configured');
            }
            $config = $this->config->getSubset($name);
        } elseif (is_array($name)) {
            $config = new Document($name);
            unset($name);
        } else {
            Assume::isInstanceOf($name, 'Jivoo\Store\Document');
            $config = $name;
            unset($name);
        }
        $driver = $config->get('driver', null);
        if (! isset($driver)) {
            throw new ConfigurationException('Database driver not set');
        }
        try {
            $driverInfo = $this->checkDriver($driver);
        } catch (InvalidDriverException $e) {
            throw new ConnectionException('Invalid database driver: ' . $e->getMessage(), 0, $e);
        }
        foreach ($driverInfo['requiredOptions'] as $option) {
            if (! isset($config[$option])) {
                throw new ConfigurationException('Database option missing: "' . $option . '"');
            }
        }
        try {
            $class = 'Jivoo\Data\Database\Drivers\\' . $driver . '\\' . $driver . 'Database';
            Assume::isSubclassOf($class, 'Jivoo\Data\Database\LoadableDatabase');
            if (! isset($definition)) {
                $definition = new DatabaseDefinitionBuilder([]);
            }
            $object = new $class($definition, $config);
            $object->setLogger($this->logger);
            if (isset($name)) {
                $this->connections[$name] = new DatabaseSchema($object);
            }
            return $object;
        } catch (ConnectionException $exception) {
            throw new ConnectionException(
                'Database connection failed (' . $driver . '): ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * Close all connections.
     */
    public function close()
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }

    /**
     * Begin transaction in all connections.
     */
    public function beginTransaction()
    {
        foreach ($this->connections as $connection) {
            $connection->beginTransaction();
        }
    }

    /**
     * Commit all transactions.
     */
    public function commit()
    {
        foreach ($this->connections as $connection) {
            $connection->commit();
        }
    }

    /**
     * Rollback all transactions.
     */
    public function rollback()
    {
        foreach ($this->connections as $connection) {
            $connection->rollback();
        }
    }
}
