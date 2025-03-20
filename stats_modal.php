<!-- System Stats Modal -->
<div class="modal fade" id="statsModal" tabindex="-1" aria-labelledby="statsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statsModalLabel">
                    <i class="bi bi-graph-up me-2"></i>
                    System Statistics
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="traffic-monitor-container">
                    <!-- Network Interface Selector -->
                    <div class="row mb-3">
                        <div class="col-md-6 offset-md-3">
                            <select id="networkInterfaceSelector" class="form-select">
                                <option value="">Select Network Interface</option>
                            </select>
                        </div>
                    </div>

                    <div id="processes" class="row mb-4">
                        <div class="col-md-4">
                            <div class="chart-container">
                                <span><i class="bi bi-cpu"></i> CPU Utilization</span>
                                <div class="cpu-chart"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="chart-container">
                                <span><i class="bi bi-memory"></i> Memory Utilization</span>
                                <div class="memory-chart"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="chart-container">
                                <span><i class="bi bi-gear-wide-connected"></i> Process Utilization</span>
                                <div class="process-chart"></div>
                            </div>
                        </div>
                    </div>

                    <div id="bandwidthChart"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Existing styles remain the same */
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load required libraries if they don't exist
        function loadScript(url, callback) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = url;
            script.onload = callback;
            document.head.appendChild(script);
        }

        // Check if Plotly is loaded
        if (typeof Plotly === 'undefined') {
            loadScript('https://cdn.jsdelivr.net/npm/plotly.js@2.14.0/dist/plotly.min.js', initTrafficMonitor);
        } else {
            initTrafficMonitor();
        }

        function initTrafficMonitor() {
            // Data for storing bandwidth information
            var bandwidthData = {
                timestamp: [], // Array to store timestamps
                rx: [],        // Array to store received data points
                tx: []         // Array to store transmitted data points
            };

            // Populate network interface selector
            function populateNetworkInterfaces() {
                $.ajax({
                    url: 'processes.php?interfaces=1',
                    dataType: 'json',
                    success: function(interfaces) {
                        var $selector = $('#networkInterfaceSelector');
                        $selector.find('option:not(:first)').remove();

                        interfaces.forEach(function(iface) {
                            $selector.append($('<option>', {
                                value: iface,
                                text: iface
                            }));
                        });

                        // Restore previously selected interface
                        var savedInterface = localStorage.getItem('selectedNetworkInterface');
                        if (savedInterface && interfaces.includes(savedInterface)) {
                            $selector.val(savedInterface);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching network interfaces:', error);
                    }
                });
            }

            // Layout settings for the chart
            var layout = {
                title: {
                    text: 'Network Bandwidth',
                    font: {
                        family: 'Roboto, sans-serif',
                        size: 20
                    }
                },
                xaxis: {
                    title: 'Time',
                    type: 'date',
                    tickformat: '%I:%M:%S %p'
                },
                yaxis: {
                    title: 'Mbps',
                    rangemode: 'tozero'
                },
                plot_bgcolor: 'rgba(250,250,250,0.95)',
                paper_bgcolor: 'rgba(250,250,250,0.95)',
                margin: {
                    l: 50,
                    r: 30,
                    b: 60,
                    t: 80,
                    pad: 4
                }
            };

            var config = {
                responsive: true,
                displayModeBar: false
            };

            // Function to update the bandwidth chart with data from the server
            function updateChart() {
                var selectedInterface = $('#networkInterfaceSelector').val();

                $.ajax({
                    url: 'processes.php?data=network' + (selectedInterface ? '&interface=' + selectedInterface : ''),
                    dataType: 'json',
                    success: function (data) {
                        // Check if data has rx and tx properties
                        if (data && typeof data.rx !== 'undefined' && typeof data.tx !== 'undefined') {
                            var rx = parseFloat(data.rx.toFixed(2));
                            var tx = parseFloat(data.tx.toFixed(2));
                            var interfaceName = data.interface || 'unknown';

                            // Store the current timestamp and data points
                            bandwidthData.timestamp.push(new Date());
                            bandwidthData.rx.push(rx);
                            bandwidthData.tx.push(tx);

                            // Limit dataset to 60 data points
                            if (bandwidthData.timestamp.length > 60) {
                                bandwidthData.timestamp.shift();
                                bandwidthData.rx.shift();
                                bandwidthData.tx.shift();
                            }

                            // Create traces for received and transmitted data
                            var traceRx = {
                                x: bandwidthData.timestamp,
                                y: bandwidthData.rx,
                                mode: 'lines+markers',
                                name: 'Received',
                                line: {color: '#3ea9de', width: 2},
                                marker: {size: 4}
                            };

                            var traceTx = {
                                x: bandwidthData.timestamp,
                                y: bandwidthData.tx,
                                mode: 'lines+markers',
                                name: 'Transmitted',
                                line: {color: '#dc3545', width: 2},
                                marker: {size: 4}
                            };

                            // Combine traces into data array
                            var chartData = [traceRx, traceTx];

                            // Update the title with the interface name
                            var updatedLayout = Object.assign({}, layout);
                            updatedLayout.title.text = 'Network Bandwidth - Interface: ' + interfaceName;

                            // Create the chart using Plotly
                            Plotly.newPlot('bandwidthChart', chartData, updatedLayout, config);
                        } else {
                            console.error('Invalid network data received:', data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching network data:', error);
                    },
                    complete: function () {
                        // Only continue updating if modal is visible
                        if ($('#statsModal').hasClass('show')) {
                            setTimeout(updateChart, 1000);
                        }
                    }
                });
            }

            // Function to update the processes chart with data from the server
            function updateProcesses() {
                var selectedInterface = $('#networkInterfaceSelector').val();

                $.ajax({
                    url: 'processes.php' + (selectedInterface ? '?interface=' + selectedInterface : ''),
                    dataType: 'json',
                    success: function (data) {
                        updateCPU('.cpu-chart', data.cpu, 'CPU', data.cores);
                        updateMemoryProcess('.memory-chart', data.memory.used, data.memory.total, 'Memory');
                        updateProcess('.process-chart', data.process, 'Process');
                    },
                    error: function (error) {
                        console.error('Error fetching process data:', error);
                    },
                    complete: function() {
                        // Only continue updating if modal is visible
                        if ($('#statsModal').hasClass('show')) {
                            setTimeout(updateProcesses, 3000);
                        }
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

            // Start updates when the modal is shown
            $('#statsModal').on('shown.bs.modal', function() {
                populateNetworkInterfaces();
                updateChart();
                updateProcesses();
            });

            // Add event listener for interface selection change
            $('#networkInterfaceSelector').on('change', function() {
                // Save selected interface to localStorage
                localStorage.setItem('selectedNetworkInterface', $(this).val());

                // Reset bandwidth data when interface changes
                bandwidthData = {
                    timestamp: [],
                    rx: [],
                    tx: []
                };
                updateChart();
                updateProcesses();
            });

            // Stop updates when modal is hidden
            $('#statsModal').on('hidden.bs.modal', function() {
                // Clear all intervals
                bandwidthData = {
                    timestamp: [],
                    rx: [],
                    tx: []
                };
            });
        }
    });
</script>