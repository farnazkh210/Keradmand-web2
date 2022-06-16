<?php

namespace BitCode\BitFormPro;

/**
 * Main class for the plugin.
 *
 * @since 1.0.0-alpha
 */

use BitCode\BitFormPro\Core\Database\DB;
use BitCode\BitFormPro\Core\Update\Updater;
use BitCode\BitFormPro\Admin\Admin_Bar;
use BitCode\BitForm\Core\Capability\Request;
use BitCode\BitFormPro\Core\Ajax\AjaxService;
use BitCode\BitFormPro\Integration\Integrations;
use BitCode\BitFormPro\Core\CronShedule;
use BitCode\BitFormPro\Core\Util\FormDuplicateEntry;
use BitCode\BitFormPro\Core\Database\PostInfoModel;
use BitCode\BitFormPro\Auth\Auth;
use BitCode\BitFormPro\Auth\UserRowAction;
use BitCode\BitForm\Core\Integration\IntegrationHandler;
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
     * Initialize the hooks
     *
     * @return void
     */
    public function initialize()
    {
        add_action('init', array($this, 'init_classes'), 10);
        add_action('init', array($this, 'handleVersionUpdateFallback'));
        add_filter('plugin_action_links_' . plugin_basename(BITFORMPRO_PLUGIN_MAIN_FILE), array($this, 'plugin_action_links'));
        add_filter('bitform_dynamic_field_filter', array($this, 'dynamicFields'), 10, 8);
    }

    /**
     * Instantiate the required classes
     *
     * @return void
     */
    public function init_classes()
    {
        if (!empty($_GET['bit_activation_key']) && !empty($_GET['f_id']) && !empty($_GET['user_id'])) {
            include_once plugin_dir_path(__FILE__) . 'activation_page.php';
            exit();
        }
        
        if (Request::Check('admin')) {
            (new Admin_Bar())->register();
            new Updater();
        }
        if (Request::Check('ajax')) {
            new AjaxService();
        }
        (new Integrations())->registerHooks();
        (new CronShedule())->cron_schedule();
        (new FormDuplicateEntry())->register();
        (new Auth())->register();

        if (method_exists('BitCode\BitForm\Core\Integration\IntegrationHandler', 'singleIntegration')) {
            $existAuth = (new IntegrationHandler(0))->singleIntegration('wp_user_auth', 'wp_auth', 1);
            if (!is_wp_error($existAuth) && isset($existAuth->id)) {
                (new UserRowAction())->userRowAction();
            }
        }
    }

    public function handleVersionUpdateFallback()
    {
        $installed = get_option('bitformpro_installed');
        $oldversion = null;
        if ($installed) {
            $oldversion = get_option('bitformpro_version');
        }
        if (!$oldversion) {
            update_option('bitformpro_version', BITFORMPRO_VERSION);
            $this->changeValidationErrObjectOfIsUnique();
        }
        // if ($oldversion && version_compare($oldversion, BITFORMPRO_VERSION, '!=')) {
        //     update_option('bitformpro_version', BITFORMPRO_VERSION);
        //     if (version_compare('1.4.10', $oldversion, '>=')) {
        //         $this->changeValidationErrObjectOfIsUnique();
        //     }
        // }
    }

    private function changeValidationErrObjectOfIsUnique()
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
                    if (isset($fldData->err) && isset($fldData->err->entryUnique)) {
                        if (isset($fldData->err->entryUnique->isEntryUnique)) {
                            unset($fldData->err->entryUnique->isEntryUnique);
                            $fldData->err->entryUnique->show = true;
                        } else {
                            $fldData->err->entryUnique->show = false;
                        }

                        if (isset($fldData->err->userUnique->isUserUnique)) {
                            unset($fldData->err->userUnique->isUserUnique);
                            $fldData->err->userUnique->show = true;
                        } else {
                            $fldData->err->userUnique->show = false;
                        }
                    }

                    if (isset($fldData->{"entryUnique:"})) {
                        unset($fldData->{"entryUnique:"});
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

    private function dynamicTaxanomyFields($taxonomy, $order, $orderBy)
    {
        $allCategoreis = get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby' => $orderBy,
                'order' => $order,
            )
        );
        return $allCategoreis;
    }
    private function userDynamicFieldsData($role, $order, $orderBy)
    {
        $args = array(
            'orderby' => $orderBy,
            'order' => $order,
            'fields' => array('ID', 'display_name', 'user_login', 'user_email', 'user_nicename')
        );
        if ($role != 'all') {
            $args['role'] = $role;
        }
        $users = get_users($args);
        return $users;
    }

    private function postDynamicFildData($postType, $order, $orderBy, $postStatus)
    {
        $postModel = new PostInfoModel();
        $posts = $postModel->getAllPosts($postType, $orderBy, $order, $postStatus);

        return $posts;
    }

    private function acfDynamicOptions($fieldKey)
    {
        $options = [];
        $types = ['select', 'checkbox', 'radio'];
        $groups = acf_get_field_groups();
        foreach ($groups as  $group) {
            foreach (acf_get_fields($group['key']) as  $acfField) {
                if (in_array($acfField['type'], $types) && $acfField['key'] === $fieldKey) {
                    $options = $acfField['choices'];
                }
            }
        }
        return $options;
    }

    public function dynamicFields($fields)
    {
        $types = ['check', 'radio', 'select'];

        foreach ($fields as $field) {
            if (in_array($field->typ, $types) && property_exists($field, "customType")) {
                if ($field->typ == 'select') {
                    $optionLvl = 'label';
                    $optionVlu = 'value';
                } else {
                    $optionLvl = 'lbl';
                    $optionVlu = 'val';
                }

                $oldOpt = $field->opt;
                $options = [];
                $field->opt = [];

                $filter = $field->customType->filter;
                if ($field->customType->fieldType  === 'user_field') {
                    $options = $this->userDynamicFieldsData($filter->role, $filter->order, $filter->orderBy);
                } elseif ($field->customType->fieldType === 'taxanomy_field') {
                    $options = $this->dynamicTaxanomyFields($filter->taxanomy, $filter->order, $filter->orderBy);
                } elseif ($field->customType->fieldType  === 'post_field') {
                    $options = $this->postDynamicFildData($filter->postType, $filter->order, $filter->orderBy, $filter->postStatus);
                } elseif ($field->customType->fieldType  === 'acf_options') {
                    $data = $this->acfDynamicOptions($filter->fieldkey);
                    if (!empty($data)) {
                        foreach (array_values($data) as $key => $option) {
                            $field->opt[$key][$optionLvl] = (string) $option;
                        }
                        foreach (array_keys($data) as $key => $option) {
                            $field->opt[$key][$optionVlu] = (string) $option;
                        }
                    }
                }
                if (!empty($options)) {
                    $lebel = $field->customType->lebel;
                    $value = $field->customType->value;

                    foreach ($options as $key => $option) {
                        if (!empty($lebel) && !empty($value)) {
                            $field->opt[$key][$optionLvl] = (string) $option->$lebel;
                            $field->opt[$key][$optionVlu] =  (string) $option->$value;
                        }
                    }
                }
                //$field->opt = array_merge($field->opt, $field->customType->oldOpt);
            }
        }
        return $fields;
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
        $links[] = '<a href="https://docs.form.bitapps.pro/" target="_blank">' . __('Docs', 'bitform') . '</a>';

        return $links;
    }

    /**
     * Retrieves the main instance of the plugin.
     *
     * @since 1.0.0-alpha
     *
     * @return BITFORMPRO Plugin main instance.
     */
    public static function instance()
    {
        return static::$instance;
    }

    public static function update_tables()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $bitformspro_db_version;
        $installed_db_version = get_site_option("bitformspro_db_version");
        if ($installed_db_version != $bitformspro_db_version) {
            DB::migrate();
        }
    }
    /**
     * Loads the plugin main instance and initializes it.
     *
     * @since 1.0.0-alpha
     *
     * @param string $main_file Absolute path to the plugin main file.
     * @return bool True if the plugin main instance could be loaded, false otherwise./
     */
    public static function load($main_file)
    {
        if (null !== static::$instance) {
            return false;
        }
        static::update_tables();
        static::$instance = new static($main_file);
        static::$instance->initialize();
        return true;
    }
}
