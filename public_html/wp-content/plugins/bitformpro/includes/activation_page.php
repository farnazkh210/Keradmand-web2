<?php

defined('ABSPATH') || die();

define('WP_INSTALLING', true);

global $current_site;

use BitCode\BitForm\Core\Integration\IntegrationHandler;

function wpmu_activate_stylesheet()
{
?>
	<style type="text/css">
		h5 {
			text-align: center;
		}

		#bf_content {
			text-align: center;
			padding: 5px;
			background: white;
			margin: 30px auto;
			box-shadow: 2px 2px 2px white;
			max-width: 100%;
			width: fit-content;
		}

		#bf_content * {
			text-align: center;
			width: 100%;
			white-space: unset;
		}
	</style>
<?php
}

add_action('wp_head', 'wpmu_activate_stylesheet');

$key = $_GET['bit_activation_key'];
$formId = $_GET['f_id'];
$userId = $_GET['user_id'];

$existAuth = (new IntegrationHandler($formId))->getAllIntegration('wp_user_auth', 'wp_auth', 1);
$code = '';

if (metadata_exists('user', $userId, 'bf_activation_code')) {
	$code = get_user_meta($userId, 'bf_activation_code', true);
}

$activation = (bool) get_user_meta($userId, 'bf_activation');

get_header(); ?>

<div id="bf_content">
	<?php if ($code == $key && !is_wp_error($existAuth)) {
		$intDetails = json_decode($existAuth[0]->integration_details);
		$body = $intDetails->acti_succ_msg;
		update_user_meta($userId, 'bf_activation', 1);
		delete_user_meta($userId, 'bf_activation_code');
	?>
		<?= $body ?>
		
	<?php }
	if ($activation === false || is_wp_error($existAuth)) { ?>
		
		<p>Your URL is invalid!</p>

	<?php }
	if ( empty($code) && $activation === true) {
	?>
		<p>Your Account is already activated !</p>
	<?php } ?>

</div>
<?php get_footer(); ?>