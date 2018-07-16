<?php
/**
 * Callbacks to generate pages
 *
 * @author      feeling4design
 * @category    Admin
 * @package     SUPER_Media_Cleaner/Classes
 * @class       SUPER_MC_Pages
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if( !class_exists( 'SUPER_MC_Pages' ) ) :

/**
 * SUPER_MC_Pages
 */
class SUPER_MC_Pages {

    /**
     * @since 3.0.0 - Documentation
     */
    public static function scan() {
        ?>
        <div class="wrap">
            <div class="notice notice-success" wfd-id="12">
                <p>
                    <strong>Quick start guide:</strong><br />
                    <strong>1.</strong> Click "Start Scan" to scan all media files that are currently not being used on your site.<br />
                    <strong>2.</strong> Review the scanned files and uncheck (if nessasary) any files that you wish to keep.<br />
                    <strong>3.</strong> Click "Clean" after the scan is completed, note that these files will not be deleted, they will be put inside a temporary directory.<br />
                    <strong>4.</strong> Review your website and see if you are missing some files, if so, you can recover any missing files by clicking "Recover Media". This allows you to undo any previous cleaning you done.<br />
                    <strong>5.</strong> When you are sure your site is working and you are not missing any media files, you can permanently delete cleaned media by clicking "Permanently delete"
                </p>
            </div>

            <div class="tiles stats">
                <div class="tile scanned">
                    <span class="title">Files scanned</span><span class="files value">0</span>
                    <span class="title">Directories scanned</span><span class="directories value">0</span>
                </div>
                <div class="tile unknown">
                    <span class="title">Unknown files</span><span class="files value">0</span>
                    <span class="title">Unknown file extensions</span><span class="extensions value">None</span>
                </div>
                <div class="tile space">
                    <span class="title">Percentage saved</span><span class="percentage value">0 %</span>
                    <span class="title">Space to be saved</span><span class="saved value">0 Bytes</span>
                    <span class="title">Total file size</span><span class="size value">0 Bytes</span>
                </div>
                <div class="tile usage">
                    <span class="title">Current usage</span><span class="current value">13</span>
                    <span class="title">(<a href="#">Add Credits</a>) Credits left</span><span class="credits value">100</span>
                </div>
            </div>

            <div class="tiles logs">
                <div class="tile log">
                    <h3>Scan activity</h3>
                    <div class="start-scan">
                        <p>Start the scan to start analysing files:</p>
                        <button type="button" class="button media-button  super-mc-start-scan">Start Scan</button>
                    </div>
                    <div class="scan-completed">
                        <p>Scan completed!</p>
                    </div>
                    <div class="progress">
                        <div class="bar" style="width:0%;"><span class="value">0%</span></div>
                    </div>
                    <ul></ul>
                </div>
                <div class="tile files">
                    <h3>Files scanned</h3>
                    <p>First start the scan.</p>
                    <ul></ul>
                </div>
                <div class="tile directories">
                    <h3>Directories scanned</h3>
                    <p>First start the scan.</p>
                    <ul></ul>
                </div>

            </div>


        </div>
        <?php
    }
    
}
endif;