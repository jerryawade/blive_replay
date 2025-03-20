<!DOCTYPE html>
<html>
<head>
    <title>Bandwidth Monitoring</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400&display=swap">
    <link rel="stylesheet" href="assets/css/FontAwesome.Pro.6.4.0/Web/css/all.css"/>
    <link rel="stylesheet" href="assets/css/traffic.css"/>
</head>
<body>
<br/>
<div id="serverName">Server: <?php echo gethostname(); ?></div>
<br/>
<div id="processes">
    <div class="chart-container">
        <span><i class="fa-light fa-microchip"></i> CPU Utilization</span>
        <div class="cpu-chart"></div>
    </div>
    <div class="chart-container">
        <span><i class="fa-light fa-memory"></i> Memory Utilization</span>
        <div class="memory-chart"></div>
    </div>
    <div class="chart-container">
        <span><i class="fa-light fa-gear-complex"></i> Process Utilization</span>
        <div class="process-chart"></div>
    </div>
</div>
<br/>
<div id="bandwidthChart"></div>
<!-- JavaScript libraries -->
<script src="assets/js/jquery-3.6.0.min.js"></script>
<script src="assets/js/plotly-latest.min.js"></script>
<script>
    $(document).ready(function () {

        // Data for storing bandwidth information
        var bandwidthData = {
            timestamp: [], // Array to store timestamps
            rx: [], // Array to store received data points
            tx: []  // Array to store transmitted data points
        };

        // Layout settings for the chart
        var layout = {
            title: 'Network Bandwidth',
            xaxis: {
                title: 'Time',
                type: 'date',
                tickformat: '%I:%M:%S %p' // Display time in 12-hour format with AM/PM
            },
            yaxis: {
                title: 'Mbps',
                rangemode: 'tozero' // Set the y-axis range from 0 to the maximum value
            },
            plot_bgcolor: 'transparent', // Set the plot background color to transparent
            paper_bgcolor: 'transparent' // Set the paper background color to transparent
        };

        var config = {
            responsive: true // Enable responsiveness for the chart
        };

        // Function to update the bandwidth chart with data from the server
        function updateChart() {
            $.ajax({
                url: 'trafficdata.php', // Change this to the path of your PHP script to fetch data
                dataType: 'json',
                success: function (data) {
                    var rx = parseFloat(data.rx.toFixed(2)); // Convert received data to a floating-point number with two decimal places
                    var tx = parseFloat(data.tx.toFixed(2)); // Convert transmitted data to a floating-point number with two decimal places
                    var interfaceName = data.interface; // Get the interface name from the server response

                    // Store the current timestamp and data points in the bandwidthData arrays
                    bandwidthData.timestamp.push(new Date());
                    bandwidthData.rx.push(rx);
                    bandwidthData.tx.push(tx);

                    // Ensure the dataset has a maximum of, e.g., 60 data points to keep the chart clean
                    if (bandwidthData.timestamp.length > 60) {
                        bandwidthData.timestamp.shift(); // Remove the oldest timestamp
                        bandwidthData.rx.shift(); // Remove the oldest received data point
                        bandwidthData.tx.shift(); // Remove the oldest transmitted data point
                    }

                    // Create traces for received and transmitted data
                    var traceRx = {
                        x: bandwidthData.timestamp,
                        y: bandwidthData.rx,
                        mode: 'lines+markers',
                        name: 'Received',
                        line: {color: 'blue'} // Set line color for received data to blue
                    };

                    var traceTx = {
                        x: bandwidthData.timestamp,
                        y: bandwidthData.tx,
                        mode: 'lines+markers',
                        name: 'Transmitted',
                        line: {color: 'red'} // Set line color for transmitted data to red
                    };

                    // Combine traces into data array
                    var data = [traceRx, traceTx];

                    // Update the title with the interface name
                    var updatedLayout = Object.assign({}, layout); // Create a copy of the layout to avoid modifying the original object
                    updatedLayout.title.text = 'Network Bandwidth - Interface: ' + interfaceName; // Update the chart title with the interface name

                    // Create the chart using Plotly
                    Plotly.newPlot('bandwidthChart', data, layout, config);
                },
                complete: function () {
                    setTimeout(updateChart, 1000); // Update the chart every 1 second
                }
            });
        }

        // Start updating the chart
        updateChart();

        // Bandwidth monitor button click event
        $(document).on('click', '#logIn', function () {
            window.location.href = 'index.php'; // Redirect to 'index.php' when the button is clicked
        });

        // Function to update the processes chart with data from the server
        function updateProcesses() {
            $.ajax({
                url: 'processes.php',
                dataType: 'json',
                success: function (data) {
                    updateCPU('.cpu-chart', data.cpu, 'CPU', data.cores);
                    updateMemoryProcess('.memory-chart', data.memory.used, data.memory.total, 'Memory');
                    updateProcess('.process-chart', data.process, 'Process');
                },
                error: function (error) {
                    console.error('Error fetching data:', error);
                }
            });
        }

        // Function to update the memory chart with usage data
        function updateMemoryProcess(selector, usedMemory, totalMemory, label) {
            $(selector).empty();
            var usedGB = usedMemory / (1024 * 1024); // Convert usedMemory from kB to GB
            var totalGB = totalMemory / (1024 * 1024); // Convert totalMemory from kB to GB
            var usedPercentage = (usedMemory / totalMemory) * 100;

            var chartBar = $('<div>').addClass('chart-bar').css('width', usedPercentage + '%');
            var chartLabel = $('<span>').text(usedPercentage.toFixed(2) + '% (' + usedGB.toFixed(2) + ' GB / ' + totalGB.toFixed(2) + ' GB)');
            $(selector).append(chartBar, chartLabel);
        }

        // Function to update the CPU chart with usage data
        function updateCPU(selector, value, label, cores) {
            $(selector).empty();
            var chartBar = $('<div>').addClass('chart-bar').css('width', value + '%');
            var chartLabel = $('<span>').text(value + '% (' + cores + ' CPU Cores)');
            $(selector).append(chartBar, chartLabel);
        }

        // Function to update the process chart with usage data
        function updateProcess(selector, value, label) {
            $(selector).empty();
            var chartBar = $('<div>').addClass('chart-bar').css('width', value + '%');
            var chartLabel = $('<span>').text(value + '%');
            $(selector).append(chartBar, chartLabel);
        }

        setInterval(updateProcesses, 3000); // Update charts every 3 seconds (adjust as needed)
    });

</script>
<br/>
<!-- EPG Login button -->
<div class="pt-1 mb-4">
    <button id="logIn" class="glass-button"><i class="fa-solid fa-right-from-bracket"></i> EPG Login</button>
</div>
</body>
</html>
