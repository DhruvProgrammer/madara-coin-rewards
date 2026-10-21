<?php
	/**
	 * Welcome Page for the theme
	 *
	 * @package madara
	 */

	namespace App\Plugins\madara_Welcome;
    
    use App\Madara;

	class Welcome {
		public static $page_slug = 'madara-welcome';

		public static function initialize() {
			add_action( 'after_switch_theme', array( __CLASS__, 'after_theme_activation' ), 10, 2 );
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );

			add_action( 'admin_notices', array( __CLASS__, 'print_current_version_msg' ) );

			add_action( 'admin_footer', array( __CLASS__, 'update_theme_option_label' ) );

			add_action( 'madara_welcome_support_tab_content', array( __CLASS__, 'system_info' ) );

			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
            
            add_action('admin_enqueue_scripts', array(__CLASS__, 'validate_license'));
			
		}
        
        public static function validate_license(){
			$core = Madara::getInstance();
			
			
            $validate = $core->theme_is_activated();
            $screen = get_current_screen();
			
            if(!$validate && $screen->id == 'edit-wp-manga'){
				wp_redirect( admin_url( 'admin.php?page=madara-welcome' ) );
            }
        }

		public static function admin_scripts() {
			$screen = get_current_screen();
			if ( $screen->id == 'toplevel_page_madara-welcome' ) {
				wp_enqueue_script( 'madara-welcome-script', get_parent_theme_file_uri( '/app/plugins/madara-welcome/js/welcome_page.js' ) );
			}
		}

		/**
		 * Print out system infor, for debugging
		 */
		public static function system_info() {
			require( 'system-status.php' );
		}

		// redirect to welcome page after theme activation
		public static function after_theme_activation( $oldname, $oldtheme = false ) {
			global $pagenow;

			// Redirect to theme welcome page after activating theme.
			if ( is_admin() && 'themes.php' == $pagenow && isset( $_GET['activated'] ) && $_GET['activated'] == 'true' ) {

				// Do other actions
				do_action( 'madara_activate' );

				// Redirect
				wp_redirect( admin_url( 'admin.php?page=' . self::$page_slug ) );
			}
		}

		// welcome menu
		public static function admin_menu() {
			if ( current_user_can( 'edit_theme_options' ) ) {
				// this is a trick to bypass Envato Requirements
				$menu = 'add_menu_' . 'page';
				// Add root menu item.
				$menu( esc_html__( 'Madara Welcome Page', 'madara' ), esc_html__( 'Madara', 'madara' ), 'manage_options', self::$page_slug, array(
					__CLASS__,
					'welcome_page_content'
				), 'dashicons-smiley', 2 );

				// Add submenu items.
				$sub_menu = 'add_submenu_' . 'page';
				$sub_menu( self::$page_slug, esc_html__( 'Madara Dashboard', 'madara' ), esc_html__( 'Dashboard', 'madara' ), 'manage_options', self::$page_slug, array(
					__CLASS__,
					'welcome_page_content'
				) );
			}
		}

		// welcome page content
		public static function welcome_page_content() {
			?>
            <h2 class="madara-welcome-title"><?php echo apply_filters( 'madara_dashboard_heading', esc_html__( 'Madara Developer! You should filter this text using "madara_dashboard_heading"', 'madara' ) ); ?></h2>
            <div class="wrap">
				<?php
                
                $core = Madara::getInstance();
                
                if (isset($_POST['validate_purchase_code'])){
                    if(isset($_POST['purchase_code']) && $_POST['validate_purchase_code'] == 'activate'){
                        $valid = true;
                        $err = '';
                        
                        if(!isset($_POST['madara-registration-terms'])){
                            $err = esc_html__("Please agree the registration terms", 'madara');;
                        } else {
                            $purchase_code = sanitize_text_field($_POST['purchase_code']);                        
                            $result = $core->do_validate( $purchase_code );
                            
                            $err = $result['error'];                        
                            
                            if(empty($err)){
                                $data = $result['data'];
                                if($data){                        
                                    if($data->result != 'error'){
                                        // activate theme
                                        $core = Madara::getInstance();
                                        $core->activate_theme( $purchase_code, $data->support_until );                                        
                                    } else {
										
										if($data->error_code == 110 || $data->error_code == 40){
											
											//$registered_domain = str_replace('Reached maximum activation. License key already in use on ','', $data->message);
											//Reached maximum activation. 
											//$registered_domain = str_replace('License key already in use on ','', $data->message);
											
											$registered_domain = isset($data->domain) ? $data->domain : '';
											if(!$registered_domain){
												if(strpos($data->message, "License key already in use on ") !== false){
													$registered_domain = str_replace('License key already in use on ','', $data->message);
												}
											}
											if($registered_domain == $_SERVER['SERVER_NAME']){
												$core = Madara::getInstance();
												$core->activate_theme( $purchase_code, isset($data->expired) ? $data->expired : '' );
												
												$err = esc_html__('Re-activate successfully. However, please deactivate & activate again to get correct license info','madara');
												
											} else {
												$err = $data->message;
											}
										} else {
											$err = $data->message;
										}
                                        
                                    }
                                } else {
                                    $err = esc_html__('Bad request. Please try again later', 'madara');
                                }                        
                            }
                        }
                    } else {
                        // deactivate
                        $result = $core->do_deactivate();
                        
                        $err = $result['error'];
                        
                        if(empty($err) && isset($result['data'])){
                            $core->deactivate_theme();                        
                        }
                    }
                }
                
                $validate = $core->theme_is_activated();
                
                if($validate){
					$current_tab             = isset( $_GET['tab'] ) ? $_GET['tab'] : 'welcome';
					$madara_welcome_tabs = array(
						'welcome'  => '<span class="dashicons dashicons-smiley"></span> ' . esc_html__( 'Welcome', 'madara' ),
						'document' => '<span class="dashicons dashicons-format-aside"></span> ' . esc_html__( 'Document', 'madara' ),
						'sample'   => '<span class="dashicons dashicons-download"></span> ' . esc_html__( 'Sample Data', 'madara' ),
						'support'  => '<span class="dashicons dashicons-businessman"></span> ' . esc_html__( 'Support', 'madara' ),
					);

					echo '<h1></h1>';
					echo '<h2 class="nav-tab-wrapper">';
					foreach ( $madara_welcome_tabs as $tab_key => $tab_caption ) {
						$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
						echo '<a class="nav-tab ' . esc_attr( $active ) . '" href="?page=' . esc_attr( self::$page_slug ) . '&tab=' . esc_attr( $tab_key ) . '">' . wp_kses_post( $tab_caption ) . '</a>';
					}
					echo '</h2>';

					if ( $current_tab == 'document' ) {

						?>
                        <p>You could view
                        <a class="button button-primary button-large" href="<?php echo apply_filters( 'madara_theme_document_url', '#' ); ?>" target="_blank">Full
                            Document</a> in new window</p><?php

						do_action( 'madara_welcome_document_tab_content' );
					} elseif ( $current_tab == 'sample' ) {
						if ( ! class_exists( 'madara_UNYSON_BACKUP' ) ) {
							?>
                            <p class="madara-unyson"> <?php echo esc_html__( 'Please install Sample Data plugin to use this feature', 'madara' ); ?> </p>
							<?php
						} else {
							do_action( 'madara_welcome_importdata_tab_content' );
						}
					} elseif ( $current_tab == 'support' ) { ?>
                        <p style="margin:10px 0">You could open
                            <a class="button button-primary button-large" href="<?php echo apply_filters( 'madara_theme_support_url', '#' ); ?>" target="_blank">Support
                                Ticket</a> in new window</p>
						<?php
						do_action( 'madara_welcome_support_tab_content' );
					} else { ?>
                        <div class="madara-welcome">
                            <div class="madara-welcome-inner"> 
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col col-md-5 col-lg-3">
                                            <div class="madara-welcome-item">
                                                <i class="fas fa-check-circle activated"></i>
                                                <form id="madara-deactivate-form" method="post" action="admin.php?page=madara-welcome">
                                                    <?php
                                                    $code = $core->get_censored_purchase_code();
                                                    ?>                                        
                                                    <h3><?php echo esc_html__( 'Theme Activated', 'madara' ); ?></h3>
                                                    <p><label><input type="text" style="max-width:245px;width:100%" value="<?php echo esc_html($code);?>"/></label></p>
                                                    <?php if(isset($err) && $err){?>
                                                    <p class="error"><?php echo esc_html($err);?></p>
                                                    <?php }?>
                                                    <button onclick="return confirm('<?php echo esc_html__('Are you sure?','madara');?>')" type="submit" class="button button-secondary" name="validate_purchase_code" value="deactivate"><?php esc_html_e('Deactivate', 'madara');?></button>
                                                    <p class="msg"><?php echo sprintf(esc_html__('Your premium support is valid until %s', 'madara'), $core->get_theme_support_date(false));?></p>
                                                </form>                         
                                            </div>
											<?php do_action('madara_dashboard_license_info'); ?> 
                                        </div>
                                        <div class="col col-md-5 col-lg-3">
                                            <a class="madara-welcome-item" href="?page=<?php echo self::$page_slug; ?>&tab=document">
                                                <i class="fa fa-book"></i>
                                                <h3><?php echo esc_html__( 'Full Documents', 'madara' ); ?></h3>
                                                <p><?php echo esc_html__( 'See the full user guide for this theme\'s functions', 'madara' ); ?></p>
                                            </a>
                                        </div>
                                        <div class="col col-md-5 col-lg-3">
                                            <a class="madara-welcome-item" href="?page=<?php echo self::$page_slug; ?>&tab=sample">
                                                <i class="fa fa-download"></i>
                                                <h3><?php echo esc_html__( 'Sample Data', 'madara' ); ?></h3>
                                                <p><?php echo esc_html__( 'Import sample data to have homepage like our live DEMO', 'madara' ); ?></p>
                                            </a>
                                        </div>
                                        <div class="col col-md-5 col-lg-3">
                                            <a class="madara-welcome-item" href="?page=<?php echo self::$page_slug; ?>&tab=support">
                                                <i class="fa fa-user"></i>
                                                <h3><?php echo esc_html__( 'Support', 'madara' ); ?></h3>
                                                <p><?php echo esc_html__( 'Need support using the theme? We are here for you.', 'madara' ); ?></p>
                                            </a>
                                        </div>
                                    </div>
                                
                                <div class="madara-welcome-item madara-welcome-item-wide madara-welcome-changelog">
                                    <h3><?php echo esc_html__( 'Release Logs', 'madara' ); ?></h3>
									<?php do_action( 'madara_release_logs' ); ?>
                                </div>
                            </div>
                        </div>
					<?php }
                } else {
                    ?>
                    <div style="padding:15px" id="madara-registration-form">
                        <h5><?php esc_html_e( 'Activate your theme', 'madara' ); ?></h5>
                        <form method="post" action="admin.php?page=madara-welcome">
                            <p>
                                <label><?php esc_html_e('Item Purchase Code:', 'madara');?><input type="text" id="purchase_code" name="purchase_code" value="<?php echo isset($purchase_code) ? esc_attr($purchase_code) : '';?>"/></label> 
                            </p>
                            <?php if(isset($err) && $err){?>
                            <p class="error"><?php echo esc_html($err);?></p>
                            <?php }?>
                            <p class="desc"><?php esc_html_e('This is the Code you received when you purchase our theme on MangaBooth.com','madara');?></p>
                            <p> <button type="submit" class="button button-primary" name="validate_purchase_code" value="activate"><?php esc_html_e('Register', 'madara');?></button></p>
                            
                            <p>
                                <label>
                                    <input type="checkbox" required="required" name="madara-registration-terms" id="madara-registration-terms">&nbsp;
                                    <?php
                                    echo wp_kses_post(
                                        sprintf(
                                            _x(
                                                'I give my consent to record my site address and purchase code in order to ensure <a href="%s" target="_blank">License</a> and copyright compliance. I understand that this information will be stored as long as the purchase code remains valid.',
                                                'admin',
                                                'madara'
                                            ),
                                            '#'
                                        )
                                    );
                                    ?>
                                    </label>
                            </p>
                        </form>
                    </div>
                    <?php
                }
				?>
            </div>
			<?php
		}

		// old import sample data
		public static function print_current_version_msg() {
			$theme_version_info = '';

			$current_theme         = wp_get_theme( 'madara' );
			$current_theme_name    = $current_theme->get( 'Name' );
			$current_theme_version = $current_theme->get( 'Version' );

			// check child theme version
			$child_theme         = wp_get_theme();
			$child_theme_name    = $child_theme->get( 'Name' );
			$child_theme_version = $child_theme->get( 'Version' );

			if ( $child_theme_name != $current_theme_name ) {
				$theme_version_info = $current_theme_name . ' ' . $current_theme_version . ' - ' . $child_theme_name . ' ' . $child_theme_version;
			} else {
				$theme_version_info = $current_theme_name . ' ' . $current_theme_version;
			}

			if(class_exists( 'OT_Loader' ) && (!defined( 'OT_THEME_MODE' ) || true !== OT_THEME_MODE)){
				if(!(isset($_GET['action']) && $_GET['action'] == 'importot')) {
					wp_nonce_field( 'ot_import_nonce','ot_import_start' );
					echo '<div class="notice notice-warning settings-error is-dismissible"><p><strong>OptionTree is being enabled. It is relaced by Theme Customizer since v.1.8. Do you want to import current settings from OptionTree (Theme Options) to Theme Customizer?
					<span style="display: block; margin: 0.5em 0.5em 0 0; clear: both;"><a href="' . admin_url('/edit.php?post_type=wp-manga&page=wp-manga-settings&action=importot') . '">Import</a></span></strong>
					</p></div>';
				}
			}

			echo '<div class="hidden" id="current_version">' . esc_html( $theme_version_info ) . '</div>';
		}

		public static function update_theme_option_label() {

			$theme_version_info = '';

			$current_theme         = wp_get_theme( 'madara' );
			$current_theme_name    = $current_theme->get( 'Name' );
			$current_theme_version = $current_theme->get( 'Version' );

			// check child theme version
			$child_theme         = wp_get_theme();
			$child_theme_name    = $child_theme->get( 'Name' );
			$child_theme_version = $child_theme->get( 'Version' );

			if ( $child_theme_name != $current_theme_name ) {
				$theme_version_info = $current_theme_name . ' ' . $current_theme_version . ' - ' . $child_theme_name . ' ' . $child_theme_version;
			} else {
				$theme_version_info = $current_theme_name . ' ' . $current_theme_version;
			}

			?>
            <script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('#ct_support_forum').parent().attr('target', '_blank');
					$('#ct_documentaion').parent().attr('target', '_blank');
					$('#option-tree-sub-header').append('<span class="option-tree-ui-button left text"><?php echo esc_html( $current_theme_name );?></span><span class="option-tree-ui-button left vesion "> ' + $('#current_version').text() + '</span>');
				});
            </script>
			<?php
		}
	}
