<?php

namespace BitCode\BitFormPro\Auth;

use BitCode\BitFormPro\Auth\Login;
use BitCode\BitFormPro\Auth\Registration;
use BitCode\BitFormPro\Auth\ForgotResetPassword;

class Auth
{

  public function __construct()
  {
    global $wpdb;
    $this->_wpdb = $wpdb;
    $this->_registration = new Registration();
    $this->_login = new Login();
    $this->_forgetReset = new ForgotResetPassword();
  }


  public function register()
  {
    add_filter('bf_wp_user_auth', array($this, 'userAuthType'), 10, 3);
  }

  public function userAuthType($intgDetail, $postData, $parameter)
  {
    switch ($intgDetail->integration_name) {
      case 'register':
        return $this->_registration->register($intgDetail->integration_details, $postData, $intgDetail->form_id);
        break;
      case 'login':
        return $this->_login->login($intgDetail->integration_details, $postData);
        break;
      case 'forgot':
        return $this->_forgetReset->sendEmailVerification($intgDetail, $postData);
        break;
      case 'reset':
        return $this->_forgetReset->resetPassword($intgDetail->integration_details,  $postData, $parameter);
        break;
      default:
        return "blank";
    }
  }
}
