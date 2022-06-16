<?php

namespace BitCode\BitForm\Frontend\Form;

use WP_Error;
use BitCode\BitForm\Core\WorkFlow\WorkFlowRunHelper;
use BitCode\BitForm\Frontend\Form\FrontendFormManager;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitForm\Core\Form\Validator\FormFieldValidator;
use BitCode\BitForm\Core\Util\FieldValueHandler;

final class FrontendFormHandler
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'loadAssets'));
        add_shortcode('bitform', array($this, 'handleFrontendRenderRequest'));
    }

    private function validPassowordResetToken($token, $userID, $formId)
    {
        $existResetInteg = (new IntegrationHandler($formId))->getAllIntegration('wp_user_auth', 'wp_auth', 1);
        if (!is_wp_error($existResetInteg) && count($existResetInteg) > 0) {
            if ($existResetInteg[0]->integration_name === 'reset') {
                $user = get_userdata($userID);
                if ($user) {
                    $validKey = check_password_reset_key($token, $user->user_login);
                    if (is_wp_error($validKey)) {
                        echo "<div id='bf-resp' style='display:grid;justify-content:center;color:#860000;'>This password reset token is invalid.</div>";
                        exit();
                    }
                } else {
                    echo "<div id='bf-resp' style='display:grid;justify-content:center;color:#860000;'>Invalid User!!</div>";
                    exit();
                }
            }
        }
    }

    public function handleFrontendRenderRequest($atts)
    {
        static $shortCodeCounter = 0;
        $shortCodeCounter += 1;
        $atts = shortcode_atts(
            array('id' => 0),
            $atts
        );
        $formID = intval($atts['id']);

        if (!$formID) {
            return __('Form ID cannot be empty', 'bit-form');
        }
        static $formsInpage = array();
        $FrontendFormManager = new FrontendFormManager($formID, $shortCodeCounter);
        if (!$FrontendFormManager->isExist()) {
            return sprintf(__('#%s no. Form doesn\'t exists', 'bit-form'), $formID);
        }
        if (!empty($_GET['token']) && !empty($_GET['id'])) {
            $this->validPassowordResetToken($_GET['token'], $_GET['id'], $formID);
        }

        $isBufferStarted = false;
        $reqField = $_SERVER['QUERY_STRING'];
        $previousValue = [];
        $errorMessages = [];
        if (!empty($reqField)) {

            foreach (explode('&', $reqField) as $keyValue) {
                // $pattern = '/([a-zA-Z0-9])([a-zA-Z])\=+/';
                $pattern = '/([^.]+)=(.*?)([^.]+)/';
                $matches = preg_match($pattern, $keyValue, $matchFormat);
                if ($matches) {
                    list($field, $value) = explode('=', $keyValue, 2);

                    if ('' == trim($value)) {
                        continue;
                    }

                    $previousValue[$field][] = sanitize_text_field(urldecode($value));
                }
            }
        }
        $FormIdentifier = esc_js($FrontendFormManager->getFormIdentifier());
        $nonce = $FrontendFormManager->getFormToken();
        $file =  count($FrontendFormManager->getUploadFields()) > 0 ? $FrontendFormManager->getUploadFields() : false;
        if ($FrontendFormManager->isSubmitted()) {
            $submitResp = $FrontendFormManager->handleSubmission();
            if (!is_wp_error($submitResp) && isset($submitResp['redirectPage'])) {
                if (!headers_sent()) {
                    wp_safe_redirect($submitResp['redirectPage']);
                    exit;
                } else {
                    $isBufferStarted = true;
                    echo "<script type='text/javascript'>window.onload = function() {window.location.replace('" . $submitResp['redirectPage'] . "')}</script>";
                }
            } elseif (is_wp_error($submitResp)) {
                $errors = $submitResp->get_error_message();
                if (\is_string($errors)) {
                    if (!$isBufferStarted) {
                        $isBufferStarted = true;
                        ob_start();
                    }
                    echo "<div id='bf-resp' style='display:grid;justify-content:center'>" . wp_kses_post($errors)  . "</div>";
                } elseif (isset($errors['$form'])) {
                    if (!$isBufferStarted) {
                        $isBufferStarted = true;
                        ob_start();
                    }
                    foreach ($errors['$form'] as $error) {
                        echo "<div id='bf-resp' style='display:grid;justify-content:center'>" . wp_kses_post($error) . "</div>";
                    }
                    unset($errors['$form']);
                    $errorMessages = $errors;
                }
                if (!empty($errors)) {
                    $errorMessages = $errors;
                }
                $previousValue  = $_POST;
                $_POST = [];
            }
        }
        if ($FrontendFormManager->checkStatus()) {
            $FrontendFormManager->setViewCount();

            if (isset($_REQUEST) && count($previousValue) > 0) {
                $formContent = $FrontendFormManager->getFormContentWithValue($previousValue);
                $fields  =  $formContent->fields;
                $layout  = $formContent->layout;
                $buttons =  !empty($formContent->buttons) ? $formContent->buttons : '';
                $additional = $formContent->additional;
            } else {
                $formContent = $FrontendFormManager->getFormContent();
                $fields  =  $formContent->fields;
                $layout  = $formContent->layout;
                $buttons =  !empty($formContent->buttons) ? $formContent->buttons : '';
                $additional = $formContent->additional;
            }

            $captchaV3Settings = $FrontendFormManager->getCaptchaV3Settings();
            if ($FrontendFormManager->getCaptchaSettings() || $captchaV3Settings) {
                $integrationHandler = new IntegrationHandler(0);
                $allFormIntegrations  = $integrationHandler->getAllIntegration('app');
                if (!is_wp_error($allFormIntegrations)) {
                    foreach ($allFormIntegrations as $integration) {
                        if (
                            $FrontendFormManager->getCaptchaSettings()
                            && !is_null($integration->integration_type)
                            && $integration->integration_type === 'gReCaptcha'
                        ) {
                            $integrationDetails = json_decode($integration->integration_details);
                            $integrationDetails->id = $integration->id;
                            $reCAPTCHA = $integrationDetails;
                            $reCAPTCHAVersion = 'v2';
                        }

                        if ($captchaV3Settings) {
                            if (!is_null($integration->integration_type) && $integration->integration_type === 'gReCaptchaV3') {
                                $integrationDetails = json_decode($integration->integration_details);
                                $integrationDetails->id = $integration->id;
                                $reCAPTCHA = $integrationDetails;
                                $reCAPTCHAVersion = 'v3';
                            }
                        }
                    }
                }
            }
            wp_enqueue_script('bitforms-frontend-script');
            if ($file) {
                wp_enqueue_script('bitforms-frontend-file');
            }

            // if ($captchaV3Settings && !empty($reCAPTCHA->siteKey)) {
            //     wp_enqueue_script('bitform-recaptchav3', "https://www.google.com/recaptcha/api.js?render={$reCAPTCHA->siteKey}");
            // }
            $fieldsKey =  $FrontendFormManager->getFieldsKey();
            $workFlowreturnedOnUserInput = null;
            if (!empty($formContent->workFlowExist)) {
                $workFlowRunHelper = new WorkFlowRunHelper($formID);
                if (!empty($formContent->workFlowExist->onload)) {
                    $workFlowreturnedOnLoad = $workFlowRunHelper->executeOnLoad(
                        'create',
                        $fields
                    );
                    if (!empty($workFlowreturnedOnLoad['fields'])) {
                        $fields = $workFlowreturnedOnLoad['fields'];
                    }
                }
                if (!empty($formContent->workFlowExist->oninput)) {
                    $workFlowreturnedOnUserInput = $workFlowRunHelper->executeOnUserInput(
                        'create',
                        $fields
                    );
                }
            }

            if (!wp_style_is('bitform-style' . $formID)) {
                $this->loadAssets(true, $formID, $file);
            }

            if ($captchaV3Settings && !empty($captchaV3Settings->hideReCaptcha)) {
                $styles = '.grecaptcha-badge { visibility: hidden; }';
                add_action('wp_footer', function () use ($styles) {
                    echo '<style>' . $styles . '</style>';
                });
            }
            $noLabel = ['decision-box', 'html', 'button', 'paypal', 'razorpay', 'recaptcha'];
            $objectToArrayConvert = json_decode(json_encode($fields), true);
            foreach ($objectToArrayConvert as $fldKey => $field) {
                if (!in_array($field['typ'], $noLabel) && isset($field['lbl'])) {
                    $lblReplaceToBackslash = str_replace('$_bf_$', '\\', $field['lbl']);
                    $objectToArrayConvert[$fldKey]['lbl'] = FieldValueHandler::replaceSmartTagWithValue($lblReplaceToBackslash);
                }
            }
            $FrontendFormManager->honeypotTrap();

            $bitFormFrontArr = array(
                'ajaxURL'   => admin_url('admin-ajax.php'),
                'nonce' => $nonce,
                'version' => BITFORMS_VERSION,
                // 'contentID' => $FormIdentifier,
                'layout' => $layout,
                'fields' => json_decode(json_encode($objectToArrayConvert), FALSE),
                'buttons' => $buttons,
                'fieldsKey' => $fieldsKey,
                'file'   => $file,
                'formId' => $formID,
                'GCLID' => $FrontendFormManager->isGCLIDEnabled(),
                'assetUrl' => BITFORMS_ASSET_URI,
                'gRecaptchaSiteKey'   => !empty($reCAPTCHA->siteKey) ? $reCAPTCHA->siteKey : null,
                'gRecaptchaVersion'   => !empty($reCAPTCHAVersion) ? $reCAPTCHAVersion : null,
                'conditional' => !empty($workFlowreturnedOnUserInput['conditional']) ? $workFlowreturnedOnUserInput['conditional'] : false,
                'fieldToCheck' => !empty($workFlowreturnedOnUserInput['fieldToCheck']) ? $workFlowreturnedOnUserInput['fieldToCheck'] : false,
                'fieldToChange' => !empty($workFlowreturnedOnUserInput['fieldToChange']) ? $workFlowreturnedOnUserInput['fieldToChange'] : false
            );

            $paymentKeys = $FrontendFormManager->checkPaymentFields();
            if (count($paymentKeys)) {
                $bitFormFrontArr['paymentKeys'] = $paymentKeys;
            }

            if (isset($additional->enabled->validateFocusLost)) {
                $bitFormFrontArr['validateFocusLost'] = true;
            }

            $bitFormsFront = apply_filters(
                'bitforms_localized_script',
                $bitFormFrontArr
            );

            $fields = \json_encode($fields);
            $layout = \json_encode($layout);
            $buttons = \json_encode($buttons);
            if (!\in_array($formID, $formsInpage)) {
                wp_localize_script('bitforms-frontend-script', $FormIdentifier, $bitFormsFront);
            } else {
                $formsInpage[] = $formID;
            }
            $formSpecificScripts = "if(typeof window._bitforms_front==='function'){_bitforms_front('$FormIdentifier')}else{document.addEventListener('DOMContentLoaded',(event)=>{ window._bitforms_front && _bitforms_front('$FormIdentifier')})}";
            wp_add_inline_script('bitforms-frontend-script', $formSpecificScripts, 'after');
            $html = $FrontendFormManager->formView($fields, $file, $errorMessages);
            if (!$isBufferStarted) {
                ob_start();
            } ?>
            <div id=<?php echo esc_attr($FormIdentifier); ?>><?php echo trim($html); ?>
            </div>
<?php
            return ob_get_clean();
        }
    }

    public function loadAssets($recheck = false, $formID = 0, $isFileExists = false)
    {
        global $post;
        if (!empty($post->post_content)) {
            \preg_match_all("/\[bitform\s+id\s*=\s*('|\")\s*(\d+)\s*('|\")\]/", $post->post_content, $shortCode);
        } else {
            $isSCExists = false;
            if (isset($post->ID) && \is_int($post->ID)) {
                global $wpdb;
                $pMetadata = $wpdb->get_results("SELECT meta_value FROM `" . $wpdb->postmeta . "` WHERE `post_id`={$post->ID} AND meta_value LIKE '%[bitform id=%' LIMIT 1");
                if ($pMetadata && !empty($pMetadata[0]->meta_value)) {
                    $isSCExists = true;
                    \preg_match_all("/\[bitform\s+id\s*=\s*('|\")\s*(\d+)\s*('|\")\]/", $pMetadata[0]->meta_value, $shortCode);
                }
            }
            if (!$isSCExists) {
                $shortCode[0] = null;
                $shortCode[1] = null;
                $shortCode[2] = [];
                $shortCode[3] = null;
            }
        }
        if ($formID !== 0) {
            $shortCode[2][] = $formID;
        }
        if (count($shortCode) === 4 && count($shortCode[2]) > 0) {
            wp_enqueue_style(
                'bitforms-style-frontend',
                BITFORMS_ASSET_URI . '/css/components.css',
                array(),
                BITFORMS_VERSION,
                'all'
            );
            foreach ($shortCode[2] as $formID) {
                if (is_readable(BITFORMS_CONTENT_DIR . '/form-styles/bitform-' . $formID . '.css')) {
                    $styleModifyTime = filemtime(BITFORMS_CONTENT_DIR . '/form-styles/bitform-' . $formID . '.css');
                    wp_enqueue_style(
                        'bitform-style' . $formID,
                        BITFORMS_UPLOAD_BASE_URL . '/form-styles/bitform-' . $formID . '.css',
                        array(),
                        $styleModifyTime,
                        'all'
                    );
                }
                if (is_readable(BITFORMS_CONTENT_DIR . '/form-styles/bitform-layout-' . $formID . '.css')) {
                    $layoutModifyTime = filemtime(BITFORMS_CONTENT_DIR . '/form-styles/bitform-layout-' . $formID . '.css');
                    wp_enqueue_style(
                        'bitform-layout-style' . $formID,
                        BITFORMS_UPLOAD_BASE_URL . '/form-styles/bitform-layout-' . $formID . '.css',
                        array(),
                        $layoutModifyTime,
                        'all'
                    );
                }
            }

            if (!$recheck) {
                wp_register_script(
                    'bitforms-frontend-script',
                    BITFORMS_ASSET_FRNT_JS_URI . '/bitformsFrontend.js',
                    array(),
                    BITFORMS_VERSION,
                    true
                );
                wp_register_script(
                    'bitforms-frontend-file',
                    BITFORMS_ASSET_FRNT_JS_URI . '/bitforms-file.js',
                    array(),
                    BITFORMS_VERSION,
                    true
                );
            } else {
                wp_enqueue_script(
                    'bitforms-frontend-script',
                    BITFORMS_ASSET_FRNT_JS_URI . '/bitformsFrontend.js',
                    array(),
                    BITFORMS_VERSION,
                    true
                );
                if ($isFileExists) {
                    wp_enqueue_script(
                        'bitforms-frontend-file',
                        BITFORMS_ASSET_JS_URI . '/bitforms-file.js',
                        array(),
                        BITFORMS_VERSION,
                        true
                    );
                }
            }
        }
    }
}
