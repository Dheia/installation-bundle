<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\InstallationBundle\Database;

use Contao\CoreBundle\Doctrine\Schema\DcaSchemaProvider;
use Doctrine\DBAL\Connection;

class Installer
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $commands;

    /**
     * @var DcaSchemaProvider
     */
    private $schemaProvider;

    /**
     * @param Connection        $connection
     * @param DcaSchemaProvider $schemaProvider
     */
    public function __construct(Connection $connection, DcaSchemaProvider $schemaProvider)
    {
        $this->connection = $connection;
        $this->schemaProvider = $schemaProvider;
    }

    /**
     * Returns the commands as array.
     *
     * @return array
     */
    public function getCommands(): array
    {
        if (null === $this->commands) {
            $this->compileCommands();
        }

        return $this->commands;
    }

    /**
     * Executes a command.
     *
     * @param string $hash
     *
     * @throws \InvalidArgumentException
     */
    public function execCommand(string $hash): void
    {
        if (null === $this->commands) {
            $this->compileCommands();
        }

        foreach ($this->commands as $commands) {
            if (isset($commands[$hash])) {
                $this->connection->query($commands[$hash]);

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('Invalid hash: %s', $hash));
    }

    /**
     * Compiles the command required to update the database.
     */
    private function compileCommands(): void
    {
        $return = [
            'CREATE' => [],
            'ALTER_TABLE' => [],
            'ALTER_CHANGE' => [],
            'ALTER_ADD' => [],
            'DROP' => [],
            'ALTER_DROP' => [],
        ];

        $config = $this->connection->getConfiguration();

        // Overwrite the schema filter (see #78)
        $previousFilter = $config->getFilterSchemaAssetsExpression();
        $config->setFilterSchemaAssetsExpression('/^tl_/');

        // Create the from and to schema
        $fromSchema = $this->connection->getSchemaManager()->createSchema();
        $toSchema = $this->schemaProvider->createSchema();

        // Reset the schema filter
        $config->setFilterSchemaAssetsExpression($previousFilter);

        $diff = $fromSchema->getMigrateToSql($toSchema, $this->connection->getDatabasePlatform());

        foreach ($diff as $sql) {
            switch (true) {
                case 0 === strncmp($sql, 'CREATE TABLE ', 13):
                    $return['CREATE'][md5($sql)] = $sql;
                    break;

                case 0 === strncmp($sql, 'DROP TABLE ', 11):
                    $return['DROP'][md5($sql)] = $sql;
                    break;

                case 0 === strncmp($sql, 'CREATE INDEX ', 13):
                case 0 === strncmp($sql, 'CREATE UNIQUE INDEX ', 20):
                case 0 === strncmp($sql, 'CREATE FULLTEXT INDEX ', 22):
                    $return['ALTER_ADD'][md5($sql)] = $sql;
                    break;

                case 0 === strncmp($sql, 'DROP INDEX', 10):
                    $return['ALTER_CHANGE'][md5($sql)] = $sql;
                    break;

                case preg_match('/^(ALTER TABLE [^ ]+) /', $sql, $matches):
                    $prefix = $matches[1];
                    $sql = substr($sql, \strlen($prefix));
                    $parts = array_reverse(array_map('trim', explode(',', $sql)));

                    for ($i = 0, $count = \count($parts); $i < $count; ++$i) {
                        $part = $parts[$i];
                        $command = $prefix.' '.$part;

                        switch (true) {
                            case 0 === strncmp($part, 'DROP ', 5):
                                $return['ALTER_DROP'][md5($command)] = $command;
                                break;

                            case 0 === strncmp($part, 'ADD ', 4):
                                $return['ALTER_ADD'][md5($command)] = $command;
                                break;

                            case 0 === strncmp($part, 'CHANGE ', 7):
                            case 0 === strncmp($part, 'RENAME ', 7):
                                $return['ALTER_CHANGE'][md5($command)] = $command;
                                break;

                            default:
                                $parts[$i + 1] = $parts[$i + 1].','.$part;
                                break;
                        }
                    }
                    break;

                default:
                    throw new \RuntimeException(sprintf('Unsupported SQL schema diff: %s', $sql));
            }
        }

        $this->checkEngineAndCollation($return);

        $return = array_filter($return);

        // HOOK: allow third-party developers to modify the array (see #3281)
        if (isset($GLOBALS['TL_HOOKS']['sqlCompileCommands']) && \is_array($GLOBALS['TL_HOOKS']['sqlCompileCommands'])) {
            foreach ($GLOBALS['TL_HOOKS']['sqlCompileCommands'] as $callback) {
                $return = \System::importStatic($callback[0])->{$callback[1]}($return);
            }
        }

        $this->commands = $return;
    }

    /**
     * Checks engine and collation and adds the ALTER TABLE queries.
     *
     * @param array $sql
     */
    private function checkEngineAndCollation(array &$sql): void
    {
        $params = $this->connection->getParams();
        $charset = $params['defaultTableOptions']['charset'];
        $collate = $params['defaultTableOptions']['collate'];
        $engine = $params['defaultTableOptions']['engine'];
        $tables = $this->connection->getSchemaManager()->listTableNames();

        foreach ($tables as $table) {
            if (0 !== strncmp($table, 'tl_', 3)) {
                continue;
            }

            $tableOptions = $this->connection
                ->query("SHOW TABLE STATUS LIKE '".$table."'")
                ->fetch(\PDO::FETCH_OBJ)
            ;

            if ($tableOptions->Engine !== $engine) {
                if ('InnoDB' === $engine) {
                    $command = 'ALTER TABLE '.$table.' ENGINE = '.$engine.' ROW_FORMAT = DYNAMIC';
                } else {
                    $command = 'ALTER TABLE '.$table.' ENGINE = '.$engine;
                }

                $sql['ALTER_TABLE'][md5($command)] = $command;
            }

            if ($tableOptions->Collation !== $collate) {
                $command = 'ALTER TABLE '.$table.' CONVERT TO CHARACTER SET '.$charset.' COLLATE '.$collate;

                $sql['ALTER_TABLE'][md5($command)] = $command;
            }
        }
    }
}
