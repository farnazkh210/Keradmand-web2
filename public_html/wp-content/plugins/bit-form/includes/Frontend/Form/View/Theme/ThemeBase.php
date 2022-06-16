<?php

namespace BitCode\BitForm\Frontend\Form\View\Theme;
use BitCode\BitForm\Core\Util\FieldValueHandler;

class ThemeBase
{
  public function inputWrapper($field, $rowID, $field_name, $error = null, $value = null, $formID = null)
  {
    $isHidden = !empty($field->valid->hide) && $field->valid->hide ? 'vis-n' : null;
    $isReqSym = empty($field->valid->req) ? null : ' <span class="fld-req-symbol">*</span>';
    $noLabel = ['decision-box', 'html', 'button', 'paypal', 'razorpay', 'recaptcha'];
    $fieldLbl = "";

    if(!in_array($field->typ, $noLabel) && isset($field->lbl)){
       $replaceToBackslash = str_replace('$_bf_$','\\', $field->lbl);
       $fieldLbl = FieldValueHandler::replaceSmartTagWithValue($replaceToBackslash);  
    }

    if(isset($field->ph)){
      $phReplaceToBackslash = str_replace('$_bf_$','\\', $field->ph);
      $field->ph = FieldValueHandler::replaceSmartTagWithValue($phReplaceToBackslash);
     }
    

    $lbl = (!in_array($field->typ, $noLabel) && !isset($field->valid->hideLbl) && isset($field->lbl)) ? "<label class='fld-lbl fld-lbl-$formID' for='$rowID'>" . esc_html($fieldLbl) . $isReqSym . "</label>" : "";
  
    $err = (isset($error) && !empty($error)) ? $error : "";
    $errStyle = !empty($err) ? "style='height: auto'" : "";
    $fieldHTML = $this->getField($field, $rowID, $field_name, $error, $value, $formID);
    $errHTML = '';
    if ($err || isset($field->err)) {
      $errHTML = <<<ERRORHTML
        <div class="error-wrapper" $errStyle>
          <div id="$rowID-error" class="error-txt">$err</div>
        </div>
ERRORHTML;
    }

    return <<<INPUTWRAPPER
      <div class="btcd-fld-itm $rowID $isHidden">
        <div class="fld-wrp fld-wrp-$formID drag">
          $lbl
          $fieldHTML
          $errHTML
        </div>
      </div>
INPUTWRAPPER;
  }

  protected function getField($field, $rowID, $field_name, $error = null, $value = null, $formID = null)
  {
    switch ($field->typ) {
      case 'text':
      case 'username':
      case 'number':
      case 'password':
      case 'email':
      case 'url':
      case 'date':
      case 'datetime-local':
      case 'time':
      case 'month':
      case 'week':
      case 'color':
        return $this->textField($field, $rowID, $field_name, $formID, $error, $value);
      case 'textarea':
        return $this->textArea($field, $rowID, $field_name, $formID, $error, $value);
      case 'check':
        return $this->checkBox($field, $rowID, $field_name, $formID, $error, $value);
      case 'radio':
        return $this->radioBox($field, $rowID, $field_name, $formID, $error, $value);
      case 'select':
        return $this->dropDown($field, $rowID, $field_name, $formID, $error, $value);
      case 'file-up':
        return $this->fileUp($field, $rowID, $field_name, $formID, $error, $value);
      case 'recaptcha':
        return $this->recaptcha($field, $rowID, $field_name, $formID, $error, $value);
      case 'decision-box':
        return $this->decisionBox($field, $rowID, $field_name, $formID, $error, $value);
      case 'html':
        return $this->html($field, $rowID, $field_name, $formID, $error, $value);
      case 'paypal':
        return $this->paypal($field, $rowID, $field_name, $formID, $error, $value);
      case 'razorpay':
        return $this->razorPay($field, $rowID, $field_name, $formID, $error, $value);
      case 'submit':
        return $this->submitBtns($field, $rowID, $field_name, $formID, $error, $value);
      case 'button':
        return $this->button($field, $rowID, $field_name, $formID, $error, $value);
      default:
        break;
    }
  }

  protected function setTag($tag, $value, $attr = null)
  {
    echo "<$tag $attr>" . esc_html($value) . "</$tag>";
  }

  protected function setAttribute($attr, $value = null)
  {
    echo " $attr='" . esc_attr($value) . "' ";
  }
  protected function setSingleValuedAttribute($attr)
  {
    echo " $attr ";
  }
}
