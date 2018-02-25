<?php
/**
 * @author Kirk Madera <kmadera@robofirm.com>
 */

namespace ConductorSshSupport\Shell\Adapter;

use ConductorCore\Shell\Adapter\ShellAdapterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use phpseclib\Net\SSH2;

/**
 * Class ConductorSshSupportRefreshAssets
 *
 * @package ConductorSshSupport
 */
class SshAdapter implements ShellAdapterInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(SSH2 $sshConnection, LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @param string $command
     *
     * @return bool
     */
    public function isCallable($command): bool
    {
        exec('which ' . escapeshellarg($command) . ' &>/dev/null', $output, $return);
        return 0 === $return;
    }

    /**
     * @param            $command
     * @param null       $cwd          Current working directory to pass to proc_open
     * @param array|null $env          Environment variables to pass to proc_open
     * @param array|null $otherOptions Other options to pass to proc_open
     *
     * @throws Exception\RuntimeException if command exits with non-zero status
     * @return string Standard output from the command
     */
    public function runShellCommand(
        string $command,
        int $priority = shellAdapterInterface::PRIORITY_NORMAL,
        string $cwd = null,
        array $env = null,
        array $otherOptions = null
    ): string {
        $this->logger->debug("Running shell command: $command");
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

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];
        $process = proc_open($command, $descriptorSpec, $pipes, $cwd, $env, $otherOptions);
        if (!is_resource($process)) {
            throw new Exception\RuntimeException(sprintf('Failed to open process for command "%s".', $command));
        }

        while ($line = fgets($pipes[2])) {
            // Do not log empty output lines
            if (!trim($line)) {
                continue;
            }
            // stderr is really any output other than the command's primary output. We cannot assume this is
            // error output.
            $this->logger->debug($line);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        if ($stderr) {
            $this->logger->debug($stderr);
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (0 != proc_close($process)) {
            throw new Exception\RuntimeException("An error occurred while running shell command: \"$command\"");
        }

        return $stdout;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
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