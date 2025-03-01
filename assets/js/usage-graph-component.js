// Compiled version of the React component for browser use
'use strict';

// Usage Graph Component
const UsageGraphComponent = () => {
    const [userData, setUserData] = React.useState([]);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState(null);
    const [timeRange, setTimeRange] = React.useState('week');
    const [selectedUser, setSelectedUser] = React.useState(null);
    const [allUsers, setAllUsers] = React.useState([]);

    React.useEffect(() => {
        // Function to fetch user data
        const fetchUserData = async () => {
            setLoading(true);
            try {
                // Get the username from the modal if it exists
                const usernameElement = document.getElementById('graphModalUsername');
                const username = usernameElement ? usernameElement.value : null;

                if (username) {
                    setSelectedUser(username);

                    const response = await fetch(`get_user_activity.php?username=${encodeURIComponent(username)}&timeRange=${timeRange}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data && data.success) {
                        setUserData(data.activityData || []);
                        setAllUsers(data.allUsers || []);
                    } else {
                        throw new Error(data.message || 'Failed to load data');
                    }
                }
            } catch (e) {
                console.error("Error fetching user data:", e);
                setError(e.message);
            } finally {
                setLoading(false);
            }
        };

        fetchUserData();
    }, [timeRange, selectedUser]);

    const handleTimeRangeChange = (e) => {
        setTimeRange(e.target.value);
    };

    const handleUserChange = (e) => {
        setSelectedUser(e.target.value);
        // Update the hidden input value for consistency
        const usernameElement = document.getElementById('graphModalUsername');
        if (usernameElement) {
            usernameElement.value = e.target.value;
        }
    };

    if (loading) {
        return React.createElement(
            'div',
            { className: 'text-center p-5' },
            React.createElement(
                'div',
                { className: 'spinner-border text-primary', role: 'status' },
                React.createElement(
                    'span',
                    { className: 'visually-hidden' },
                    'Loading...'
                )
            ),
            React.createElement(
                'p',
                { className: 'mt-3' },
                'Loading activity data...'
            )
        );
    }

    if (error) {
        return React.createElement(
            'div',
            { className: 'alert alert-danger m-3' },
            React.createElement(
                'h5',
                { className: 'alert-heading' },
                React.createElement('i', { className: 'bi bi-exclamation-triangle-fill me-2' }),
                ' Error Loading Data'
            ),
            React.createElement('p', null, error),
            React.createElement(
                'button',
                {
                    className: 'btn btn-sm btn-outline-danger',
                    onClick: () => {
                        setError(null);
                        setLoading(true);
                    }
                },
                React.createElement('i', { className: 'bi bi-arrow-clockwise me-1' }),
                ' Try Again'
            )
        );
    }

    return React.createElement(
        'div',
        { className: 'p-2' },
        React.createElement(
            'div',
            { className: 'mb-4 d-flex justify-content-between align-items-center flex-wrap' },
            React.createElement(
                'div',
                { className: 'mb-2 me-2' },
                React.createElement(
                    'label',
                    { htmlFor: 'userSelect', className: 'form-label me-2' },
                    'User:'
                ),
                React.createElement(
                    'select',
                    {
                        id: 'userSelect',
                        className: 'form-select form-select-sm d-inline-block w-auto',
                        value: selectedUser || '',
                        onChange: handleUserChange
                    },
                    allUsers.map(user => React.createElement(
                        'option',
                        { key: user, value: user },
                        user
                    ))
                )
            ),
            React.createElement(
                'div',
                { className: 'btn-group btn-group-sm', role: 'group' },
                React.createElement('input', {
                    type: 'radio',
                    className: 'btn-check',
                    name: 'timeRange',
                    id: 'day',
                    value: 'day',
                    checked: timeRange === 'day',
                    onChange: handleTimeRangeChange
                }),
                React.createElement(
                    'label',
                    { className: 'btn btn-outline-primary', htmlFor: 'day' },
                    'Day'
                ),
                React.createElement('input', {
                    type: 'radio',
                    className: 'btn-check',
                    name: 'timeRange',
                    id: 'week',
                    value: 'week',
                    checked: timeRange === 'week',
                    onChange: handleTimeRangeChange
                }),
                React.createElement(
                    'label',
                    { className: 'btn btn-outline-primary', htmlFor: 'week' },
                    'Week'
                ),
                React.createElement('input', {
                    type: 'radio',
                    className: 'btn-check',
                    name: 'timeRange',
                    id: 'month',
                    value: 'month',
                    checked: timeRange === 'month',
                    onChange: handleTimeRangeChange
                }),
                React.createElement(
                    'label',
                    { className: 'btn btn-outline-primary', htmlFor: 'month' },
                    'Month'
                ),
                React.createElement('input', {
                    type: 'radio',
                    className: 'btn-check',
                    name: 'timeRange',
                    id: 'year',
                    value: 'year',
                    checked: timeRange === 'year',
                    onChange: handleTimeRangeChange
                }),
                React.createElement(
                    'label',
                    { className: 'btn btn-outline-primary', htmlFor: 'year' },
                    'Year'
                )
            )
        ),
        userData.length === 0
            ? React.createElement(
                'div',
                { className: 'alert alert-info' },
                React.createElement('i', { className: 'bi bi-info-circle me-2' }),
                'No activity data available for this time period.'
            )
            : React.createElement(
                'div',
                { style: { width: '100%', height: 400 } },
                React.createElement(
                    Recharts.ResponsiveContainer,
                    null,
                    React.createElement(
                        Recharts.LineChart,
                        {
                            data: userData,
                            margin: { top: 5, right: 30, left: 20, bottom: 5 }
                        },
                        React.createElement(Recharts.CartesianGrid, { strokeDasharray: '3 3' }),
                        React.createElement(Recharts.XAxis, { dataKey: 'date' }),
                        React.createElement(Recharts.YAxis, null),
                        React.createElement(Recharts.Tooltip, null),
                        React.createElement(Recharts.Legend, null),
                        React.createElement(Recharts.Line, {
                            type: 'monotone',
                            dataKey: 'played_vlc',
                            name: 'Video Plays',
                            stroke: '#8884d8',
                            activeDot: { r: 8 }
                        }),
                        React.createElement(Recharts.Line, {
                            type: 'monotone',
                            dataKey: 'livestream_click',
                            name: 'Livestream Views',
                            stroke: '#82ca9d'
                        })
                    )
                )
            )
    );
};

// Render the component when the modal is shown
function renderUsageGraph() {
    const container = document.getElementById('usageGraphContainer');
    if (container && window.React && window.ReactDOM && window.Recharts) {
        ReactDOM.render(React.createElement(UsageGraphComponent), container);
    } else {
        console.error('Required libraries or container not found');
    }
}

// Add event listener to initialize the graph when modal is shown
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, setting up modal listener");
    const usageGraphModal = document.getElementById('usageGraphModal');
    if (usageGraphModal) {
        console.log("Graph modal found, adding event listener");
        usageGraphModal.addEventListener('shown.bs.modal', function() {
            console.log("Modal shown, rendering graph");
            renderUsageGraph();
        });
    } else {
        console.log("Graph modal NOT found");
    }
});