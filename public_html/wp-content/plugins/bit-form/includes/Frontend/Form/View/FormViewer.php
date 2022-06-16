<?php

namespace BitCode\BitForm\Frontend\Form\View;

use BitCode\BitForm\Frontend\Form\FrontendFormManager;

class FormViewer
{
    private $_theme  = 'Default';
    private $_themeDetails;
    private $_fields;
    private $_layout;
    private $_buttons;
    private $_form;
    private $_error;
    private $_previousValue;
    public function __construct(FrontendFormManager $formManager, $form_contents, $errorMessages = null, $previousValue = null)
    {
        $this->_fields = $form_contents->fields;
        $this->_layout = $form_contents->layout;
        $this->_buttons = isset($form_contents->buttons) ? $form_contents->buttons : '';
        $this->_form = $formManager;
        $this->_error = $errorMessages;
        $this->_previousValue = $previousValue;
    }
    public function getView($hasFile)
    {
        $name = str_replace(' ', null, $this->_theme);
        $file = strpos($name, 'Theme') !== false ? $name . '.php' : $name . 'Theme.php';
        if (file_exists(__DIR__ . '/' . $file)) {
            $className = __NAMESPACE__ . '\\Theme\\' . $name . 'Theme';
            $this->_themeDetails = new $className;
        } else {
            $className = __NAMESPACE__ . '\\Theme\\' . 'DefaultTheme';
            $this->_themeDetails = new $className;
        }
        return $this->setView($hasFile);
    }

    public function setTheme($theme = 'Default')
    {
        $this->_theme = $theme;
    }

    private function setView($hasFile)
    {
        $file_upload_tag = null;
        $formID = $this->_form->getFormID();
        if ($hasFile) {
            $file_upload_tag = 'enctype="multipart/form-data"';
            wp_enqueue_script('bitforms-frontend-file');
        }
        $formIdentifier = $this->_form->getFormIdentifier();
        $fieldHtml = "";
        foreach ($this->_layout->lg as $key => $row) {
            $field_name =  $row->i;
            if ($this->_fields->{$row->i}->typ === 'button' && $this->_fields->{$row->i}->btnTyp === 'submit') {
                $field_name = $formIdentifier;
            }
            // $field_name .= isset($this->_fields->{$row->i}->lbl) ? preg_replace('/[\`\~\!\@\#\$\'\.\s\?\+\-\*\&\|\/\\\!]/', '_', $this->_fields->{$row->i}->lbl) : "";
            $error = isset($this->_error[$field_name]) ? $this->_error[$field_name] : null;
            $valueToEsc = isset($this->_previousValue[$field_name]) ?
                $this->_previousValue[$field_name] : (isset($this->_fields->{$row->i}->val) ? $this->_fields->{$row->i}->val : null);
            if (empty($valueToEsc)) {
                $value = null;
            } else {
                if ((isset($this->_fields->{$row->i}->mul) ||  $this->_fields->{$row->i}->typ === 'check' || $this->_fields->{$row->i}->typ === 'select')) {
                    if ($this->_fields->{$row->i}->typ === 'select' && is_string($valueToEsc)) {
                        $valueToEsc = explode(',', $valueToEsc);
                    }
                    if (is_array($valueToEsc)) {
                        $value = array_map('esc_attr', $valueToEsc);
                    } else {
                        $value = esc_attr($valueToEsc);
                    }
                } else {
                    $value = !is_array($valueToEsc) ? esc_attr($valueToEsc) :
                        esc_attr($valueToEsc[count($valueToEsc) - 1]);
                }
            }

            $fieldHtml .= $this->_themeDetails->inputWrapper(
                $this->_fields->{$row->i},
                $row->i,
                $field_name,
                $error,
                $value,
                $formID
            );
        }

        if (!empty($this->_buttons)) {
            $this->_buttons->name = $formIdentifier;
            $buttonClass = "button-{$formIdentifier}";
            $subBtn = $this->_themeDetails->getField($this->_buttons, $buttonClass, '');
        }

        $formHTML =
            <<<FORM
<div id="f-{$formID}">
    <form novalidate id="form-{$formIdentifier}" class="_frm-bg-{$formID}" action="" $file_upload_tag method='post'>
        <input type="hidden" name="bitforms_token" value="{$this->_form->getFormToken()}">
        <input type="hidden" name="bitforms_id" value="bitforms_{$this->_form->getFormID()}">
        <div class="_frm-{$formID}">
          <div class="_frm-g _frm-g-{$formID}">
            $fieldHtml
          </div>
        </div>
    </form>
</div>
FORM;
        return $formHTML;
    }
}
