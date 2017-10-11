<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\InstallationBundle\Database;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Parent class for version update classes.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
abstract class AbstractVersionUpdate implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $messages = [];

    /**
     * Constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Checks if there is a message.
     *
     * @return bool
     */
    public function hasMessage()
    {
        return !empty($this->messages);
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function getMessage()
    {
        return implode($this->messages);
    }

    /**
     * Checks whether the update should be run.
     *
     * @return bool
     */
    abstract public function shouldBeRun();

    /**
     * Runs the update.
     */
    abstract public function run();

    /**
     * Adds a message.
     *
     * @param string $message
     */
    protected function addMessage($message)
    {
        $this->messages[] = $message;
    }

    /**
     * Prepends a message.
     *
     * @param string $message
     */
    protected function prependMessage($message)
    {
        array_unshift($this->messages, $message);
    }
}
