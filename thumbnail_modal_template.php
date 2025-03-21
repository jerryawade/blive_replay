    <?php
/**
 * thumbnail_modal_template.php
 * Returns the HTML for the thumbnail selector modal
 */

// Check if user is authenticated
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

// Return the HTML template for the thumbnail selector modal
?>
<!-- Thumbnail Selection Modal -->
<div class="modal fade" id="thumbnailSelectorModal" tabindex="-1" aria-labelledby="thumbnailSelectorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="thumbnailSelectorModalLabel">
                    <i class="bi bi-card-image me-2"></i>
                    Select Thumbnail
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="thumbnailSelectorSpinner" class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Generating thumbnails...</span>
                    </div>
                    <p class="mt-3">Generating thumbnails from your video...</p>
                </div>
                
                <div id="thumbnailSelectorContent" style="display: none;">
                    <p class="mb-3">Select the thumbnail you want to use for this recording:</p>
                    
                    <div class="row thumbnail-options">
                        <!-- Thumbnails will be loaded dynamically here -->
                    </div>
                </div>
                
                <div id="thumbnailSelectorError" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span class="error-message">Error message will appear here</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary icon-btn" id="regenerateThumbnailsBtn">
                    <i class="bi bi-arrow-clockwise"></i>
                    Regenerate Thumbnails
                </button>
                <button type="button" class="btn btn-secondary icon-btn" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add thumbnail selection styles -->
<style>
    .thumbnail-option {
        cursor: pointer;
        margin-bottom: 1.5rem;
        transition: transform 0.2s, border-color 0.2s;
        border: 3px solid transparent;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .thumbnail-option:hover {
        transform: scale(1.03);
    }
    
    .thumbnail-option.selected {
        border-color: #3ea9de;
        box-shadow: 0 0 10px rgba(62, 169, 222, 0.5);
    }
    
    .thumbnail-image-container {
        position: relative;
        width: 100%;
        height: 0;
        padding-top: 56.25%; /* 16:9 aspect ratio */
        overflow: hidden;
        background-color: transparent;
    }
    
    .thumbnail-image {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    
    .thumbnail-label {
        padding: 8px;
        background-color: #f8f9fa;
        text-align: center;
        font-size: 0.9rem;
        color: #495057;
        border-top: 1px solid #dee2e6;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .thumbnail-option {
            margin-bottom: 1rem;
        }
        
        .thumbnail-label {
            font-size: 0.8rem;
            padding: 5px;
        }
    }
</style>
