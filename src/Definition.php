<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data;

/**
 * Data source definition.
 */
interface Definition
{

    /**
     * Get list of fields.
     *
     * @return string[] List of field names.
     */
    public function getFields();

    /**
     * Get type of field
     *
     * @param string $field
     *            Field name.
     * @return DataType|null Type of field or null if not defined.
     */
    public function getType($field);
    
    /**
     * Whether the field is virtual.
     *
     * @param string $field
     *            Field name.
     * @return bool True if field is virtual.
     */
    public function isVirtual($field);

    /**
     * Get fields of primary key.
     * Should return same result as `getKey('PRIMARY')`.
     *
     * @return string[] List of field names or empty array if no primary key.
     */
    public function getPrimaryKey();

    /**
     * Get names of indexes/keys.
     *
     * @return string[] Names of keys.
     */
    public function getKeys();

    /**
     * Get fields of key.
     *
     * @param string $key
     *            Key name.
     * @return string[] List of field names.
     */
    public function getKey($key);

    /**
     * Whether key is unique.
     *
     * @param string $key
     *            Key name.
     * @return bool True if unique, false otherwise.
     */
    public function isUnique($key);
}
