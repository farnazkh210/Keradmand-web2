<?php

/**
 * Get set Form,fields
 */

namespace BitCode\BitForm\Frontend\Form;

/**
 * FrontendFormManager class
 */

use WP_Error;
use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Form\FormManager;
use BitCode\BitForm\Core\Util\DateTimeHelper;
use BitCode\BitForm\Core\Database\FormEntryModel;
use BitCode\BitForm\Frontend\Form\View\FormViewer;
use BitCode\BitForm\Core\WorkFlow\WorkFlowRunHelper;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Form\Validator\FormFieldValidator;
use BitCode\BitForm\Core\Util\ApiResponse as UtilApiResponse;

final class FrontendFormManager extends FormManager
{
    private $_form_identifier;
    private $_form_token;
    private $_form_id;
    // private $_has_upload = false;
    public function __construct($form_id, $shortCodeCounter = null)
    {
        parent::__construct($form_id);
        $this->_form_identifier = 'bitforms_' . $form_id . '_submit_';
        $this->_form_identifier .= !empty(get_post()->ID) ? get_post()->ID : '';
        $this->_form_identifier .= !empty($shortCodeCounter) ? "_$shortCodeCounter" : '';
        $this->_form_token = wp_create_nonce('bitforms_' . $form_id);
        $this->_form_id = $form_id;
    }

    public function getFormIdentifier()
    {
        return $this->_form_identifier;
    }

    public function getFormID()
    {
        return $this->_form_id;
    }

    public function getFormToken()
    {
        return $this->_form_token;
    }

    public function isSubmitted()
    {
        return isset($_POST[$this->_form_identifier]) ? true : false;
    }

    public function getSubmittedFields($submitted_data)
    {
        unset($submitted_data[$this->_form_identifier]);
        return array_keys($submitted_data);
    }

    public function formView($fields = null, $hasFile = false, $errorMessages = null, $previousValue = null)
    {
        $formContents = $this->getFormContent();
        if (!empty($fields)) {
            $formContents->fields = is_string($fields) ? json_decode($fields) : $fields;
        } else {
            $workFlowRunHelper = new WorkFlowRunHelper($this->form_id);
            $workFlowreturnedOnLoad = $workFlowRunHelper->executeOnLoad(
                'create',
                $formContents->fields
            );
            $formContents->fields = empty($workFlowreturnedOnLoad['fields']) ? $formContents->fields : $workFlowreturnedOnLoad['fields'];
        }
        $formViewer = new FormViewer($this, $formContents, $errorMessages, $previousValue);
        return $formViewer->getView($hasFile);
    }

    private function checkEmptySubmission($data, $file)
    {
        $form_fields = $this->getFields();
        $emptySubmission = true;
        foreach ($form_fields as $key => $field) {
            if (!empty($data[$key]) || !empty($file[$key]['name'])) {
                $emptySubmission = false;
                break;
            }
        }
        return $emptySubmission;
    }

    private function getParams()
    {
        $url = parse_url(wp_get_referer());
        $parameter = [];
        if (isset($url['query'])) {
            $queries = explode('&', $url['query']);
            foreach ($queries as $query) {
                list($field, $value) = explode('=', $query);
                $parameter[$field] = $value;
            }
        }
        return $parameter;
    }


    public function handleSubmission()
    {
        $validated = $this->beforeSubmittedValidate();

        if ($validated === true) {
            unset($_POST['hidden_fields']);

            $redirectPage = '';
            $regSuccMsg = '';

            $existAuth = (new IntegrationHandler($this->_form_id))->getAllIntegration('wp_user_auth', 'wp_auth', 1);
            if (!is_wp_error($existAuth) && count($existAuth) > 0) {

                $parameter = $this->getParams();
                $existAuthFilter = has_filter('bf_wp_user_auth');

                if ($existAuthFilter === true) {
                    $result = apply_filters('bf_wp_user_auth', $existAuth[0], $_POST, $parameter);

                    if (isset($result['auth_type']) && $result['auth_type'] === 'register') {
                        if (!$result['success']) {
                            return new WP_Error('errors', __($result['message'], 'bit-form'));
                        } elseif (isset($result['success'])) {
                            $redirectPage = $result['redirect_url'];
                            $regSuccMsg = $result['message'];
                            $newNonce = wp_create_nonce('bitforms_' . $this->_form_id);
                        }
                    } else {
                        if (!$result['success']) {
                            return new WP_Error('errors', __($result['message'], 'bit-form'));
                        } else {
                            return $result;
                        }
                    }
                }
            }

            $saveResponse = $this->saveFormEntry($_POST);
            if (is_wp_error($saveResponse)) {
                return $saveResponse;
            }
            $captchaV3Settings = $this->getCaptchaV3Settings();
            if ($captchaV3Settings) {
                $token = $_POST['g-recaptcha-response'];
                $integrationHandler = new IntegrationHandler(0);
                $allFormIntegrations  = $integrationHandler->getAllIntegration('app', 'gReCaptchaV3');
                if (!is_wp_error($allFormIntegrations)) {
                    foreach ($allFormIntegrations as $integration) {
                        if (!is_null($integration->integration_type) && $integration->integration_type === 'gReCaptchaV3') {
                            $integrationDetails = json_decode($integration->integration_details);
                            $integrationDetails->id = $integration->id;
                            $reCAPTCHA = $integrationDetails;
                        }
                    }
                }
                if (!empty($reCAPTCHA->secretKey)) {
                    $gRecaptchaResponse = HttpHelper::post(
                        'https://www.google.com/recaptcha/api/siteverify',
                        ['secret' => $reCAPTCHA->secretKey, 'response' => $token]
                    );
                    if ($captchaV3Settings && !empty($saveResponse['triggerData'])) {
                        $logID = $saveResponse['triggerData']['logID'];
                        $integId = $reCAPTCHA->id;
                        $saveApiResponse = new UtilApiResponse();
                        $saveApiResponse->apiResponse($logID, $integId, ['type_name' => 'ReCaptcha', 'type' => 'v3'], 'success', $gRecaptchaResponse);
                    }
                }
                unset($_POST['g-recaptcha-response']);
            }
            if (!empty($redirectPage) && empty($saveResponse['redirectPage']) || $saveResponse['redirectPage'] == null) {
                $saveResponse['redirectPage'] = $redirectPage;
            }
            if (!empty($regSuccMsg) && isset($saveResponse['dflt_message'])) {
                $saveResponse['message'] = $regSuccMsg;
            }
            $saveResponse = IntegrationHandler::maybeSetCronForIntegration($saveResponse, 'create');

            $responseMsg = is_array($saveResponse) && !empty($saveResponse) ? $saveResponse : __('Form Submitted Successfully', 'bit-form');
            if (isset($newNonce)) {
                $responseMsg['new_nonce'] = $newNonce;
            }
            $_POST = array();

            return $responseMsg;
        }

        return $validated;
    }

    public function validateFormSubmission($submitted_data)
    {
        $hidden_fields = isset($submitted_data['hidden_fields']) ? $submitted_data['hidden_fields'] : '';
        $submitted_fields = $this->getSubmittedFields($submitted_data);
        $form_fields = $this->getFields();
        $form_fields_names = array_keys($form_fields);
        if ($this->isGCLIDEnabled()) {
            array_push($form_fields_names, 'GCLID');
        }
        foreach ($submitted_fields as $key => $field) {
            if ($field !== 'hidden_fields' && !in_array($field, $form_fields_names) || strpos($hidden_fields, $field) !== false) {
                unset($submitted_data[$field]);
            }
        }
        return $submitted_data;
    }

    public function beforeSubmittedValidate()
    {
        if ($this->verifySubmissionNonce()) {
            if ($this->isExist()) {
                $isRestricted = $this->checkSubmissionRestriction();
                if ($isRestricted && !empty($isRestricted)) {
                    return new WP_Error('spam_detection', $isRestricted[0]);
                }
                if ($this->isTrappedInHoneypot()) {
                    return new WP_Error('spam_detection', __('Token verification failed', 'bit-form'));
                }
                $captchaSettings = $this->getCaptchaSettings();
                $captchaV3Settings = $this->getCaptchaV3Settings();
                if ($captchaSettings || $captchaV3Settings) {
                    $token = $_POST['g-recaptcha-response'];
                    if (!isset($_POST['g-recaptcha-response'])) {
                        return new WP_Error('spam_detection', __('Please verify reCAPTCHA', 'bit-form'));
                    }
                    $integrationHandler = new IntegrationHandler(0);
                    $allFormIntegrations  = $integrationHandler->getAllIntegration('app', $captchaSettings ? 'gReCaptcha' : 'gReCaptchaV3');
                    if (!is_wp_error($allFormIntegrations)) {
                        foreach ($allFormIntegrations as $integration) {
                            if (!is_null($integration->integration_type) && $integration->integration_type === ($captchaSettings ? 'gReCaptcha' : 'gReCaptchaV3')) {
                                $integrationDetails = json_decode($integration->integration_details);
                                $integrationDetails->id = $integration->id;
                                $reCAPTCHA = $integrationDetails;
                            }
                        }
                    }
                    if (!empty($reCAPTCHA->secretKey)) {
                        $gRecaptchaResponse = HttpHelper::post(
                            'https://www.google.com/recaptcha/api/siteverify',
                            ['secret' => $reCAPTCHA->secretKey, 'response' => $token]
                        );
                        $isgReCaptchaVerified = false;
                        if (!is_wp_error($gRecaptchaResponse)) {
                            if (
                                $captchaV3Settings
                                && !empty($gRecaptchaResponse->score)
                                && ((float) $gRecaptchaResponse->score < (float) $captchaV3Settings->score)
                            ) {
                                wp_send_json_error(
                                    __(
                                        $captchaV3Settings->message,
                                        'bit-form'
                                    )
                                );
                            }

                            $isgReCaptchaVerified = $gRecaptchaResponse->success;
                        }
                        if (!$isgReCaptchaVerified) {
                            return new WP_Error('spam_detection', __('Please verify reCAPTCHA', 'bit-form'));
                        }
                    }
                }
                $existAuth = (new IntegrationHandler($this->_form_id))->getAllIntegration('wp_user_auth', 'wp_auth', 1);
                if (!is_wp_error($existAuth) && count($existAuth) > 0 && is_user_logged_in()) {
                    return new WP_Error('auth_error', __('You are already logged in', 'bit-form'));
                }
                $validateForm = $this->validateFormSubmission($_POST);
                $form_fields = $this->getFields();
                $formFieldValidator = new FormFieldValidator($form_fields, $_POST, $_FILES);
                $validUniuqFields = [];

                $existFilter = has_filter('bf_check_duplicate_entry');
                if ($existFilter === true) {
                    $validUniuqFields = apply_filters('bf_check_duplicate_entry', $form_fields, $_POST);
                }

                $validateField = $formFieldValidator->validate('create', $this->_form_id);
                if ($validateForm && $validateField && count($validUniuqFields) == 0) {

                    return true;
                } else {
                    $error = __('Please submit form with valid fields', 'bit-form');
                    if (!$validateForm) {
                        $errorMessages = $error;
                    } else if (count($formFieldValidator->getMessage()) > 0) {
                        $errorMessages = $formFieldValidator->getMessage();
                    } else {
                        $errorMessages = count($validUniuqFields) == 0 ? $error : $validUniuqFields;
                    }
                    return new WP_Error('validation_error', $errorMessages);
                }
            }
            return new WP_Error('unknown_form', __('Form does not exist', 'bit-form'));
        } else {
            return new WP_Error('token_expired', __('Token expired', 'bit-form'));
        }
    }

    public function verifySubmissionNonce()
    {
        if (!isset($_POST['bitforms_token'])) {
            return false;
        }
        $token = sanitize_text_field($_POST['bitforms_token']);
        unset($_POST['_ajax_nonce'], $_POST['action'], $_POST['bitforms_id'], $_POST['bitforms_token']);
        if (!is_user_logged_in()) {
            return true;
        }
        return wp_verify_nonce($token, "bitforms_{$this->form_id}");
    }

    public function setViewCount()
    {
        if (!current_user_can('manage_options')) {
            $update_status =  $this->formModel->update(
                array(
                    'views' => intval(static::$form[0]->views)  + 1
                ),
                array(
                    'id' => $this->form_id
                )
            );
        }
    }

    public function checkSubmissionRestriction()
    {
        $formContents = $this->getFormContent();
        $fromRestrictionSetitingsEnabled = empty($formContents->additional->enabled) ? null : $formContents->additional->enabled;
        $fromRestrictionSetitings = empty($formContents->additional->settings) ? null : $formContents->additional->settings;
        if (is_null($formContents->additional->enabled) || is_null($formContents->additional->settings)) {
            return false;
        }
        $restrictionMessage = array();
        $ipTool = new IpTool();
        $ipAddress = $ipTool->getIP();
        foreach ($fromRestrictionSetitingsEnabled as $restrictionKey => $isEnabled) {
            if ($isEnabled) {
                if ($restrictionKey === 'entry_limit'  && isset($fromRestrictionSetitings->{$restrictionKey})) {
                    $formEntry = new FormEntryModel();
                    $countResult = $formEntry->count(
                        array(
                            'form_id' => $this->form_id
                        )
                    );
                    $count = !empty($countResult[0]) && !empty($countResult[0]->count) ? $countResult[0]->count : false;
                    if ($count && $count >= intval($fromRestrictionSetitings->{$restrictionKey})) {
                        $restrictionMessage[] = __('Sorry!! Entry limit exceeded', "bit-form");
                    }
                }
                if ($restrictionKey === 'onePerIp') {
                    $formEntry = new FormEntryModel();
                    $countResult = $formEntry->count(
                        array(
                            'form_id' => $this->form_id,
                            'user_ip' => ip2long($ipAddress)
                        )
                    );
                    $count = !empty($countResult[0]) && !empty($countResult[0]->count) ? $countResult[0]->count : false;

                    if ($count && $count > 0) {
                        $restrictionMessage[] = __('Sorry!! You have already submitted', "bit-form");
                    }
                }
                if ($restrictionKey === 'is_login' && get_current_user_id() === 0) {
                    $restrictionMessage[] = __($fromRestrictionSetitings->is_login->message, "bit-form");
                }
                if ($restrictionKey === 'empty_submission') {
                    $isEmpty = $this->checkEmptySubmission($_POST, $_FILES);
                    if ($isEmpty) {
                        $restrictionMessage[] = __($fromRestrictionSetitings->empty_submission->message, "bit-form");
                    }
                }
                if ($restrictionKey === 'restrict_form'  && isset($fromRestrictionSetitings->{$restrictionKey})) {
                    $day = empty($fromRestrictionSetitings->{$restrictionKey}->day) ? null : $fromRestrictionSetitings->{$restrictionKey}->day;
                    $date = empty($fromRestrictionSetitings->{$restrictionKey}->date) ? null : $fromRestrictionSetitings->{$restrictionKey}->date;
                    $time = empty($fromRestrictionSetitings->{$restrictionKey}->time) ? null : $fromRestrictionSetitings->{$restrictionKey}->time;

                    $isdayOk = $isdateOk = $istimeOk = true;
                    $dayNotOkMsg = $dateNotOkMsg = $timeNotOkMsg = '';
                    $dateTimeHelper = new DateTimeHelper();
                    if (
                        !empty($day)
                        && is_array($day)
                        && (in_array("Friday", $day)
                            || in_array("Saturday", $day)
                            || in_array("Sunday", $day)
                            || in_array("Monday", $day)
                            || in_array("Tuesday", $day)
                            || in_array("Wednesday", $day)
                            || in_array("Thursday", $day))
                        && (!in_array($dateTimeHelper->getDay('full-name'), $day))
                    ) {
                        $isdayOk = false;
                        $dayMsgVarsFormat = '';
                        foreach ($day as $dayIndex => $dayValue) {
                            if ($dayIndex > 0) {
                                $dayMsgVarsFormat .= ', ';
                            }
                            $dayMsgVarsFormat .= '%s';
                        }
                        $dayNotOkMsg = vsprintf(__("in $dayMsgVarsFormat", 'bit-form'), $day);
                    }
                    if (
                        !empty($day)
                        && is_array($day)
                        && (in_array("Custom", $day))
                    ) {
                        $startDate = empty($date->from) ? '00-00-0000' : $date->from;
                        $endDate = empty($date->to) ? '00-00-0000' : $date->to;
                        if (!empty($date->from) && strpos($startDate, 'T') !== false) {
                            $startDate = $dateTimeHelper->getDate($startDate, false, null, 'm-d-Y');
                        }
                        if (!empty($date->to) && strpos($endDate, 'T') !== false) {
                            $endDate = $dateTimeHelper->getDate($endDate, false, null, 'm-d-Y');
                        }
                        $currentDate = $dateTimeHelper->getDate(null, null, null, 'm-d-Y');
                        if (!($currentDate >= $startDate && $currentDate <= $endDate)) {
                            $isdateOk = false;
                            $dateNotOkMsg = sprintf(__("within %s to %s", 'bit-form'), $startDate, $endDate);
                        }
                    }

                    if (!empty($time)) {
                        $startTime = empty($time->from) ? '00:00' : $time->from;
                        $endTime = empty($time->to) ? '23:59.999' : $time->to;
                        $currentTime = $dateTimeHelper->getTime(null, null, null, 'H:i');
                        if (!($currentTime >= $startTime && $currentTime <= $endTime)) {
                            $istimeOk = false;
                            $startTime = $dateTimeHelper->getTime($startTime, 'H:i', null);
                            $endTime = $dateTimeHelper->getTime($endTime, 'H:i', null);
                            $isTimeOk = false;
                            $timeNotOkMsg = sprintf(__("%s to %s", 'bit-form'), $startTime, $endTime);
                        }
                    }

                    if (!($isdateOk && $isdayOk && $istimeOk)) {
                        if (!$isdayOk) {
                            $restrictionMessage[] = !empty($timeNotOkMsg) ? sprintf(__("Form is available %s From %s", 'bit-form'), $dayNotOkMsg, $timeNotOkMsg) :
                                sprintf(__("Form is available %s", 'bit-form'), $dayNotOkMsg, $timeNotOkMsg);
                        } elseif (!$isdateOk) {
                            $restrictionMessage[] = !empty($timeNotOkMsg) ? sprintf(__("Form is available %s From %s", 'bit-form'), $dateNotOkMsg, $timeNotOkMsg) :
                                sprintf(__("Form is available %s", 'bit-form'), $dateNotOkMsg, $timeNotOkMsg);
                        } elseif (!$istimeOk) {
                            $restrictionMessage[] = sprintf(__("Form is available on %s", 'bit-form'), $timeNotOkMsg);
                        }
                    }
                }
                if ($restrictionKey === 'blocked_ip'  && isset($fromRestrictionSetitings->{$restrictionKey})) {
                    $isIpBlocked = false;
                    foreach ($fromRestrictionSetitings->{$restrictionKey} as $ipIndex => $ipDetails) {
                        if (!empty($ipDetails->status) && $ipDetails->status && !empty($ipDetails->ip) && $ipDetails->ip === $ipAddress) {
                            $isIpBlocked = true;
                            break;
                        }
                    }
                    if ($isIpBlocked) {
                        $restrictionMessage[] = sprintf(__("Sorry!! Your IP address is %s, Blocked from submitting the form", 'bit-form'), $ipAddress);
                    }
                }
                if ($restrictionKey === 'private_ip'  && isset($fromRestrictionSetitings->{$restrictionKey})) {
                    $isIpWhiteListed = false;
                    foreach ($fromRestrictionSetitings->{$restrictionKey} as $ipIndex => $ipDetails) {
                        if (!empty($ipDetails->status) && $ipDetails->status && !empty($ipDetails->ip) && $ipDetails->ip === $ipAddress) {
                            $isIpWhiteListed = true;
                            break;
                        }
                    }
                    if (!$isIpWhiteListed) {
                        $restrictionMessage[] = sprintf(__("Sorry!! Your IP address is %s, Blocked from submitting the form", 'bit-form'), $ipAddress);
                    }
                }
            }
        }
        return $restrictionMessage;
    }

    public function honeypotTrap()
    {
        if ($this->isHoneypotActive()) {
            $time = \time();
            $token = base64_encode(base64_encode($time . "." . wp_hash(wp_get_session_token() . $time)));
            $script = "document.addEventListener('DOMContentLoaded',(event)=>{ let frm=document.getElementById('form-{$this->_form_identifier}'),token=document.createElement('input');token.type='hidden',token.name='token',token.value='$token',frm.prepend(token);let nam=document.createElement('input');nam.type='text',nam.className='vis-n',nam.name='{$token}.name',frm.prepend(nam);let em=document.createElement('input');em.type='email',em.className='vis-n',em.name='{$token}.email',frm.prepend(em);let msg=document.createElement('textarea');msg.className='vis-n',msg.name='{$token}.message',frm.prepend(msg);})";
            wp_add_inline_script('bitforms-frontend-script', $script, 'after');
        }
        return;
    }

    /**
     * Will check if form is submitted by a bot
     *
     * @return Boolean true - if submitted by bot else false
     */
    public function isTrappedInHoneypot()
    {
        if (!$this->isHoneypotActive()) {
            return false;
        }

        if (empty($_POST['token'])) {
            return true;
        } else {
            $token = $_POST['token'];
        }

        $dtoken = explode('.', base64_decode(base64_decode($token)))[1];
        $time = explode('.', base64_decode(base64_decode($token)))[0];

        if (time() - $time < 6 || hash_equals($dtoken, wp_hash(wp_get_session_token() . $time)) === false) {
            return true;
        }
        if (
            !empty($_POST[$token . '.name'])
            || !empty($_POST[$token . '_name'])
            || !empty($_POST[$token . '.email'])
            || !empty($_POST[$token . '_email'])
            || !empty($_POST[$token . '.message'])
            || !empty($_POST[$token . '_message'])
        ) {
            return true;
        }
        unset($_POST['token'], $_POST[$token . 'name'], $_POST[$token . 'email'], $_POST[$token . 'message']);
        return false;
    }

    public function isHoneypotActive()
    {
        $formContents = $this->getFormContent();
        $enabled = empty($formContents->additional->enabled) ? null : $formContents->additional->enabled;
        if (!empty($enabled->honeypot) && $enabled->honeypot) {
            return true;
        }
        return false;
    }

    public function checkPaymentFields()
    {
        $formContents = $this->getFormContent();
        $fields = $formContents->fields;

        $payments = [];
        foreach ($fields as $fldData) {
            if ($fldData->typ === 'paypal' && property_exists($fldData, 'payIntegID')) {
                $payments['paypalKey'] = $this->getClientKey($fldData->payIntegID, 'clientID');
            } else if ($fldData->typ === 'razorpay' && property_exists($fldData->options, 'payIntegID')) {
                $payments['razorpayKey'] = $this->getClientKey($fldData->options->payIntegID, 'apiKey');
            }
        }

        return $payments;
    }

    private function getClientKey($integID, $keyName)
    {
        $client = '';
        if (!empty($integID)) {
            $integrationHandler = new IntegrationHandler(0);
            $integration = $integrationHandler->getAIntegration($integID, 'app', 'payments');
            if (!is_wp_error($integration)) {
                $integration_details = json_decode($integration[0]->integration_details);
                $client = base64_encode($integration_details->{$keyName});
            }
        }
        return $client;
    }
}
