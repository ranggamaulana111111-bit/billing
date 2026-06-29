<?php

namespace App\Services\Olt;

class SshTunnel
{
    private $process;

    private array $pipes = [];

    private int $localPort;

    private string $tmpDir;

    public function __construct(
        private string $jumpHost,
        private int $jumpPort,
        private string $jumpUser,
        string $jumpPassword,
        private string $targetHost,
        private int $targetPort,
        int $timeout = 15,
    ) {
        $this->localPort = $this->findFreePort();
        $this->tmpDir = sys_get_temp_dir();

        $cmd = $this->buildCommand($jumpPassword);
        $this->start($cmd, $timeout);
    }

    public function getLocalPort(): int
    {
        return $this->localPort;
    }

    public function close(): void
    {
        $this->stop();
    }

    // ── Private ──

    private function buildCommand(string $password): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $ssh = 'ssh';
            $keyFile = $this->tmpDir.'\olt_tunnel_key';
            $null = '2>NUL';

            // Use sshpass if available, otherwise try plink or plain ssh (key-based)
            $plink = $this->which('plink.exe') ?? $this->which('plink');

            if ($plink) {
                // PuTTY plink supports -pw for password
                return sprintf(
                    '"%s" -ssh -P %d -pw "%s" -L 127.0.0.1:%d:%s:%d -N %s@%s %s',
                    $plink, $this->jumpPort, $password,
                    $this->localPort, $this->targetHost, $this->targetPort,
                    $this->jumpUser, $this->jumpHost, $null
                );
            }

            // Fallback: try plain ssh with ssh keys
            return sprintf(
                '%s -o StrictHostKeyChecking=no -o UserKnownHostsFile="%s" -o ConnectTimeout=10 -p %d -L 127.0.0.1:%d:%s:%d -N %s@%s %s',
                $ssh, $this->tmpDir.'\olt_known_hosts', $this->jumpPort,
                $this->localPort, $this->targetHost, $this->targetPort,
                $this->jumpUser, $this->jumpHost, $null
            );
        }

        // Linux / macOS
        $sshCmd = 'ssh';
        $passCmd = '';

        if ($password) {
            $sshpass = $this->which('sshpass');
            if ($sshpass) {
                $passCmd = sprintf('sshpass -p "%s" ', str_replace('"', '\"', $password));
            }
        }

        return sprintf(
            '%s%s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -p %d -L 127.0.0.1:%d:%s:%d -N %s@%s 2>/dev/null & echo $!',
            $passCmd, $sshCmd, $this->jumpPort,
            $this->localPort, $this->targetHost, $this->targetPort,
            $this->jumpUser, $this->jumpHost
        );
    }

    private function start(string $cmd, int $timeout): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Start SSH tunnel in background using START /B
            $fullCmd = sprintf('START /B CMD /C "%s"', $cmd);
            $this->process = proc_open($fullCmd, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $this->pipes);
        } else {
            $this->process = proc_open($cmd, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $this->pipes);
        }

        if (! $this->process) {
            throw new \RuntimeException('Gagal menjalankan SSH tunnel. Pastikan OpenSSH Client terinstall.');
        }

        // Read PID on Linux
        if (PHP_OS_FAMILY !== 'Windows' && isset($this->pipes[1])) {
            $pid = trim(fgets($this->pipes[1]));
        }

        // Close stdin so process can run in background
        if (isset($this->pipes[0])) {
            fclose($this->pipes[0]);
            unset($this->pipes[0]);
        }

        // Wait for tunnel to be ready
        $start = microtime(true);
        $ready = false;
        while ((microtime(true) - $start) < $timeout) {
            $sock = @fsockopen('127.0.0.1', $this->localPort, $e, $s, 1);
            if ($sock) {
                fclose($sock);
                $ready = true;
                break;
            }
            usleep(200_000);
        }

        if (! $ready) {
            $this->stop();
            throw new \RuntimeException(
                'SSH tunnel tidak dapat dibuat. Pastikan:'.PHP_EOL.
                '1. OpenSSH Client sudah terinstall di server ini'.PHP_EOL.
                '2. Jump host ('.$this->jumpHost.') reachable via SSH port '.$this->jumpPort.PHP_EOL.
                '3. SSH key sudah disetup atau gunakan plink/sshpass untuk auth password'
            );
        }
    }

    private function stop(): void
    {
        if (isset($this->pipes[0])) {
            fclose($this->pipes[0]);
        }
        if (isset($this->pipes[1])) {
            fclose($this->pipes[1]);
        }
        if (isset($this->pipes[2])) {
            fclose($this->pipes[2]);
        }
        if ($this->process) {
            $status = proc_get_status($this->process);
            if ($status && $status['running']) {
                if (PHP_OS_FAMILY === 'Windows') {
                    exec('taskkill /F /T /PID '.$status['pid'].' 2>NUL');
                } else {
                    exec('kill '.$status['pid'].' 2>/dev/null');
                }
            }
            proc_close($this->process);
            $this->process = null;
        }
    }

    private function findFreePort(): int
    {
        $sock = @socket_create_listen(0);
        if ($sock) {
            socket_getsockname($sock, $addr, $port);
            socket_close($sock);

            return $port;
        }
        // fallback
        $sock = @fsockopen('127.0.0.1', 0, $e, $s, 1);
        if ($sock) {
            fclose($sock);

            return 2222; // arbitrary
        }

        return 2222;
    }

    private function which(string $cmd): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $where = exec('where '.$cmd.' 2>NUL', $out, $code);

            return $code === 0 ? $where : null;
        }
        $path = exec('which '.$cmd.' 2>/dev/null', $out, $code);

        return $code === 0 ? $path : null;
    }
}
