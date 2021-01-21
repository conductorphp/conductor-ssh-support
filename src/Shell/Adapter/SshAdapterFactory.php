<?php

namespace ConductorSshSupport\Shell\Adapter;

use ConductorSshSupport\Exception;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use League\Flysystem\Azure\AzureAdapter;
use phpseclib\Net\SSH2;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SshAdapterFactory implements FactoryInterface
{

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     *
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $this->validateOptions($options);

        $clientOptions = $options['client'];
        $client = new SSH2($clientOptions['host'], $clientOptions['port'] ?? 22, $clientOptions['timeout'] ?? 10);

        return new SshAdapter(
            $client,
            $clientOptions['username'],
            $clientOptions['key'] ?? null,
            $clientOptions['password'] ?? null
        );
    }

    /**
     *
     * @param array $options
     *
     * @throws Exception\InvalidArgumentException if options invalid
     */
    private function validateOptions(array $options): void
    {
        $requiredOptions = ['client'];
        $allowedOptions = ['client'];

        $missingRequiredOptions = array_diff($requiredOptions, array_keys($options));
        if ($missingRequiredOptions) {
            throw new Exception\InvalidArgumentException(
                sprintf(
                    'Missing %s constructor options: %s',
                    AzureAdapter::class,
                    implode(', ', $missingRequiredOptions)
                )
            );
        }

        $disallowedOptions = array_diff(array_keys($options), $allowedOptions);
        if ($disallowedOptions) {
            throw new Exception\InvalidArgumentException(
                sprintf(
                    'Invalid %s constructor options: %s',
                    AzureAdapter::class,
                    implode(', ', $disallowedOptions)
                )
            );
        }
    }
}
