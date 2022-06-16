<?php

/**
 * ZohoBigin Integration
 *
 */

namespace BitCode\BitFormPro\Integration\Gclid;

use BitCode\BitForm\Core\Util\IpTool;
use BitCode\BitForm\Core\Util\HttpHelper;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
use BitCode\BitFormPro\Integration\Gclid\ApiHelper;
use BitCode\BitFormPro\Core\Database\GclidInfoModel;

/**
 * Provide functionality for ZohoCrm integration
 */
class GclidHandler
{
    private $_formID;
    private $_integrationID;

    public function __construct($integrationID, $fromID)
    {
        $this->_formID = $fromID;
        $this->_integrationID = $integrationID;
    }

    /**
     * Helps to register ajax function's with wp
     *
     * @return null
     */
    public static function registerAjax()
    {
        add_action('wp_ajax_bitforms_google_generate_token', array(__CLASS__, 'generateTokens'));
        add_action('wp_ajax_bitforms_save_google_refresh_token', array(__CLASS__, '_saveRefreshedToken'));
        add_action('wp_ajax_bitforms_get_gclid_info', array(__CLASS__, 'getGclidInfo'));
        add_action('wp_ajax_bitform_google_adword_config', array(__class__, 'getGoogleAdwordConfig'));
    }

    /**
     * Process ajax request for generate_token
     *
     * @return JSON zoho bigin api response and status
     */
    public static function generateTokens()
    {
        if (isset($_REQUEST['_ajax_nonce']) && wp_verify_nonce($_REQUEST['_ajax_nonce'], 'bitforms_save')) {
            $inputJSON = file_get_contents('php://input');
            $requestsParams = json_decode($inputJSON);
            if (
                empty($requestsParams->clientId)
                || empty($requestsParams->clientSecret)
                || empty($requestsParams->redirectURI)
                || empty($requestsParams->code)
            ) {
                wp_send_json_error(
                    __(
                        'Requested parameter is empty',
                        'bitform'
                    ),
                    400
                );
            }
            $apiEndpoint = \urldecode('https://oauth2.googleapis.com/token');
            $requestParams = array(
                "grant_type" => "authorization_code",
                "client_id" => \urldecode($requestsParams->clientId),
                "client_secret" => \urldecode($requestsParams->clientSecret),
                "redirect_uri" => \urldecode($requestsParams->redirectURI),
                "code" => \urldecode($requestsParams->code)
            );
            $apiResponse = HttpHelper::post($apiEndpoint, $requestParams);
            if (is_wp_error($apiResponse) || !empty($apiResponse->error)) {
                wp_send_json_error(
                    empty($apiResponse->error) ? 'Unknown' : $apiResponse->error,
                    400
                );
            }
            $license = get_option('bitformpro_integrate_key_data');
            $googleAdsApi = new ApiHelper($apiResponse, $requestsParams->clientCustomerId, $license['key']);
            $response = $googleAdsApi->authenticateGoogleAdword();
            $xml_data = simplexml_load_string($response->data);
            $json = json_encode($xml_data);
            $authenticateCustomer = json_decode($json, TRUE);
            if (is_wp_error($authenticateCustomer) || !empty($authenticateCustomer['ApiError'])) {
                wp_send_json_error(
                    empty($authenticateCustomer['ApiError']) ? 'Unknown' : $authenticateCustomer['ApiError']['type'],
                    400
                );
            }
            $apiResponse->generates_on = \time();
            wp_send_json_success($apiResponse, 200);
        } else {
            wp_send_json_error(
                __(
                    'Token expired',
                    'bitform'
                ),
                401
            );
        }
    }
    /**
     * Helps to refresh zoho bigin access_token
     *
     * @param  Array $apiData Contains required data for refresh access token
     * @return JSON  $tokenDetails API token details
     */
    public static function _refreshAccessToken($apiData)
    {
        if (
            empty($apiData->clientId)
            || empty($apiData->clientSecret)
            || empty($apiData->tokenDetails)
        ) {
            return false;
        }
        $tokenDetails = $apiData->tokenDetails;

        $apiEndpoint = "https://oauth2.googleapis.com/token";
        $requestParams = array(
            "grant_type" => "refresh_token",
            "client_id" => $apiData->clientId,
            "client_secret" => $apiData->clientSecret,
            "refresh_token" => $tokenDetails->refresh_token,
        );

        $apiResponse = HttpHelper::post($apiEndpoint, $requestParams);
        if (is_wp_error($apiResponse) || !empty($apiResponse->error)) {
            return false;
        }
        $tokenDetails->generates_on = \time();
        $tokenDetails->access_token = $apiResponse->access_token;
        return $tokenDetails;
    }

    /**
     * Save updated access_token to avoid unnecessary token generation
     *
     * @param Integer $fromID        ID of Integration related form
     * @param Integer $integrationID ID of Zoho bigin Integration
     * @param Obeject $tokenDetails  refreshed token info
     *
     * @return null
     */
    public static function _saveRefreshedToken()
    {
        \ignore_user_abort();
        if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
            unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
            $inputJSON = file_get_contents('php://input');
            $requestsParams = json_decode($inputJSON);
            $integrationHandler = new IntegrationHandler(0, IpTool::getUserDetail());
            $googleDetails = $integrationHandler->getAllIntegration('app', 'Google');
            if (isset($googleDetails->errors['result_empty'])) {
                $result = $integrationHandler->saveIntegration('Gclid', 'Google', json_encode($requestsParams), 'app');
            } else {
                $result = $integrationHandler->updateIntegration($googleDetails[0]->id, 'Gclid', 'Google', json_encode($requestsParams), 'app');
            }
            if (is_wp_error($result)) {
                return false;
            }
            wp_send_json_success($result, 200);
        }
    }
    public static function getGclidInfo()
    {
        if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
            unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
            $inputJSON = file_get_contents('php://input');
            $requestsParams = json_decode($inputJSON);
            $gclid = $requestsParams->gclid;
            $gclidInfoModel = new GclidInfoModel();
            $gclidInfo = $gclidInfoModel->gclidDetail($gclid);
            if (is_wp_error($gclidInfo)) {
                return false;
            }
            wp_send_json_success($gclidInfo, 200);
        }
    }

    public static function getGoogleAdwordConfig()
    {
        if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
            unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
            $integrationHandler = new IntegrationHandler(0, IpTool::getUserDetail());
            $googleDetails = $integrationHandler->getAllIntegration('app', 'Google');
            if (is_wp_error($googleDetails)) {
                return false;
            }
            wp_send_json_success($googleDetails, 200);
        }
    }
}
