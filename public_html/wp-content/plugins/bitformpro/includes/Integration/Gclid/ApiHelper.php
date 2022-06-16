<?php

namespace BitCode\BitFormPro\Integration\Gclid;

use BitCode\BitForm\Core\Util\HttpHelper;

class ApiHelper
{

    private $_defaultHeader;
    private $_licenseKey;

    public function __construct($tokenDetails, $customerId, $licenseKey)
    {
        $this->_defaultHeader['Authorization'] = "Bearer {$tokenDetails->access_token}";
        $this->_defaultHeader['clientCustomerId'] = $customerId;
        $this->_licenseKey = $licenseKey;
    }

    public function getGclidInfo()
    {
        $data = [
            'header' => $this->_defaultHeader,
            'licenseKey' => $this->_licenseKey,
            'isAuthorization' => false,
        ];
        return HttpHelper::post('https://api.bitpress.pro/getGoogleAdData', $data);
    }

    public function authenticateGoogleAdword()
    {
        $data = [
            'header' => $this->_defaultHeader,
            'licenseKey' => $this->_licenseKey,
            'isAuthorization' => true,
        ];
        return HttpHelper::post('https://api.bitpress.pro/getGoogleAdData', $data);
    }
}
