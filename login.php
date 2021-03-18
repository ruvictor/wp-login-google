<?php
/**
 * Plugin Name: Login With Google
 * Description: This plugin will add a Login with Google Button
 * Plugin URI: https://vicodemedia.com
 * Author: Victor Rusu
 * Version: 0.0.1
**/

//* Don't access this file directly
defined( 'ABSPATH' ) or die();

/**
 * Google App Configuration
 **/
// call sdk library
require_once 'google-api/vendor/autoload.php';

$gClient = new Google_Client();
$gClient->setClientId("962708155950-m1ovo45qcu6k4mql3sjjjgcv9g62qdm9.apps.googleusercontent.com");
$gClient->setClientSecret("OsWJ8W4c4eSYSrHKRqND95QI");
$gClient->setApplicationName("Vicode Media Login");
$gClient->setRedirectUri("https://localhost/wp-admin/admin-ajax.php?action=vm_login_google");
$gClient->addScope("https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email");

// login URL
$login_url = $gClient->createAuthUrl();

// generate button shortcode
add_shortcode('google-login', 'vm_login_with_google');
function vm_login_with_google(){
    global $login_url;
    $btnContent = '
        <style>
            .googleBtn{
                display: table;
                margin: 0 auto;
                background: #4285F4;
                padding: 15px;
                border-radius: 3px;
                color: #fff;
            }
        </style>
    ';
    if(!is_user_logged_in()){
            // checking to see if the registration is opend
            if(!get_option('users_can_register')){
                return($btnContent . 'Registration is closed!');
            }else{
                return $btnContent . '<a class="googleBtn" href="'.$login_url.'">Login With Google</a>';
            }

    }else{
        $current_user = wp_get_current_user();
        return $btnContent . '<div class="googleBtn">Hi, ' . $current_user->first_name . '! - <a href="/wp-login.php?action=logout">Log Out</a></div>';
    }
}

// add ajax action
add_action('wp_ajax_vm_login_google', 'vm_login_google');
function vm_login_google(){
    // echo "fffff";
    global $gClient;
    // checking for google code
    if (isset($_GET['code'])) {
        $token = $gClient->fetchAccessTokenWithAuthCode($_GET['code']);
        if(!isset($token["error"])){
            // get data from google
            $oAuth = new Google_Service_Oauth2($gClient);
            $userData = $oAuth->userinfo_v2_me->get();
        }

        // check if user email already registered
        if(!email_exists($userData['email'])){
            // generate password
            $bytes = openssl_random_pseudo_bytes(2);
            $password = md5(bin2hex($bytes));
            $user_login = $userData['id'];


            $new_user_id = wp_insert_user(array(
                'user_login'		=> $user_login,
                'user_pass'	 		=> $password,
                'user_email'		=> $userData['email'],
                'first_name'		=> $userData['givenName'],
                'last_name'			=> $userData['familyName'],
                'user_registered'	=> date('Y-m-d H:i:s'),
                'role'				=> 'subscriber'
                )
            );
            if($new_user_id) {
                // send an email to the admin
                wp_new_user_notification($new_user_id);
                
                // log the new user in
                do_action('wp_login', $user_login, $userData['email']);
                wp_set_current_user($new_user_id);
                wp_set_auth_cookie($new_user_id, true);
                
                // send the newly created user to the home page after login
                wp_redirect(home_url()); exit;
            }
        }else{
            //if user already registered than we are just loggin in the user
            $user = get_user_by( 'email', $userData['email'] );
            do_action('wp_login', $user->user_login, $user->user_email);
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            wp_redirect(home_url()); exit;
        }


        var_dump($userData);
    }else{
        wp_redirect(home_url());
        exit();
    }
}

// ALLOW LOGGED OUT users to access admin-ajax.php action
function add_google_ajax_actions(){
    add_action('wp_ajax_nopriv_vm_login_google', 'vm_login_google');
}
add_action('admin_init', 'add_google_ajax_actions');