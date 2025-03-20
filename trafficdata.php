<?php
// Function to get network bandwidth and hostname
function getNetworkBandwidth() {
    $interface = 'eno3'; // Replace with your network interface name (e.g., eth0, wlan0)

    // Start or resume the session
    session_start();

    // Get the previous data from the session if available
    $prevData = isset($_SESSION['network_bandwidth_data']) ? $_SESSION['network_bandwidth_data'] : null;

    $now = time(); // Current timestamp

    if ($prevData) {
        list($prevTime, $prevRx, $prevTx) = explode("\t", $prevData);

        // Calculate the time elapsed since the last reading (in seconds)
        $timeElapsed = max(1, $now - $prevTime); // Avoid division by zero

        // Calculate the receive and transmit speed in Mbps (Megabits per second)
        $rxSpeed = ($prevRx > 0) ? (float)(file_get_contents("/sys/class/net/$interface/statistics/rx_bytes") - $prevRx) * 8 / $timeElapsed / 1024 / 1024 : 0;
        $txSpeed = ($prevTx > 0) ? (float)(file_get_contents("/sys/class/net/$interface/statistics/tx_bytes") - $prevTx) * 8 / $timeElapsed / 1024 / 1024 : 0;
    } else {
        // If the data doesn't exist in the session, set the speeds to zero
        $rxSpeed = 0;
        $txSpeed = 0;
    }

    // Save the current data to the session for the next reading
    $_SESSION['network_bandwidth_data'] = "$now\t" . file_get_contents("/sys/class/net/$interface/statistics/rx_bytes") . "\t" . file_get_contents("/sys/class/net/$interface/statistics/tx_bytes");

    // Create an array to store the receive and transmit speed in Mbps along with the hostname
    $data = array(
        'rx' => $rxSpeed,
        'tx' => $txSpeed,
        'interface' => $interface
    );

    return $data; // Return the speed data and hostname
}

// Call the function and print the result as a JSON object
echo json_encode(getNetworkBandwidth());
