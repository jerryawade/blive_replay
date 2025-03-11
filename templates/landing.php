<div class="landing-container">
    <div class="landing-options">
        <?php if (isAdmin() || (isset($settings['show_recordings']) && $settings['show_recordings'])): ?>
            <div class="landing-option">
                <a href="?page=recordings" class="text-decoration-none">
                    <img src="assets/imgs/recordings-icon.png" alt="Recordings" class="landing-image">
                    <h3><?php echo isAdmin() ? 'MANAGE RECORDINGS' : 'REPLAY/RECORDINGS'; ?></h3>
                </a>
            </div>
        <?php endif; ?>

        <?php if (isAdmin() || (isset($settings['show_livestream']) && $settings['show_livestream'])): ?>
            <div class="landing-option">
                <a href="<?php echo htmlspecialchars($formattedStreamUrl); ?>"
                   onclick="logLiveStreamClick(event, <?php echo $settings['open_webpage_for_livestream'] ? 'true' : 'false'; ?>)"
                   class="text-decoration-none">
                    <img src="assets/imgs/live-stream-icon.png" alt="Live Stream" class="landing-image">
                    <h3>LIVE STREAM</h3>
                </a>
            </div>
        <?php endif; ?>

        <?php if (!isAdmin() && 
            (!isset($settings['show_recordings']) || !$settings['show_recordings']) && 
            (!isset($settings['show_livestream']) || !$settings['show_livestream'])): ?>
            <div class="alert alert-info">
                No options are currently available. Please contact your administrator.
            </div>
        <?php endif; ?>
    </div>
</div>
