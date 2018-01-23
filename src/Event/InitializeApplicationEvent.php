<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2018 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\InstallationBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class InitializeApplicationEvent extends Event
{
    /**
     * @var string
     */
    private $output;

    /**
     * Returns the console output.
     *
     * @return string
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Sets the console output and stops event propagation.
     *
     * @param string $output
     */
    public function setOutput(string $output): void
    {
        $this->output = $output;

        $this->stopPropagation();
    }

    /**
     * Checks if there is a console output.
     *
     * @return bool
     */
    public function hasOutput(): bool
    {
        return null !== $this->output;
    }
}
