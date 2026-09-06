<?php

namespace MCPConnect;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress admin screen(s): settings, permissions, connections, logs and
 * diagnostics, plus the admin-post handlers behind them.
 */
final class Admin {

	const SLUG  = 'mcp-connect-wp';
	const NONCE = 'mcp_connect_admin';

	const TABS = array( 'connect', 'permissions', 'clients', 'logs', 'diagnostics' );

	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ) );

		add_action( 'admin_post_mcp_connect_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_mcp_connect_revoke', array( $this, 'handle_revoke' ) );
		add_action( 'admin_post_mcp_connect_delete_client', array( $this, 'handle_delete_client' ) );
		add_action( 'admin_post_mcp_connect_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	public function ajax_nonce_action() {
		return self::NONCE;
	}

	public function add_menu() {
		add_options_page(
			__( 'MCP Connect for WordPress', 'mcp-connect-wp' ),
			__( 'MCP Connect for WordPress', 'mcp-connect-wp' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
		add_action( 'load-settings_page_' . self::SLUG, array( $this, 'load_page' ) );
	}

	public function load_page() {
		wp_enqueue_style( 'mcp-connect-wp-admin', plugin_dir_url( MCP_CONNECT_FILE ) . 'admin/css/admin.css', array(), MCP_CONNECT_VERSION );
		wp_enqueue_script( 'mcp-connect-wp-admin', plugin_dir_url( MCP_CONNECT_FILE ) . 'admin/js/admin.js', array( 'jquery' ), MCP_CONNECT_VERSION, true );
		wp_localize_script(
			'mcp-connect-wp-admin',
			'MCPConnectAdmin',
			array(
				'nonce' => wp_create_nonce( self::NONCE ),
				'rest'  => esc_url_raw( rest_url( 'mcp-connect-wp/v1' ) ),
				'i18n'  => array(
					'testing' => __( 'Comprobando conexión…', 'mcp-connect-wp' ),
					'ok'      => __( 'Conectado correctamente', 'mcp-connect-wp' ),
					'fail'    => __( 'Error de conexión', 'mcp-connect-wp' ),
				),
			)
		);
	}

	public function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connect';
		return in_array( $tab, self::TABS, true ) ? $tab : 'connect';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'mcp-connect-wp' ) );
		}

		$plugin = $this->plugin;
		$tab    = $this->current_tab();
		$view   = $this->view_path( $tab );

		?>
		<div class="wrap mcp-connect-wp-admin">
			<h1><?php echo esc_html__( 'MCP Connect for WordPress', 'mcp-connect-wp' ); ?></h1>
			<?php $this->render_tabs( $tab ); ?>
			<div class="mcp-connect-wp-content">
				<?php
				if ( $view ) {
					include $view;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function view_path( $tab ) {
		$map = array(
			'connect'      => 'settings-connect.php',
			'permissions'  => 'settings-permissions.php',
			'clients'      => 'settings-clients.php',
			'logs'         => 'settings-logs.php',
			'diagnostics'  => 'settings-diagnostics.php',
		);
		$file = MCP_CONNECT_DIR . 'admin/views/' . $map[ $tab ];
		return file_exists( $file ) ? $file : null;
	}

	private function render_tabs( $active ) {
		$tabs = array(
			'connect'      => __( 'Conectar', 'mcp-connect-wp' ),
			'permissions'  => __( 'Permisos', 'mcp-connect-wp' ),
			'clients'      => __( 'Conexiones autorizadas', 'mcp-connect-wp' ),
			'logs'         => __( 'Logs', 'mcp-connect-wp' ),
			'diagnostics'  => __( 'Diagnóstico', 'mcp-connect-wp' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$url   = add_query_arg( array( 'page' => self::SLUG, 'tab' => $key ), admin_url( 'options-general.php' ) );
			$class = 'nav-tab' . ( $key === $active ? ' nav-tab-active' : '' );
			printf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $label ) );
		}
		echo '</nav>';
	}

	/* ------------------------------------------------------------------ *
	 *  Handlers
	 * ------------------------------------------------------------------ */

	private function check_cap_nonce() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'mcp-connect-wp' ), 403 );
		}
		if ( ! isset( $_POST['_mcp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_mcp_nonce'] ), self::NONCE ) ) {
			wp_die( esc_html__( 'Nonce inválido.', 'mcp-connect-wp' ), 403 );
		}
	}

	private function redirect_after_save( $extra = array() ) {
		$url = add_query_arg(
			array_merge( array( 'page' => self::SLUG, 'tab' => $this->current_tab(), 'updated' => 1 ), $extra ),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_save() {
		$this->check_cap_nonce();
		$plugin  = $this->plugin;
		$posted  = isset( $_POST['mcp_connect'] ) ? wp_unslash( $_POST['mcp_connect'] ) : array();
		$current = (array) get_option( Settings::OPTION, array() );
		$section = isset( $posted['section'] ) ? sanitize_key( (string) $posted['section'] ) : '';

		// Merge onto the current settings so saving one tab never wipes the
		// options owned by the other tab (e.g. Conectar vs Permisos).
		$settings = $current;

		if ( 'permissions' === $section ) {
			$matrix = array();
			$scopes = isset( $posted['scopes'] ) && is_array( $posted['scopes'] ) ? $posted['scopes'] : array();
			foreach ( Settings::CATEGORIES as $cat ) {
				foreach ( Settings::MODES as $mode ) {
					$key                 = $cat . ':' . $mode;
					$allowed             = Permissions::is_valid_scope( $key );
					$matrix[ $cat ][ $mode ] = $allowed && ! empty( $scopes[ $key ] );
				}
			}
			$settings['scopes'] = $matrix;
		} else {
			$settings['enabled']             = ! empty( $posted['enabled'] );
			$settings['enable_registration'] = ! empty( $posted['enable_registration'] );
			$settings['trust_proxy_headers'] = ! empty( $posted['trust_proxy_headers'] );
			$settings['log_level']           = in_array( $posted['log_level'] ?? 'errors', array( 'all', 'errors', 'off' ), true ) ? $posted['log_level'] : 'errors';
			$settings['delete_on_uninstall'] = ! empty( $posted['delete_on_uninstall'] );

			$origins = array();
			if ( ! empty( $posted['cors_origins'] ) ) {
				foreach ( preg_split( '/[\r\n,]+/', (string) $posted['cors_origins'] ) as $line ) {
					$origin = $plugin->url->normalize_origin( trim( $line ) );
					if ( $origin ) {
						$origins[] = $origin;
					}
				}
				$origins = array_values( array_unique( $origins ) );
			}
			if ( ! empty( $posted['cors_origins_override'] ) ) {
				unset( $settings['cors_origins'] );
			} else {
				$settings['cors_origins'] = $origins;
			}

			if ( $settings['enabled'] && empty( $current['scopes'] ) ) {
				update_option( self::SLUG . '_flush', 1, false );
			}
		}

		update_option( Settings::OPTION, $settings, false );

		$this->redirect_after_save();
	}

	public function handle_revoke() {
		$this->check_cap_nonce();
		$auth_id = isset( $_POST['auth_id'] ) ? (int) $_POST['auth_id'] : 0;
		if ( $auth_id ) {
			$this->plugin->tokens->revoke_authorization( $auth_id );
		}
		$this->redirect_after_save();
	}

	public function handle_delete_client() {
		$this->check_cap_nonce();
		$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		if ( '' !== $client_id ) {
			$this->plugin->tokens->delete_client( $client_id );
		}
		$this->redirect_after_save();
	}

	public function handle_clear_logs() {
		$this->check_cap_nonce();
		$this->plugin->logger->clear();
		$this->redirect_after_save();
	}

	public function maybe_flush_rewrites() {
		if ( get_option( self::SLUG . '_flush' ) ) {
			flush_rewrite_rules( false );
			delete_option( self::SLUG . '_flush' );
		}
	}

	/* ------------------------------------------------------------------ *
	 *  Diagnostics
	 * ------------------------------------------------------------------ */

	public function run_diagnostics() {
		global $wpdb, $wp_version;
		$plugin = $this->plugin;

		$checks = array();

		$ok_home = wp_parse_url( home_url( '/' ) );
		$ok_site = wp_parse_url( site_url( '/' ) );

		$https = $plugin->url->is_https();
		$checks[] = array(
			'name'    => __( 'Conexión HTTPS', 'mcp-connect-wp' ),
			'ok'      => $https,
			'message' => $https
				? __( 'El sitio se sirve por HTTPS.', 'mcp-connect-wp' )
				: __( 'HTTPS no está activo: necesitas HTTPS para que los agentes de IA puedan conectarse.', 'mcp-connect-wp' ),
		);

		$permalinks = get_option( 'permalink_structure' );
		$checks[] = array(
			'name'    => __( 'Enlaces permanentes', 'mcp-connect-wp' ),
			'ok'      => (bool) $permalinks,
			'message' => $permalinks
				? sprintf( __( 'Estructura: %s', 'mcp-connect-wp' ), '<code>' . esc_html( $permalinks ) . '</code>' )
				: __( 'Sin estructura de enlaces permanentes: activa una estructura distinta de "Simple".', 'mcp-connect-wp' ),
		);

		foreach ( array( 'clients', 'codes', 'tokens', 'authorizations', 'logs' ) as $table ) {
			$name = Install::table( $table );
			$tables_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name;
			$checks[] = array(
				'name'    => sprintf( __( 'Tabla %s', 'mcp-connect-wp' ), $table ),
				'ok'      => $tables_ok,
				'message' => $tables_ok ? esc_html( $name ) : __( 'No existe. Al activar el plugin se crea automáticamente.', 'mcp-connect-wp' ),
			);
		}

		$checks[] = array(
			'name'    => __( 'Servidor MCP', 'mcp-connect-wp' ),
			'ok'      => $plugin->is_enabled(),
			'message' => $plugin->is_enabled()
				? __( 'Plugin activo y servidor habilitado.', 'mcp-connect-wp' )
				: __( 'El plugin está desactivado.', 'mcp-connect-wp' ),
		);

		$checks[] = array(
			'name'    => __( 'Criptografía', 'mcp-connect-wp' ),
			'ok'      => Crypto::available(),
			'message' => Crypto::available()
				? __( 'random_bytes y hash disponibles.', 'mcp-connect-wp' )
				: __( 'Faltan funciones de criptografía.', 'mcp-connect-wp' ),
		);

		$checks[] = array(
			'name'    => __( 'Registro dinámico', 'mcp-connect-wp' ),
			'ok'      => $plugin->settings->is_registration_enabled(),
			'message' => $plugin->settings->is_registration_enabled()
				? __( 'Los clientes pueden registrarse dinámicamente.', 'mcp-connect-wp' )
				: __( 'Registro dinámico desactivado.', 'mcp-connect-wp' ),
		);

		$checks[] = array(
			'name'    => __( 'WordPress', 'mcp-connect-wp' ),
			'ok'      => version_compare( $wp_version, '6.0', '>=' ),
			'message' => sprintf( __( 'Versión: %s', 'mcp-connect-wp' ), esc_html( $wp_version ) ),
		);

		return $checks;
	}
}