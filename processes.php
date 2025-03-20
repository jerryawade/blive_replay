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

// GPU usage with support for both NVIDIA and AMD
function getGpuUsage() {
    try {
        // Check available GPU tools
        $nvidiaSmiExists = trim(@shell_exec('which nvidia-smi 2>/dev/null'));
        $rocmSmiExists = trim(@shell_exec('which rocm-smi 2>/dev/null'));
        $amdgpuProTopExists = trim(@shell_exec('which amdgpu-pro-top 2>/dev/null'));
        
        // First try NVIDIA
        if (!empty($nvidiaSmiExists)) {
            return getNvidiaGpuStats();
        }
        // Then try AMD with ROCm
        else if (!empty($rocmSmiExists)) {
            return getAmdRocmGpuStats();
        }
        // Then try AMD with amdgpu-pro-top
        else if (!empty($amdgpuProTopExists)) {
            return getAmdLegacyGpuStats();
        }
        
        return ['error' => 'No supported GPU tools found (nvidia-smi, rocm-smi, or amdgpu-pro-top)'];
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

// Get NVIDIA GPU statistics using nvidia-smi
function getNvidiaGpuStats() {
    try {
        // Get GPU utilization and memory stats
        $gpuUtilization = @shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits');
        $gpuMemoryUsed = @shell_exec('nvidia-smi --query-gpu=memory.used --format=csv,noheader,nounits');
        $gpuMemoryTotal = @shell_exec('nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits');
        $gpuTemperature = @shell_exec('nvidia-smi --query-gpu=temperature.gpu --format=csv,noheader,nounits');
        $gpuName = @shell_exec('nvidia-smi --query-gpu=name --format=csv,noheader,nounits');
        
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
            'type' => 'nvidia',
            'name' => trim($gpuName),
            'utilization' => (int)trim($gpuUtilization),
            'memory' => [
                'used' => (int)trim($gpuMemoryUsed),
                'total' => (int)trim($gpuMemoryTotal)
            ],
            'temperature' => (int)trim($gpuTemperature),
            'processes' => $processes
        ];
    } catch (Throwable $e) {
        return ['error' => 'Error getting NVIDIA GPU stats: ' . $e->getMessage()];
    }
}

// Get AMD GPU statistics using rocm-smi (for newer AMD GPUs with ROCm)
function getAmdRocmGpuStats() {
    try {
        // Get GPU utilization using rocm-smi
        $gpuUtilization = @shell_exec('rocm-smi --showuse --json');
        $utilData = json_decode($gpuUtilization, true);
        
        // Get GPU memory using rocm-smi
        $gpuMemory = @shell_exec('rocm-smi --showmemuse --json');
        $memData = json_decode($gpuMemory, true);
        
        // Get GPU temperature using rocm-smi
        $gpuTemp = @shell_exec('rocm-smi --showtemp --json');
        $tempData = json_decode($gpuTemp, true);
        
        // Get GPU name
        $gpuInfo = @shell_exec('rocm-smi --showproductname --json');
        $infoData = json_decode($gpuInfo, true);
        
        // Get processes - this is more complex with rocm-smi
        $processes = [];
        $processOutput = @shell_exec('rocm-smi --showpids');
        
        if ($processOutput) {
            // Extract PIDs from the output
            if (preg_match_all('/(\d+)/', $processOutput, $matches)) {
                $pids = array_unique($matches[1]);
                foreach ($pids as $pid) {
                    // Get process details
                    $cmdline = @file_get_contents("/proc/$pid/cmdline");
                    $processName = $cmdline ? basename(explode("\0", $cmdline)[0]) : "Unknown";
                    
                    $processes[] = [
                        'pid' => $pid,
                        'name' => $processName,
                        'memory' => 'N/A' // ROCm doesn't easily provide per-process memory usage
                    ];
                }
            }
        }
        
        // Parse the data - note that this handles only the first GPU for simplicity
        $gpuData = [];
        
        if (is_array($utilData) && count($utilData) > 0) {
            $gpuIndex = array_keys($utilData)[0];
            $gpuName = $infoData[$gpuIndex]['Card serie'] ?? 'AMD GPU';
            
            // Get GPU utilization
            $gpuUtil = $utilData[$gpuIndex]['GPU use (%)'] ?? 0;
            $gpuUtil = str_replace('%', '', $gpuUtil);
            
            // Get memory usage
            $memUsed = 0;
            $memTotal = 0;
            if (isset($memData[$gpuIndex]['VRAM Total Memory (MB)'])) {
                $memTotal = str_replace(' MB', '', $memData[$gpuIndex]['VRAM Total Memory (MB)']);
                $memUsed = str_replace(' MB', '', $memData[$gpuIndex]['VRAM Total Used Memory (MB)'] ?? '0');
            }
            
            // Get temperature
            $temperature = 0;
            if (isset($tempData[$gpuIndex]['Temperature (Sensor edge) (C)'])) {
                $temperature = str_replace('C', '', $tempData[$gpuIndex]['Temperature (Sensor edge) (C)']);
            }
            
            return [
                'type' => 'amd',
                'name' => $gpuName,
                'utilization' => (int)$gpuUtil,
                'memory' => [
                    'used' => (int)$memUsed,
                    'total' => (int)$memTotal
                ],
                'temperature' => (int)$temperature,
                'processes' => $processes
            ];
        }
        
        return ['error' => 'Unable to parse ROCm SMI output'];
    } catch (Throwable $e) {
        return ['error' => 'Error getting AMD ROCm GPU stats: ' . $e->getMessage()];
    }
}

// Get AMD GPU statistics using amdgpu-pro-top (for older AMD GPUs)
function getAmdLegacyGpuStats() {
    try {
        // Run amdgpu-pro-top in batch mode (one-time output)
        $gpuStats = @shell_exec('amdgpu-pro-top -b');
        
        // Default values
        $utilization = 0;
        $memUsed = 0;
        $memTotal = 0;
        $temperature = 0;
        $gpuName = 'AMD GPU';
        
        // Extract utilization
        if (preg_match('/GPU Load\s+:\s+(\d+)%/', $gpuStats, $matches)) {
            $utilization = (int)$matches[1];
        }
        
        // Extract memory
        if (preg_match('/VRAM Usage\s+:\s+(\d+)M\s+\/\s+(\d+)M/', $gpuStats, $matches)) {
            $memUsed = (int)$matches[1];
            $memTotal = (int)$matches[2];
        }
        
        // Extract temperature - amdgpu-pro-top might not show this directly
        $tempOutput = @shell_exec('sensors | grep -A 5 amdgpu');
        if (preg_match('/temp1:\s+\+(\d+\.\d+)/', $tempOutput, $matches)) {
            $temperature = (int)$matches[1];
        }
        
        // Get GPU name
        $lspciOutput = @shell_exec('lspci | grep -i vga | grep -i amd');
        if (preg_match('/VGA compatible controller: (.+)/', $lspciOutput, $matches)) {
            $gpuName = trim($matches[1]);
        }
        
        // Processes - this is challenging with amdgpu-pro-top
        // We'll try to extract what we can
        $processes = [];
        preg_match_all('/(\d+)\s+\|\s+(\S+)/', $gpuStats, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (count($match) >= 3 && is_numeric($match[1])) {
                $processes[] = [
                    'pid' => $match[1],
                    'name' => $match[2],
                    'memory' => 'N/A'
                ];
            }
        }
        
        return [
            'type' => 'amd-legacy',
            'name' => $gpuName,
            'utilization' => $utilization,
            'memory' => [
                'used' => $memUsed,
                'total' => $memTotal
            ],
            'temperature' => $temperature,
            'processes' => $processes
        ];
    } catch (Throwable $e) {
        return ['error' => 'Error getting AMD Legacy GPU stats: ' . $e->getMessage()];
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
