<?php

/**
 * Handle Form Create,Update,delete Operation
 *
 */

namespace BitCode\BitForm\Admin\Form;

class CustomFieldHandler
{
    public function updatedEntries($formEntries, $fieldDetails)
    {
        foreach ($formEntries['entries'] as $index => $entry) {
            foreach ($fieldDetails as $field) {
                $fieldKey = $field['key'];
                if (isset($field['customType']) && !empty($entry->$fieldKey) && !empty($field['customType']->hiddenValue)) {
                    $hiddenvalue = $field['customType']->hiddenValue;
                    if ($field['customType']->fieldType == 'taxanomy_field') {
                        $hiddenvalue = $field['customType']->hiddenValue;
                        if (isset($field['mul'])) {
                            $mul = $field['mul'];
                        } else {
                            $mul = '';
                        }

                        $value = $this->getTermFieldValue($field['type'], $entry->$fieldKey, $mul, $hiddenvalue);
                        $formEntries['entries'][$index]->$fieldKey = $value;
                    } else if ($field['customType']->fieldType == "user_field") {
                        $hiddenvalue = $field['customType']->hiddenValue;
                        if (isset($field['mul'])) {
                            $mul = $field['mul'];
                        } else {
                            $mul = '';
                        }

                        $value = $this->getUserFieldValue($field['type'], $entry->$fieldKey, $mul, $hiddenvalue);
                        $formEntries['entries'][$index]->$fieldKey = $value;
                    } else if ($field['customType']->fieldType == "post_field") {
                        $hiddenvalue = $field['customType']->hiddenValue;
                        if (isset($field['mul'])) {
                            $mul = $field['mul'];
                        } else {
                            $mul = '';
                        }

                        $value = $this->getPostFieldValue($field['type'], $entry->$fieldKey, $mul, $hiddenvalue);
                        $formEntries['entries'][$index]->$fieldKey = $value;
                    }
                }
            }
        }
        return $formEntries;
    }

    public function updatedData($form_fields, $toUpdateValues)
    {
        foreach ($form_fields as $field) {
            if (isset($field['customType']) && !empty($toUpdateValues[$field['key']])) {
                if (isset($field['customType']->hiddenValue)) {
                    $hiddenvalue = $field['customType']->hiddenValue;
                    if ($field['customType']->fieldType == 'taxanomy_field') {
                        if (isset($field['mul'])) {
                            $mul = $field['mul'];
                        } else {
                            $mul = '';
                        }

                        $value = $this->getTermFieldValue($field['type'], $toUpdateValues[$field['key']], $mul, $hiddenvalue);
                        $toUpdateValues[$field['key']] = $value;
                    } else if ($field['customType']->fieldType == "user_field") {
                        if (isset($field['mul'])) {
                            $mul = $field['mul'];
                        } else {
                            $mul = '';
                        }

                        $value = $this->getUserFieldValue($field['type'], $toUpdateValues[$field['key']], $mul, $hiddenvalue);
                        $toUpdateValues[$field['key']] = $value;
                    } else if ($field['customType']->fieldType == "post_field") {
                        if (isset($field['mul'])) {
                            $mul = $field['mul'];
                        } else {
                            $mul = '';
                        }

                        $value = $this->getPostFieldValue($field['type'], $toUpdateValues[$field['key']], $mul, $hiddenvalue);
                        $toUpdateValues[$field['key']] = $value;
                    }
                }
            }
        }
        return $toUpdateValues;
    }

    public function getPostFieldValue($type, $fieldValue, $mul, $hiddenvalue)
    {
        $value = '';
        
        if ($type == "select") {
           
          if ($mul == true) {
                $multipleValue = [];
                foreach (explode(',', $fieldValue) as $val) {
                    $exists = get_post($val);
                    if ($exists != false) {
                        $multipleValue[] = $exists->$hiddenvalue;
                    }
                }
                $value = implode(",", $multipleValue);
            } else {
                $exists = get_post($fieldValue);
                if ($exists != false) {
                    $value = is_array($exists->$hiddenvalue) ? $exists->$hiddenvalue : (string) $exists->$hiddenvalue;
                }
            }

        } else if ($type == "radio" || $type == "check") {

            if (is_array(json_decode($fieldValue))) {
                $multipleValues = [];
                foreach (json_decode($fieldValue) as $value) {
                    $exists = get_post($value);
                    if ($exists != false) {
                        $multipleValues[] = $exists->$hiddenvalue;
                    }
                }
                $value = json_encode($multipleValues);
            } else {
                $exists = get_post($fieldValue);
                if ($exists != false) {
                    $value = $exists->$hiddenvalue;
                }
            }

        }
        return $value;
    }

    public function getUserFieldValue($type, $fieldValue, $mul, $hiddenvalue)
    {
        $value = '';
        if ($type == "select") {

            if ($mul == true) {
                $multipleValue = [];
                foreach (explode(',', $fieldValue) as $val) {
                    $exists = get_user_by('ID', $val);
                    if ($exists != false) {
                        $multipleValue[] = $exists->data->$hiddenvalue;
                    }
                }
                $value = implode(",", $multipleValue);
            } else {
                $exists = get_user_by('ID', $fieldValue);
                if ($exists != false) {
                    $value = is_array($exists->data->$hiddenvalue) ? $exists->data->$hiddenvalue : (string) $exists->data->$hiddenvalue;
                }
            }

        } else if ($type == "radio" || $type == "check") {

            if (is_array(json_decode($fieldValue))) {
                $multipleValues = [];
                foreach (json_decode($fieldValue) as $value) {
                    $exists = get_user_by('ID', $value);
                    if ($exists != false) {
                        $multipleValues[] = $exists->data->$hiddenvalue;
                    }
                }
                $value = json_encode($multipleValues);
            } else {
                $exists = get_user_by('ID', $fieldValue);
                if ($exists != false) {
                    $value = $exists->data->$hiddenvalue;
                }
            }

        }
        return $value;
    }

    public function getTermFieldValue($type, $fieldValue, $mul, $hiddenvalue)
    {
        $value = '';
        if ($type == "select") {

            if ($mul == true) {
                $multipleValue = [];
                foreach (explode(',', $fieldValue) as $val) {
                    $exists = get_term_by('term_taxonomy_id', $val);
                    if ($exists != false) {
                        $multipleValue[] = $exists->$hiddenvalue;
                    }
                }
                $value = implode(",", $multipleValue);
            } else {
                $exists = get_term_by('term_taxonomy_id', $fieldValue);
                if ($exists != false) {
                    $value = is_array($exists->$hiddenvalue) ? $exists->$hiddenvalue : (string) $exists->$hiddenvalue;
                }
            }

        } else if ($type == "radio" || $type == "check") {

            if (is_array(json_decode($fieldValue))) {
                $multipleValues = [];
                foreach (json_decode($fieldValue) as $value) {
                    $exists = get_term_by('term_taxonomy_id', $value);
                    if ($exists != false) {
                        $multipleValues[] = $exists->$hiddenvalue;
                    }
                }
                $value = json_encode($multipleValues);
            } else {
                $exists = get_term_by('term_taxonomy_id', $fieldValue);
                if ($exists != false) {
                    $value = $exists->$hiddenvalue;
                }
            }
            
        }
        return $value;
    }
}
