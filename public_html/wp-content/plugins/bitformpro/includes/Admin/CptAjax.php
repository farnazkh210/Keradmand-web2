<?php

namespace BitCode\BitFormPro\Admin;

class CptAjax
{
  public function register()
  {
    add_action('wp_ajax_bitforms_add_post_type', array($this, 'addPostType'));
    add_action('wp_ajax_bitforms_getAll_post_type', array($this, 'getAllPost'));
    add_action('wp_ajax_bitforms_update_post_type', array($this, 'updatePostType'));
    add_action('wp_ajax_bitforms_delete_post_type', array($this, 'deletePostType'));
  }

  public function addPostType()
  {
    \ignore_user_abort();
    if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
      unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
      $inputJSON = file_get_contents('php://input');
      $get_cpt = get_option('bitform_custom_post_types');
      if ($get_cpt == '') {
        $option = (object)array();
        $option->name = $_POST['slug'];
        $option->public_queryable = isset($_POST['public_queryable'])? 1:0;
        $option->show_in_rest = isset($_POST['show_in_rest']) ? 1:0;
        $option->menu_name = $_POST['menu_name'];
        $option->singular_label = $_POST['singular_label'];
        $option->public = isset($_POST['public']) ? 1:0;
        $option->rewrite = true;
        $option->show_ui =  isset($_POST['show_ui']) ? 1:0;
        $option->show_in_menu = isset($_POST['show_in_menu']) ? 1:0;
        $option->menu_icon = $_POST['menu_icon'];
        $result = update_option('bitform_custom_post_types', [$option]);
      } else {
        $key = count($get_cpt);
        $get_cpt[$key]->name = $_POST['slug'];
        $get_cpt[$key]->public_queryable = isset($_POST['public_queryable'])? 1:0;
        $get_cpt[$key]->show_in_rest = isset($_POST['show_in_rest'])? 1:0;
        $get_cpt[$key]->menu_name = $_POST['menu_name'];
        $get_cpt[$key]->singular_label = $_POST['singular_label'];
        $get_cpt[$key]->public = isset($_POST['public'])? 1:0;
        $get_cpt[$key]->rewrite = true;
        $get_cpt[$key]->show_ui = isset($_POST['show_ui'])? 1:0;
        $get_cpt[$key]->show_in_menu = isset($_POST['show_in_menu'])? 1:0;
        $get_cpt[$key]->menu_icon = $_POST['menu_icon'];
        $result = update_option('bitform_custom_post_types', $get_cpt);
      }
      if ($result) {
        wp_send_json_success($result, 200);
      }
    }
  }

  public function getAllPost()
  {
    \ignore_user_abort();
    if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
      unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
      $cpts = $cpts = get_option('bitform_custom_post_types');
      if ($cpts != null) {
        foreach ($cpts as $key => $cpt) {
          if (!empty($cpt->name)) {
            $post_types[$key] = $cpt->name;
          }
        }
        $data['all_cpt'] = $cpts;
        $data['types'] = $post_types;
        wp_send_json_success($data, 200);
      }
    }
  }

  private function searchForType($slug, $cpts)
  {
    foreach ($cpts as $key => $val) {
      if ($val->name === $slug) {
        return $key;
      }
    }
  }
  public function updatePostType()
  {
    \ignore_user_abort();
    if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
      unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
      $cpts = get_option('bitform_custom_post_types');
      $id = $this->searchForType($_POST['post_type'], $cpts);
      if ($id !== null) {
        $cpts[$id]->name = $_POST['name'];
        $cpts[$id]->public_queryable = isset($_POST['public_queryable'])? 1:0;
        $cpts[$id]->show_in_rest = isset($_POST['show_in_rest'])? 1:0;
        $cpts[$id]->menu_name = $_POST['menu_name'];
        $cpts[$id]->singular_label = $_POST['singular_label'];
        $cpts[$id]->public = isset($_POST['public'])? 1:0;
        $cpts[$id]->rewrite = true;
        $cpts[$id]->show_ui = isset($_POST['show_ui'])? 1:0;
        $cpts[$id]->show_in_menu = isset($_POST['show_in_menu'])? 1:0;
        $cpts[$id]->menu_icon = $_POST['menu_icon'];
        update_option('bitform_custom_post_types', $cpts);
        wp_send_json_success($cpts[$id], 200);
      }
    }
  }

  public function deletePostType()
  {
    \ignore_user_abort();
    if (wp_verify_nonce(sanitize_text_field($_REQUEST['_ajax_nonce']), 'bitforms_save')) {
      unset($_REQUEST['_ajax_nonce'], $_REQUEST['action']);
      $inputJSON = file_get_contents('php://input');
      $queryParams = json_decode($inputJSON);
      update_option('bitform_custom_post_types', $queryParams->postData);
      wp_send_json_success($queryParams->postData, 200);
    }
  }
}
