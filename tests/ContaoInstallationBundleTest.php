<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2018 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\InstallationBundle\Tests;

use Contao\InstallationBundle\ContaoInstallationBundle;
use PHPUnit\Framework\TestCase;

class ContaoInstallationBundleTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $bundle = new ContaoInstallationBundle();

        $this->assertInstanceOf('Contao\InstallationBundle\ContaoInstallationBundle', $bundle);
    }
}
