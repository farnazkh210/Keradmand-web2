<?php

/**
 * User Login
 *
 */

namespace BitCode\BitFormPro\Auth;


/**
 * Provide functionality for Login
 */
class Login
{

  public function __construct()
  {
    global $wpdb;
    $this->_wpdb = $wpdb;
  }

  private function loginFieldMapping($loginMap, $fieldValues)
  {
    $fieldData = [];
    foreach ($loginMap as $fieldKey => $fieldPair) {
      if (!empty($fieldPair->loginField) && !empty($fieldPair->formField)) {
        if ($fieldPair->formField === 'custom' && isset($fieldPair->customValue)) {
          $fieldData[$fieldPair->loginField] = $fieldPair->customValue;
        } else {
          $fieldData[$fieldPair->loginField] = $fieldValues[$fieldPair->formField];
        }
      }
    }
    return $fieldData;
  }

  private function errorMsg($errors, $data)
  {
    
    switch ($errors) {
      case isset($errors['incorrect_password']):
        $data['message'] = $errors['incorrect_password'][0];
        break;
      case isset($errors['invalid_username']):
        $data['message'] = $errors['invalid_username'][0];
        break;
      case isset($errors['invalid_email']):
        $data['message'] = $errors['invalid_email'][0];
        break;
      case isset($errors['empty_username']):
        $data['message'] = $errors['empty_username'][0];
        break;
      case isset($errors['empty_password']):
        $data['message'] = $errors['empty_password'][0];
        break;
      case isset($errors['bitform_confirmation_error']): 
        $data['message'] = $errors['bitform_confirmation_error'][0];
        break;
      default:
        return "";
    }
    return $data;
  }

  public function login($integDetails, $fieldValues)
  {
    $data = [];
    
    if (is_user_logged_in()) {
      $data['success'] = false;
      $data['message'] = "You are already logged in.";
      return $data;
    }

    $integDetails = is_string($integDetails) ? json_decode($integDetails) : $integDetails;
    $userData = $this->loginFieldMapping($integDetails->login_map, $fieldValues);
    $user = wp_authenticate($userData['user_login'], $userData['password']);
    
    if (!is_wp_error($user) && !isset($user->errors)) {
      wp_set_current_user($user->data->ID);
      if (!isset($userData['remember']) && $userData['remember'] != 1) {
        wp_set_auth_cookie($user->data->ID);
      } else {
        wp_set_auth_cookie($user->data->ID, true);
      }
    }

    if (isset($user->errors)) $data = $this->errorMsg($user->errors, $data);

    if (!isset($data['message'])) {
      $data['message'] = $integDetails->succ_msg;
      $data['redirectPage'] = !empty($integDetails->redirect_url) ? $integDetails->redirect_url : home_url('/wp-admin');
      $data['success'] = true;
    } else {
      $data['success'] = false;
    }
    return $data;
  }
}
