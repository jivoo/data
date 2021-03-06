<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Query;

use Jivoo\Data\Query\Builders\DeleteSelectionBuilder;

/**
 * A trait that implements {@see Deletable}.
 */
trait DeletableTrait
{
    
    /**
     * Return the data source to make selections on.
     *
     * @return \Jivoo\Data\DataSource
     */
    abstract protected function getSource();

    /**
     * Delete selected records.
     *
     * @return int Number of deleted records.
     */
    public function delete()
    {
        $selection = new DeleteSelectionBuilder($this->getSource());
        return $selection->delete();
    }
}
