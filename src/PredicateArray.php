<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data;

/**
 * An array with a predicate.
 */
class PredicateArray implements \ArrayAccess, \Countable, \Iterator
{

    /**
     * @var array
     */
    private $original;

    /**
     * @var callable
     */
    private $predicate;

    private $array = array();

    public function __construct($array, $predicate)
    {
        $this->original = $array;
        $this->predicate = $predicate;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        if (isset($this->array[$offset])) {
            return true;
        }
        if (isset($this->original[$offset])) {
            if (call_user_func($this->predicate, $this->original[$offset])) {
                $this->array[$offset] = $this->original[$offset];
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        if (isset($this->array[$offset])) {
            return $this->array[$offset];
        }
        if (isset($this->original[$offset])) {
            if (call_user_func($this->predicate, $this->original[$offset])) {
                $this->array[$offset] = $this->original[$offset];
                return $this->array[$offset];
            }
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->array[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        if (isset($this->array[$offset])) {
            unset($this->array[$offset]);
        }
        if (isset($this->original[$offset])) {
            unset($this->original[$offset]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return current($this->original);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return key($this->original);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        while (true) {
            next($this->original);
            if (key($this->original) === null) {
                break;
            }
            if (call_user_func($this->predicate, current($this->original))) {
                break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        reset($this->original);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return key($this->original) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
    }
}
