<?php
/**
 * Project: xvn.
 * File: config.php.

 */
 
// Config file is commonly included multiple times. Guard against:
// - duplicated constants (PHP Notice: Constant ... already defined)
// - duplicated session_start() (PHP Notice: A session had already been started)
if (!defined('XVN_CONFIG_LOADED')) {
	define('XVN_CONFIG_LOADED', 1);

	// Start session only if it hasn't been started yet
	if (session_id() === '') {
		session_start();
	}

	date_default_timezone_set('Asia/Ho_Chi_Minh');

	//=============== Custom configuration ==================//
	define('DB_NAME', 'vieclam.vn'); //database name
	define('DB_USER', 'root'); //database user
	define('DB_PASSWORD', ''); //database password
	define('DB_HOST', 'localhost'); //sql server
	// $mail_acc = $this->helper->get_config('smtp_protocol');
	// $mail_pass = $this->helper->get_config('smtp_password');
	/*** define mailer ***/
	define('MAIL_PROTOCOL', 'SMTP');
	define('MAIL_HOST', 'smtp.gmail.com');
	define('MAIL_ACC', 'vuxuancuong98gl@gmail.com');
	define('MAIL_PASS', 'sgap bhor woox labx');
	define('MAIL_PORT', 587);
	define('MAIL_AUTH', true);
	define('MAIL_SECURE', 'tls');

	/*** define Xiao SMS ***/
	define('SMS_API_KEY', 'key-c4a8d21f56a2827fa24a41b3a63dcbb7');
	define('SMS_API_SECRECT', 'C09E8A7C0D6A47BA117A3964A94EB8');

	/*** define Theme ***/
	define('ThemeMaster', 'frontend'); //Replace xpanel by your theme's name
	define('AdminThemeMaster', 'backend'); //Replace xpanel by your admin theme's name

	/*** define site path ***/
	define('XC_URL','http://localhost/vieclam.vn'); //Replace by your site url
}

$siteurl = XC_URL;
/*** template path ***/
$template_path = XC_URL.'/template/'.ThemeMaster; //Warning: Don't change here
$admintemplate_path = XC_URL.'/template/'.AdminThemeMaster; //Warning: Don't change here
$upload_path = XC_URL.'/uploads';
$image_path = XC_URL.'/uploads/images';

/*** Set Application Name ***/
$app_name = 'Cong thong tin viec lam';
?>