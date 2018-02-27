<?php
/**
 * @author Kirk Madera <kmadera@robofirm.com>
 */

namespace ConductorSshSupport\Shell\Adapter;

use ConductorCore\Exception;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ConductorSshSupportRefreshAssets
 *
 * @package ConductorSshSupport
 */
class SshAdapter implements ShellAdapterInterface, LoggerAwareInterface
{
    /**
     * @var SSH2
     */
    private $sshClient;
    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $key;
    /**
     * @var string
     */
    private $password;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        SSH2 $sshClient,
        string $username,
        string $key = null,
        string $password = null,
        LoggerInterface $logger = null
    ) {
        $this->sshClient = $sshClient;
        $this->username = $username;
        $this->key = $key;
        $this->password = $password;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @throws Exception\RuntimeException if authentication fails
     */
    private function authenticate()
    {
        if ($this->key) {
            $key = new RSA();
            if ($this->password) {
                $key->setPassword($this->password);
            }
            $key->loadKey($this->key);
        } else {
            $key = $this->password;
        }

        if (!$this->sshClient->login($this->username, $key)) {
            throw new Exception\RuntimeException('Could not authenticate SSH2 adapter.');
        }
    }

    /**
     * @inheritdoc
     */
    public function isCallable($command): bool
    {
        $this->authenticate();
        $this->sshClient->exec('which ' . escapeshellarg($command));
        return (0 === $this->sshClient->getExitStatus());
    }

    /**
     * @inheritdoc
     */
    public function runShellCommand(
        string $command,
        string $currentWorkingDirectory = null,
        array $environmentVariables = null,
        int $priority = ShellAdapterInterface::PRIORITY_NORMAL,
        array $options = null
    ): string {
        $this->authenticate();
        $this->logger->debug("Running shell command: $command");

        if ($environmentVariables) {
            foreach ($environmentVariables as $key => $value) {
                $command = escapeshellcmd($key) . '=' . escapeshellarg($value) . ' && ' . $command;
            }
        }

        if ($currentWorkingDirectory) {
            $command = 'cd ' . escapeshellarg($currentWorkingDirectory) . ' && ' . $command;
        }

        if (ShellAdapterInterface::PRIORITY_LOW == $priority) {
            $command = 'ionice -c3 nice -n 19 bash -c ' . escapeshellarg($command);
        } elseif (ShellAdapterInterface::PRIORITY_HIGH == $priority) {
            if (0 == posix_getuid()) {
                $command = 'ionice -c 1 -n 0 ' . $command;
            } else {
                $command = 'ionice -c 2 -n 0 ' . $command;
            }
        }

        $output = $this->sshClient->exec($command);
        $stdErr = $this->sshClient->getStdError();
        $exitStatus = $this->sshClient->getExitStatus();

        $this->logger->debug($stdErr);
        if (0 !== $exitStatus) {
            throw new Exception\RuntimeException("An error occurred while running shell command: \"$command\"");
        }

        return $output;
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
