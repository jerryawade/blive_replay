<?php
/**
 * Processes.php - Get current system resource usage
 * Used by the traffic.php system monitor
 */

// Start session and check authentication
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

// Function to get available network interfaces
function getNetworkInterfaces() {
    $interfaces = glob('/sys/class/net/*');
    $validInterfaces = [];

    foreach ($interfaces as $iface) {
        $ifaceName = basename($iface);
        // Skip loopback and invalid interfaces
        if ($ifaceName != 'lo' && file_exists("/sys/class/net/$ifaceName/statistics/rx_bytes")) {
            $validInterfaces[] = $ifaceName;
        }
    }

    return $validInterfaces;
}

// Function to get network bandwidth
function getNetworkBandwidth($interface = null) {
    // If no interface provided, auto-detect or use default
    if (!$interface) {
        $interfaces = getNetworkInterfaces();
        $interface = $interfaces[0] ?? 'eth0'; // Fallback to eth0 if no interfaces found
    }

    // Get the previous data from the session if available
    $prevData = isset($_SESSION['network_bandwidth_data']) ? $_SESSION['network_bandwidth_data'] : null;
    $now = time();

    if ($prevData) {
        list($prevTime, $prevRx, $prevTx) = explode("\t", $prevData);

        // Calculate time elapsed since last reading (in seconds)
        $timeElapsed = max(1, $now - $prevTime);

        // Get current RX/TX values
        $rxCurrent = file_exists("/sys/class/net/$interface/statistics/rx_bytes") ?
            file_get_contents("/sys/class/net/$interface/statistics/rx_bytes") : 0;
        $txCurrent = file_exists("/sys/class/net/$interface/statistics/tx_bytes") ?
            file_get_contents("/sys/class/net/$interface/statistics/tx_bytes") : 0;

        // Calculate speeds in Mbps
        $rxSpeed = ($prevRx > 0) ? (float)($rxCurrent - $prevRx) * 8 / $timeElapsed / 1024 / 1024 : 0;
        $txSpeed = ($prevTx > 0) ? (float)($txCurrent - $prevTx) * 8 / $timeElapsed / 1024 / 1024 : 0;

        // Save current data for next reading
        $_SESSION['network_bandwidth_data'] = "$now\t$rxCurrent\t$txCurrent";
    } else {
        // If no previous data, set speeds to zero and save current data
        $rxSpeed = 0;
        $txSpeed = 0;

        $rxCurrent = file_exists("/sys/class/net/$interface/statistics/rx_bytes") ?
            file_get_contents("/sys/class/net/$interface/statistics/rx_bytes") : 0;
        $txCurrent = file_exists("/sys/class/net/$interface/statistics/tx_bytes") ?
            file_get_contents("/sys/class/net/$interface/statistics/tx_bytes") : 0;

        $_SESSION['network_bandwidth_data'] = "$now\t$rxCurrent\t$txCurrent";
    }

    return [
        'rx' => round($rxSpeed, 2),
        'tx' => round($txSpeed, 2),
        'interface' => $interface
    ];
}

// Determine which data to return based on the request
if (isset($_GET['data']) && $_GET['data'] === 'network') {
    // Get interface from request or use default
    $interface = $_GET['interface'] ?? null;

    // Return only network bandwidth data
    echo json_encode(getNetworkBandwidth($interface));
    exit;
}

// If request is to get available interfaces
if (isset($_GET['interfaces'])) {
    echo json_encode(getNetworkInterfaces());
    exit;
}

// Collect all data (using default interface)
$cpuInfo = getCpuUsage();
$memoryInfo = getMemoryUsage();
$processUtilization = getProcessUtilization();
$networkInfo = getNetworkBandwidth();

// Return data as JSON
echo json_encode([
    'cpu' => $cpuInfo['usage'] ?? 0,
    'cores' => $cpuInfo['cores'] ?? 1,
    'memory' => [
        'total' => $memoryInfo['total'] ?? 0,
        'used' => $memoryInfo['used'] ?? 0,
        'free' => $memoryInfo['free'] ?? 0,
        'percent' => $memoryInfo['usage_percent'] ?? 0
    ],
    'process' => $processUtilization,
    'network' => $networkInfo,
    'timestamp' => time()
]);