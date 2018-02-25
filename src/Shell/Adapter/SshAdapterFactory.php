<?php

namespace ConductorSshSupport\Shell\Adapter;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use League\Flysystem\Azure\AzureAdapter;
use MicrosoftAzure\Storage\Blob\Internal\IBlob;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use ConductorAzureBlobFilesystemSupport\Exception;

class AzureAdapterFactory implements FactoryInterface
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

        $client = $options['client'];
        $azureContainer = $options['container'];
        $prefix = isset($options['prefix']) ? $options['prefix'] : null;

        $endpoint = sprintf(
            'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
            $client['account_name'],
            $client['account_key']
        );
        /** @var IBlob $blobRestProxy */
        $blobRestProxy = ServicesBuilder::getInstance()->createBlobService($endpoint);
        return new AzureAdapter($blobRestProxy, $azureContainer, $prefix);
    }

    /**
     * @param array $options
     * @throws Exception\InvalidArgumentException if options invalid
     */
    private function validateOptions(array $options): void
    {
        $requiredOptions = ['ssh'];
        $allowedOptions = ['ssh'];

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
