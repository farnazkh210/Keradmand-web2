<?php

namespace BitCode\BitForm;

/**
 * Main class for the plugin.
 *
 * @since 1.0.0-alpha
 */

use BitCode\BitForm\Admin\Admin_Bar;
use BitCode\BitForm\Core\Database\DB;
use BitCode\BitForm\Core\Util\Activation;
use BitCode\BitForm\Core\Form\FormHandler;
use BitCode\BitForm\Core\Ajax\AjaxService;
use BitCode\BitForm\Core\Util\Deactivation;
use BitCode\BitForm\Core\Util\Uninstallation;
use BitCode\BitForm\Core\Capability\Request;
use BitCode\BitForm\Core\Util\GutenBlockProvider;
use BitCode\BitForm\Core\Integration\Integrations;
use BitCode\BitForm\Core\Util\FileDownloadProvider;
use BitCode\BitForm\Core\Fallback\FormEntryMetaChange;
use BitCode\BitForm\Admin\Form\AdminFormHandler;
use BitCode\BitForm\Core\Database\FormModel;

final class Plugin
{

    /**
     * Main instance of the plugin.
     *
     * @since 1.0.0-alpha
     * @var   Plugin|null
     */
    private static $instance = null;


    /**
     * Registers the plugin with WordPress.
     *
     * @since 1.0.0-alpha
     */
    public function register()
    {
        add_action('plugins_loaded', array($this, 'init_plugin'));
        (new Activation())->activate();
        (new Deactivation())->register();
        (new Uninstallation())->register();
    }


    /**
     * Initialize the hooks
     *
     * @return void
     */
    public function init_hooks()
    {
        add_action('init', array($this, 'registerBitformsPostType'));
        add_action('init', array($this, 'registerCustomPostType'));
        add_action("bitforms_exec_integrations", array(Integrations::class, 'integrationExecutionHelper'), 1, 5);
        // Localize our plugin
        add_action('init', array($this, 'localization_setup'));

        // initialize the classes
        add_action('init', array($this, 'init_classes'));

        add_action('init', array($this, 'entryMetaKeyUpdate'));

        add_filter('plugin_action_links_' . plugin_basename(BITFORMS_PLUGIN_MAIN_FILE), array($this, 'plugin_action_links'));
    }

    public function entryMetaKeyUpdate()
    {
        $installed = get_option('bitforms_installed');
        $oldversion = null;
        if ($installed) {
            $oldversion = get_option('bitforms_version');
        }
        if ($oldversion && version_compare($oldversion, BITFORMS_VERSION, '!=')) {
            update_option('bitforms_version', BITFORMS_VERSION);
            if (version_compare('1.0.4', $oldversion, '>=')) {
                // if version is lower or equal than 1.0.4
                $formEntryMetaChange = new FormEntryMetaChange();
                $formEntryMetaChange->changeMeta();
            }

            if (version_compare('1.4.1', $oldversion, '>=')) {
                // if version is lower or equal than 1.4.1
                $this->addButtonToFieds();
            }

            if (version_compare('1.4.7', $oldversion, '>=')) {
                // if version is equal or lower than 1.4.7
                $this->validationErrMsgFallback();
            }

            if (version_compare('1.4.11', $oldversion, '>=')) {
                // if version is equal or lower than 1.4.11
                $this->buttonValidsObjectFallback();
            }

            if (version_compare('1.4.12.2', $oldversion, '>=')) {
                // if version is equal or lower than 1.4.12.2
                $this->validationEmailFallback();
            }
        }
    }

    public function validationErrMsgFallback()
    {
        $formModel = new FormModel();
        $forms = $formModel->get(
            ['id', 'form_content']
        );

        if (isset($forms) && !is_wp_error($forms)) {
            foreach ($forms as $form) {
                $formID = $form->id;
                $formContent = json_decode($form->form_content);
                $fields = $formContent->fields;

                foreach ($fields as $fldKey => $fldData) {
                    if (isset($fldData->valid->req)) {
                        if (!isset($fldData->err)) {
                            $fldData->err = (object) [];
                        }
                        $fldData->err->req = (object) [
                            'show' => true,
                            'dflt' => 'This field is required'
                        ];
                    }

                    if ($fldData->typ === 'check') {
                        $reqOpts = array_map(function ($op) {
                            return $op->lbl;
                        }, array_filter($fldData->opt, function ($ot) {
                            return isset($ot->req);
                        }));
                        if (!empty($reqOpts)) {
                            if (!isset($fldData->err)) {
                                $fldData->err = (object) [];
                            }
                            $fldData->err->req = (object) [
                                'show' => true,
                                'dflt' => implode(', ', $reqOpts) . ' is required'
                            ];
                        }
                    }

                    if ($fldData->typ === 'number') {
                        if (!isset($fldData->err)) {
                            $fldData->err = (object) [];
                        }
                        $fldData->err->invalid = (object) [
                            'show' => true,
                            'dflt' => 'Number is invalid'
                        ];

                        if (isset($fldData->mn)) {
                            $fldData->err->mn = (object) [
                                'show' => true,
                                'dflt' => 'Minimum number is ' . $fldData->mn
                            ];
                        }

                        if (isset($fldData->mx)) {
                            $fldData->err->mx = (object) [
                                'show' => true,
                                'dflt' => 'Maximum number is ' . $fldData->mx
                            ];
                        }
                    }

                    if ($fldData->typ === 'email') {
                        $fldData->pattern = '^$_bf_$w+([.-]?$_bf_$w+)*@$_bf_$w+([.-]?$_bf_$w+)*($_bf_$.$_bf_$w{2,3})+$';
                        if (!isset($fldData->err)) {
                            $fldData->err = (object) [];
                        }
                        $fldData->err->invalid = (object) [
                            'show' => true,
                            'dflt' => 'Email is invalid'
                        ];
                    }

                    if ($fldData->typ === 'url') {
                        $fldData->attr->pattern = '^(?:(?:https?|ftp):$_bf_$/$_bf_$/)?(?:(?!(?:10|127)(?:$_bf_$.$_bf_$d{1,3}){3})(?!(?:169$_bf_$.254|192$_bf_$.168)(?:$_bf_$.$_bf_$d{1,3}){2})(?!172$_bf_$.(?:1[6-9]|2$_bf_$d|3[0-1])(?:$_bf_$.$_bf_$d{1,3}){2})(?:[1-9]$_bf_$d?|1$_bf_$d$_bf_$d|2[01]$_bf_$d|22[0-3])(?:$_bf_$.(?:1?$_bf_$d{1,2}|2[0-4]$_bf_$d|25[0-5])){2}(?:$_bf_$.(?:[1-9]$_bf_$d?|1$_bf_$d$_bf_$d|2[0-4]$_bf_$d|25[0-4]))|(?:(?:[a-z$_bf_$u00a1-$_bf_$uffff0-9]-*)*[a-z$_bf_$u00a1-$_bf_$uffff0-9]+)(?:$_bf_$.(?:[a-z$_bf_$u00a1-$_bf_$uffff0-9]-*)*[a-z$_bf_$u00a1-$_bf_$uffff0-9]+)*(?:$_bf_$.(?:[a-z$_bf_$u00a1-$_bf_$uffff]{2,})))(?::$_bf_$d{2,5})?(?:$_bf_$/$_bf_$S*)?$';
                        if (!isset($fldData->err)) {
                            $fldData->err = (object) [];
                        }
                        $fldData->err->invalid =  [
                            'show' => true,
                            'dflt' => 'URL is invalid'
                        ];
                    }
                    $fields->{$fldKey} = $fldData;
                }

                $formContent->fields = $fields;

                $formModel->update(
                    array(
                        "form_content" => wp_json_encode($formContent)
                    ),
                    array(
                        "id" => $formID,
                    )
                );
            }
        }
    }

    public function validationEmailFallback()
    {
        $formModel = new FormModel();
        $forms = $formModel->get(
            ['id', 'form_content']
        );

        if (!is_wp_error($forms)) {
            foreach ($forms as $form) {
                $formID = $form->id;
                $formContent = json_decode($form->form_content);
                $fields = $formContent->fields;

                foreach ($fields as $fldKey => $fldData) {
                    if ($fldData->typ === 'email') {
                        $fldData->pattern = '^$_bf_$w+([.-]?$_bf_$w+)*@$_bf_$w+([.-]?$_bf_$w+)*($_bf_$.$_bf_$w{1,24})+$';
                    }
                    $fields->{$fldKey} = $fldData;
                }

                $formContent->fields = $fields;

                $formModel->update(
                    array(
                        "form_content" => wp_json_encode($formContent)
                    ),
                    array(
                        "id" => $formID,
                    )
                );
            }
        }
    }

    public function buttonValidsObjectFallback()
    {
        $formModel = new FormModel();
        $forms = $formModel->get(
            ['id', 'form_content']
        );

        if (!is_wp_error($forms)) {
            foreach ($forms as $form) {
                $formID = $form->id;
                $formContent = json_decode($form->form_content);
                $fields = $formContent->fields;

                foreach ($fields as $fldKey => $fldData) {
                    if ($fldData->typ === 'button' && !isset($fldData->valid)) {
                        $fldData->valid = (object) [];
                    }
                    $fields->{$fldKey} = $fldData;
                }

                $formContent->fields = $fields;

                $formModel->update(
                    array(
                        "form_content" => wp_json_encode($formContent)
                    ),
                    array(
                        "id" => $formID,
                    )
                );
            }
        }
    }

    /**
     * Updates bit form content tables on wp db
     *
     * @return void
     */
    public function update_tables()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $bitforms_db_version;
        $installed_db_version = get_site_option("bitforms_db_version");
        if ($installed_db_version != $bitforms_db_version) {
            DB::migrate();
        }
    }

    /**
     * Register post type for bitforms
     *
     * @return void
     */
    public function registerBitformsPostType()
    {
        $args = array(
            'label'           => __('Bitform Pages', 'bit-form'),
            'public'          => true,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'capability_type' => 'page',
            'hierarchical'    => false,
            'query_var'       => false,
            'supports'        => array('title'),
            'show_in_rest'    => false
        );
        register_post_type('bitforms', $args);
    }

    public function registerCustomPostType()
    {
        flush_rewrite_rules(false);
        $cpts = get_option('bitform_custom_post_types');
        if (!empty($cpts)) {
            foreach ($cpts as $cpt) {
                $labels = array(
                    'name'                  => _x($cpt->name, 'Post type general name', 'bit-form'),
                    'singular_name'         => _x($cpt->singular_label, 'Post type singular name', 'bit-form'),
                    'menu_name'             => _x($cpt->menu_name, 'Admin Menu text', 'bit-form'),
                );
                $args = array(
                    'labels'             => $labels,
                    'public'             => $cpt->public == 1 ? true : false,
                    'publicly_queryable' => $cpt->public_queryable == 1 ? true : false,
                    'show_ui'            =>  $cpt->show_ui == 1 ? true : false,
                    'show_in_menu'       =>  $cpt->show_in_menu == 1 ? true : false,
                    'query_var'          => true,
                    'rewrite'            => array('slug' => $cpt->name),
                    'capability_type'    => 'post',
                    'has_archive'        => true,
                    'hierarchical'       => false,
                    'show_in_rest'         =>  isset($cpt->show_in_rest) == 1 ? true : false,
                    'menu_icon' =>        $cpt->menu_icon,
                    'supports'           => array('title', 'editor', 'author', 'excerpt', 'comments'),
                );
                register_post_type($cpt->name, $args);
            }
        }
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup()
    {
        load_plugin_textdomain('bitform', false, dirname(BITFORMS_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Instantiate the required classes
     *
     * @return void
     */
    public function init_classes()
    {
        if (Request::Check('admin')) {
            (new Admin_Bar())->register();
        }
        if (Request::Check('ajax')) {
            new AjaxService();
        }
        if (Request::Check('frontend')) {
            $formHandler = new FormHandler();
            $formHandler->frontend;
        }
        if (Request::isPluginPage()) {
            (new FileDownloadProvider())->register();
        }
        if (current_user_can('edit_posts')) {
            (new GutenBlockProvider())->register();
        }
    }

    /**
     * Plugin action links
     *
     * @param  array $links
     *
     * @return array
     */
    public function plugin_action_links($links)
    {
        $links[] = '<a href="https://docs.form.bitapps.pro" target="_blank">' . __('Docs', 'bit-form') . '</a>';

        return $links;
    }


    public function init_plugin()
    {
        $this->init_hooks();
        $this->update_tables();
        do_action('bitform_loaded');
    }

    /**
     * Retrieves the main instance of the plugin.
     *
     * @since 1.0.0-alpha
     *
     * @return BITFORM Plugin main instance.
     */
    public static function instance()
    {
        return static::$instance;
    }

    /**
     * Loads the plugin main instance and initializes it.
     *
     * @param  string $main_file Absolute path to the plugin main file.
     *
     * @return bool True if the plugin main instance could be loaded, false otherwise.
     */
    public static function load($main_file)
    {
        if (null !== static::$instance) {
            return false;
        }

        static::$instance = new static($main_file);
        static::$instance->register();
        return true;
    }

    public function addButtonToFieds()
    {
        // if version is lower or equal than 1.3.13
        $formModel = new FormModel();
        $forms = $formModel->get(
            ['id', 'form_content'],
            [
                'form_content' => ['operator' => 'LIKE', 'value' => '%\"buttons\":%']
            ]
        );
        if (!is_wp_error($forms)) {
            $adminFormHandler = new AdminFormHandler();
            foreach ($forms as $form) {
                $formID = $form->id;
                $formContent = json_decode($form->form_content);
                $fields = json_encode($formContent->fields);
                if (isset($formContent->buttons)) {
                    if (strpos($fields, '"btnTyp":"submit"') === false) {
                        $align = isset($formContent->buttons->align) ? $formContent->buttons->align : 'right';
                        $btnSiz = isset($formContent->buttons->btnSiz) ? $formContent->buttons->btnSiz : 'md';
                        $fulW = isset($formContent->buttons->fulW) ? $formContent->buttons->fulW : false;
                        $btnData = [
                            'typ' => 'button',
                            'btnSiz' => $btnSiz,
                            'fulW' => $fulW,
                        ];
                        $newID = 0;
                        foreach ($formContent->fields as $key => $value) {
                            $oldID = \preg_replace('/bf?\d+-(\d+)-?/', "$1", $key);
                            if ($oldID > $newID) {
                                $newID = $oldID;
                            }
                        }
                        $newID = $newID + 1;
                        $sBtnID = "bf${formID}-${newID}";
                        $lY = 0;
                        $mY = 0;
                        $sY = 0;
                        foreach ($formContent->layout->lg  as $lg) {
                            if ($lg->y > $lY) {
                                $lY = $lg->y;
                            }
                        }
                        $lY = $lY + $lg->h + 1;
                        foreach ($formContent->layout->md  as $md) {
                            if ($md->y > $mY) {
                                $mY = $md->y;
                            }
                        }
                        $mY = $mY + $md->h + 1;
                        foreach ($formContent->layout->sm  as $sm) {
                            if ($sm->y > $sY) {
                                $sY = $sm->y;
                            }
                        }
                        $sY = $sY + $sm->h + 1;
                        if (isset($formContent->buttons->rstBtnTxt)) {
                            $newID += 1;
                            $rBtnID = "bf${formID}-${newID}";

                            if ($fulW) {
                                $btnData['btnTyp'] = 'submit';
                                $btnData['align'] = $align;
                                $btnData['txt'] = $formContent->buttons->subBtnTxt;
                                $formContent->fields->{$sBtnID} = (object) $btnData;
                                $rBtnData = $btnData;
                                $rBtnData['btnTyp'] = 'reset';
                                $rBtnData['align'] = $align;
                                $rBtnData['txt'] = $formContent->buttons->rstBtnTxt;
                                $formContent->fields->{$rBtnID} = (object) $rBtnData;
                            } else {
                                $btnData['btnTyp'] = 'submit';
                                $btnData['align'] = 'right';
                                $btnData['txt'] = $formContent->buttons->subBtnTxt;
                                $formContent->fields->{$sBtnID} = (object) $btnData;
                                $rBtnData = $btnData;
                                $rBtnData['btnTyp'] = 'reset';
                                $rBtnData['align'] = 'left';
                                $rBtnData['txt'] = $formContent->buttons->rstBtnTxt;
                                $formContent->fields->{$rBtnID} = (object) $rBtnData;
                            }
                            $subBtnLay = [
                                "h" => 2,
                                "i" => $sBtnID,
                                "minH" => 2,
                                "x" => 0,
                                "y" => 1000
                            ];

                            $subBtnLay["w"] = 3;
                            $subBtnLay["y"] = $lY;


                            $formContent->layout->lg[] = (object) $subBtnLay;

                            $subBtnLay["w"] = 2;
                            $subBtnLay["y"] = $mY;


                            $formContent->layout->md[] = (object) $subBtnLay;

                            $subBtnLay["w"] = 1;
                            $subBtnLay["y"] = $sY;


                            $formContent->layout->sm[] = (object) $subBtnLay;


                            $subBtnLay["i"] = $rBtnID;
                            $subBtnLay["x"] = 3;
                            $subBtnLay["w"] = 3;
                            $subBtnLay["y"] = $lY;

                            $formContent->layout->lg[] = (object) $subBtnLay;

                            $subBtnLay["x"] = 2;
                            $subBtnLay["w"] = 2;
                            $subBtnLay["y"] = $mY;

                            $formContent->layout->md[] = (object) $subBtnLay;

                            $subBtnLay["w"] = 1;
                            $subBtnLay["x"] = 1;
                            $subBtnLay["y"] = $sY;

                            $formContent->layout->sm[] = (object) $subBtnLay;
                        } else {
                            $btnData['btnTyp'] = 'submit';
                            $btnData['txt'] = $formContent->buttons->subBtnTxt;
                            $btnData['align'] = $align;
                            $formContent->fields->{$sBtnID} = (object) $btnData;

                            $subBtnLay = [
                                "h" => 2,
                                "i" => $sBtnID,
                                "minH" => 2,
                                "x" => 0,
                                "w" => 6,
                            ];

                            $subBtnLay["y"] = $lY;

                            $formContent->layout->lg[] = (object) $subBtnLay;

                            $subBtnLay["y"] = $mY;

                            $formContent->layout->md[] = (object) $subBtnLay;

                            $subBtnLay["y"] = $sY;

                            $formContent->layout->sm[] = (object) $subBtnLay;
                        }
                    }
                    $adminFormHandler->saveLayoutStyleSheet($formContent->layout, 'bitform-layout-' . $formID . '.css');
                    unset($formContent->buttons);
                    $formModel->update(
                        array(
                            "form_content" => wp_json_encode($formContent)
                        ),
                        array(
                            "id" => $formID,
                        )
                    );
                }
            }
        }
    }
}
