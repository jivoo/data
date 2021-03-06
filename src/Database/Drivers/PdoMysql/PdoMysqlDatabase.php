<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\Database\Drivers\PdoMysql;

use Jivoo\Data\Database\Common\MysqlTypeAdapter;
use Jivoo\Data\Database\Common\PdoDatabase;
use Jivoo\Data\Database\ConnectionException;

/**
 * PDO MySQL database driver.
 */
class PdoMysqlDatabase extends PdoDatabase
{

    /**
     * {@inheritdoc}
     */
    public function init($options = array())
    {
        $this->setTypeAdapter(new MysqlTypeAdapter($this));
        if (isset($options['tablePrefix'])) {
            $this->tablePrefix = $options['tablePrefix'];
        }
        try {
            if (isset($options['password'])) {
                $this->pdo = new \PDO(
                    'mysql:host=' . $options['server'] . ';dbname=' . $options['database'],
                    $options['username'],
                    $options['password']
                );
            } else {
                $this->pdo = new \PDO(
                    'mysql:host=' . $options['server'] . ';dbname=' . $options['database'],
                    $options['username']
                );
            }
        } catch (\PDOException $exception) {
            throw new ConnectionException($exception->getMessage(), 0, $exception);
        }
    }
}
