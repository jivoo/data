<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Query;

use Jivoo\Data\DataType;
use Jivoo\Data\DataSource;
use Jivoo\Data\Definition;

/**
 * An interface for readable models and selections.
 */
interface Readable extends Selectable, \Traversable, \Countable
{

    /**
     * Set offset.
     *
     * @param int $offset
     *            Offset.
     * @return static
     */
    public function offset($offset);

    /**
     * Set alias for selection source.
     *
     * @param string $alias
     *            Alias.
     * @return static
     */
    public function alias($alias);

    /**
     * Make a projection.
     *
     * @param string|string[]|Expression|Expression[] $expression
     *            Expression or array of expressions (if the keys are strings,
     *            they are used as aliases).
     * @param string $alias
     *            Alias.
     * @return \Iterator A {@see Record} iterator.
     * @todo Rename to 'project' ?
     */
    public function select($expression, $alias = null);

    /**
     * Append an extra virtual field to the returned records.
     *
     * @param string $field
     *            Name of new field.
     * @param Expression|string $expression
     *            Expression for field, e.g. 'COUNT(*)'.
     * @param DataType|null $type
     *            Optional type of field.
     * @return static
     */
    public function with($field, $expression, DataType $type = null);

    /**
     * Append an extra virtual field (with a record as the value) to the returned
     * records.
     *
     * @param string $field
     *            Name of new field, expects the associated model to be
     *            aliased with the same name.
     * @param Definition $schema
     *            Schema of associated record.
     * @return static
     */
    public function withRecord($field, Definition $schema);

    /**
     * Group by one or more columns.
     *
     * @param string|string[] $columns
     *            A single column name or a list of column
     *            names.
     * @param Expression|string $predicate
     *            Grouping predicate.
     * @return static
     */
    public function groupBy($columns, $predicate = null);

    /**
     * Perform an inner join with another data source.
     *
     * @param DataSource $other
     *            Other source.
     * @param string|Expression $condition
     *            Join condition.
     * @param string $alias
     *            Alias for joined model/table.
     * @return static
     */
    public function innerJoin(DataSource $other, $condition, $alias = null);

    /**
     * Perform a left join with another data source.
     *
     * @param DataSource $other
     *            Other source.
     * @param string|Expression $condition
     *            Join condition.
     * @param string $alias
     *            Alias for joined model/table.
     * @return static
     */
    public function leftJoin(DataSource $other, $condition, $alias = null);

    /**
     * Perform a right join with another data source.
     *
     * @param DataSource $other
     *            Other source.
     * @param string|Expression $condition
     *            Join condition.
     * @param string $alias
     *            Alias for joined model/table.
     * @return static
     */
    public function rightJoin(DataSource $other, $condition, $alias = null);

    /**
     * Fetch only distinct records (i.e.
     * prevent duplicate records in result).
     *
     * @param bool $distinct
     *            Whether to fetch only distinct records.
     * @return static
     */
    public function distinct($distinct = true);

    /**
     * Return first record in selection.
     *
     * @return \Jivoo\Data\Record|null A record if available..
     */
    public function first();

    /**
     * Return last record in selection.
     *
     * @return \Jivoo\Data\Record|null A record if available.
     */
    public function last();

    /**
     * Find row number of a record in selection.
     *
     * @param \Jivoo\Data\Record $record
     *            A record.
     * @return int Row number.
     */
    public function rowNumber(\Jivoo\Data\Record $record);
    
    /**
     * Count number of records in selection.
     *
     * @return int Number of records.
     */
    // public function count();
    
    /**
     * Convert selection to an array.
     *
     * @return \Jivoo\Data\Record[] Array of records.
     */
    public function toArray();
}
