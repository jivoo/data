<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Database;

/**
 * A type and migration adapter.
 */
interface MigrationTypeAdapter extends Migratable, TypeAdapter, DatabaseDefinition
{

    /**
     * Whether or not a table exists.
     *
     * @param string $table
     *            Table name.
     * @return bool True if table exists, false otherwise.
     */
    public function tableExists($table);
}
