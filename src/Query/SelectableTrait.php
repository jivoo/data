<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Query;

use Jivoo\Data\Query\Builders\SelectionBuilder;

/**
 * A trait that implements {@see Selectable}.
 */
trait SelectableTrait
{
    
    /**
     * Return the data source to make selections on.
     *
     * @return \Jivoo\Data\DataSource
     */
    abstract protected function getSource();
    
    /**
     * Implements methods {@see Boolean::and()} and {@see Boolean::or()}.
     *
     * @param string $method
     *            Method name ('and' or 'or')
     * @param mixed[] $args
     *            List of parameters
     * @return static
     */
    public function __call($method, $args)
    {
        switch ($method) {
            case 'and':
            case 'or':
                $args = func_get_args();
                $selection = new SelectionBuilder($this->getSource());
                return call_user_func_array([$selection, $method . 'Where'], $args);
        }
        throw new \Jivoo\InvalidMethodException('Invalid method: ' . $method);
    }

    /**
     * Combine expression using AND operator.
     *
     * @param Expression|string $expr
     *            Expression
     * @param mixed $vars,...
     *            Additional values to replace placeholders in
     *            $expr with.
     * @return static
     */
    public function where($expr)
    {
        $args = func_get_args();
        $selection = new SelectionBuilder($this->getSource());
        return call_user_func_array([$selection, 'andWhere'], $args);
    }

    /**
     * Combine expression using AND operator.
     *
     * @param Expression|string $expr
     *            Expression
     * @param mixed $vars,...
     *            Additional values to replace placeholders in
     *            $expr with.
     * @return static
     */
    public function andWhere($expr)
    {
        $args = func_get_args();
        $selection = new SelectionBuilder($this->getSource());
        return call_user_func_array([$selection, 'andWhere'], $args);
    }

    /**
     * Combine expression using OR operator.
     *
     * @param Expression|string $expr
     *            Expression
     * @param mixed $vars,...
     *            Additional values to replace placeholders in
     *            $expr with.
     * @return static
     */
    public function orWhere($expr)
    {
        $args = func_get_args();
        $selection = new SelectionBuilder($this->getSource());
        return call_user_func_array([$selection, 'orWhere'], $args);
    }

    /**
     * Order selection by a column or expression.
     *
     * @param Expression|string|null $expression
     *            Expression or column.
     *            If null all ordering will be removed from selection.
     * @return SelectionBuilder A selection.
     */
    public function orderBy($expr)
    {
        $selection = new SelectionBuilder($this->getSource());
        return $selection->orderBy($expr);
    }

    /**
     * Order selection by a column or expression, in descending order.
     *
     * @param Expression|string $expression
     *            Expression or column.
     * @return SelectionBuilder A selection.
     */
    public function orderByDescending($expr)
    {
        $selection = new SelectionBuilder($this->getSource());
        return $selection->orderByDescending($expr);
    }

    /**
     * Reverse the ordering.
     *
     * @return SelectionBuilder A selection.
     */
    public function reverseOrder()
    {
        $selection = new SelectionBuilder($this->getSource());
        return $selection->reversOrder();
    }

    /**
     * Limit number of records.
     *
     * @param
     *            int Number of records.
     * @return SelectionBuilder A selection.
     */
    public function limit($limit)
    {
        $selection = new SelectionBuilder($this->getSource());
        return $selection->limit($limit);
    }
}
