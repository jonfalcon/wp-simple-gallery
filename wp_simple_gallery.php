<?php
/**
 * Plugin Name: WP Simple Gallery
 * Description: A simple wordpress gallery plugins that enables you create galleries, sliders and/or carousels. Simply create the gallery and use the shortcode <code>[wpsg id="gallery_id_here" layout="simple|slider|carousel"]</code>.
 * Author: Jon Falcon
 * Version: 1.1
 */

class WP_Simple_Gallery {
    static $self          = null;
    private $post_type    = "wp-simple-gallery";
    private $slug         = "wp_simple_gallery";
    private $singular     = "WP Simple Gallery";
    private $plural       = "Galleries";
    private $description  = "Create and manage galleries.";
    private $nonce        = "simple-gallery";
    private $nonce_action = "save-simple-gallery";
    
    private function __clone(){ trigger_error( "Cloning is not allowed", E_USER_ERROR ); }
    private function __wakeup(){ trigger_error( "Unserialization is not allowed", E_USER_ERROR ); }
    private function __construct() {}
    
    static function get_instance() {
        if(!isset(self::$self)) {
            self::$self = new self();
        }
        return self::$self;
    }
    
    public function install() {
        // hooks
        add_action('init', array($this, 'init'));
        add_action("add_meta_boxes", array($this, "register_metaboxes"));
        add_action("save_post", array($this, "save_metabox"));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('wpsg', array($this, 'shortcode'));
    }

    public function get_url() {
        return plugins_url('', __FILE__);
    }

    public function init() {
        if( !post_type_exists( $this->post_type ) ){
            register_post_type(
              $this->post_type,
              array(
                "label"     => __( $plural, $this->get_slug() ),
                "labels"    => array(
                    "name"          => _x( $this->plural, $this->post_type, $this->get_slug() ),
                    "singular_name" => __( $this->singular, $this->get_slug() ),
                    "add_new_item"  => sprintf( __( "Add New %s", $this->get_slug() ), $this->singular ),
                    "edit_item"     => sprintf( __( "Edit %s", $this->get_slug() ), $this->singular ),
                    "new_item"      => sprintf( __( "New %s", $this->get_slug() ), $this->singular ),
                    "view_item"     => sprintf( __( "View %s", $this->get_slug() ), $this->singular ),
                    "search_items"  => sprintf( __( "Search %s", $this->get_slug() ), $this->singular ),
                    "not_found"     => sprintf( __( "No %s found.", $this->get_slug() ), $this->singular ),
                    "not_found_in_trash" => sprintf( __( "No %s found in trash", $this->get_slug() ), $this->singular )
                ),
                "public"      => false,
                "show_ui"     => true,
                "has_archive" => false,
                "menu_icon"   => plugins_url('images/gallery.png', __FILE__),
                "supports"    => array( "title", "thumbnail", "editor" )
              )
            );
        }
    }

    public function save_metabox($post_id) {
        /* Bail if we're doing an auto save */  
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return; 
        /* if our nonce isn't there, or we can't verify it, bail */ 
        if( !isset( $_POST[$this->nonce_action] ) || !wp_verify_nonce( $_POST[$this->nonce_action], $this->nonce ) ) return; 
        /* if our current user can't edit this post, bail */  
        if( !current_user_can( 'edit_post' ) ) return; 

        $slides = array();
        foreach($_POST['slider-image'] as $index => $image) {
            $image_arr = @getimagesize($image);
            if($image_arr) {
                $slides[$index]['image']       = $image;
                $slides[$index]['title']       = trim($_POST['slider-title'][$index]);
                $slides[$index]['link']        = trim($_POST['slider-link'][$index]);
                $slides[$index]['description'] = trim($_POST['slider-description'][$index]);
            }
        }
        update_post_meta($post_id, "slides", $slides);
    }

    function register_metaboxes() {
        global $pagenow;
        if($pagenow == 'post.php' && isset($_GET['post'])) {
            add_meta_box("gallery-help-metabox", "Help and Tips", array($this, "gallery_help_metabox"), $this->get_post_type(), "side");
        }
        add_meta_box("gallery-slides-metabox", "Gallery Slides", array($this, "gallery_slides_metabox"), $this->get_post_type(), "normal", "high");
    }

    function gallery_help_metabox($post) {
        $slider = new WPS_Gallery($post->ID);
        ?>
            Insert any of the shortcodes in your post: <br />
            <ul>
                <?php
                    $shortcodes_list = apply_filters('wpsg_shortcodes_list', array(
                            array(
                                "[wpsg id='" . $slider->get_id() . "']",
                                " for display a simple gallery."
                            ),
                            array(
                                "[wpsg id='" . $slider->get_id() . "' layout='slider']",
                                " for display a slider."
                            ),
                            array(
                                "[wpsg id='" . $slider->get_id() . " layout='carousel']",
                                " for display a carousel."
                            ),
                            array(
                                "[wpsg id='" . $slider->get_id() . " layout='slider-with-thumbnails']",
                                " for display a slider with thumbnails."
                            )
                        ), $slider->get_id());
                    foreach($shortcodes_list as $sh):
                        list($shortcode, $description) = $sh;
                ?>
                    <li><p><input type="text" class="inline-input-text" value="<?php echo $shortcode; ?>" />
                        <br /><?php echo $description; ?></p></li>
                <?php endforeach; ?>
            </ul>
        <?php
    }

    function gallery_slides_metabox($post) {
        $post_id = isset( $_GET['post'] ) ? $_GET['post'] : null;
        $slider = new WPS_Gallery($post_id);
        $slides = $slider->get_slides();
        ?>
            <style type="text/css">
                #postdivrich {
                    display: none;
                }
                #gallery-slides-metabox h3.hndle {
                    background: transparent;
                }
                #gallery-slides-metabox .handlediv {
                    display: none;
                }
                #gallery-slides-metabox .normal-title {
                    position: relative;
                    cursor: default;
                }
                #gallery-slides-metabox .normal-title .removeslide {
                    background-color: #f30;
                    display: block;
                    position: absolute;
                    top: 0;
                    right: 10px;
                    font-style: italic;
                    font-size: 12px;
                    padding: 5px 10px;
                    color: #fff;
                    text-shadow: none;
                    z-index: 99;
                    text-decoration: none;
                    -webkit-border-radius: 10px;
                    -moz-border-radius: 10px;
                    border-radius: 10px;
                    -webkit-background-clip: padding-box;
                    background-clip: padding-box;
                }
                #gallery-slides-metabox .normal-title .removeslide:hover {
                    background-color: #f60;
                }
                .inline-input-text {
                    background-color: transparent !important;
                    outline: 0 !important;
                    width: 200px;
                    padding-left: 10px;
                }

                #gallery-slides-metabox .drop-zone {
                    border: 3px dashed #ddd;
                    height: 250px;
                }
            </style>
            <script type="text/javascript">
            // <![CDATA[
                (function($){
                    $(document).ready(function(){
                        /* draggable items */
                        $( '.draggable-items' ).sortable( {
                            revert      : true,
                            axis        : 'y',
                            placeholder : 'drop-zone'
                        } );

                        /* Add a new image div */
                        $( '#add-more-slide' ).click( function() {
                            $( '.cloneme' ).clone()
                                .removeClass( 'cloneme hidden' )
                                .appendTo( '.draggable-items' );
                            remove_slide();
                            update_slide_image();
                        } );
                        remove_slide();
                        update_slide_image();

                        /* remove slide */
                        function remove_slide(){
                            jQuery( '.removeslide' ).click( function(e) {
                                jQuery( this ).parents('li').remove();
                                e.preventDefault();
                            } );
                        }

                        /* upload file */
                        function update_slide_image() {
                            var _send_to_editor = window.send_to_editor;
                            $( '.upload-slide' ).click( function(e) {
                                var $parent = $(this).parents('.form-table');
                                window.send_to_editor = function ( html ) {
                                    var img_url     = jQuery( 'img', html ).attr( 'src' );
                                    var img_title   = jQuery( 'img', html ).attr( 'title' );
                                    
                                    $parent.find( 'input[name^=slider-image]' ).val( img_url );
                                    $parent.find( 'input[name^=slider-title]' ).val( img_title );
                                    window.send_to_editor = _send_to_editor;
                                    tb_remove();
                                }
                                tb_show( 'slider', jQuery( this ).attr( 'href' )  );
                                e.preventDefault();
                            });
                        }
                    });
                })(jQuery);
            // ]]>
            </script>
            <?php if($slider->exists()): ?>
                
            <?php endif; ?>
            <ul class="draggable-items">
                <?php
                    for($i = 0; $i <= count($slides); $i++):
                        if(isset($slides[$i])) {
                            $slide  = $slides[$i];
                            $hidden = "";
                        } else {
                            $slide = array(
                                    "image"       => "",
                                    "title"       => "",
                                    "link"        => "",
                                    "description" => ""
                                );
                            $hidden = "cloneme hidden";
                        }
                ?>
                    <li class="<?php echo $hidden; ?>">
                        <h3 class="normal-title">
                            <span class="slidetitle">&nbsp;</span>
                            <a href="#" class="removeslide">&minus; Remove this slide</a>
                        </h3>
                        <table class="form-table">
                            <tbody>
                                <tr class="form-field">
                                    <th scope="row" valign="top">Slide Image URL</th>
                                    <td>
                                        <input type="text" name="slider-image[]" style="width: 65%" value="<?php echo $slide['image']; ?>" />
                                        <a href="<?php echo admin_url( 'media-upload.php?post_id=' . $post->ID . '&type=image&TB_iframe=1&width=640&height=448', __FILE__ ); ?>" class="upload-slide button-secondary">Upload Image</a>
                                        <p class="description">You can insert the image url on the given input box or click the upload button to upload the file or choose a file from the media library.</p>
                                    </td>
                                </tr>
                                <tr class="form-field">
                                    <th scope="row" valign="top">Slide Title</th>
                                    <td>
                                        <input type="text" name="slider-title[]" value="<?php echo $slide['title']; ?>" />
                                    </td>
                                </tr>
                                <tr class="form-field">
                                    <th scope="row" valign="top">Slide Link</th>
                                    <td>
                                        <input type="text" name="slider-link[]" value="<?php echo $slide['link']; ?>" />
                                        <p class="description">You can leave this empty if you want to hide this.</p>
                                    </td>
                                </tr>
                                <tr class="form-field">
                                    <th scope="row" valign="top">Slide Description</th>
                                    <td>
                                        <textarea style="width: 95%" name="slider-description[]"><?php echo $slide['description']; ?></textarea>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </li>
                <?php endfor; ?>
            </ul>
            <p><a class="button-primary" id="add-more-slide">+ Add more slide</a></p>
        <?php
        wp_nonce_field($this->nonce, $this->nonce_action);
    }

    public function enqueue_scripts() {
        // flexslider
        wp_enqueue_script('jquery-flexslider', plugins_url('js/jquery.flexslider-min.js', __FILE__), array('jquery'));
        wp_enqueue_script('jquery-mousewheel', plugins_url('js/jquery.mousewheel.js', __FILE__), array('jquery'));
        wp_enqueue_style('jquery-flexslider-style', plugins_url('css/flexslider.css', __FILE__));

        // colorbox
        wp_enqueue_script('jquery-colorbox', plugins_url('js/jquery.colorbox-min.js', __FILE__), array('jquery'));
        wp_enqueue_style('jquery-colorbox-style', plugins_url('css/colorbox.css', __FILE__));

        // simple gallery
        wp_enqueue_style('wpsg-simple-gallery', plugins_url('css/wpsg.css', __FILE__));
    }

    public function shortcode($atts) {
        extract(shortcode_atts(array(
                'id'       => 0,
                'layout'   => 'simple',
                'colorbox' => 1,
                'width'    => ''
            ), $atts));
        $gallery = new WPS_Gallery($id);
        $html    = '';

        if(!$gallery->has_slides()) {
            $html = '<p>No slides added yet.</p>';
        }

        if($layout == 'slider') {
            $html = $this->display_slider($id, $gallery->get_slides(), $colorbox, $width);
        } else if($layout == 'carousel') {
            $html = $this->display_carousel($id, $gallery->get_slides(), $colorbox);
        } else if($layout == 'slider-with-thumbnails') {
            $html = $this->display_slider_with_thumbnails($id, $gallery->get_slides(), $colorbox);
        } else {
            $html = $this->display_gallery($id, $gallery->get_slides(), $colorbox);
        }

        return apply_filters('wpsg_shortcode_html', $html, $gallery, $atts);
    }

    public function display_slider($id, $slides, $colorbox = true, $width = 'auto') {
        ob_start();
        if(!count($slides)) return false;
        $gid = "wpsg_slider_" . $id;
        ?>
            <div id="<?php echo $gid; ?>" class="flex-slider" style="width: <?php echo $width; ?>">
                <div class="flexslider <?php echo $gid; ?>">
                  <ul class="slides">
                    <?php foreach($slides as $slide): ?>
                        <li>
                            <?php if(trim($slide['link'])): ?>
                                <a href="<?php echo $slide['link']; ?>">
                            <?php else: ?>
                                <a href="<?php echo $slide['image']; ?>" class="display-on-colorbox">
                            <?php endif; ?>
                                <img src="<?php echo $slide['image']; ?>" alt="<?php echo $slide['title']; ?>">
                            </a>
                            <?php if(trim($slide['description'])): ?>
                                <p class="flex-caption"><?php echo $slide['description']; ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
              <script type="text/javascript">
                (function($){
                    $(document).ready(function() {
                        $('.<?php echo $gid; ?>').flexslider({
                            animation: 'slide',
                            slideshow: false
                        });
                        <?php if($colorbox): ?>
                            $('.<?php echo $gid; ?> .display-on-colorbox').colorbox();
                        <?php endif; ?>
                    });
                })(jQuery);
              </script>
        <?php
        return ob_get_clean();
    }

    public function display_carousel($id, $slides, $colorbox = true) {
        ob_start();
        if(!count($slides)) return false;
        $gid = "wpsg_carousel_" . $id;
        ?>
            <div id="<?php echo $gid; ?>" class="flex-slider">
                <div class="flexslider carousel <?php echo $gid; ?>">
                  <ul class="slides">
                    <?php foreach($slides as $slide): ?>
                        <li>
                            <?php if(trim($slide['link'])): ?>
                                <a href="<?php echo $slide['link']; ?>">
                            <?php else: ?>
                                <a href="<?php echo $slide['image']; ?>" class="display-on-colorbox">
                            <?php endif; ?>
                                <img src="<?php echo plugins_url('images/timthumb.php', __FILE__); ?>?src=<?php echo $slide['image']; ?>&w=180&h=100" alt="<?php echo $slide['title']; ?>">
                            </a>
                        </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
              <script type="text/javascript">
                (function($){
                    $(document).ready(function() {
                        $('.<?php echo $gid; ?>').flexslider({
                            animation       : "slide",
                            animationLoop   : false,
                            itemWidth       : 210,
                            itemMargin      : 5
                        });
                        <?php if($colorbox): ?>
                            $('.<?php echo $gid; ?> .display-on-colorbox').colorbox();
                        <?php endif; ?>
                    });
                })(jQuery);
              </script>
        <?php
        return ob_get_clean();
    }

    public function display_slider_with_thumbnails($id, $slides, $colorbox = true) {
        ob_start();
        if(!count($slides)) return false;
        $gid = "wpsg_slider_main_" . $id;
        $tid = "wpsg_slider_thumbs_" . $id;
        ?>
            <div class="flex-slider">
                <div id="<?php echo $gid; ?>" class="flexslider slider-with-thumbnails <?php echo $gid; ?>">
                  <ul class="slides">
                    <?php foreach($slides as $slide): ?>
                        <li>
                            <?php if(trim($slide['link'])): ?>
                                <a href="<?php echo $slide['link']; ?>">
                            <?php else: ?>
                                <a href="<?php echo $slide['image']; ?>" class="display-on-colorbox">
                            <?php endif; ?>
                                <img src="<?php echo $slide['image']; ?>" alt="<?php echo $slide['title']; ?>">
                            </a>
                            <?php if(trim($slide['description'])): ?>
                                <p class="flex-caption"><?php echo $slide['description']; ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <!-- thumbnail nav -->
                <div id="<?php echo $tid; ?>" class="flexslider carousel <?php echo $tid; ?>">
                  <ul class="slides">
                    <?php foreach($slides as $slide): ?>
                        <li>
                            <img src="<?php echo plugins_url('images/timthumb.php', __FILE__); ?>?src=<?php echo $slide['image']; ?>&w=180&h=100" alt="<?php echo $slide['title']; ?>">
                        </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
              <script type="text/javascript">
                (function($){
                    $(document).ready(function() {
                        // The slider being synced must be initialized first
                        $('#<?php echo $tid; ?>').flexslider({
                            animation       : "slide",
                            controlNav      : false,
                            animationLoop   : false,
                            slideshow       : false,
                            itemWidth       : 210,
                            itemMargin      : 5,
                            asNavFor        : '#<?php echo $gid; ?>'
                        });
                      
                        $('#<?php echo $gid; ?>').flexslider({
                            animation       : "slide",
                            controlNav      : false,
                            animationLoop   : false,
                            slideshow       : false,
                            sync            : "#<?php echo $tid; ?>"
                        });

                        <?php if($colorbox): ?>
                            $('.<?php echo $gid; ?> .display-on-colorbox').colorbox();
                        <?php endif; ?>
                    });
                })(jQuery);
              </script>
        <?php
        return ob_get_clean();
    }

    public function display_gallery($id, $slides, $colorbox = true) {
        ob_start();
        if(!count($slides)) return false;
        $gid = "wpsg_simple_gallery_" . $id;
        ?>
            <div id="<?php echo $gid; ?>" class="wpsg-gallery carousel <?php echo $gid; ?>">
              <ul class="slides">
                <?php foreach($slides as $slide): ?>
                    <li>
                        <?php if(trim($slide['link'])): ?>
                            <a href="<?php echo $slide['link']; ?>">
                        <?php else: ?>
                            <a href="<?php echo $slide['image']; ?>" class="display-on-colorbox">
                        <?php endif; ?>
                            <img src="<?php echo plugins_url('images/timthumb.php', __FILE__); ?>?src=<?php echo $slide['image']; ?>&w=180&h=100" alt="<?php echo $slide['title']; ?>">
                        </a>
                    </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <script type="text/javascript">
                (function($){
                    $(document).ready(function() {
                        <?php if($colorbox): ?>
                            $('#<?php echo $gid; ?> .display-on-colorbox').colorbox();
                        <?php endif; ?>
                    });
                })(jQuery);
            </script>
        <?php
        return ob_get_clean();
    }

    public function get_slug() {
        return $this->slug;
    }
    
    public function get_post_type(){
        return $this->post_type;
    }

    public function get_description(){
        return $this->description;
    }

    public function dropdown($selected = 0) {
        query_posts(array(
                'post_type'      => $this->get_post_type(),
                'posts_per_page' => -1,
                'post_status'    => 'publish'
            ));
        $options = "";
        if(have_posts()) {
            while(have_posts()) {
                the_post();
                $options .= '<option value="' . get_the_ID() . '"';
                if($selected == get_the_ID()) {
                    $options .= ' selected';
                }
                $options .= '>' . get_the_title() . '</option>';
            }
        } else {
            $options = '<option>No slider created yet.</option>';
        }
        return $options;
    }
}


/**
 * Simple Slider Class
 * 
 */
class WPS_Gallery {
    private $ID = null;
    private $slides = array();
    private $options = array();

    public function __construct($data = null) {
        if(!is_null($data)) {
            $this->populate($data);
        }
    }

    public function populate($data) {
        global $wpdb;

        $post = get_post($data);
        if(WP_Simple_Gallery::get_instance()->get_post_type() !== $post->post_type) return;

        if($post) {
            $this->ID = $data;
            $this->title = $post->post_title;
        }

        if($this->exists()) {
            $this->slides = get_post_meta($this->ID, "slides", true);

            foreach($wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE {$wpdb->postmeta}.post_id={$this->ID} AND {$wpdb->postmeta}.meta_key <> 'slides'") as $meta) {
                $this->{$meta->meta_key} = $meta->meta_value;
            }
        }
    }

    public function exists() {
        return !is_null($this->ID);
    }

    public function get_id() {
        return $this->ID;
    }

    public function get_slides() {
        return $this->slides;
    }

    public function has_slides() {
        return (bool) count($this->slides);
    }

    public function get_options() {
        return $this->options;
    }

    public function get_option($option, $default = null) {
        $value = $this->$option;
        if(is_null($value) && !is_null($default)) {
            return $default;
        }
        return $value;
    }

    public function __set($option, $value) {
        if(isset($this->$option)) return;
        $this->options[$option] = $value;
    }

    public function __get($option) {
        if(isset($this->options[$option])) {
            return $this->options[$option];
        }
        return null;
    }
}

function wpsg() {
    return WP_Simple_Gallery::get_instance();
}

function wpsg_flush_rewrite_rules() {
    wpsg()->init();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpsg_flush_rewrite_rules' );
wpsg()->install();