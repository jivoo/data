<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data;

/**
 * Contains mutable data for a single record belonging to a {@see Model}.
 */
interface Record extends \ArrayAccess
{

    /**
     * Get value of a field.
     *
     * @param string $field
     *            Field name.
     * @return mixed Value.
     * @throws \Jivoo\InvalidPropertyException If the field does not exist.
     */
    public function __get($field);

    /**
     * Determine if a field is set.
     *
     * @param string $field
     *            Field name.
     * @return bool True if not null, false otherwise.
     * @throws \Jivoo\InvalidPropertyException If the field does not exist.
     */
    public function __isset($field);
    
    /**
     * Get model.
     *
     * @return Model Model.
     */
    public function getModel();
    
    /**
     * Get the data definition.
     *
     * @return Definition Definition.
     */
    public function getDefinition();

    /**
     * Get non-virtual data as an associative array.
     *
     * @return array Array of data.
     */
    public function getData();
    
    /**
     * Get virtual data as an associative array. Virtual data can be accessed
     * with the getters and setters like non-virtual data, but is not saved.
     *
     * @return array Array of data.
     */
    public function getVirtualData();

    /**
     * Set value of a field.
     *
     * @param string $field
     *            Field name.
     * @param mixed $value
     *            Value.
     * @throws \Jivoo\InvalidPropertyException If the field does not exist.
     */
    public function __set($field, $value);

    /**
     * Set a field value to null.
     *
     * @param string $field
     *            Field name.
     * @throws \Jivoo\InvalidPropertyException If the field does not exist.
     */
    public function __unset($field);

    /**
     * Set value of a field (for chaining).
     *
     * @param string $field
     *            Field name.
     * @param mixed $value
     *            Value.
     * @return self Self.
     * @throws \Jivoo\InvalidPropertyException If the field does not exist.
     */
    public function set($field, $value);

    /**
     * Add data to record.
     *
     * @param array $data
     *            Associative array of field names and values.
     * @param string[]|null $allowedFields
     *            List of allowed fields (null for all
     *            fields allowed), fields that are not allowed (or not in the model) will be
     *            ignored.
     */
    public function addData(array $data, $allowedFields = null);

    /**
     * Get associative array of field names and error messages.
     *
     * @return string[] Associative array of field names and error messages.
     */
    public function getErrors();

    /**
     * Whether or not the record contains errors.
     *
     * @return bool True if record is considered valid (i.e. no errors).
     */
    public function isValid();

    /**
     * Save record.
     *
     * @return bool True if successfully saved, false on errors.
     */
    public function save();

    /**
     * Delete record.
     */
    public function delete();

    /**
     * Determine if the record is new (i.e. not yet saved).
     *
     * @return bool True if new, false otherwise.
     */
    public function isNew();

    /**
     * Determine if the record has unsaved data.
     *
     * @return bool True if saved, false if unsaved data exists.
     */
    public function isSaved();
}
