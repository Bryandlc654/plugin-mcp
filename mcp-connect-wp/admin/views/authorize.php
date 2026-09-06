<?php
// Consent screen rendered by MCPConnect\OAuth_Server. $data is sanitized by the caller.
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( sprintf( __( 'Connect %s', 'mcp-connect-wp' ), $data['site_name'] ) ); ?></title>
<style>
	* { box-sizing: border-box; }
	body {
		margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
		background: linear-gradient(135deg, #2271b1 0%, #1d2327 100%); color: #1d2327;
	}
	.card {
		background: #fff; border-radius: 12px; width: 94%; max-width: 480px; padding: 36px 36px 28px;
		box-shadow: 0 20px 60px rgba(0,0,0,.35);
	}
	.brand { text-align: center; margin-bottom: 24px; }
	.brand h1 { font-size: 20px; margin: 0; }
	.brand p { margin: 4px 0 0; color: #646970; font-size: 13px; }
	.user { display: flex; align-items: center; gap: 12px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 8px; padding: 12px; margin-bottom: 20px; }
	.user img { width: 44px; height: 44px; border-radius: 50%; }
	.user .name { font-weight: 600; font-size: 14px; }
	.user .email { color: #646970; font-size: 12px; }
	.agent { text-align: center; font-size: 14px; color: #50575e; margin: 8px 0 16px; }
	.agent strong { color: #1d2327; display: block; font-size: 16px; margin-top: 2px; }
	.scopes { border: 1px solid #e2e4e7; border-radius: 8px; padding: 8px 16px; margin-bottom: 22px; }
	.scopes h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #50575e; margin: 10px 0 6px; }
	.scopes ul { margin: 0 0 10px; padding: 0; list-style: none; }
	.scopes li { padding: 6px 0 6px 26px; position: relative; font-size: 14px; }
	.scopes li::before { content: "✓"; position: absolute; left: 2px; color: #2271b1; font-weight: 700; }
	.hint { font-size: 12px; color: #646970; text-align: center; margin-bottom: 20px; }
	.actions { display: flex; gap: 10px; }
	button {
		flex: 1; border: 0; border-radius: 6px; padding: 12px 16px; font-size: 15px; font-weight: 600;
		cursor: pointer; transition: background .15s, transform .05s;
	}
	button:active { transform: scale(.99); }
	.cancel { background: #f6f7f7; color: #1d2327; border: 1px solid #dcdcde; }
	.cancel:hover { background: #f0f0f1; }
	.approve { background: #2271b1; color: #fff; }
	.approve:hover { background: #135e96; }
	.foot { margin-top: 18px; text-align: center; font-size: 12px; color: #8c8f94; }
</style>
</head>
<body>
<div class="card">
	<div class="brand">
		<h1><?php echo esc_html( $data['site_name'] ); ?></h1>
		<p><?php echo esc_html( __( 'Conectar agente de IA', 'mcp-connect-wp' ) ); ?></p>
	</div>
	<div class="user">
		<?php if ( $data['avatar'] ) : ?><img src="<?php echo esc_url( $data['avatar'] ); ?>" alt=""><?php endif; ?>
		<div>
			<div class="name"><?php echo esc_html( $data['user_name'] ); ?></div>
			<div class="email"><?php echo esc_html( $data['user_email'] ); ?></div>
		</div>
	</div>
	<div class="agent">
		<?php echo esc_html( __( 'Un agente de IA solicita acceso a', 'mcp-connect-wp' ) ); ?>:
		<strong><?php echo esc_html( $data['client_name'] ); ?></strong>
	</div>
	<div class="scopes">
		<h2><?php echo esc_html( __( 'Permisos', 'mcp-connect-wp' ) ); ?></h2>
		<ul>
			<?php foreach ( $data['scopes'] as $s ) : ?>
				<li><?php echo esc_html( $s['label'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php if ( $data['redirect_uri'] ) : ?>
		<p class="hint"><?php printf( esc_html__( 'Al autorizar redirigiremos a: %s', 'mcp-connect-wp' ), '<code style="word-break:break-all">' . esc_html( $data['redirect_uri'] ) . '</code>' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( \MCPConnect\Plugin::instance()->url->oauth_endpoint( 'authorize' ) ); ?>">
		<input type="hidden" name="_mcp_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mcp_connect_authorize' ) ); ?>">
		<input type="hidden" name="action" value="approve">
		<input type="hidden" name="response_type" value="code">
		<input type="hidden" name="client_id" value="<?php echo esc_attr( $data['client_id'] ); ?>">
		<input type="hidden" name="redirect_uri" value="<?php echo esc_attr( $data['redirect_uri'] ); ?>">
		<input type="hidden" name="scope" value="<?php echo esc_attr( $data['scope_string'] ); ?>">
		<input type="hidden" name="state" value="<?php echo esc_attr( $data['state'] ); ?>">
		<input type="hidden" name="code_challenge" value="<?php echo esc_attr( $data['code_challenge'] ); ?>">
		<input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( $data['code_challenge_method'] ); ?>">
		<input type="hidden" name="resource" value="<?php echo esc_attr( $data['resource'] ); ?>">
		<div class="actions">
			<button type="submit" name="deny" value="1" class="cancel" onclick="this.form.action.value='cancel'"><?php echo esc_html__( 'Cancelar', 'mcp-connect-wp' ); ?></button>
			<button type="submit" class="approve"><?php echo esc_html__( 'Autorizar', 'mcp-connect-wp' ); ?></button>
		</div>
	</form>
	<p class="foot"><?php echo esc_html( __( 'Autorizado por', 'mcp-connect-wp' ) ); ?> <strong><?php echo esc_html( $data['site_name'] ); ?></strong> · MCP Connect for WordPress</p>
</div>
</body>
</html>