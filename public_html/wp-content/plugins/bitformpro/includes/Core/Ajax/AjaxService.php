<?php

namespace BitCode\BitFormPro\Core\Ajax;

use BitCode\BitForm\Core\Capability\Request;
use BitCode\BitFormPro\Admin\AdminAjax;
use BitCode\BitFormPro\Integration\Integrations;
use BitCode\BitFormPro\Admin\CptAjax;

class AjaxService
{
    public function __construct()
    {
        if (Request::Check('ajax')) {
            $this->loadPublicAjax();
        }
        if (Request::Check('admin')) {
            $this->loadAdminAjax();
            $this->loadIntegrationsAjax();
        }
    }

    /**
     * Helps to register admin side ajax
     * 
     * @return null
     */
    public function loadAdminAjax()
    {
        (new AdminAjax())->register();
        (new CptAjax())->register();
    }

    /**
     * Helps to register frontend ajax
     * 
     * @return null
     */
    protected function loadPublicAjax()
    {
        // (new FrontendAjax())->register();
    }

    /**
     * Helps to register integration ajax
     * 
     * @return null
     */
    public function loadIntegrationsAjax()
    {
        (new Integrations())->registerAjax();
    }
}
