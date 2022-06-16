<?php

namespace BitCode\BitForm\Core\Form\Validator;

use BitCode\BitForm\Core\Util\FieldValueHandler;
use BitCode\BitForm\Core\WorkFlow\WorkFlowRunHelper;

final class FormFieldValidator
{
    private $_form_fields = null;
    private $_submitted_fields = null;
    private $_submitted_files = null;
    private $_messages = array();
    public function __construct($form_fields, $submitted_fields, $submitted_files)
    {
        $this->_form_fields = $form_fields;
        $this->_submitted_fields = $submitted_fields;
        $this->_submitted_files = $submitted_files;
        $this->removeUnnecessaryField();
    }

    private function removeUnnecessaryField()
    {
        if (!isset($_POST)) {
            return;
        }
        unset($_POST['bitforms_token'], $_POST['bitforms_id']);
    }


    public function validate($workFlowRun, $formID)
    {
        if (FieldValueHandler::isEmpty($this->_form_fields)) {
            return;
        }
        $hidden_fields = isset($this->_submitted_fields['hidden_fields']) ? $this->_submitted_fields['hidden_fields'] : '';
        unset($this->_submitted_fields['hidden_fields']);
        foreach ($this->_form_fields as $field_name => $field_data) {

            if (isset($this->_submitted_fields[$field_name])) {
                $this->_form_fields[$field_name]['value'] = FieldValueHandler::isEmpty($this->_submitted_fields[$field_name]) ? null : $this->_submitted_fields[$field_name];
            }

            if (
                (isset($field_data['valid']['req'])
                    && $field_data['valid']['req']
                    && $field_data['type'] !== 'file-up'
                    && FieldValueHandler::isEmpty($this->_submitted_fields[$field_name]))
            ) {
                if (strpos($hidden_fields, $field_name) !== false) continue;
                $this->_messages[$field_name] = !FieldValueHandler::isEmpty($field_data['valid']['req']['reqMsg']) ? $field_data['valid']['req']['reqMsg'] : $field_data['label'] . __(' is required.', 'bit-form');
                continue;
            } elseif (
                isset($field_data['valid']['req'])
                && $field_data['valid']['req']
                && $field_data['type'] === 'file-up'
            ) {
                if (strpos($hidden_fields, $field_name) !== false) continue;
                if (FieldValueHandler::isEmpty($this->_submitted_files[$field_name]['name'])) {
                    $this->_messages[$field_name]
                        = !FieldValueHandler::isEmpty($field_data['valid']['req']['reqMsg']) ?
                        $field_data['valid']['req']['reqMsg'] :
                        $field_data['label'] . __(' is required.', 'bit-form');
                    continue;
                }
            } elseif (isset($this->_submitted_fields[$field_name]) && !FieldValueHandler::isEmpty($this->_submitted_fields[$field_name])) {
                switch ($field_data['type']) {
                    case 'email': {
                            if (!$this->validateEmail($this->_submitted_fields[$field_name])) {
                                $this->_messages[$field_name]
                                    = !FieldValueHandler::isEmpty($field_data['valid']['typMsg']) ?
                                    $field_data['valid']['typMsg'] :
                                    $field_data['label'] . __(' should be an email. please provide a valid email address.', 'bit-form');
                            }
                            break;
                        }
                    case 'time': {
                            if (!$this->validateTime($this->_submitted_fields[$field_name])) {
                                $this->_messages[$field_name]
                                    = !FieldValueHandler::isEmpty($field_data['valid']['typMsg']) ?
                                    $field_data['valid']['typMsg'] :
                                    $field_data['label'] . __(' should be Time Format', 'bit-form');
                            }
                            break;
                        }
                    case 'phone': {
                            if (!$this->validatePhone($this->_submitted_fields[$field_name])) {
                                $this->_messages[$field_name]
                                    = !FieldValueHandler::isEmpty($field_data['valid']['typMsg']) ?
                                    $field_data['valid']['typMsg'] :
                                    $field_data['label'] . __(' should be a phone number', 'bit-form');
                            }
                            break;
                        }
                    case 'number': {
                            if (!$this->validateNumber($this->_submitted_fields[$field_name])) {
                                $this->_messages[$field_name]
                                    = !FieldValueHandler::isEmpty($field_data['valid']['typMsg']) ?
                                    $field_data['valid']['typMsg'] :
                                    $field_data['label'] . __(' should be a number', 'bit-form');
                            }
                            break;
                        }
                    case 'url': {
                            if (!$this->validateURL($this->_submitted_fields[$field_name])) {
                                $this->_messages[$field_name]
                                    = !FieldValueHandler::isEmpty($field_data['valid']['typMsg']) ?
                                    $field_data['valid']['typMsg'] :
                                    $field_data['label'] . __(' should be an URL', 'bit-form');
                            }
                            break;
                        }
                    case 'date': {
                            if (!$this->validateDate($this->_submitted_fields[$field_name])) {
                                $this->_messages[$field_name]
                                    = !FieldValueHandler::isEmpty($field_data['valid']['typMsg']) ?
                                    $field_data['valid']['typMsg'] :
                                    $field_data['label'] . __(' should be a date', 'bit-form');
                            }
                            break;
                        }
                    default:
                        break;
                }
            }
        }
        $workFlowRunHelper = new WorkFlowRunHelper($formID);
        $workFlowreturnedOnValidate = $workFlowRunHelper->executeOnValidate(
            $workFlowRun,
            $this->_form_fields,
            $this->_submitted_fields
        );
        if (!empty($workFlowreturnedOnValidate)) {
            $this->_messages['$form'] = $workFlowreturnedOnValidate;
        }

        if (count($this->_messages) > 0) {
            return false;
        } else {
            return true;
        }
    }

    public function getMessage()
    {
        return $this->_messages;
    }

    private function validateEmail($value)
    {
        return preg_match("/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/", $value);
    }

    private function validateTime($value)
    {
        return preg_match('/^([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $value);
    }

    private function validatePhone($value)
    {
        return preg_match('/^([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $value);
    }

    private function validateNumber($value)
    {
        return preg_match('/^(\+|-)?\d+(\.)?\d*$/', $value);
    }

    private function validateURL($value)
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateDate($value)
    {
        $date = date_create_from_format('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
}
