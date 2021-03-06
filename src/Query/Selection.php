<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Query;

/**
 * An immutable record selection with a predicate, an ordering and a limit.
 */
interface Selection
{

    /**
     * An optional predicate expression.
     *
     * @return Expression|null
     */
    public function getPredicate();

    /**
     * List of 2-tuples describing ordering.
     * Each tuple consists of a string
     * (the field name) and a bool (true if descending order, false if ascending).
     *
     * @return array[]
     */
    public function getOrdering();

    /**
     * Optional selection limit.
     *
     * @return int|null Limit.
     */
    public function getLimit();
}
