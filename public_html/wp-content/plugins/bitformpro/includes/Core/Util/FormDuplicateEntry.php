<?php

namespace BitCode\BitFormPro\Core\Util;

class FormDuplicateEntry
{
    private $_form_fields = null;
    private $_submitted_fields = null;
    // private $_submitted_files = null;
    private $_messages = array();

    public function register()
    {
        add_filter('bf_check_duplicate_entry', array($this, 'checkDuplicateEntry'), 10, 2);
    }

    public function checkDuplicateEntry($form_fields, $data = '')
    {
        $this->_form_fields = $form_fields;

        if (empty($this->_form_fields)) {
            return;
        }
        $this->_submitted_fields = $data;

        foreach ($this->_form_fields as $field_name => $field_data) {
            $this->_form_fields[$field_name]['value'] = empty($this->_submitted_fields[$field_name]) ? null : $this->_submitted_fields[$field_name];
            if (!empty($this->_submitted_fields[$field_name])) {
                $entryUniqueErrObj = isset($field_data['entryUnique']) ? $field_data['entryUnique'] : null;
                $userUniqueErrObj = isset($field_data['userUnique']) ? $field_data['userUnique'] : null;
                $value = isset($this->_submitted_fields[$field_name]) ? $this->_submitted_fields[$field_name] : '';
                switch ($field_data['type']) {
          case 'text': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'textarea': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'email': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              if ($userUniqueErrObj && isset($userUniqueErrObj->show) && $userUniqueErrObj->show) {
                  $this->userUniquErrMsg($field_name, $value, $userUniqueErrObj, $field_data['type']);
              }
              break;
            }
            case 'username': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              if ($userUniqueErrObj && isset($userUniqueErrObj->show) && $userUniqueErrObj->show) {
                  $this->userUniquErrMsg($field_name, $value, $userUniqueErrObj, $field_data['type']);
              }
              break;
            }
            case 'password': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'number': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'url': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'date': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'check': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $value = json_encode($this->_submitted_fields[$field_name]);
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'radio': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'select': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          case 'color': {
              if ($entryUniqueErrObj && isset($entryUniqueErrObj->show) && $entryUniqueErrObj->show) {
                  $this->entryUniquErrMsg($field_name, $value, $entryUniqueErrObj);
              }
              break;
            }
          default:
            break;
        }
            }
        }
        return  $this->_messages;
    }

    private function isEntryExists($metaKey, $metaValue)
    {
        global $wpdb;
        $results = $wpdb->get_results("SELECT meta_id FROM {$wpdb->prefix}bitforms_form_entrymeta WHERE `meta_key`='$metaKey' && `meta_value`='$metaValue'", OBJECT);
        return $results;
    }
    private function isUserExists($value, $type)
    {
        global $wpdb;
        $key = $type == 'email' ? 'user_email' : 'user_login';
        $result = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}users WHERE `$key`='$value'", OBJECT);
        return $result;
    }

    private function entryUniquErrMsg($field_name, $value, $unique)
    {
        if ($this->isEntryExists($field_name, $value)) {
            $errMesssage = isset($unique->msg) ? $unique->msg : $unique->dflt;
            $this->_messages[$field_name] = __(strip_tags($errMesssage), 'bitform');
        }
    }

    private function userUniquErrMsg($field_name, $value, $unique, $type)
    {
        if ($this->isUserExists($value, $type)) {
            $errMesssage = isset($unique->msg) ? $unique->msg : $unique->dflt;
            $this->_messages[$field_name] = __(strip_tags($errMesssage), 'bitform');
        }
    }
}
