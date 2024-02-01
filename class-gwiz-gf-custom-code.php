<?php
/**
 * @package gravityforms-custom-code
 * @copyright Copyright (c) 2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://github.com/gravitywiz/gravityforms-custom-code
 */
defined( 'ABSPATH' ) || die();

GFForms::include_feed_addon_framework();

class GWiz_GF_Custom_Code extends GFFeedAddOn {

	// TODO REMOVE
	public $default_settings = array();

	/**
	 * @var GWiz_GF_Custom_Code\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap The updater instance.
	 */
	public $updater;

	/**
	 * @var null|GWiz_GF_Custom_Code
	 */
	private static $instance = null;

	protected $_version        = GWIZ_GF_CUSTOM_CODE_VERSION;
	protected $_path           = 'gravityforms-custom-code/gravityforms-custom-code.php';
	protected $_full_path      = __FILE__;
	protected $_slug           = 'gravityforms-custom-code';
	protected $_title          = 'Gravity Forms Custom Code';
	protected $_short_title    = 'Custom Code';
	protected $_multiple_feeds = false;

	/**
	 * Defines the capabilities needed for the Add-On.
	 *
	 * @var array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array(
		'gravityforms-custom-code',
		'gravityforms-custom-code_uninstall',
		'gravityforms-custom-code_results',
		'gravityforms-custom-code_settings',
		'gravityforms-custom-code_form_settings',
	);

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @var string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms-custom-code_settings';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @var string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms-custom-code_form_settings';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @var string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms-custom-code_uninstall';

	/**
	 * Disable async feed processing for now as it can prevent results mapped to fields from working in notifications.
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = false;

	/**
	 * Allow re-ordering of feeds.
	 *
	 * @var bool
	 */
	protected $_supports_feed_ordering = true;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Give the form settings and plugin settings panels a nice shiny icon.
	 */
	public function get_menu_icon() {
		// TODO update
		return $this->get_base_url() . '/icon.svg';
	}

	/**
	 * Defines the minimum requirements for the add-on.
	 *
	 * @return array
	 */
	public function minimum_requirements() {
		return array(
			'gravityforms' => array(
				'version' => '2.5',
			),
			'wordpress'    => array(
				'version' => '4.8',
			),
		);
	}

	/**
	 * Load dependencies and initialize auto-updater
	 */
	public function pre_init() {
		parent::pre_init();

		$this->setup_autoload();
		$this->init_auto_updater();

		// TODO is this needed?
		add_filter( 'gform_export_form', array( $this, 'export_feeds_with_form' ) );
		// TODO is this needed?
		add_action( 'gform_forms_post_import', array( $this, 'import_feeds_with_form' ) );
	}

	/**
	 * @credit https://github.com/google/site-kit-wp
	 */
	public function setup_autoload() {
		$class_map = array_merge(
			include plugin_dir_path( __FILE__ ) . 'third-party/vendor/composer/autoload_classmap.php'
		);

		spl_autoload_register(
			function ( $class ) use ( $class_map ) {
				if ( isset( $class_map[ $class ] ) && substr( $class, 0, 27 ) === 'GWiz_GF_Custom_Code\\Dependencies' ) {
					require_once $class_map[ $class ];
				}
			},
			true,
			true
		);
	}

	/**
	 * Initialize the auto-updater.
	 */
	public function init_auto_updater() {
		// Initialize GitHub auto-updater
		add_filter(
			'inc2734_github_plugin_updater_plugins_api_gravitywiz/gravityforms-custom-code',
			array( $this, 'filter_auto_updater_response' ), 10, 2
		);

		return;

		// TODO: fix this scoper namespace issue that causes this to throw.
		$this->updater = new GWiz_GF_Custom_Code\Dependencies\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
			plugin_basename( plugin_dir_path( __FILE__ ) . 'gravityforms-custom-code.php' ),
			'gravitywiz',
			'gravityforms-custom-code',
			array(
				'description_url' => 'https://raw.githubusercontent.com/gravitywiz/gravityforms-custom-code/master/readme.md',
				'changelog_url'   => 'https://raw.githubusercontent.com/gravitywiz/gravityforms-custom-code/master/changelog.txt',
				'icons'           => array(
					'svg' => 'https://raw.githubusercontent.com/gravitywiz/gravityforms-custom-code/master/icon.svg',
				),
				'banners'         => array(
					'low' => 'https://gravitywiz.com/wp-content/uploads/2022/12/gfoai-by-dalle-1.png',
				),
				'requires_php'    => '5.6.0',
			)
		);
	}

	/**
	 * Filter the GitHub auto-updater response to remove sections we don't need and update various fields.
	 *
	 * @param stdClass $obj
	 * @param stdClass $response
	 *
	 * @return stdClass
	 */
	public function filter_auto_updater_response( $obj, $response ) {
		$remove_sections = array(
			'installation',
			'faq',
			'screenshots',
			'reviews',
			'other_notes',
		);

		foreach ( $remove_sections as $section ) {
			if ( isset( $obj->sections[ $section ] ) ) {
				unset( $obj->sections[ $section ] );
			}
		}

		if ( isset( $obj->active_installs ) ) {
			unset( $obj->active_installs );
		}

		$obj->homepage = 'https://gravitywiz.com/gravity-forms-custom-code/';
		$obj->author   = '<a href="https://gravitywiz.com/" target="_blank">Gravity Wiz</a>';

		$parsedown = new GWiz_GF_Custom_Code\Dependencies\Parsedown();
		$changelog = trim( $obj->sections['changelog'] );

		// Remove the "Changelog" h1.
		$changelog = preg_replace( '/^# Changelog/m', '', $changelog );

		// Remove the tab before the list item so it's not treated as code.
		$changelog = preg_replace( '/^\t- /m', '- ', $changelog );

		// Convert h2 to h4 to avoid weird styles that add a lot of whitespace.
		$changelog = preg_replace( '/^## /m', '#### ', $changelog );

		$obj->sections['changelog'] = $parsedown->text( $changelog );

		return $obj;
	}

	/**
	 * Initialize the add-on. Similar to construct, but done later.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();

		load_plugin_textdomain( $this->_slug, false, basename( dirname( __file__ ) ) . '/languages/' );

		// Filters/actions
		// add_filter( 'gform_validation_message', array( $this, 'modify_validation_message' ), 15, 2 );

		if ( current_user_can( 'administrator' ) ) {
			add_filter( 'gform_tooltips', array( $this, 'tooltips' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_script' ) );
			add_action( 'gform_post_save_feed_settings', array( $this, 'save_custom_code_settings' ), 10, 4 );
			add_filter( 'gform_noconflict_scripts', array( $this, 'noconflict_scripts' ) );
			add_filter( 'gform_noconflict_styles', array( $this, 'noconflict_styles' ) );
		}

		add_filter( 'gform_register_init_scripts', array( $this, 'register_init_script' ), 99 );
	}

	public function enqueue_editor_script() {
		if ( GFForms::get_page() !== 'form_settings_gravityforms-custom-code' ) {
			return;
		}

		$editor_settings['js_code_editor']  = wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) );
		$editor_settings['css_code_editor'] = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_localize_script( 'jquery', 'editor_settings', $editor_settings );

		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
	}

	public function noconflict_scripts( $scripts = array() ) {
		$scripts[] = 'code-editor';
		$scripts[] = 'jshint';
		$scripts[] = 'jsonlint';
		$scripts[] = 'wp-theme-plugin-editor';
		return $scripts;
	}

	public function noconflict_styles( $scripts = array() ) {
		$scripts[] = 'code-editor';
		$scripts[] = 'wp-codemirror';
		return $scripts;
	}
	public function save_custom_code_settings( $feed_id, $form_id, $settings, $feed_addon_instance ) {
		$form = GFAPI::get_form( $form_id );

		$form['custom_js']  = esc_html( rgpost( 'custom_js' ) );
		$form['custom_css'] = esc_html( rgpost( 'custom_css' ) );

		GFAPI::update_form( $form );
	}

	public function register_init_script( $form ) {
		if ( ! $this->is_applicable_form( $form ) ) {
			return;
		}

		$allowed_entities = array(
			'&#039;' => '\'',
			'&quot;' => '"',
		);

		$script = html_entity_decode( str_replace( array_keys( $allowed_entities ), $allowed_entities, $this->get_custom_js( $form ) ) );
		$script = str_replace( 'GFFORMID', $form['id'], $script );
		$script = '( function( $ ) { ' . $script . ' } )( jQuery );';

		$slug = "{$this->_slug}_{$form['id']}";

		GFFormDisplay::add_init_script( $form['id'], $slug, GFFormDisplay::ON_PAGE_RENDER, $script );
	}

	public function is_applicable_form( $form ) {
		return ! empty( $this->get_custom_js( $form ) );
	}

	public function get_custom_js( $form ) {
		// TODO can we migrate fully to `custom_js`?
		return rgar( $form, 'custom_js', rgar( $form, 'customJS' ) );
	}

	public function get_custom_css( $form ) {
		return rgar( $form, 'custom_css' );
	}

	/**
	 * Registers tooltips with Gravity Forms. Needed for some things like radio choices.
	 *
	 * @param $tooltips array Existing tooltips.
	 *
	 * @return array
	 */
	public function tooltips( $tooltips ) {
		return $tooltips;
	}

	public function can_duplicate_feed( $feed_id ) {
		// TODO: this might need to be false
		return false;
	}

	public function feed_settings_fields() {
		$form_id = rgget( 'id' );
		$form    = GFAPI::get_form( $form_id );
		return array(
			array(
				'title'  => 'JavaScript',
				'fields' => array(
					array(
						'name'          => 'custom_js',
						'type'          => 'editor_js',
						'callback'      => function ( $setting ) use ( $form ) {
							return $this->render_custom_js_setting( $form );
						},
					),
				),
			),
			array(
				'title'  => 'CSS',
				'fields' => array(
					array(
						'name'          => 'custom_css',
						'type'          => 'editor_css',
						'callback'      => function ( $setting ) use ( $form ) {
							return $this->render_custom_css_setting( $form );
						},
					),
				),
			),
		);
	}

	public function render_custom_js_setting( $form ) {
		// GF 2.5 may fire `gform_form_settings` before `save_custom_js_setting`
		$custom_js = $this->get_custom_js( $form );
		$post_js   = esc_html( rgpost( 'custom_js' ) );
		// Always favor posted JS if it's available
		$custom_js = ( $post_js ) ? $post_js : $custom_js;
		return $this->get_code_editor_markup( 'js', $custom_js );
	}

	public function render_custom_css_setting( $form ) {
		// GF 2.5 may fire `gform_form_settings` before `save_custom_js_setting`
		$custom_css = $this->get_custom_css( $form );
		$post_css   = esc_html( rgpost( 'custom_css' ) );
		// Always favor posted JS if it's available
		$custom_css = ( $post_css ) ? $post_css : $custom_css;
		return $this->get_code_editor_markup( 'css', $custom_css );
	}

	/**
	 * @param $type string The type of code editor to get. One of 'js' or 'css
	 * @parap $code string The code to render in the editor.
	 */
	public function get_code_editor_markup( $type, $code ) {
		$type_display_name = $type === 'js' ? 'Javascript' : 'CSS';
		/* translators: %s: The string "Javascript" or "CSS". */
		$description  = sprintf( __( 'Add any custom %s that you would like to output wherever this form is rendered.' ), $type_display_name );
		$gform_id_msg = __( 'Use <code>GFFORMID</code> to automatically set the current form ID when the code is rendered.' );

		return <<<EOT
			<tr id="custom_{$type}_setting" class="child_setting_row">
				<td colspan="2">
					<p>{$description}<br>{$gform_id_msg}</p>
					<textarea id="custom_{$type}" name="custom_{$type}" spellcheck="false"
						style="width:100%%;height:14rem;">{$code}</textarea>
				</td>
			</td>
			<script>
				jQuery( document ).ready( function( $ ) {
					wp.codeEditor.initialize( $( "#custom_{$type}" ), editor_settings.{$type}_code_editor );
				} );
			</script>
			<style type="text/css">
				.CodeMirror-wrap { border: 1px solid #e1e1e1; }
			</style>
EOT;
	}

	// public function add_legacy_custom_js_setting( $settings, $form ) {
	// 	return $settings;
	// }

	/**
	 * Processes the feed.
	 *
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 *
	 * @return array|void|null
	 */
	public function process_feed( $feed, $entry, $form ) {
		// TODO is this needed?
		return $entry;
	}

	/**
	 * Returns validation error message markup.
	 *
	 * @param string $validation_message  The validation message to add to the markup.
	 * @param array  $form                The submitted form data.
	 *
	 * @return false|string
	 */
	protected function get_validation_error_markup( $validation_message, $form ) {
		$error_classes = $this->get_validation_error_css_classes( $form );
		ob_start();

		if ( ! $this->is_gravityforms_supported( '2.5' ) ) {
			?>
			<div class="<?php echo esc_attr( $error_classes ); ?>"><?php echo esc_html( $validation_message ); ?></div>
			<?php
			return ob_get_clean();
		}
		?>
		<h2 class="<?php echo esc_attr( $error_classes ); ?>">
			<span class="gform-icon gform-icon--close"></span>
			<?php echo esc_html( $validation_message ); ?>
		</h2>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the CSS classes for the validation markup.
	 *
	 * @param array $form The submitted form data.
	 */
	protected function get_validation_error_css_classes( $form ) {
		$container_css = $this->is_gravityforms_supported( '2.5' ) ? 'gform_submission_error' : 'validation_error';

		return "{$container_css} hide_summary";
	}

	/**
	 * Export Custom Code Add-On feed when exporting forms.
	 *
	 * @param array $form The current form being exported.
	 *
	 * @return array
	 */
	public function export_feeds_with_form( $form ) {
		// TODO export the custom code feed if it exists (make sure this works)
		$feeds = $this->get_feeds( $form['id'] );

		if ( ! isset( $form['feeds'] ) ) {
			$form['feeds'] = array();
		}

		$form['feeds'][ $this->get_slug() ] = $feeds;

		return $form;
	}

	/**
	 * Import Custom Code Add-On feed when importing forms.
	 *
	 * @param array $forms Imported forms.
	 */
	public function import_feeds_with_form( $forms ) {
		// TODO import the custom code feed if it exists. (make sure this works)
		foreach ( $forms as $import_form ) {
			// Ensure the imported form is the latest.
			$form = GFAPI::get_form( $import_form['id'] );

			if ( ! rgars( $form, 'feeds/' . $this->get_slug() ) ) {
				continue;
			}

			foreach ( rgars( $form, 'feeds/' . $this->get_slug() ) as $feed ) {
				GFAPI::add_feed( $form['id'], $feed['meta'], $this->get_slug() );
			}

			// Remove feeds from the form array as it's no longer needed.
			unset( $form['feeds'][ $this->get_slug() ] );

			if ( empty( $form['feeds'] ) ) {
				unset( $form['feeds'] );
			}

			GFAPI::update_form( $form );
		}
	}
}
