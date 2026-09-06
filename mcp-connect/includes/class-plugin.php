<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin container: wires every component together.
 */
final class Plugin {

	const VERSION = MCP_CONNECT_VERSION;

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Settings|null */
	public $settings;

	/** @var Url_Manager|null */
	public $url;

	/** @var Logger|null */
	public $logger;

	/** @var Token_Store|null */
	public $tokens;

	/** @var MCP_Tools|null */
	public $tools;

	/** @var MCP_Server|null */
	public $server;

	/** @var MCP_Auth|null */
	public $auth;

	/** @var OAuth_Server|null */
	public $oauth;

	/** @var Discovery|null */
	public $discovery;

	/** @var OAuth_Client_Registration|null */
	public $registration;

	/** @var MCP_Router|null */
	public $router;

	/** @var Admin|null */
	public $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init() {
		$this->settings     = new Settings();
		$this->url          = new Url_Manager( $this->settings );
		$this->logger       = new Logger();
		$this->tokens       = new Token_Store();
		$this->tools        = new MCP_Tools();
		$this->auth         = new MCP_Auth( $this->tokens, $this->url );
		$this->server       = new MCP_Server( $this->tools, $this->auth, $this->logger, $this->url );
		$this->discovery    = new Discovery( $this->settings, $this->url );
		$this->registration = new OAuth_Client_Registration( $this->tokens, $this->url, $this->settings );
		$this->oauth        = new OAuth_Server( $this->tokens, $this->url, $this->settings, $this->logger, $this->registration, $this->discovery );
		$this->router       = new MCP_Router( $this );
		$this->admin        = new Admin( $this );

		add_action( 'init', array( $this, 'register_core' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 5 );
	}

	public function register_core() {
		$this->url->register();
		$this->tools->register_tools();
		$this->router->register_rewrites();
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'mcp-connect', false, dirname( plugin_basename( MCP_CONNECT_FILE ) ) . '/languages' );
	}

	public function is_enabled() {
		return (bool) $this->settings->get( 'enabled', true );
	}
}