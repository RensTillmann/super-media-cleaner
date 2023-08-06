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

    private static function formatSizeUnits($bytes) {
        if ($bytes >= 1000000000) {
            $bytes = number_format($bytes / 1000000000, 2) . ' GB';
        } elseif ($bytes >= 1000000) {
            $bytes = number_format($bytes / 1000000, 2) . ' MB';
        } elseif ($bytes >= 1000) {
            $bytes = number_format($bytes / 1000, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }
    public static function used($file){
        global $wpdb;
        // Check if file is used
        $used = false;
        // Search in post meta data
        $sql = $wpdb->prepare("
        SELECT post_id, post_type, post_status, post_title, 
        CONCAT(',', CAST(meta_value AS CHAR), ',') AS compareValue 
        FROM $wpdb->postmeta AS pm INNER JOIN $wpdb->posts AS p ON p.ID = pm.post_id
        WHERE post_status IN ('publish') 
        AND (meta_key != '_wp_attached_file' 
        AND (meta_key LIKE '%s' OR meta_key LIKE '%s' OR meta_key LIKE '%s'))
        HAVING compareValue LIKE '%s' OR compareValue LIKE '%s'",
        "%file%", "%gallery%", "%ids%",
        "%," . $wpdb->esc_like($file["attachmentId"]) . ",%",
        "%" . $wpdb->esc_like($file["attachedFile"]) . "%");
        $results = $wpdb->get_results($sql);
        foreach($results as $k => $v){
            $used = true;
            $file['usedBy'][$v->post_id] = array(
                'post_title' => $v->post_title,
                'post_type' => $v->post_type,
                'post_status' => $v->post_status
            );
        }
        if($used===true) $file['used'] = true;

        // Search in post_content
        $sql = $wpdb->prepare("
        SELECT ID, post_content, post_status, post_title, post_type
        FROM $wpdb->posts AS p
        WHERE post_status IN ('publish') AND p.post_content LIKE '%s'",
        "%" . $wpdb->esc_like($file["attachedFile"]) . "%");
        $results = $wpdb->get_results($sql);
        foreach($results as $k => $v){
            $used = true;
            $file['usedBy'][$v->ID] = array(
                'post_title' => $v->post_title,
                'post_type' => $v->post_type,
                'post_status' => $v->post_status
            );
        }
        if($used===true) $file['used'] = true;
        return $file;
    }
    public static function file_url( $file, $uploadfolder=false ) {
        $file = realpath($file);
        if(!$uploadfolder) {
            $uploadfolder = wp_upload_dir();
        }
        $basedir = realpath($uploadfolder['basedir']);
        $url = str_replace($basedir, $uploadfolder['baseurl'], $file);
        $url = str_replace('\\', '/', $url);
        return $url;
    }
    public static function getFileType($mime){
        switch (true) {
            case stristr($mime, 'application/'):
                return 'application';
                break;
            case stristr($mime, 'audio/'):
                return 'audio';
                break;
            case stristr($mime, 'font/'):
                return 'font';
                break;
            case stristr($mime, 'image/'):
                return 'image';
                break;
            case stristr($mime, 'message/'):
                return 'message';
                break;
            case stristr($mime, 'model/'):
                return 'model';
                break;
            case stristr($mime, 'multipart/'):
                return 'multipart';
                break;
            case stristr($mime, 'text/'):
                return 'text';
                break;
            case stristr($mime, 'video/'):
                return 'video';
                break;
            default:
                return 'other';
                break;
        }
        // tmp if(@exif_imagetype($filename)===false){
        // tmp     // not an image, perhaps a video?
        // tmp     $mime = mime_content_type($filename);
        // tmp     if(!strstr($mime, "video/")){
        // tmp         $fileType = $mime;
        // tmp         //'documents' => array(), // .pdf, .docx, .csv
        // tmp         //'data' => array(), // .txt, .log, .data, .xml 
        // tmp         continue;
        // tmp     }else{
        // tmp         $fileType = 'videos';
        // tmp     }
        // tmp }else{
        // tmp     $fileType = 'images';
        // tmp }
    }
    public static function scan_page() {
        global $wpdb;

        // Before scanning, scrape website
        $r = wp_remote_post(
            'https://api.dev.super-forms.com/scrape',
            array(
                'method' => 'POST',
                'timeout' => 999999,
                'data_format' => 'body',
                'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'body' => json_encode(array(
                    'home_url' => get_home_url()
                ))
            )
        );
        //$r = wp_remote_get('https://api.dev.super-forms.com/scrape/'.get_home_url(), array(
        //    'timeout' => 999999, // no timeout
        //    'sslverify' => false
        //));
        if(is_wp_error($r)){
            echo json_encode($r->errors);
            exit;
        }
        //var_dump($r);
        //var_dump($r['body']);
        $crawler = json_decode($r['body'], true); // use the content
        //var_dump($crawler);
        //exit;
        if(!is_array($crawler)) $crawler = array();
        if(!is_array($crawler['images'])) $crawler['images'] = array();

        $uploadfolder = wp_upload_dir();
        $dir = $uploadfolder['basedir'];
        $i = new RecursiveDirectoryIterator($dir);
        $stats = array(
            'totalDirectories'=>0,
            'totalFiles'=>0,
            'totalBytes'=>0,
            'totalUsedBytes'=>0,
            'totalUnusedBytes'=>0,
            'files'=>array(
                // categorize by media type 
                // http://www.iana.org/assignments/media-types/media-types.xhtml
                'application' => array(),
                'audio' => array(),
                'font' => array(),
                'image' => array(),
                'message' => array(),
                'model' => array(),
                'multipart' => array(),
                'text' => array(),
                'video' => array(),
                'other' => array()

                //'images' => array(), // .jpg, .png, .gif
                //'videos' => array(), // .mp4
                //'documents' => array(), // .pdf, .docx, .csv
                //'data' => array(), // .txt, .log, .data, .xml 
                //'other' => array()
            ),
            'attachmentsChecked'=>array()
        );
        $x = 0;
        $limit = 200;
        
        if(!isset($_GET['type'])) $_GET['type'] = 'image,video,audio';
        if(is_array($_GET['type'])) $_GET['type'] = implode(',', $_GET['type']);
        if(!isset($_GET['ext'])) $_GET['ext'] = ''; //jpg,jpeg,png,gif,ico,bmp,wbmp,webp,mp4,mp3';
        $filterByFileType = $_GET['type'];
        $items = explode(',', $filterByFileType);
        $filterByFileType = array();
        foreach($items as $k => $v){
            if(trim($v)==='') continue;
            $filterByFileType[] = strtolower(trim($v));
        }

        $filterByFileExt = $_GET['ext'];
        $items = explode(',', $filterByFileExt);
        $filterByFileExt = array();
        foreach($items as $k => $v){
            if(trim($v)==='') continue;
            $filterByFileExt[] = strtolower(trim($v));
        }
        foreach (new RecursiveIteratorIterator($i) as $filename=>$cur) {
            if($x>=$limit) break; // 10 per request
            if(is_dir($filename)) {
                $stats['totalDirectories']++;
                continue; // skip dirs
            }
            $mime = mime_content_type($filename);
            $fileType = self::getFileType($mime);
            if(count($filterByFileType)!==0 && !in_array(strtolower($fileType), $filterByFileType)) continue;
            if(count($filterByFileExt)!==0 && !in_array(strtolower($cur->getExtension()), $filterByFileExt)) continue;
            $x++;
            $size = $cur->getSize();
            $stats['totalBytes'] += $size;
            $stats['totalFiles']++;
            $guid = self::file_url($filename);
            $re = '/-\d+x\d+(?=\.+)/';
            $regexResult = preg_replace($re, '', $guid, 1);
            $originGuid = $regexResult;
            if(isset($stats['attachmentsChecked'][$originGuid])){
                $attachmentId = $stats['attachmentsChecked'][$originGuid];
            }else{
                $stats['attachmentsChecked'][$originGuid] = 0;
                $attachmentId = 0;
                $attachment = $wpdb->get_row($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = '%s'", $originGuid));
                if($attachment) {
                    $attachmentId = $attachment->ID;
                    $stats['attachmentsChecked'][$originGuid] = $attachmentId;
                }
            }
            $file = array(
                'used' => false,
                'usedBy' => array(),
                'mime' => $mime,
                'type' => $fileType,
                'attachmentId' => absint($attachmentId),
                'basename'=>$cur->getBasename(),
                'bytes'=>$size,
                'size'=>self::formatSizeUnits($size),
                'ext'=>$cur->getExtension(),
                'mtime'=>date('Y/m/d H:i:m', $cur->getMTime()),
                //'perms'=>$cur->getPerms(),
                'changed'=>date('Y/m/d H:i:m', $cur->getCTime()),
                'location'=>$filename,
                // e.g: `http://localhost/dev/wp-content/uploads/2022/11/example.jpg`
                'guid' => $guid, 
                // e.g: `2022/11/mobile_wallpaper.jpg`
                'attachedFile'=>str_replace(trailingslashit($uploadfolder['basedir']), '', $filename) 
            );
            // "usedBy": [],
            // "attachmentId": 0,
            // "basename": "Exam+ple.jpg",
            // "size": 103543,
            // "ext": "jpg",
            // "mtime": "2022\/11\/12 14:38:11",
            // "perms": 33188,
            // "changed": "2022\/11\/12 14:38:11",
            // "location": "\/home\/f4d\/domains\/f4d.nl\/public_html\/media-cleaner\/wp-content\/uploads\/2022\/11\/Exam+ple.jpg",
            // "guid": "https:\/\/f4d.nl\/media-cleaner\/wp-content\/uploads\/2022\/11\/Exam+ple.jpg",
            // "attachedFile": "2022\/11\/Exam+ple.jpg"
            $file = self::used($file);
            $stats['files'][$fileType][] = $file;
        }
        $stats['totalBytes'] = self::formatSizeUnits($stats['totalBytes']);
        $html = '<table class="smc-file-list">';
            $html .= '<thead>';
                $html .= '<tr>';
                $html .= '<th>Status</th>';
                $html .= '<th>Media type</th>';
                $html .= '<th>Thumbnail</th>';
                $html .= '<th>Filename</th>';
                $html .= '<th>Filesize</th>';
                $html .= '<th>Extension</th>';
                $html .= '<th>Last modified</th>';
                //$html .= '<th>Permissions</th>';
                $html .= '<th>Used by</th>';
                $html .= '<th>Location</th>';
                $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
                foreach($stats['files'] as $ok => $ov){
                    foreach($stats['files'][$ok] as $k => $v){
                        if($v['used']===true) {
                            $stats['totalUsedBytes'] = $stats['totalUsedBytes']+$v['bytes'];
                            //continue;
                        }
                        $stats['totalUnusedBytes'] = $stats['totalUnusedBytes']+$v['bytes'];
                        $html .= '<tr data-fileType="'.$ok.'">';
                            if($v['used']===true){
                                $html .= '<td><span class="used">Used</span></td>';
                            }else{
                                $html .= '<td><span class="not-used">Not used</span></td>';
                            }
                            $html .= '<td>'.$v['mime'].'</td>';
                            if($ok==='image'){
                                //if(@exif_imagetype($v['location'])!==false){
                                //if(@is_array(getimagesize($v['location']))){
                                $html .= '<td><img src="'.$v['guid'].'" style="height:30px;"/></td>';
                            } else {
                                $html .= '<td>&nbsp;</td>';
                            }
                            $html .= '<td>'.$v['basename'].'</td>';
                            $html .= '<td>'.$v['size'].'</td>';
                            $html .= '<td>'.$v['ext'].'</td>';
                            $html .= '<td>'.$v['mtime'].'</td>';
                            //$html .= '<td>'.$v['perms'].'</td>';
                            $html .= '<td>';
                            foreach($v['usedBy'] as $id => $pv){
                                $html .= '#'.$id.' - '.$pv['post_title'].' ['.$pv['post_type'].'] - ('.$pv['post_status'].')<br />';
                            }
                            $html .= '</td>';
                            $html .= '<td>'.$v['attachedFile'].'</td>';
                        $html .= '</tr>';
                    }
                }
            $html .= '</tbody>';
        $html .= '</table>';

        echo '<form method="get" class="filters">';
        echo '<span class="field-label">Filter by media type:</span>';
        echo '<label'.(in_array('image', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="image" '.(in_array('image', $filterByFileType) ? 'checked="checked"' : '').' data-type="image" />Image</label>';
        echo '<label'.(in_array('video', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="video" '.(in_array('video', $filterByFileType) ? 'checked="checked"' : '').' data-type="video" />Video</label>';
        echo '<label'.(in_array('audio', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="audio" '.(in_array('audio', $filterByFileType) ? 'checked="checked"' : '').' data-type="audio" />Audio</label>';
        echo '<label'.(in_array('text', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="text" '.(in_array('text', $filterByFileType) ? 'checked="checked"' : '').' data-type="text" />Text</label>';
        echo '<label'.(in_array('application', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="application" '.(in_array('application', $filterByFileType) ? 'checked="checked"' : '').' data-type="application" />Application</label>';
        echo '<label'.(in_array('font', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="font" '.(in_array('font', $filterByFileType) ? 'checked="checked"' : '').' data-type="font" />Font</label>';
        echo '<label'.(in_array('message', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="message" '.(in_array('message', $filterByFileType) ? 'checked="checked"' : '').' data-type="message" />Message</label>';
        echo '<label'.(in_array('model', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="model" '.(in_array('model', $filterByFileType) ? 'checked="checked"' : '').' data-type="model" />Model</label>';
        echo '<label'.(in_array('multipart', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="multipart" '.(in_array('multipart', $filterByFileType) ? 'checked="checked"' : '').' data-type="multipart" />Multipart</label>';
        echo '<label'.(in_array('other', $filterByFileType) ? ' class="smc-active"' : '').'><input type="checkbox" name="type[]" value="other" '.(in_array('other', $filterByFileType) ? 'checked="checked"' : '').' data-type="other" />Other</label>';
        echo '<br />';
        //echo 'Filter by media type (leave blank to display all media types): 
        // <input style="width:500px;" type="text" name="type" value="'.$_GET['type'].'" /><br />';
        echo '<span class="field-label">Filter by file extension:</span><input style="width:500px;" type="text" name="ext" value="'.$_GET['ext'].'" placeholder="e.g: jpg,jpeg,png,gif etc." /><br />';
        echo '<span class="field-label"></span><i>Seperate each extension with a comma, leave blank to display all file extensions. Visit <a href="https://www.iana.org/assignments/media-types">https://www.iana.org/assignments/media-types</a> to see a full list of media types and file extensions.</i>';
        echo '<input type="hidden" name="page" value="super_media_cleaner_scan" />';
        echo '<br /><span class="field-label"></span><button type="submit" class="smc-filter">Apply filter</button><button class="smc-start-scan">Start scan</button><br />';
        //echo '<div class="actions">';
        //echo '<p>Click the button below to start the scan, files will not be deleted. It will only try to find unused files.</p>';
        //echo '</div>';
        echo '</form>';
        
        echo '<div class="stats">';
            echo 'Total directories: '.$stats['totalDirectories'].'<br />';
            echo 'Total files: '.$stats['totalFiles'].'<br />';
            echo 'Size (used files): '.self::formatSizeUnits($stats['totalUsedBytes']).'<br />';
            echo 'Size (unused files): '.self::formatSizeUnits($stats['totalUnusedBytes']).'<br />';
            echo 'Size (all files): '.$stats['totalBytes'].' bytes<br />';
        echo '</div>';

        echo '<div class="crawler">';
            echo 'Crawler results:';
            if(!is_array($crawler['images'])){
                echo ' no results...';
            }else{
                $chtml = '<table class="smc-file-list">';
                    $chtml .= '<thead>';
                        $chtml .= '<tr>';
                            $chtml .= '<th>Image URL</th>';
                            $chtml .= '<th>Name</th>';
                            $chtml .= '<th>Extension</th>';
                            $chtml .= '<th>Page URL</th>';
                            $chtml .= '<th>Source</th>';
                        $chtml .= '</tr>';
                    $chtml .= '</thead>';
                    $chtml .= '<tbody>';
                    foreach($crawler['images'] as $k => $v){
                        $chtml .= '<tr>';
                            $chtml .= '<td>'.$v['url'].'</td>';
                            $chtml .= '<td>'.$v['name'].'</td>';
                            $chtml .= '<td>'.$v['ext'].'</td>';
                            $chtml .= '<td>'.$v['origin'].'</td>';
                            $chtml .= '<td>'.esc_html($v['source']).'</td>';
                        $chtml .= '</tr>';
                    }
                    $chtml .= '</tbody>';
                $chtml .= '</table>';
                echo $chtml;
            }
        echo '</div>';

        echo $html;
        ?>
        <script>
            jQuery(document).ready(function($){
                debugger;
                var $table = $('table.smc-file-list');
                $table.floatThead({
                    scrollContainer: function($table){
                        debugger;
                        return $table.parentNode; //closest('div');
                    }
                });
            });
        </script>

        <div class="log">
            <h3>Log:</h3>
            <p class="smc-scan-log"></p>
        </div>
        <?php
        //self::scan();
    }

        // tmp    if(!is_dir($path) ) {
        // tmp        $info = pathinfo($path);
        // tmp        $size = filesize($path);
        // tmp        $known = false;
        // tmp        if( array_key_exists( $info['extension'], super_media_cleaner()->extensions ) ) {
        // tmp            $known = true;
        // tmp        }else{
        // tmp            if (!in_array($info['extension'], $stats['unknown_file_extensions'])){
        // tmp                $stats['unknown_file_extensions'][] = $info['extension'];
        // tmp                $stats['unknown_files'] = $stats['unknown_files']+1;
        // tmp            }
        // tmp        }

        // tmp        $found = false;
        // tmp        $found_in = array();
        // tmp        // check if file was found in a post gallery
        // tmp        $post_id = self::search_in_galleries( $path, $delete_transient );
        // tmp        if($post_id!=0){
        // tmp            $found = true;
        // tmp            $found_in[] = 'galleries';
        // tmp        }

        // tmp        // check in theme mods
        // tmp        $found_in_theme_mods = self::search_in_theme_mods( $path, $delete_transient );
        // tmp        if($found_in_theme_mods){
        // tmp            $found = true;
        // tmp            $found_in[] = 'theme_mods';
        // tmp        }

        // tmp        // check if url was found in post content

        // tmp        // check if file is attached to a post
        // tmp        self::search_in_attachments();


        // tmp        /*
        // tmp        // check if file was found in post content
        // tmp        search in post types:
        // tmp        - nav_menu_item
        // tmp        - attachment


        // tmp        $post_id = self::search_in_post($path);
        // tmp        if($post_id!=0){
        // tmp            $found = true;
        // tmp        }


        // tmp        $posts = $wpdb->get_col( $wpdb->prepare( "select p.id from $wpdb->posts p
        // tmp            where p.post_status != 'inherit'
        // tmp            and p.post_status != 'trash'
        // tmp            and p.post_type != 'attachment'
        // tmp            and p.post_type != 'shop_order'
        // tmp            and p.post_type != 'shop_order_refund'
        // tmp            and p.post_type != 'nav_menu_item'
        // tmp            and p.post_type != 'revision'
        // tmp            and p.post_type != 'auto-draft'
        // tmp            and p.post_type != 'wphb_minify_group'
        // tmp            and p.post_type != 'customize_changeset'
        // tmp            and p.post_type != 'oembed_cache'
        // tmp            and p.post_type not like '%acf-%'
        // tmp            and p.post_type not like '%edd_%'
        // tmp            limit %d, %d", $limit, $limitsize
        // tmp            )
        // tmp        );
        // tmp        */

        // tmp        //error_log('1 - '.$value);
        // tmp        //error_log('x - '.self::json_encode_unicode($value));
        // tmp        //error_log('y - '.self::json_encode_unicode(substr($value, 0, 35)));
        // tmp        //error_log('2 - '.json_encode($value, 0, 35));
        // tmp        //error_log('3 - '.substr($value, 0, 35));
        // tmp        //error_log('4 - '.json_encode(substr($value, 0, 35)));
        // tmp        
        // tmp        // @important - do not use substr() before calling json_encode()
        // tmp        $structure['files'][] = array(
        // tmp            'file' => $value,
        // tmp            'found' => $found,
        // tmp            'found_in' => $found_in,
        // tmp            'post_id' => $post_id,
        // tmp            'post_edit_url' => get_edit_post_link($post_id),
        // tmp            'path' => $path,
        // tmp            'url' => self::file_url($path, $uploadfolder),
        // tmp            'extension' => $info['extension'],
        // tmp            'known' => $known,
        // tmp            'size' => $size 
        // tmp        );
        // tmp        $total_size = $total_size + $size;
        // tmp        if($found){
        // tmp            $stats['total_size_saved'] = $stats['total_size_saved'] + $size;
        // tmp        }
        // tmp        $stats['total_size'] = $stats['total_size'] + $size;
        // tmp        $stats['files_scanned'] = $stats['files_scanned'] + 1;
        // tmp        $files_scanned++;
        // tmp    }else if($value != '.' && $value != '..') {
        // tmp        self::scan_directory($path, $structure, $stats);
        // tmp        $structure['directories'][] = array(
        // tmp            'file' => $value,
        // tmp            'path' => $path
        // tmp        );
        // tmp        $stats['directories_scanned'] = $stats['directories_scanned'] + 1;
        // tmp        $folders_scanned++;
        // tmp    }
        // tmp}
        // tmpexit;

        // tmp $start = $_POST['start'];
        // tmp if( $start=='true' ) {
        // tmp     $uploadfolder = wp_upload_dir();
        // tmp     $dir = $uploadfolder['basedir'];
        // tmp     $stats = array(
        // tmp         'status' => 'analysing',  // analysing, scanning, cleaning
        // tmp         'log' => '',
        // tmp         'type' => 'notice',
        // tmp         'query_offset' => 0,
        // tmp         'urls_found' => 0,
        // tmp         'files_scanned' => 0,
        // tmp         'directories_scanned' => 0,
        // tmp         'unknown_files' => 0,
        // tmp         'unknown_file_extensions' => array(),
        // tmp         'total_size' => 0,
        // tmp         'total_size_saved' => 0,
        // tmp         'total_files' => self::getFileCount($dir),
        // tmp     );
        // tmp     set_transient( 'super_mc_stats', $stats, 60 * 60 * 2 );
        // tmp }else{
        // tmp     $dir = sanitize_text_field($_POST['dir']);
        // tmp     if( $dir=='' ) {
        // tmp         $uploadfolder = wp_upload_dir();
        // tmp         $dir = $uploadfolder['basedir'];
        // tmp         $stats['total_files'] = self::getFileCount($dir);
        // tmp     }
        // tmp     $stats = get_transient('super_mc_stats');
        // tmp }


        // tmp $result = self::scan_directory($dir, array(), $stats);
        // tmp $json = json_encode($result);
        // tmp echo $json;

        /*
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
        */
    //}
    
}
endif;