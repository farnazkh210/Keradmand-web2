<?php
namespace BitCode\BitForm\Core\Ajax;

use BitCode\BitForm\Admin\AdminAjax;
use BitCode\BitForm\Core\Capability\Request;
use BitCode\BitForm\Frontend\Ajax\FrontendAjax;
use BitCode\BitForm\Core\Integration\Integrations;

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
    }

    /**
     * Helps to register frontend ajax
     * 
     * @return null
     */
    protected function loadPublicAjax()
    {
        (new FrontendAjax())->register();
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
