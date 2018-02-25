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
     * @param string $command
     *
     * @return bool
     */
    public function isCallable($command): bool
    {
        $this->authenticate();
        $this->sshClient->exec('which ' . escapeshellarg($command) . ' &>/dev/null');
        return (0 === $this->sshClient->getExitStatus());
    }

    /**
     * @param            $command
     * @param null       $currentWorkingDirectory Current working directory to pass to proc_open
     * @param array|null $environmentVariables    Environment variables to pass to proc_open
     * @param array|null $options                 Other options to pass to proc_open
     *
     * @throws Exception\RuntimeException if command exits with non-zero status
     * @return string Standard output from the command
     */
    public function runShellCommand(
        string $command,
        int $priority = shellAdapterInterface::PRIORITY_NORMAL,
        string $currentWorkingDirectory = null,
        array $environmentVariables = null,
        array $options = null
    ): string {
        $this->authenticate();
        $this->logger->debug("Running shell command: $command");

        if ($currentWorkingDirectory) {
            $command = 'cd ' . escapeshellarg($currentWorkingDirectory) . ' && ';
        }

        if (shellAdapterInterface::PRIORITY_LOW == $priority) {
            $command = 'ionice -c3 nice -n 19 bash -c ' . escapeshellarg($command);
        } elseif (shellAdapterInterface::PRIORITY_HIGH == $priority) {
            $command = 'nice -n -20 bash -c ' . escapeshellarg($command);
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
