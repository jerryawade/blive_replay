<?php
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}
require_once 'settings.php';
$settingsManager = new SettingsManager();
$settings = $settingsManager->getSettings();
date_default_timezone_set($settings['timezone'] ?? 'America/Chicago');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLIVE RePlay</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/imgs/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/imgs/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/imgs/favicon-16x16.png">
    <link rel="manifest" href="/assets/imgs/site.webmanifest">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        body {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background-color: #f4f4f4;
        }
        .manual-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #3ea9de;
            border-bottom: 2px solid #3ea9de;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            margin-bottom: 1.5rem;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
        }
        th {
            background-color: #f1f3f5;
            font-weight: 600;
        }
        #manual-content {
            max-width: 100%;
            overflow-x: auto;
        }
        @media print {
            body {
                padding: 0;
                margin: 0;
                background-color: white;
            }
            .manual-container {
                padding: 0;
                box-shadow: none;
                width: 100%;
            }
            .d-flex, .btn, #printManualBtn {
                display: none !important;
            }
            /* Ensure all content is visible */
            body #manual-content,
            body #manual-content * {
                visibility: visible !important;
            }
            #manual-content {
                display: block !important;
                width: 100%;
                margin: 0;
                padding: 10px;
            }
            /* Table-specific styles */
            table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 10pt;
                line-height: 1.4;
            }
            thead {
                display: table-header-group !important;
            }
            tbody {
                display: table-row-group !important;
            }
            tr {
                display: table-row !important;
            }
            th, td {
                display: table-cell !important;
                border: 1px solid #333 !important;
                padding: 10px 12px;
                text-align: left;
                vertical-align: top;
                word-wrap: break-word;
                max-width: 0;
            }
            th {
                background-color: #e0e0e0 !important;
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #000;
                border-bottom: 2px solid #333 !important;
            }
            td {
                background-color: #fff;
            }
            th:nth-child(1), td:nth-child(1) { width: 25%; } /* Feature */
            th:nth-child(2), td:nth-child(2) { width: 15%; } /* Interval */
            th:nth-child(3), td:nth-child(3) { width: 60%; } /* Description */
            tr:nth-child(even) td {
                background-color: #f9f9f9 !important;
            }
            img {
                max-width: 100% !important;
                height: auto;
                display: block;
            }
            #manual-content a[href^="#"]::after {
                content: none;
            }
            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            p, table, figure, ul, ol {
                page-break-inside: avoid;
            }
            pre {
                white-space: pre-wrap;
                word-wrap: break-word;
                border: 1px solid #ddd;
                padding: 8px;
                background-color: #f8f8f8 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
<div class="container mt-4 manual-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-primary icon-btn">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        <div>
            <button id="printManualBtn" class="btn btn-success icon-btn">
                <i class="bi bi-printer me-2"></i>Print Manual
            </button>
        </div>
    </div>
    <div id="manual-content">
        <h1>BLIVER RePlay User Manual</h1>
        <p>Loading manual content...</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.3.10/purify.min.js"></script>
<script>
    let isContentLoaded = false;

    function loadManualContent() {
        console.log('Fetching user_manual.md...');
        fetch('user_manual.md')
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.text();
            })
            .then(markdown => {
                const htmlContent = DOMPurify.sanitize(marked.parse(markdown));
                document.getElementById('manual-content').innerHTML = htmlContent;
                addHeadingIds();
                isContentLoaded = true;
                console.log('Content rendered.');
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('manual-content').innerHTML = `
                    <h1>BLIVER RePlay User Manual</h1>
                    <p>Error loading manual: ${error.message}. Please ensure 'user_manual.md' is available.</p>
                `;
                isContentLoaded = true;
            });
    }

    function addHeadingIds() {
        const headings = document.querySelectorAll('#manual-content h1, #manual-content h2, #manual-content h3, #manual-content h4, #manual-content h5, #manual-content h6');
        headings.forEach(heading => {
            if (!heading.id) {
                const id = heading.textContent
                    .toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^a-z0-9-]/g, '');
                heading.id = id;
                console.log(`Added ID "${id}" to heading: ${heading.textContent}`);
            }
        });
    }

    loadManualContent();

    document.getElementById('printManualBtn').addEventListener('click', function() {
        console.log('Print clicked. isContentLoaded:', isContentLoaded);
        if (!isContentLoaded) {
            alert('Manual is still loading. Please wait a moment and try again.');
            setTimeout(() => {
                if (isContentLoaded) window.print();
            }, 2000);
        } else {
            window.print();
        }
    });

    window.addEventListener('beforeprint', () => {
        console.log('Print preview opened. Current content:', document.getElementById('manual-content').innerHTML.substring(0, 100));
    });
</script>
</body>
</html>
