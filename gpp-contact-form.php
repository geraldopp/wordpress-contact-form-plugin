<?php
/*
Plugin Name: GPP Contact Form
Description: A super simple contact form. Using the shortcode: [gpp-simple-contact-form] you will have a contact form for your website. The form has integration with Akismet, to avoid spam. It also has a human verification. After the form submission users have to wait 20 minutes to send the next message.
Author: Geraldo Pena Perez
Version: 1.0.0
*/

if(!defined('ABSPATH')){
	exit;
}
/* Contact Form */
// Shortcode for display: "gpp-simple-contact-form"

function gpp_generate_contact_form(){
	
	//Responses
	$responses = array(
		"Verification incorrect.",
		"Please supply all information.",
		"Email address invalid.",
		"Message was not sent. Try Again later.",
		"Thanks! Your message has been sent.",
		"You'll get our reply soon. You can send another message in 20 minutes."
	);
	
	$error_color = "#ff0000";
	$success_color = "#00dc00";
	$response_color = 'inherit';
	$response_msg = "";
	$show_form = true;
	
	//User data
	$name = $_POST['gpp_contact_name'];
	$email = $_POST['gpp_contact_email'];
	$message = $_POST['gpp_contact_msg'];
	$human = $_POST['gpp_contact_human'];
	$human_result = $_POST['gpp_contact_human_result'];
	
	//Form data
	$to = get_option('admin_email');
	$subject = "Message from ".get_bloginfo('name');
	$headers = array(
		'From: '.$to,
		'Reply-To: '.$email,
		'Content-type: text/plain',
		'charset: utf-8'	
	);
	$number1 = rand(0,9);
	$number2 = rand(0,9);
	$operation_type = array("+", "-", "x");
	$operation = rand(0,2);
	switch($operation){
		case 0:
			$human_new_result = $number1 + $number2;
			break;
		case 1:
			$human_new_result = $number1 - $number2;
			break;
		case 2:
			$human_new_result = $number1 * $number2;
			break;
		default:
			$human_new_result = 0;
			break;
	}
	
	//Form processing
	if(isset($_POST['gpp_contact_submitted'])){
		if($human == $human_result){
			if(!empty($name) && !empty($email) && !empty($message)){
				if(filter_var($email, FILTER_VALIDATE_EMAIL)){
					$name = sanitize_text_field($name);
					$email = sanitize_email($email);
					$message = sanitize_textarea_field($message);
					$aski = array(
						'comment_author' => $name,
						'comment_author_email' => $email,
						'comment_author_url' => '',
						'comment_content' => $message
					);
					if(!gpp_askimet_spam_check($aski)){
						$mail_message = $name . ' wrote the following message:' . "\r\n" . $message;
						$sent = wp_mail($to, $subject, $mail_message, $headers); //send mail
						if($sent){
							setcookie("gpp_contact_form_sent", true, time() + (60 * 20));
							$show_form = false;
							unset($_POST);
							$response_color = $success_color;
							$response_msg = $responses[4];
						}else{
							$response_color = $error_color;
							$response_msg = $responses[3];
						}
					}else{
						$response_color = $error_color;
						$response_msg = $responses[3];
					}
				}else{
					$response_color = $error_color;
					$response_msg = $responses[2];
				}  
			}else{
				$response_color = $error_color;
				$response_msg = $responses[1];
			}                                         
		}else{
			$response_color = $error_color;
			$response_msg = $responses[0];
		}
	}
	
	//Form layout
	if(!isset($_COOKIE['gpp_contact_form_sent'])){
		$output = '
		<style>
			#gpp-contact-form {
				width: 90%;
				margin: 0 auto;
			}
			#gpp-contact-form .response {
				padding: 6px 24px;
				border: 2px solid '.$response_color.';
				color: '.$response_color.';
			}
			#gpp-contact-form .cols-2 {
				display: grid;
				grid-template-columns: 1fr 1fr;
				column-gap: 25px;
			}
			#gpp-contact-form .cols-2 input {
				width: 100%;
			}
			@media screen and (max-width: 678px){
				#gpp-contact-form .cols-2 {
					display: block;
				}
			}
		</style>
		<div id="gpp-contact-form">
		<p class="response">'.$response_msg.'</p>';
		if($show_form){
			$output .= '
			<form action="' . get_permalink() . '" method="post">
			<div class="cols-2">
			<div>
			<p><label for="gpp-contact-name">Name: <span>*</span><br>
			<input id="gpp-contact-name" type="text" name="gpp_contact_name" value="' . esc_attr($name) . '"></label></p>
			</div>
			<div>
			<p><label for="gpp-contact-email">Email: <span>*</span><br>
			<input id="gpp-contact-email" type="email" name="gpp_contact_email" value="' . esc_attr($email) . '"></label></p>
			</div>
			</div>
			<p><label for="gpp-contact-msg">Message: <span>*</span><br>
			<textarea id="gpp-contact-msg" type="text" name="gpp_contact_msg">' . esc_textarea($message) . '</textarea></label></p>
			<p><label for="gpp-contact-human">Verification: <span>*</span><br>
			'. $number1 . ' ' . $operation_type[$operation] . ' ' . $number2 . ' = ' .'
			<input id="gpp-contact-human" type="text" style="width: 60px;" name="gpp_contact_human"></label></p>
			<input type="hidden" name="gpp_contact_human_result" value="'. esc_attr($human_new_result) .'">
			<input type="hidden" name="gpp_contact_submitted" value="1">
			<p><input type="submit"></p>
			</form>';

		}
		$output .= '</div>';
	}else{
		$output = '
			<style>
				#gpp-contact-form .response {
					padding: 6px 24px;
					border: 2px solid '.$success_color.';
					color: '.$success_color.';
				}
			</style>
			<div id="gpp-contact-form">
			<p class="response">'.$responses[5].'</p>
			</div>';
	}
	
	//Render output
	return $output;
}
add_shortcode('gpp-simple-contact-form', 'gpp_generate_contact_form');

//Akismet verification
function gpp_askimet_spam_check($content){
	
	/*
	Using Akismet in Custom Forms
	https://www.binarymoon.co.uk/2010/03/akismet-plugin-theme-stop-spam-dead/
	http://www.beliefmedia.com/akismet-custom-forms

	$content['comment_author'] = $name;
	$content['comment_author_email'] = $email;
	$content['comment_author_url'] = $website;
	$content['comment_content'] = $message;
	*/
 
	// innocent until proven guilty
	$isSpam = FALSE;
	$content = (array) $content;
	if (function_exists('akismet_init')) {

		$wpcom_api_key = get_option('wordpress_api_key');

		if (!empty($wpcom_api_key)) {

			global $akismet_api_host, $akismet_api_port;

			// set remaining required values for akismet api
			$content['user_ip'] = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );
			$content['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			$content['referrer'] = $_SERVER['HTTP_REFERER'];
			$content['blog'] = get_option('home');

			if (empty($content['referrer'])) {
				$content['referrer'] = get_permalink();
			}

			$queryString = '';

			foreach ($content as $key => $data) {
				if (!empty($data)) {
					$queryString .= $key . '=' . urlencode(stripslashes($data)) . '&';
				}
			}

			$response = akismet_http_post($queryString, $akismet_api_host, '/1.1/comment-check', $akismet_api_port);

			if ($response[1] == 'true') {
				update_option('akismet_spam_count', get_option('akismet_spam_count') + 1);
				$isSpam = TRUE;
			}

		}

	}
	return $isSpam;
}

// Function to change sender name
function gpp_mail_sender_name($original_email_from){
    return get_bloginfo('name');
}
add_filter('wp_mail_from_name', 'gpp_mail_sender_name');
	