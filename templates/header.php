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
    <?php if (isAuthenticated() && isAdmin()): ?>
        <!-- Include the modern recording controller script for admin users -->
        <script src="assets/js/recording_controls.js"></script>
        <script src="assets/js/settings.js"></script>
        <script src="assets/js/stream_monitor.js"></script>
    <?php endif; ?>

    <?php if (isAuthenticated() && isset($settings['vlc_webpage_url']) && !empty($settings['vlc_webpage_url'])): ?>
        <script>
            // Make VLC webpage URL available to JavaScript
            window.vlcWebpageUrl = "<?php echo htmlspecialchars($settings['vlc_webpage_url']); ?>";
        </script>
    <?php endif; ?>
</head>
<body class="container mt-4" <?php echo isAdmin() ? 'data-is-admin="true"' : ''; ?>>
<div class="d-flex justify-content-between align-items-center mb-4">
    <img src="assets/imgs/Bethany Live Replay-04.png" alt="BLIVE RePlay" class="replay-logo">
    <?php if (isAuthenticated()): ?>
        <div class="d-flex align-items-center gap-2 mt-2">
                <span class="me-3 d-flex align-items-center">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                    <?php if (isAdmin()): ?>
                        (Admin)
                    <?php endif; ?>
                    <!-- Info icon for all authenticated users -->
                    <button type="button" class="btn btn-link btn-sm p-0 ms-2" id="aboutInfoButton"
                            title="About RePlay">
                        <i class="bi bi-info-circle-fill text-primary"></i>
                    </button>
                </span>
            <form method="post" class="d-inline">
                <?php if (isAdmin()): ?>
                    <button type="button" class="btn custom-btn icon-btn ms-2" data-bs-toggle="modal"
                            data-bs-target="#settingsModal">
                        <i class="bi bi-gear"></i>
                        Settings
                    </button>
                    <button type="button" class="btn custom-btn icon-btn ms-2" data-bs-toggle="modal"
                            data-bs-target="#usersModal">
                        <i class="bi bi-people"></i>
                        Manage Users
                    </button>
                    <button type="button" class="btn custom-btn icon-btn ms-2" data-bs-toggle="modal"
                            data-bs-target="#activityLogModal">
                        <i class="bi bi-journal-text"></i>
                        Log
                    </button>
                <?php endif; ?>
                <button type="submit" name="return_to_landing" class="btn custom-btn icon-btn ms-2">
                    <i class="bi bi-house"></i>
                    Landing Page
                </button>
                <button type="submit" name="logout" class="btn btn-secondary icon-btn ms-2">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
