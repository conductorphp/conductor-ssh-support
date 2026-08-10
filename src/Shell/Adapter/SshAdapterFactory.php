<?php

namespace ConductorSshSupport\Shell\Adapter;

use ConductorSshSupport\Exception;
use phpseclib3\Net\SSH2;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SshAdapterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SshAdapter
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
                    SshAdapter::class,
                    implode(', ', $missingRequiredOptions)
                )
            );
        }

        $disallowedOptions = array_diff(array_keys($options), $allowedOptions);
        if ($disallowedOptions) {
            throw new Exception\InvalidArgumentException(
                sprintf(
                    'Invalid %s constructor options: %s',
                    SshAdapter::class,
                    implode(', ', $disallowedOptions)
                )
            );
        }
    }
}
