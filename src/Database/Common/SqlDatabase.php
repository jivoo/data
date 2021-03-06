<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Database\Common;

use Jivoo\Data\Database\Database;

/**
 * An SQL database.
 */
interface SqlDatabase extends Database, \Jivoo\Data\Query\Expression\Quoter
{
    
    /**
     * Execute a raw sql query on database.
     *
     * @param string $sql
     *            Raw sql.
     * @return \Jivoo\Data\Database\ResultSet A result set.
     * @throws \Jivoo\Data\Database\QueryException if query failed.
     */
    public function query($sql);
    
    /**
     * Execute a raw sql insert or replace statement on database.
     *
     * @param string $sql
     *            Raw sql.
     * @param string|null $pk
     *            Name of auto incrementing primary key if any (only needed for
     *            some database systems).
     * @return int The last insert id if any.
     * @throws \Jivoo\Data\Database\QueryException if query failed.
     */
    public function insert($sql, $pk = null);
    
    /**
     * Get SQL for LIMIT / OFFSET.
     *
     * @param int $limit
     *            Limit.
     * @param int|null $offset
     *            Optional offset.
     * @return string SQL.
     */
    public function sqlLimitOffset($limit, $offset = null);
    
    /**
     * Execute a raw sql statement on database.
     *
     * @param string $sql
     *            Raw sql.
     * @return int Number of affected rows.
     * @throws \Jivoo\Data\Database\QueryException if query failed.
     */
    public function execute($sql);
    
    public function tableExists($table);
    
    public function createTable($table);
    
    public function dropTable($table);
    
    /**
     * Convert table name.
     * E.g. "UserSession" to "prefix_user_session".
     *
     * @param string $name
     *            Table name.
     * @return string Real table name.
     */
    public function tableName($name);

    /**
     * Escape a string and surround with quotation marks.
     *
     * @param string $string
     *            String.
     * @return string String surrounded with quotation marks.
     */
    public function quoteString($string);

    /**
     * Get type adapter.
     *
     * @return \Jivoo\Data\Database\TypeAdapter Type adapter for database.
     */
    public function getTypeAdapter();
}
