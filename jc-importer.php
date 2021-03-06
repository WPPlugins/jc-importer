<?php
/**
 * Plugin Name: ImportWP
 * Plugin URI: https://www.importwp.com
 * Description: Wordpress CSV/XML Importer Plugin, Easily import users, posts, custom post types and taxonomies from XML or CSV files
 * Author: James Collings <james@jclabs.co.uk>
 * Author URI: http://www.jamescollings.co.uk
 * Version: 0.3.1
 *
 * @package ImportWP
 * @author James Collings <james@jclabs.co.uk>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'app/core/exceptions.php';
require_once 'app/parse/parser.php';

// libs.
require_once 'app/libs/xmloutput.php';
require_once 'app/libs/class-importwp-premium.php';

// attachments.
require_once 'app/attachment/class-jci-attachment.php';
require_once 'app/attachment/class-jci-ftp-attachments.php';
require_once 'app/attachment/class-jci-curl-attachments.php';
require_once 'app/attachment/class-jci-upload-attachments.php';
require_once 'app/attachment/class-jci-string-attachments.php';

// mappers.
require_once 'app/mapper/Mapper.php';
require_once 'app/mapper/PostMapper.php';
require_once 'app/mapper/TableMapper.php';
require_once 'app/mapper/UserMapper.php';
require_once 'app/mapper/VirtualMapper.php';
require_once 'app/mapper/TaxMapper.php';

// parsers.
require_once 'app/parse/data-csv.php';
require_once 'app/parse/data-xml.php';

// templates.
require_once 'app/templates/template.php';
require_once 'app/templates/template-user.php';
require_once 'app/templates/template-post.php';
require_once 'app/templates/template-page.php';
require_once 'app/templates/template-tax.php';

require_once 'app/helpers/form.php';
require_once 'app/functions.php';

/**
 * Class JC_Importer
 *
 * Core plugin class
 */
class JC_Importer {

	/**
	 * Current Plugin Version
	 *
	 * @var string
	 */
	protected $version = '0.3.1';

	/**
	 * Plugin base directory
	 *
	 * @var string
	 */
	protected $plugin_dir;

	/**
	 * Plugin base url
	 *
	 * @var string
	 */
	protected $plugin_url;

	/**
	 * List of available template strings
	 *
	 * @var array[string]
	 */
	protected $templates = array();

	/**
	 * Current plugin database schema version
	 *
	 * @var int
	 */
	protected $db_version = 2;

	/**
	 * Debug flag
	 *
	 * @var bool
	 */
	protected $debug = false;

	/**
	 * Loaded Importer Class
	 *
	 * @var JC_Importer_Core
	 */
	public $importer;

	/**
	 * Single instance of class
	 *
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * Return current instance of class
	 *
	 * @return JC_Importer
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * JC_Importer constructor.
	 */
	public function __construct() {

		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugins_url( '/', __FILE__ );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'db_update_check' ) );

		$this->parsers = apply_filters( 'jci/register_parser', array() );

		// activation.
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		add_action( 'admin_init', array( $this, 'load_plugin' ) );
	}

	/**
	 * Setup plugin, loading all classes
	 *
	 * @return void
	 */
	public function init() {

		do_action( 'jci/before_init' );

		$this->register_post_types();

		// register templates.
		$this->templates = apply_filters( 'jci/register_template', $this->templates );

		// load importer.
		require_once 'app/core/importer.php';

		// core models.
		require_once 'app/models/importer.php';
		require_once 'app/models/log.php';

		if ( is_admin() ) {

			// load importer.
			$importer_id = isset( $_GET['import'] ) && ! empty( $_GET['import'] ) ? intval( $_GET['import'] ) : 0;
			if ( $importer_id > 0 ) {
				$this->importer = new JC_Importer_Core( $importer_id );
			}

			require_once 'app/admin.php';
			new JC_Importer_Admin( $this );

			require_once 'app/ajax.php';
			new JC_Importer_Ajax( $this );
		}

		ImporterModel::init( $this );
		ImportLog::init( $this );
		JCI_FormHelper::init( $this );

		// plugin loaded.
		do_action( 'jci/init' );
	}

	/**
	 * Register jc-imports custom post types
	 *
	 * @return void
	 */
	function register_post_types() {

		// importers.
		register_post_type( 'jc-imports', array(
			'public'            => false,
			'has_archive'       => false,
			'show_in_nav_menus' => false,
			'label'             => 'Importer',
		) );

		// importer csv/xml files.
		register_post_type( 'jc-import-files', array(
			'public'            => false,
			'has_archive'       => false,
			'show_in_nav_menus' => false,
			'label'             => 'Importer Files',
		) );

		// importer tempaltes.
		register_post_type( 'jc-import-template', array(
			'public'            => false,
			'has_archive'       => false,
			'show_in_nav_menus' => false,
			'label'             => 'Template',
		) );
	}

	/**
	 * Set Plugin Activation
	 *
	 * @return void
	 */
	function activation() {
		add_option( 'Activated_Plugin', 'jcimporter' );
	}

	/**
	 * Run Activation Functions
	 *
	 * @return void
	 */
	function load_plugin() {

		if ( is_admin() ) {

			if ( get_option( 'Activated_Plugin' ) === 'jcimporter' ) {

				// scaffold log table.
				require_once 'app/models/schema.php';
				$schema = new JCI_DB_Schema( $this );
				$schema->install();
				delete_option( 'Activated_Plugin' );
			}

			$this->db_update_check();
		}
	}

	/**
	 * Check if database requires an upgrade
	 */
	public function db_update_check() {

		$curr_db = intval( get_site_option( 'jci_db_version' ) );
		if ( is_admin() && $curr_db < $this->db_version ) {

			require_once 'app/models/schema.php';
			$schema = new JCI_DB_Schema( $this );
			$schema->upgrade( $curr_db );

			update_site_option( 'jci_db_version', $this->db_version );
		}
	}

	/**
	 * Get plugin directory
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Get plugin url
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Is debug enabled?
	 *
	 * @return bool
	 */
	public function is_debug() {
		return $this->debug;
	}

	/**
	 * Get importer template
	 *
	 * @param string $template Template name.
	 *
	 * @return mixed|string
	 */
	public function get_template( $template ) {

		if ( isset( $this->templates[ $template ] ) ) {
			$temp                         = $this->templates[ $template ];
			$this->templates[ $template ] = new $temp;

			return $this->templates[ $template ];
		}

		return false;
	}

	/**
	 * Get importer templates
	 *
	 * @return array
	 */
	public function get_templates() {
		return $this->templates;
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}

/**
 * Globally access JC_Importer instance.
 *
 * @return JC_Importer
 */
function JCI() {
	return JC_Importer::instance();
}

$GLOBALS['jcimporter'] = JCI();
