<?php
session_start();
$publicRequest = isset($_GET['data']) && $_GET['data'] === 'network';

if (!$publicRequest && (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

// Get valid interfaces
function getNetworkInterfaces() {
    $interfaces = glob('/sys/class/net/*');
    $valid = [];

    foreach ($interfaces as $iface) {
        $name = basename($iface);
        if ($name !== 'lo' && file_exists("/sys/class/net/$name/statistics/rx_bytes")) {
            $valid[] = $name;
        }
    }

    sort($valid);
    return $valid ?: ['eno3'];
}

// Bandwidth logic
function getNetworkBandwidth($interface = null) {
    if (!is_array($_SESSION['network_bandwidth_data'] ?? null)) {
        $_SESSION['network_bandwidth_data'] = [];
    }

    try {
        if (!$interface) {
            $interfaces = getNetworkInterfaces();
            $interface = $interfaces[0] ?? 'eno3';
        }

        $now = microtime(true);
        $rxPath = "/sys/class/net/$interface/statistics/rx_bytes";
        $txPath = "/sys/class/net/$interface/statistics/tx_bytes";
        $rxCurrent = file_exists($rxPath) ? (float)@file_get_contents($rxPath) : 0;
        $txCurrent = file_exists($txPath) ? (float)@file_get_contents($txPath) : 0;

        $prevData = $_SESSION['network_bandwidth_data'][$interface] ?? null;
        if ($prevData) {
            $parts = explode("\t", $prevData);
            if (count($parts) === 3) {
                list($prevTime, $prevRx, $prevTx) = $parts;
                $timeElapsed = max(0.001, $now - (float)$prevTime);
                $rxDelta = max(0, $rxCurrent - (float)$prevRx);
                $txDelta = max(0, $txCurrent - (float)$prevTx);
                $rxSpeed = $rxDelta * 8 / $timeElapsed / 1_000_000;
                $txSpeed = $txDelta * 8 / $timeElapsed / 1_000_000;
            } else {
                $rxSpeed = $txSpeed = 0;
            }
        } else {
            $rxSpeed = $txSpeed = 0;
        }

        $_SESSION['network_bandwidth_data'][$interface] = "$now\t$rxCurrent\t$txCurrent";

        return [
            'rx' => round($rxSpeed, 2),
            'tx' => round($txSpeed, 2),
            'interface' => $interface
        ];
    } catch (Throwable $e) {
        error_log("Bandwidth error for $interface: " . $e->getMessage());
        return ['rx' => 0, 'tx' => 0, 'interface' => $interface, 'error' => $e->getMessage()];
    }
}

// CPU usage
function getCpuUsage() {
    try {
        $load = sys_getloadavg();
        $cores = (int)@exec('nproc') ?: 1;
        return ['usage' => $load[0] ?? 0, 'cores' => $cores];
    } catch (Throwable $e) {
        return ['usage' => 0, 'cores' => 1];
    }
}

// Memory usage
function getMemoryUsage() {
    try {
        $free = @shell_exec('free -k');
        preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $free, $matches);
        return [
            'total' => (int)($matches[1] ?? 0),
            'used' => (int)($matches[2] ?? 0),
            'free' => (int)($matches[3] ?? 0)
        ];
    } catch (Throwable $e) {
        return ['total' => 0, 'used' => 0, 'free' => 0];
    }
}

// GPU usage with nvidia-smi
function getGpuUsage() {
    try {
        // Check if nvidia-smi is available
        $nvidiaSmiExists = @shell_exec('which nvidia-smi');
        if (empty($nvidiaSmiExists)) {
            return ['error' => 'nvidia-smi not found'];
        }

        // Get GPU utilization and memory stats
        $gpuUtilization = @shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits');
        $gpuMemoryUsed = @shell_exec('nvidia-smi --query-gpu=memory.used --format=csv,noheader,nounits');
        $gpuMemoryTotal = @shell_exec('nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits');
        $gpuTemperature = @shell_exec('nvidia-smi --query-gpu=temperature.gpu --format=csv,noheader,nounits');
        
        // Get running processes
        $gpuProcessesOutput = @shell_exec('nvidia-smi --query-compute-apps=pid,used_memory --format=csv,noheader,nounits');
        $processes = [];
        
        if ($gpuProcessesOutput) {
            $processLines = explode("\n", trim($gpuProcessesOutput));
            foreach ($processLines as $line) {
                if (empty(trim($line))) continue;
                
                $parts = explode(',', $line);
                if (count($parts) >= 2) {
                    $pid = trim($parts[0]);
                    $usedMemory = (int)trim($parts[1]);
                    
                    // Get process name
                    $cmdline = @file_get_contents("/proc/$pid/cmdline");
                    $processName = $cmdline ? basename(explode("\0", $cmdline)[0]) : "Unknown";
                    
                    $processes[] = [
                        'pid' => $pid,
                        'name' => $processName,
                        'memory' => $usedMemory
                    ];
                }
            }
        }
        
        return [
            'utilization' => (int)trim($gpuUtilization),
            'memory' => [
                'used' => (int)trim($gpuMemoryUsed),
                'total' => (int)trim($gpuMemoryTotal)
            ],
            'temperature' => (int)trim($gpuTemperature),
            'processes' => $processes
        ];
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

// Interface list
if (isset($_GET['interfaces'])) {
    echo json_encode(getNetworkInterfaces());
    exit;
}

// Bandwidth data
if (isset($_GET['data']) && $_GET['data'] === 'network') {
    $interface = $_GET['interface'] ?? 'eno3';
    echo json_encode(getNetworkBandwidth($interface));
    exit;
}

// GPU data
if (isset($_GET['data']) && $_GET['data'] === 'gpu') {
    echo json_encode(getGpuUsage());
    exit;
}

// Default: CPU + Memory + GPU
echo json_encode([
    'cpu' => getCpuUsage(),
    'memory' => getMemoryUsage(),
    'gpu' => getGpuUsage()
]);
