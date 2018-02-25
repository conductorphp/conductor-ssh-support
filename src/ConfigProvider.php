<?php

namespace ConductorSshSupport;

class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     *
     * @return array
     */
    public function __invoke()
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
        ];
    }

    /**
     * Returns the container dependencies
     *
     * @return array
     */
    private function getDependencyConfig(): array
    {
        return require(__DIR__ . '/../config/dependencies.php');
    }

}
