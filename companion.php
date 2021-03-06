<?php
/*
Plugin Name: Companion Plugin
Plugin URI: https://github.com/Automattic/companion
Description: Helps keep the launched WordPress in order.
Version: 1.18
Author: Osk
*/

// Do nothing if it happens to be running in an Atomic site.
// This may happen when importing a full Jurassic Ninja site into an Atomic one
// and this plugin is eventually imported into the site.
if ( defined( 'IS_ATOMIC' ) && IS_ATOMIC ) {
	return true;
}

// Do a minimal set of stuff on a multisite installation on sites other than the main one
if ( is_multisite() && ! is_main_site() ) {
	add_action( 'pre_current_active_plugins', 'companion_hide_plugin' );
	add_action( 'admin_notices', 'companion_admin_notices' );
	return true;
}

$companion_api_base_url = get_option( 'companion_api_base_url' );

add_action( 'wp_login', 'companion_wp_login', 1, 2 );
add_action( 'after_setup_theme', 'companion_after_setup_theme' );
add_action( 'admin_notices', 'companion_admin_notices' );
add_action( 'pre_current_active_plugins', 'companion_hide_plugin' );
/*
 * Run this function as early as we can relying in WordPress loading plugin in alphabetical order
 */
companion_tamper_with_jetpack_constants();
add_action( 'init', 'companion_add_jetpack_constants_option_page' );

function clipboard( $target, $inner = '&#x1f4cb;' ) {
?>
	<a class="jurassic_ninja_field_clipboard" target="<?php echo $target; ?>" href="#"><?php echo $inner; ?></a>
<?php
}

function companion_admin_notices() {
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen->id === 'post' ) {
			return;
		}
	}
	$password_option_key = 'jurassic_ninja_admin_password';
	$sysuser_option_key = 'jurassic_ninja_sysuser';
	$admin_password = is_multisite() ? get_blog_option( 1, $password_option_key ) : get_option( $password_option_key );
	$ssh_password = $admin_password;
	$sysuser = is_multisite() ? get_blog_option( 1, $sysuser_option_key ) : get_option( $sysuser_option_key );
	$host = parse_url( network_site_url(), PHP_URL_HOST );
	$sftp = 'sftp://'. $sysuser . ':' . $admin_password . '@' . $host . ':22/' . get_home_path(); // Extra `/` after port is needed for some SFTP apps
	$ssh = 'ssh ' . $sysuser . '@'. $host;
	?>
	<div class="notice notice-success is-dismissible">
		<h3 class="jurassic_ninja_welcome">
			<img src="https://i2.wp.com/jurassic.ninja/wp-content/uploads/2018/05/jurassicninja-transparent.png?w=80&ssl=1" alt="">
			<?php echo esc_html__( 'Welcome to Jurassic Ninja!' ); ?>
		</h3>
		<p>
			<strong><span id="jurassic_url" class="jurassic_ninja_field"><?php echo esc_html( network_site_url() ); ?></span></strong>
			<?php clipboard( 'jurassic_url' ); ?>
			<?php echo esc_html__( 'will be destroyed in 7 days.' ); ?>
		</p>
		<p>
			<strong>User:</strong> <code id="jurassic_username" class="jurassic_ninja_field">demo</code> 
			<code id="jurassic_password" class="jurassic_ninja_field"><?php echo esc_html( $admin_password ); ?></code>
			<?php clipboard( 'jurassic_password' ); ?>
		</p>
		<p>
			<strong>SSH User:</strong> <code id="jurassic_ssh_user" class="jurassic_ninja_field"><?php echo esc_html( $sysuser ); ?></code>
			<?php clipboard( 'jurassic_ssh_user' ); ?>
			<strong>Password:</strong> <code id="jurassic_ssh_password" class="jurassic_ninja_field"><?php echo esc_html( $ssh_password ); ?></code>
			<?php clipboard( 'jurassic_ssh_password' ); ?>
			<span style="display:none" id="jurassic_ssh"><?php echo esc_html( $ssh ); ?></span>
			<span style="display:none" id="jurassic_sftp"><?php echo esc_html( $sftp ); ?></span>
			<strong>SSH command</strong>
			<?php clipboard( 'jurassic_ssh' ); ?>
			<strong>SFTP connection string</strong>
			<?php clipboard( 'jurassic_sftp' ); ?>
		</p>
		<p>
			<strong>Server path:</strong> <code id="jurassic_ninja_server_path" class="jurassic_ninja_field"><?php echo esc_html( get_home_path() ); ?></code>
			<?php clipboard( 'jurassic_ninja_server_path' ); ?>
		</p>
	</div>
	<style type="text/css">
		.jurassic_ninja_welcome {
			display: flex;
			align-items: center;
		}
		.jurassic_ninja_welcome img {
			margin: 0 5px 0 0;
			max-width: 40px;
		}

		code.jurassic_ninja_field {
			color: #0366d6;
			background: #eff7ff;
			font: .95em/2 SFMono-Regular,Consolas,Liberation Mono,Menlo,monospace;
		}
		.jurassic_ninja_field_clipboard {
			user-select: all;
			cursor: pointer;
		}
	</style>
	<script>
		/**
		 * Helper to copy-paste credential fields in notice
		 */
		function jurassic_ninja_clippy( str ) {
			var el = document.createElement( 'input' );
			el.value = str;
			document.body.appendChild( el );
			el.select();
			document.execCommand( 'copy' );
			document.body.removeChild( el );
		};

		var jurassic_ninja_fields = document.getElementsByClassName( 'jurassic_ninja_field_clipboard' );

		// IE11 compatible way to loop this
		// https://developer.mozilla.org/en-US/docs/Web/API/NodeList#Example
		Array.prototype.forEach.call( jurassic_ninja_fields, function ( field ) {
			field.addEventListener( 'click', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				// These html entities are represented by images in the end
				// So we need to figure out if the target of the click is a link or an img
				const isChild = ! e.target.getAttribute( 'target' );
				const el = ! isChild ?
					document.getElementById( e.target.getAttribute( 'target' ) ) :
					document.getElementById( e.target.parentNode.getAttribute( 'target' ) );
				const str = el.innerText; 
				jurassic_ninja_clippy( str );
				// Transition to checkmark and back
				if ( isChild ) {
					const parent = e.target.parentNode;
					parent.innerHTML = '&#10004;';
					setTimeout( () => {
						parent.innerHTML= '&#x1f4cb;';
					}, 1000 );
				}
			} );
		} );
	</script>
	<?php
}

function companion_hide_plugin() {
	global $wp_list_table;
	$hidearr = array( 'companion/companion.php' );
	$myplugins = $wp_list_table->items;
	foreach ( $myplugins as $key => $val ) {
		if ( in_array( $key, $hidearr, true ) ) {
			unset( $wp_list_table->items[ $key ] );
		}
	}
}

function companion_wp_login() {
	global $companion_api_base_url;

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	$auto_login = get_option( 'auto_login' );

	update_option( 'auto_login', 0 );

	if ( empty( $auto_login ) ) {
		$urlparts = wp_parse_url( network_site_url() );
		$domain = $urlparts['host'];
		$url = "$companion_api_base_url/extend";
		wp_remote_post( $url, [
			'headers' => [
				'content-type' => 'application/json',
			],
			'body' => wp_json_encode( [
				'domain' => $domain,
			] ),
		] );
	} else {
		$urlparts = wp_parse_url( network_site_url() );
		$domain = $urlparts ['host'];
		$url = "$companion_api_base_url/checkin";
		wp_remote_post( $url, [
			'headers' => [
				'content-type' => 'application/json',
			],
			'body' => wp_json_encode( [
				'domain' => $domain,
			] ),
		] );
		wp_safe_redirect( '/wp-admin' );
		exit( 0 );
	}
}


function companion_after_setup_theme() {
	$auto_login = get_option( 'auto_login' );
	// Only autologin for requests to the homepage.
	if ( ! empty( $auto_login ) && ( $_SERVER['REQUEST_URI'] == '/' ) ) {
		$password = get_option( 'jurassic_ninja_admin_password' );
		$creds = array();
		$creds['user_login'] = 'demo';
		$creds['user_password'] = $password;
		$creds['remember'] = true;
		$user = wp_signon( $creds, companion_site_uses_https() );
	}
}

function companion_site_uses_https() {
	$url = network_site_url();
	$scheme = wp_parse_url( $url )['scheme'];
	return 'https' === $scheme;
}

function companion_add_jetpack_constants_option_page() {
	$jetpack_beta_present_and_supports_jetpack_constants_settings = class_exists( 'Jetpack_Beta' ) &&
		version_compare( JPBETA_VERSION, '3', '>' );
	if ( ! companion_is_jetpack_here() || $jetpack_beta_present_and_supports_jetpack_constants_settings ) {
		return;
	}
	if ( ! class_exists( 'RationalOptionPages' ) ) {
		require 'RationalOptionPages.php';
	}

	$jetpack_sandbox_domain = defined( 'JETPACK__SANDBOX_DOMAIN' ) ? JETPACK__SANDBOX_DOMAIN : '';
	$deprecated = '<strong>' . sprintf(
		esc_html__( 'This is no longer needed see %s.', 'companion' ),
		'<code>JETPACK__SANDBOX_DOMAIN</code>'
	) . '</strong>';

	$options_page = array(
		'companion' => array(
			'page_title' => __( 'Jurassic Ninja Tweaks for Jetpack Constants', 'companion' ),
			'menu_title' => __( 'Jetpack Constants', 'companion' ),
			'menu_slug' => 'companion_settings',
			'parent_slug' => 'options-general.php',
			'sections' => array(
				'jetpack_tweaks' => array(
					'title' => __( 'Sites', 'companion' ),
					'text' => '<p>' . __( 'Configure some defaults constants used by Jetpack.', 'companion' ) . '</p>',
					'fields' => array(
						'jetpack_sandbox_domain' => array(
							'id' => 'jetpack_sandbox_domain',
							'title' => __( 'JETPACK__SANDBOX_DOMAIN', 'companion' ),
							'text' => sprintf(
								esc_html__( "The domain of a WordPress.com Sandbox to which you wish to send all of Jetpack's remote requests. Must be a ___.wordpress.com subdomain with DNS permanently pointed to a WordPress.com sandbox. Current value for JETPACK__SANDBOX_DOMAIN: %s", 'companion' ),
								'<code>' . esc_html( $jetpack_sandbox_domain ) . '</code>'
							),
							'placeholder' => esc_attr( $jetpack_sandbox_domain ),
						),
						'jetpack_beta_blocks' => array(
							'id' => 'jetpack_beta_blocks',
							'title' => __( 'JETPACK_BETA_BLOCKS', 'companion' ),
							'text' =>
								esc_html__( 'Check to enable Jetpack blocks for Gutenberg that are on Beta stage of development', 'companion' ),
							'type' => 'checkbox',
						),
						'jetpack_protect_api_host' => array(
							'id' => 'jetpack_protect_api_host',
							'title' => __( 'JETPACK_PROTECT__API_HOST', 'companion' ),
							'text' => sprintf(
								esc_html__( "Base URL for API requests to Jetpack Protect's REST API. Current value for JETPACK_PROTECT__API_HOST: %s", 'companion' ),
								'<code>' . esc_html( JETPACK_PROTECT__API_HOST ) . '</code>'
							),
							'placeholder' => esc_attr( JETPACK_PROTECT__API_HOST ),
						),
						'jetpack_should_not_use_connection_iframe' => array(
							'id' => 'jetpack_should_not_use_connection_iframe',
							'title' => __( 'JETPACK_SHOULD_NOT_USE_CONNECTION_IFRAME', 'companion' ),
							'text' => sprintf(
								esc_html__( "Don't use connection iFrame", 'companion' )
							),
							'type' => 'checkbox',
						),
						'jetpack_dev_debug' => array(
							'id' => 'jetpack_dev_debug',
							'title' => __( 'JETPACK_DEV_DEBUG', 'companion' ),
							'text' =>
								esc_html__( 'Check to enable offline mode, and access features that can be used without a connection to WordPress.com', 'companion' ),
							'type' => 'checkbox',
						),
						'jetpack_no_user_testing' => array(
							'id' => 'jetpack_no_user_testing',
							'title' => __( 'JETPACK_NO_USER_TEST_MODE', 'companion' ),
							'text' =>
								esc_html__( 'Check to enable No User Testing Mode. This will allow you to test the Jetpack connection without an authorized user.', 'companion' ),
							'type' => 'checkbox',
						),
					),
				),
			),
		),
	);
	new RationalOptionPages( $options_page );
}

function companion_is_jetpack_here() {
	return class_exists( 'Jetpack' );
}

function companion_get_option( $slug, $default = null ) {
	$options = get_option( 'companion', array() );
	return isset( $options[ $slug ] )  ? $options[ $slug ] : $default;
}


function companion_tamper_with_jetpack_constants() {
	if ( ! ( defined( 'JETPACK__SANDBOX_DOMAIN' ) && JETPACK__SANDBOX_DOMAIN ) && companion_get_option( 'jetpack_sandbox_domain', '' ) ) {
		define( 'JETPACK__SANDBOX_DOMAIN', companion_get_option( 'jetpack_sandbox_domain', '' ) );
	}

	if ( ! ( defined( 'JETPACK_PROTECT__API_HOST' ) && JETPACK_PROTECT__API_HOST ) && companion_get_option( 'jetpack_protect_api_host', '' ) ) {
		define( 'JETPACK_PROTECT__API_HOST', companion_get_option( 'jetpack_protect_api_host', '' ) );
	}
	if ( ! ( defined( 'JETPACK_BETA_BLOCKS' ) && JETPACK_BETA_BLOCKS ) && companion_get_option( 'jetpack_beta_blocks', '' ) ) {
		define( 'JETPACK_BETA_BLOCKS', companion_get_option( 'jetpack_beta_blocks', '' ) ? true : false );
	}
	if ( ! ( defined( 'JETPACK_SHOULD_NOT_USE_CONNECTION_IFRAME' ) && JETPACK_SHOULD_NOT_USE_CONNECTION_IFRAME ) && companion_get_option( 'jetpack_should_not_use_connection_iframe', '' ) ) {
		define( 'JETPACK_SHOULD_NOT_USE_CONNECTION_IFRAME', companion_get_option( 'jetpack_should_not_use_connection_iframe', '' ) ? true : false );
	}
	if ( ! ( defined( 'JETPACK_DEV_DEBUG' ) && JETPACK_DEV_DEBUG ) && companion_get_option( 'jetpack_dev_debug', '' ) ) {
		define( 'JETPACK_DEV_DEBUG', companion_get_option( 'jetpack_dev_debug', '' ) ? true : false );
	}
	if ( ! ( defined( 'JETPACK_NO_USER_TEST_MODE' ) && JETPACK_NO_USER_TEST_MODE ) && companion_get_option( 'jetpack_no_user_testing', '' ) ) {
		define( 'JETPACK_NO_USER_TEST_MODE', companion_get_option( 'jetpack_no_user_testing', '' ) ? true : false );
	}
}
