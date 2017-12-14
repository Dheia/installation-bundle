<?php

/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\InstallationBundle\Config;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Dumps the parameters into the parameters.yml file.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 * @author Andreas Schempp <https://github.com/aschempp>
 */
class ParameterDumper
{
    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var array
     */
    private $parameters = ['parameters' => []];

    /**
     * Constructor.
     *
     * @param string          $rootDir
     * @param Filesystem|null $filesystem
     */
    public function __construct($rootDir, Filesystem $filesystem = null)
    {
        $this->rootDir = $rootDir;
        $this->filesystem = $filesystem ?: new Filesystem();

        foreach (['config/parameters.yml.dist', 'config/parameters.yml'] as $file) {
            if (file_exists($rootDir.'/'.$file)) {
                $this->parameters = array_merge(
                    $this->parameters,
                    Yaml::parse(file_get_contents($rootDir.'/'.$file))
                );
            }
        }
    }

    /**
     * Sets a parameter.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setParameter($name, $value)
    {
        $this->parameters['parameters'][$name] = $value;
    }

    /**
     * Sets multiple parameters.
     *
     * @param array $params
     */
    public function setParameters(array $params)
    {
        foreach ($params['parameters'] as $name => $value) {
            $this->setParameter($name, $value);
        }
    }

    /**
     * Dumps the parameters into the parameters.yml file.
     */
    public function dump()
    {
        if (
            empty($this->parameters['parameters']['secret']) ||
            'ThisTokenIsNotSoSecretChangeIt' === $this->parameters['parameters']['secret']
        ) {
            $this->parameters['parameters']['secret'] = bin2hex(random_bytes(32));
        }

        if (isset($this->parameters['parameters']['database_port'])) {
            $this->parameters['parameters']['database_port'] = (int) $this->parameters['parameters']['database_port'];
        }

        $this->filesystem->dumpFile(
            $this->rootDir.'/config/parameters.yml',
            "# This file has been auto-generated during installation\n".Yaml::dump($this->getEscapedValues())
        );
    }

    /**
     * Escapes % and @.
     *
     * @return array<string,array>
     *
     * @see http://symfony.com/doc/current/service_container/parameters.html#parameters-in-configuration-files
     */
    private function getEscapedValues()
    {
        $parameters = [];

        foreach ($this->parameters['parameters'] as $key => $value) {
            if (\is_string($value)) {
                if (0 === strpos($value, '@')) {
                    $value = '@'.$value;
                }

                if (false !== strpos($value, '%')) {
                    $value = str_replace('%', '%%', $value);
                }
            }

            $parameters[$key] = $value;
        }

        return ['parameters' => $parameters];
    }
}
