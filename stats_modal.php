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
                    
                    <!-- GPU Stats Container -->
                    <div id="gpu-container" class="mb-4">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-gpu-card me-2"></i>
                            GPU Statistics <span id="gpu-name-display" class="badge bg-secondary ms-2">N/A</span>
                        </h6>
                        <div id="gpu-stats" class="row">
                            <div class="col-md-4">
                                <div class="chart-container">
                                    <span><i class="bi bi-speedometer"></i> GPU Utilization</span>
                                    <div class="gpu-utilization-chart"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="chart-container">
                                    <span><i class="bi bi-memory"></i> GPU Memory</span>
                                    <div class="gpu-memory-chart"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="chart-container">
                                    <span><i class="bi bi-thermometer-half"></i> GPU Temperature</span>
                                    <div class="gpu-temp-chart"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- GPU Processes Table -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <i class="bi bi-list-task me-2"></i>
                                        GPU Processes
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm" id="gpu-processes-table">
                                                <thead>
                                                    <tr>
                                                        <th>PID</th>
                                                        <th>Process Name</th>
                                                        <th>Memory Usage (MB)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- GPU processes will be populated here -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Network Interface Selector -->
                    <div class="row mb-3">
                        <div class="col-md-6 offset-md-3">
                            <select id="networkInterfaceSelector" class="form-select">
                                <option value="">Select Network Interface</option>
                            </select>
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
    .chart-container {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        height: 110px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .chart-container span {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .chart-container span + div,
    .chart-container span + span {
        margin-top: auto;
    }
    
    .metrics-value {
        font-size: 22px;
        color: #333;
        margin-bottom: 5px;
    }
    
    .metrics-detail {
        font-size: 14px;
        color: #666;
    }
    
    .text-danger { color: #dc3545; }
    
    #bandwidthChart {
        height: 300px;
        width: 100%;
    }
    
    /* GPU specific styles */
    .temp-warning {
        color: #ff9900;
    }
    
    .temp-danger {
        color: #dc3545;
    }
    
    #gpu-container {
        display: none; /* Hidden by default, will be shown if GPU is detected */
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Plotly is already loaded, if not load it
        function loadScript(url, callback) {
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = url;
            script.onload = callback;
            script.onerror = () => console.error('Failed to load ' + url);
            document.head.appendChild(script);
        }

        // Load Plotly if not present
        if (typeof Plotly === 'undefined') {
            loadScript('https://cdn.jsdelivr.net/npm/plotly.js@2.14.0/dist/plotly.min.js', initTrafficMonitor);
        } else {
            initTrafficMonitor();
        }

        function initTrafficMonitor() {
            var bandwidthData = {
                timestamp: [],
                rx: [],
                tx: []
            };
            
            // Variable to hold our update timers
            var chartUpdateTimer = null;
            var processUpdateTimer = null;
            var gpuUpdateTimer = null;

            // Populate the network interface dropdown
            function populateNetworkInterfaces() {
                $.ajax({
                    url: 'processes.php?interfaces=1',
                    dataType: 'json',
                    success: function(interfaces) {
                        console.log('Interfaces received:', interfaces);
                        var $selector = $('#networkInterfaceSelector');
                        $selector.find('option:not(:first)').remove();
                        
                        if (interfaces && interfaces.length) {
                            interfaces.forEach(function(iface) {
                                $selector.append($('<option>', { value: iface, text: iface }));
                            });
                            
                            // Try to restore previously selected interface from localStorage
                            var savedInterface = localStorage.getItem('selectedNetworkInterface');
                            if (savedInterface && interfaces.includes(savedInterface)) {
                                $selector.val(savedInterface);
                            } else if (interfaces.length > 0) {
                                // Select first interface if no saved preference
                                $selector.val(interfaces[0]);
                            }
                            
                            // Trigger change to start data collection
                            $selector.trigger('change');
                        } else {
                            $selector.after('<p class="text-danger">No network interfaces found</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Interfaces error:', xhr.responseText);
                        $('#networkInterfaceSelector').after('<p class="text-danger">Failed to load interfaces</p>');
                    }
                });
            }

            // Plotly chart configuration
            var layout = {
                title: { 
                    text: 'Network Bandwidth', 
                    font: { family: 'Roboto, sans-serif', size: 20 } 
                },
                xaxis: { 
                    title: 'Time', 
                    type: 'date', 
                    tickformat: '%H:%M:%S' 
                },
                yaxis: { 
                    title: 'Mbps', 
                    rangemode: 'tozero' 
                },
                plot_bgcolor: 'rgba(250,250,250,0.95)',
                paper_bgcolor: 'rgba(250,250,250,0.95)',
                margin: { l: 50, r: 30, b: 60, t: 80, pad: 4 },
                legend: {
                    orientation: 'h',
                    xanchor: 'center',
                    x: 0.5,
                    y: 1.1
                }
            };

            var config = { 
                responsive: true, 
                displayModeBar: false 
            };

            // Update network bandwidth chart
            function updateChart() {
                var selectedInterface = $('#networkInterfaceSelector').val();
                if (!selectedInterface) {
                    return;
                }
                
                $.ajax({
                    url: 'processes.php?data=network&interface=' + selectedInterface,
                    dataType: 'json',
                    success: function(data) {
                        console.log('Network data:', data);
                        if (data.error) {
                            $('#bandwidthChart').html(`<p class="text-danger">Error: ${data.error}</p>`);
                            return;
                        }
                        
                        var rx = parseFloat(data.rx || 0).toFixed(2);
                        var tx = parseFloat(data.tx || 0).toFixed(2);
                        var interfaceName = data.interface || selectedInterface;

                        // Add new data points
                        bandwidthData.timestamp.push(new Date());
                        bandwidthData.rx.push(rx);
                        bandwidthData.tx.push(tx);

                        // Keep only last 60 data points
                        if (bandwidthData.timestamp.length > 60) {
                            bandwidthData.timestamp.shift();
                            bandwidthData.rx.shift();
                            bandwidthData.tx.shift();
                        }

                        var traceRx = {
                            x: bandwidthData.timestamp,
                            y: bandwidthData.rx,
                            mode: 'lines+markers',
                            name: 'Download (Mbps)',
                            line: { color: '#3ea9de', width: 2 },
                            marker: { size: 4 }
                        };

                        var traceTx = {
                            x: bandwidthData.timestamp,
                            y: bandwidthData.tx,
                            mode: 'lines+markers',
                            name: 'Upload (Mbps)',
                            line: { color: '#dc3545', width: 2 },
                            marker: { size: 4 }
                        };

                        // Update chart title to show current interface
                        layout.title.text = 'Network Bandwidth - Interface: ' + interfaceName;
                        
                        // Create or update the chart
                        Plotly.newPlot('bandwidthChart', [traceRx, traceTx], layout, config);
                    },
                    error: function(xhr, status, error) {
                        console.error('Network fetch error:', xhr.responseText);
                        $('#bandwidthChart').html('<p class="text-danger">Failed to fetch network data</p>');
                    },
                    complete: function() {
                        // Only schedule next update if modal is still visible
                        if ($('#statsModal').hasClass('show')) {
                            chartUpdateTimer = setTimeout(updateChart, 1000);
                        }
                    }
                });
            }

            // Update system process information
            function updateProcesses() {
                $.ajax({
                    url: 'processes.php',
                    dataType: 'json',
                    success: function(data) {
                        console.log('Process data:', data);
                        
                        // CPU Utilization
                        if (data.cpu) {
                            var cpuUsage = data.cpu.usage || 0;
                            var cpuCores = data.cpu.cores || 1;
                            var cpuPercent = Math.min(100, Math.round((cpuUsage / cpuCores) * 100));
                            updateCPU('.cpu-chart', cpuPercent, 'CPU', cpuCores, cpuUsage);
                        }
                        
                        // Memory Utilization
                        if (data.memory) {
                            updateMemoryProcess(
                                '.memory-chart', 
                                data.memory.used || 0, 
                                data.memory.total || 0, 
                                'Memory'
                            );
                        }
                        
                        // Process Utilization (using CPU load average as proxy)
                        if (data.cpu) {
                            var processPercent = Math.min(100, Math.round((data.cpu.usage / data.cpu.cores) * 100));
                            updateProcess('.process-chart', processPercent, 'Process');
                        }
                        
                        // GPU Utilization (check if GPU data exists)
                        if (data.gpu && !data.gpu.error) {
                            $('#gpu-container').show();
                            updateGPUStats(data.gpu);
                        } else if (data.gpu && data.gpu.error) {
                            // Hide GPU section if there's an error (likely no GPU available)
                            $('#gpu-container').hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Process fetch error:', xhr.responseText);
                        $('#processes').prepend('<p class="text-danger">Failed to fetch process data</p>');
                    },
                    complete: function() {
                        // Only schedule next update if modal is still visible
                        if ($('#statsModal').hasClass('show')) {
                            processUpdateTimer = setTimeout(updateProcesses, 3000);
                        }
                    }
                });
            }
            
            // Update GPU statistics
            function updateGPUStats(gpuData) {
                // Update GPU name and type badge
                var gpuName = gpuData.name || 'Unknown GPU';
                var gpuType = gpuData.type || 'unknown';
                var badgeClass = 'bg-secondary';
                
                if (gpuType === 'nvidia') {
                    badgeClass = 'bg-success';
                } else if (gpuType === 'amd' || gpuType === 'amd-legacy') {
                    badgeClass = 'bg-danger';
                }
                
                $('#gpu-name-display').text(gpuName).removeClass().addClass('badge ms-2 ' + badgeClass);
                
                // Update GPU utilization
                var utilization = gpuData.utilization || 0;
                $('.gpu-utilization-chart').empty().append(
                    $('<div>').addClass('metrics-value').html('<strong>' + utilization + '%</strong>')
                );
                
                // Update GPU memory
                if (gpuData.memory) {
                    var usedMem = gpuData.memory.used || 0;
                    var totalMem = gpuData.memory.total || 1;
                    var usedPercent = totalMem > 0 ? Math.round((usedMem / totalMem) * 100) : 0;
                    
                    $('.gpu-memory-chart').empty().append(
                        $('<div>').addClass('metrics-value').html('<strong>' + usedPercent + '%</strong>'),
                        $('<div>').addClass('metrics-detail').text(usedMem + ' MB / ' + totalMem + ' MB')
                    );
                }
                
                // Update GPU temperature
                var temp = gpuData.temperature || 0;
                var tempClass = temp >= 80 ? 'temp-danger' : (temp >= 70 ? 'temp-warning' : '');
                
                $('.gpu-temp-chart').empty().append(
                    $('<div>').addClass('metrics-value ' + tempClass).html('<strong>' + temp + '°C</strong>')
                );
                
                // Update GPU processes table
                var $tbody = $('#gpu-processes-table tbody');
                $tbody.empty();
                
                if (gpuData.processes && gpuData.processes.length > 0) {
                    gpuData.processes.forEach(function(process) {
                        var memoryText = process.memory === 'N/A' ? 'N/A' : process.memory + ' MB';
                        
                        $tbody.append(
                            $('<tr>').append(
                                $('<td>').text(process.pid),
                                $('<td>').text(process.name),
                                $('<td>').text(memoryText)
                            )
                        );
                    });
                } else {
                    $tbody.append(
                        $('<tr>').append(
                            $('<td>').attr('colspan', 3).addClass('text-center').text('No GPU processes running')
                        )
                    );
                }
            }

            // Update Memory usage display
            function updateMemoryProcess(selector, usedMemory, totalMemory, label) {
                $(selector).empty();
                var usedGB = usedMemory / (1024 * 1024);
                var totalGB = totalMemory / (1024 * 1024);
                var usedPercentage = totalMemory ? (usedMemory / totalMemory * 100) : 0;
                
                $(selector).append(
                    $('<div>').addClass('metrics-value').html('<strong>' + usedPercentage.toFixed(2) + '%</strong>'),
                    $('<div>').addClass('metrics-detail').text(usedGB.toFixed(2) + ' GB / ' + totalGB.toFixed(2) + ' GB')
                );
            }

            // Update CPU usage display
            function updateCPU(selector, value, label, cores, rawUsage) {
                $(selector).empty();
                $(selector).append(
                    $('<div>').addClass('metrics-value').html('<strong>' + value + '%</strong>'),
                    $('<div>').addClass('metrics-detail').text(rawUsage.toFixed(2) + ' / ' + cores + ' cores')
                );
            }

            // Update Process usage display
            function updateProcess(selector, value, label) {
                $(selector).empty();
                $(selector).append(
                    $('<div>').addClass('metrics-value').html('<strong>' + value + '%</strong>')
                );
            }

            // Modal show event - start all updates
            $('#statsModal').on('shown.bs.modal', function() {
                // Clear any existing timers
                clearTimers();
                
                // Reset data
                bandwidthData = { timestamp: [], rx: [], tx: [] };
                
                // Populate interfaces and start updates
                populateNetworkInterfaces();
                updateProcesses();
            });

            // Interface selection changed
            $('#networkInterfaceSelector').on('change', function() {
                var selectedInterface = $(this).val();
                if (selectedInterface) {
                    localStorage.setItem('selectedNetworkInterface', selectedInterface);
                    
                    // Reset bandwidth data for new interface
                    bandwidthData = { timestamp: [], rx: [], tx: [] };
                    
                    // Start chart updates
                    clearTimeout(chartUpdateTimer);
                    updateChart();
                }
            });

            // Modal hide event - clean up
            $('#statsModal').on('hidden.bs.modal', function() {
                clearTimers();
                bandwidthData = { timestamp: [], rx: [], tx: [] };
            });
            
            // Helper to clear all update timers
            function clearTimers() {
                if (chartUpdateTimer) {
                    clearTimeout(chartUpdateTimer);
                    chartUpdateTimer = null;
                }
                
                if (processUpdateTimer) {
                    clearTimeout(processUpdateTimer);
                    processUpdateTimer = null;
                }
                
                if (gpuUpdateTimer) {
                    clearTimeout(gpuUpdateTimer);
                    gpuUpdateTimer = null;
                }
            }
        }
    });
</script>
