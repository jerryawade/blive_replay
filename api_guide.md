# BLIVE RePlay API Usage Guide

## Table of Contents
- Introduction
- Authentication
- Endpoints
  - System Status
  - System Information
  - Recording Management
  - Data Retrieval
  - Logs and Monitoring
- Error Handling
- Client Libraries
- Security Best Practices

## Introduction

The BLIVER RePlay API provides comprehensive programmatic access to the recording and streaming system. This guide offers detailed documentation, example requests, and code samples to help you integrate with the API.

## Authentication

### API Key Configuration
- **Location:** System Settings > API Configuration
- **Requirements:** 
  - Minimum 32 characters
  - Unique and randomly generated
  - Keep confidential

### Authentication Methods
1. **Query Parameter**
   ```
   /api.php?endpoint=status&api_key=YOUR_API_KEY
   ```

2. **Header Authentication (Recommended)**
   ```http
   X-API-Key: YOUR_API_KEY
   ```

## Endpoints

### 1. System Status

#### Endpoint
```
GET /api.php?endpoint=status&api_key=YOUR_API_KEY
```

#### Example Response
```json
{
  "recording_active": true,
  "timestamp": 1709832450,
  "recording_start": 1709831200,
  "duration": 1250,
  "filename": "BLIVE_20240306_123000.mp4",
  "server_time": "2024-03-06 12:34:10"
}
```

#### Python Example
```python
import requests

def get_system_status(api_key):
    url = "https://yourdomain.com/api.php"
    params = {
        "endpoint": "status",
        "api_key": api_key
    }
    
    try:
        response = requests.get(url, params=params)
        response.raise_for_status()
        return response.json()
    except requests.RequestException as e:
        print(f"Error fetching system status: {e}")
        return None

# Usage
status = get_system_status("your_api_key")
if status:
    print(f"Recording Active: {status['recording_active']}")
    print(f"Current Recording: {status.get('filename', 'No active recording')}")
```

#### Node.js Example
```javascript
const axios = require('axios');

async function getSystemStatus(apiKey) {
    try {
        const response = await axios.get('https://yourdomain.com/api.php', {
            params: {
                endpoint: 'status',
                api_key: apiKey
            }
        });
        return response.data;
    } catch (error) {
        console.error('Error fetching system status:', error.message);
        return null;
    }
}

// Usage
getSystemStatus('your_api_key')
    .then(status => {
        if (status) {
            console.log(`Recording Active: ${status.recording_active}`);
            console.log(`Current Recording: ${status.filename || 'No active recording'}`);
        }
    });
```

### 2. System Information

#### Endpoint
```
GET /api.php?endpoint=info&api_key=YOUR_API_KEY
```

#### Example Response
```json
{
  "version": "1.6.0",
  "system_time": "2024-03-06 12:34:10",
  "timezone": "America/Chicago",
  "total_recordings": 157,
  "scheduler_enabled": true,
  "php_version": "8.1.12",
  "ffmpeg_version": "FFmpeg version 4.4.2",
  "uptime": "up 45 days, 6 hours, 22 minutes"
}
```

### 3. Recording Management

#### Start Recording
```
GET /api.php?endpoint=start_recording&api_key=YOUR_API_KEY
```

#### Stop Recording
```
GET /api.php?endpoint=stop_recording&api_key=YOUR_API_KEY
```

#### Example Responses
```json
{
  "success": true,
  "message": "Recording started successfully",
  "details": {
    "filename": "BLIVE_20240306_123456.mp4",
    "start_time": 1709832450
  }
}
```

#### Bash/Curl Example
```bash
#!/bin/bash

API_KEY="your_api_key"
BASE_URL="https://yourdomain.com/api.php"

# Start Recording
start_recording() {
    response=$(curl -s "${BASE_URL}?endpoint=start_recording&api_key=${API_KEY}")
    echo "Start Recording Response: $response"
}

# Stop Recording
stop_recording() {
    response=$(curl -s "${BASE_URL}?endpoint=stop_recording&api_key=${API_KEY}")
    echo "Stop Recording Response: $response"
}

# Usage
start_recording
sleep 300  # Record for 5 minutes
stop_recording
```

### 4. Data Retrieval

#### Recordings List
```
GET /api.php?endpoint=recordings&api_key=YOUR_API_KEY&limit=20
```

#### Example Response
```json
{
  "success": true,
  "count": 20,
  "recordings": [
    {
      "filename": "BLIVER_20240305_200000.mp4",
      "size": 1073741824,
      "size_formatted": "1.07 GB",
      "date": "2024-03-05 20:00:00",
      "timestamp": 1709673600,
      "duration": "02:15:30",
      "note": "Sunday Service Recording"
    }
  ]
}
```

#### Scheduled Recordings
```
GET /api.php?endpoint=schedule&api_key=YOUR_API_KEY
```

#### Example Response
```json
{
  "success": true,
  "enabled": true,
  "current_recording": null,
  "last_action": "manual_stop",
  "last_action_time": "2024-03-06 11:45:22",
  "next_schedule": {
    "id": "sch_20240306_001",
    "title": "Sunday Morning Service",
    "type": "weekly",
    "start_time": "10:00",
    "end_time": "12:00",
    "next_run": "2024-03-10 10:00:00"
  },
  "schedules": [
    {
      "id": "sch_20240306_001",
      "title": "Sunday Morning Service",
      "type": "weekly",
      "enabled": true,
      "weekdays": [0],
      "start_time": "10:00",
      "end_time": "12:00"
    }
  ]
}
```

### 5. Logs and Monitoring

#### Recent Logs
```
GET /api.php?endpoint=logs&api_key=YOUR_API_KEY&type=ffmpeg&limit=50
```

#### User Activity
```
GET /api.php?endpoint=activity&api_key=YOUR_API_KEY&range=week
```

## Error Handling

### Common Error Responses

1. **Authentication Failure**
```json
{
  "error": "Invalid API key",
  "status": 401
}
```

2. **Endpoint Disabled**
```json
{
  "error": "Requested endpoint is disabled",
  "status": 403
}
```

3. **Recording Control Disabled**
```json
{
  "error": "Recording control via API is disabled",
  "status": 403
}
```

## Client Libraries

### Recommended Client Libraries

1. **Python**
   - `requests`: HTTP library
   - `aiohttp`: Async HTTP client

2. **JavaScript/Node.js**
   - `axios`: Promise-based HTTP client
   - `node-fetch`: Lightweight fetch implementation

3. **PHP**
   - `Guzzle`: Comprehensive HTTP client
   - Native `file_get_contents()` for simple requests

## Security Best Practices

1. **API Key Management**
   - Generate a strong, unique API key
   - Rotate keys periodically
   - Never expose keys in public repositories

2. **Connection Security**
   - Always use HTTPS
   - Implement IP whitelisting
   - Use API key with minimal required permissions

3. **Rate Limiting**
   - Implement client-side request throttling
   - Monitor and log API usage

### Rate Limiting Example (Python)
```python
import time
import requests
from requests.adapters import HTTPAdapter
from requests.packages.urllib3.util.retry import Retry

class RateLimitedAPIClient:
    def __init__(self, api_key, max_requests_per_minute=60):
        self.api_key = api_key
        self.max_requests_per_minute = max_requests_per_minute
        self.requests_made = 0
        self.last_reset_time = time.time()
        
        # Configure retry strategy
        retry_strategy = Retry(
            total=3,
            status_forcelist=[429, 500, 502, 503, 504],
            method_whitelist=["GET", "POST"]
        )
        adapter = HTTPAdapter(max_retries=retry_strategy)
        self.session = requests.Session()
        self.session.mount("https://", adapter)
    
    def _check_rate_limit(self):
        current_time = time.time()
        
        # Reset request count every minute
        if current_time - self.last_reset_time >= 60:
            self.requests_made = 0
            self.last_reset_time = current_time
        
        # Check if we've exceeded rate limit
        if self.requests_made >= self.max_requests_per_minute:
            wait_time = 60 - (current_time - self.last_reset_time)
            print(f"Rate limit reached. Waiting {wait_time:.2f} seconds.")
            time.sleep(wait_time)
            self._check_rate_limit()
        
        self.requests_made += 1
    
    def get(self, endpoint, params=None):
        self._check_rate_limit()
        
        params = params or {}
        params['api_key'] = self.api_key
        
        response = self.session.get(
            'https://yourdomain.com/api.php', 
            params=params
        )
        
        response.raise_for_status()
        return response.json()

# Usage
client = RateLimitedAPIClient('your_api_key')
status = client.get('status')
print(status)
```

## Troubleshooting

1. **Connection Issues**
   - Verify API endpoint URL
   - Check network connectivity
   - Confirm firewall settings

2. **Authentication Problems**
   - Validate API key
   - Check system settings
   - Verify IP restrictions

## Support

For additional support:
- Review system logs
- Check API settings
- Contact system administrator

## Changelog

- **v1.6.0 (March 2025)**
  - Initial comprehensive API documentation
  - Added rate limiting examples
  - Enhanced security recommendations
