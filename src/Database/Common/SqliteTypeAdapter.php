<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Database\Common;

use Jivoo\Data\Database\MigrationTypeAdapter;
use Jivoo\Data\DefinitionBuilder;
use Jivoo\Data\DataType;
use Jivoo\Data\Query\E;
use Jivoo\Utilities;
use Jivoo\Json;
use Jivoo\Data\Database\TypeException;

/**
 * Type and migration adapter for SQLite database drivers.
 */
class SqliteTypeAdapter implements MigrationTypeAdapter
{

    /**
     * @var SqlDatabase Database.
     */
    private $db;

    /**
     * Construct type adapter.
     *
     * @param SqlDatabaseBase $db
     *            Database.
     */
    public function __construct(SqlDatabase $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function encode(DataType $type, $value)
    {
        $value = $type->convert($value);
        if (! isset($value)) {
            return 'NULL';
        }
        switch ($type->type) {
            case DataType::BOOLEAN:
                return $value ? 1 : 0;
            case DataType::INTEGER:
            case DataType::DATETIME:
            case DataType::DATE:
                return intval($value);
            case DataType::FLOAT:
                return floatval($value);
            case DataType::STRING:
            case DataType::TEXT:
            case DataType::BINARY:
            case DataType::ENUM:
                return $this->db->quoteString($value);
            case DataType::OBJECT:
                return $this->db->quoteString(Json::encode($value));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decode(DataType $type, $value)
    {
        if (! isset($value)) {
            return null;
        }
        switch ($type->type) {
            case DataType::BOOLEAN:
                return $value != 0;
            case DataType::INTEGER:
            case DataType::DATE:
            case DataType::DATETIME:
                return intval($value);
            case DataType::FLOAT:
                return floatval($value);
            case DataType::TEXT:
            case DataType::BINARY:
            case DataType::STRING:
            case DataType::ENUM:
                return strval($value);
            case DataType::OBJECT:
                return Json::decode($value);
        }
    }

    /**
     * Convert a schema type to an SQLite type
     *
     * @param DataType $type
     *            Type.
     * @param bool $isPrimaryKey
     *            True if primary key.
     * @return string SQLite type.
     */
    public function fromDataType(DataType $type, $isPrimaryKey = false)
    {
        $primaryKey = '';
        if ($isPrimaryKey) {
            $primaryKey = ' PRIMARY KEY';
        }
        switch ($type->type) {
            case DataType::INTEGER:
                if ($type->size == DataType::BIG) {
                    $column = 'INTEGER(8)';
                } elseif ($type->size == DataType::SMALL) {
                        $column = 'INTEGER(2)';
                } elseif ($type->size == DataType::TINY) {
                        $column = 'INTEGER(1)';
                } else {
                    $column = 'INTEGER';
                }
                if ($isPrimaryKey and $type->serial) {
                    $primaryKey .= ' AUTOINCREMENT';
                }
                break;
            case DataType::FLOAT:
                $column = 'REAL';
                break;
            case DataType::STRING:
                $column = 'TEXT(' . $type->length . ')';
                break;
            case DataType::BOOLEAN:
                $column = 'INTEGER(1)';
                break;
            case DataType::BINARY:
                $column = 'BLOB';
                break;
            case DataType::DATE:
                $column = 'INTEGER';
                break;
            case DataType::DATETIME:
                $column = 'INTEGER';
                break;
            case DataType::TEXT:
            case DataType::ENUM:
            case DataType::OBJECT:
            default:
                $column = 'TEXT';
                break;
        }
        $column .= $primaryKey;
        if ($type->notNull) {
            $column .= ' NOT';
        }
        $column .= ' NULL';
        if (isset($type->default)) {
            $column .= E::interpolate(' DEFAULT %_', array(
                $type,
                $type->default
            ), $this->db);
        }
        return $column;
    }

    /**
     * Convert output of PRAGMA to DataType.
     *
     * @param array $row
     *            Row result.
     * @throws TypeException If type unsupported.
     * @return DataType The type.
     */
    private function toDataType($row)
    {
        if (preg_match('/ *([^ (]+) *(\(([0-9]+)\))? */i', $row['type'], $matches) !== 1) {
            throw new TypeException('Cannot read type "' . $row['type'] . '" for column: ' . $row['name']);
        }
        $actualType = strtolower($matches[1]);
        $length = isset($matches[3]) ? $matches[3] : 0;
        $null = (isset($row['notnull']) and $row['notnull'] != '1');
        $default = null;
        if (isset($row['dflt_value'])) {
            $default = stripslashes(preg_replace('/^\'|\'$/', '', $row['dflt_value']));
        }
        switch ($actualType) {
            case 'integer':
                return DataType::integer(DataType::BIG, $null, isset($default) ? intval($default) : null);
            case 'real':
                return DataType::float($null, isset($default) ? floatval($default) : null);
            case 'text':
                return DataType::text($null, $default);
            case 'blob':
                return DataType::binary($null, $default);
        }
        throw new TypeException('Unsupported SQLite type for column: ' . $row['name']);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition($table)
    {
        $result = $this->db->query('PRAGMA table_info("' . $this->db->tableName($table) . '")');
        $definition = new DefinitionBuilder();
        $primaryKey = array();
        while ($row = $result->fetchAssoc()) {
            $column = $row['name'];
            if (isset($row['pk']) and $row['pk'] == '1') {
                $primaryKey[] = $column;
            }
            $definition->$column = $this->toDataType($row);
        }
        $definition->setPrimaryKey($primaryKey);
        $result = $this->db->query('PRAGMA index_list("' . $this->db->tableName($table) . '")');
        while ($row = $result->fetchAssoc()) {
            $key = $row['name'];
            $unique = $row['unique'] == 1;
            $name = preg_replace(
                '/^' . preg_quote($this->db->tableName($table) . '_', '/') . '/',
                '',
                $key,
                1,
                $count
            );
            if ($count == 0) {
                continue;
            }
            $columnResult = $this->db->query('PRAGMA index_info("' . $key . '")');
            $columns = array();
            while ($row = $columnResult->fetchAssoc()) {
                $columns[] = $row['name'];
            }
            if ($unique) {
                $definition->addUnique($columns, $name);
            } else {
                $definition->addKey($columns, $name);
            }
        }
        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function tableExists($table)
    {
        $result = $this->db->query('PRAGMA table_info("' . $this->db->tableName($table) . '")');
        return $result->hasRows();
    }

    /**
     * {@inheritdoc}
     */
    public function getTables()
    {
        $prefix = $this->db->tableName('');
        $prefixLength = strlen($prefix);
        $result = $this->db->query('SELECT name FROM sqlite_master WHERE type = "table"');
        $tables = array();
        while ($row = $result->fetchRow()) {
            $name = $row[0];
            if (substr($name, 0, $prefixLength) == $prefix) {
                $name = substr($name, $prefixLength);
                $tables[] = Utilities::underscoresToCamelCase($name);
            }
        }
        return $tables;
    }

    /**
     * {@inheritdoc}
     */
    public function createTable($table, \Jivoo\Data\Definition $definition)
    {
        $sql = 'CREATE TABLE "' . $this->db->tableName($table) . '" (';
        $columns = $definition->getFields();
        $first = true;
        $primaryKey = $definition->getPrimaryKey();
        $singlePrimary = count($primaryKey) == 1;
        foreach ($columns as $column) {
            $type = $definition->getType($column);
            if (! $first) {
                $sql .= ', ';
            } else {
                $first = false;
            }
            $sql .= $column;
            $sql .= ' ' . $this->fromDataType($type, $singlePrimary and $primaryKey[0] == $column);
        }
        if (! $singlePrimary) {
            $sql .= ', PRIMARY KEY (' . implode(', ', $definition->getPrimaryKey()) . ')';
        }
        $sql .= ')';
        $this->db->execute($sql);
        foreach ($definition->getKeys() as $key) {
            if ($key == 'PRIMARY') {
                continue;
            }
            $sql = 'CREATE';
            if ($definition->isUnique($key)) {
                $sql .= ' UNIQUE';
            }
            $sql .= ' INDEX "';
            $sql .= $this->db->tableName($table) . '_' . $key;
            $sql .= '" ON "' . $this->db->tableName($table);
            $sql .= '" (';
            $sql .= implode(', ', $definition->getKey($key)) . ')';
            $this->db->execute($sql);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function renameTable($table, $newName)
    {
        try {
            $definition = $this->db->getDefinition()->getDefinition($table);
            $this->db->beginTransaction();
            $this->createTable($newName, $definition);
            $sql = 'INSERT INTO ' . $this->db->quoteModel($newName);
            $sql .= ' SELECT * FROM ' . $this->db->quoteModel($table);
            $this->db->execute($sql);
            $this->dropTable($table);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($table)
    {
        $sql = 'DROP TABLE "' . $this->db->tableName($table) . '"';
        $this->db->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn($table, $column, DataType $type)
    {
        $sql = 'ALTER TABLE "' . $this->db->tableName($table) . '" ADD ' . $column;
        $sql .= ' ' . $this->fromDataType($type);
        $this->db->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteColumn($table, $column)
    {
        try {
            $definition = $this->db->getDefinition()->getDefinition($table);
            $this->db->beginTransaction();
            $tempName = $table . '_MigrationBackup';
            $this->createTable($tempName, $definition);
            $sql = 'INSERT INTO ' . $this->db->quoteModel($tempName);
            $sql .= ' SELECT * FROM ' . $this->db->quoteModel($table);
            $this->db->execute($sql);
            $this->dropTable($table);
            $newDefinition = new DefinitionBuilder($definition);
            unset($newDefinition->$column);
            $this->createTable($table, $newDefinition);
            $sql = 'INSERT INTO ' . $this->db->quoteModel($table);
            $sql .= ' SELECT ' . implode(', ', $newDefinition->getFields());
            $sql .= ' FROM ' . $this->db->quoteModel($tempName);
            $this->db->execute($sql);
            $this->dropTable($tempName);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function alterColumn($table, $column, DataType $type)
    {
        try {
            $definition = $this->db->getDefinition()->getDefinition($table);
            $this->db->beginTransaction();
            $tempName = $table . '_MigrationBackup';
            $this->createTable($tempName, $definition);
            $sql = 'INSERT INTO ' . $this->db->quoteModel($tempName);
            $sql .= ' SELECT * FROM ' . $this->db->quoteModel($table);
            $this->db->execute($sql);
            $this->dropTable($table);
            $newDefinition = new DefinitionBuilder($definition);
            $newDefinition->$column = $type;
            $this->createTable($table, $newDefinition);
            $sql = 'INSERT INTO ' . $this->db->quoteModel($table);
            $sql .= ' SELECT * FROM ' . $this->db->quoteModel($tempName);
            $this->db->execute($sql);
            $this->dropTable($tempName);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function renameColumn($table, $column, $newName)
    {
        try {
            $definition = $this->db->getDefinition()->getDefinition($table);
            $this->db->beginTransaction();
            $tempName = $table . '_MigrationBackup';
            $this->createTable($tempName, $definition);
            $sql = 'INSERT INTO ' . $this->db->quoteModel($tempName);
            $sql .= ' SELECT * FROM ' . $this->db->quoteModel($table);
            $this->db->execute($sql);
            $this->dropTable($table);
            $newDefinition = new DefinitionBuilder($definition);
            $type = $newDefinition->getType($column);
            unset($newDefinition->$column);
            $newDefinition->$newName = $type;
            $this->createTable($table, $newDefinition);
            $columns = array();
            foreach ($definition->getFields() as $field) {
                if ($field != $column) {
                    $columns[] = $field;
                }
            }
            $columns[] = $column;
            $sql = 'INSERT INTO ' . $this->db->quoteModel($table);
            $sql .= ' SELECT ' . implode(', ', $columns) . ' FROM ' . $this->db->quoteModel($tempName);
            $this->db->execute($sql);
            $this->dropTable($tempName);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createKey($table, $key, array $columns, $unique = true)
    {
        $sql = 'CREATE';
        if ($unique) {
            $sql .= ' UNIQUE';
        }
        $sql .= ' INDEX "';
        $sql .= $this->db->tableName($table) . '_' . $key;
        $sql .= '" ON "' . $this->db->tableName($table);
        $sql .= '" (';
        $sql .= implode(', ', $columns) . ')';
        $this->db->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteKey($table, $key)
    {
        $sql = 'DROP INDEX "';
        $sql .= $this->db->tableName($table) . '_' . $key . '"';
        $this->db->execute($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function alterKey($table, $key, array $columns, $unique = true)
    {
        try {
            $this->db->beginTransaction();
            $this->deleteKey($table, $key);
            $this->createKey($table, $key, $columns, $unique);
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
