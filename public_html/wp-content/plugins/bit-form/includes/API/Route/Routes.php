<?php

namespace BitCode\BitForm\API\Route;

use WP_REST_Controller;
use WP_REST_Server;
use BitCode\BitForm\API\Controller\EntryController;
use BitCode\BitForm\API\Controller\NoteController;


class Routes extends WP_REST_Controller
{
  function __construct()
  {
    $this->namespace = 'bitform';
    $this->rest_base = 'v1';
    $this->entryController = new EntryController();
    $this->noteController  = new NoteController();
  }

  public function register_routes()
  {
    /* form routes */
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/forms/',
      [
        [
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => [$this->entryController, 'get_forms'],
          'permission_callback' => array($this, 'get_items_permissions_check'),
        ],
        'schema' => [$this, 'get_item_schema']
      ]
    );
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/fields/(?P<form_id>[\d]+)',
      [
        [
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => [$this->entryController, 'get_fields'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );
    /* form routes*/

    /* entry routes*/
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/entry/(?P<form_id>[\d]+)',
      [
        [
          'methods'             => WP_REST_Server::CREATABLE,
          'callback'            => [$this->entryController, 'entry_store'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/form/response/(?P<id>[\d]+)',
      [
        [
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => [$this->entryController, 'getEntryResponse'],
          'permission_callback' =>  [$this, 'get_items_permissions_check']
        ]
      ]
    );
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/entry/(?P<entry_id>[\d]+)',
      [
        [
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => [$this->entryController, 'entry_view'],
          'permission_callback' => [$this, 'get_items_permissions_check']

        ],
        [
          'methods'             => WP_REST_Server::DELETABLE,
          'callback'            => [$this->entryController, 'entry_delete'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );

    register_rest_route(
      $this->namespace,
      $this->rest_base . '/entry_update/(?P<entry_id>[\d]+)/',
      [
        [
          'methods'             => WP_REST_Server::EDITABLE,
          'callback'            => [$this->entryController, 'entry_update'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );
    /* entry routes*/

    /* note routes*/
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/notes/',
      [
        [
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => [$this->noteController, 'get_notes'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/create-note/',
      [
        [
          'methods'             => WP_REST_Server::CREATABLE,
          'callback'            => [$this->noteController, 'create_note'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/note/(?P<id>[\d]+)',
      [
        [
          'methods'             => WP_REST_Server::READABLE,
          'callback'            => [$this->noteController, 'note_edit'],
          'permission_callback' => [$this, 'get_items_permissions_check'],
          'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
        ],
        [
          'methods'             => WP_REST_Server::EDITABLE,
          'callback'            => [$this->noteController, 'note_update'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ],
        [
          'methods'             => WP_REST_Server::DELETABLE,
          'callback'            => [$this->noteController, 'note_delete'],
          'permission_callback' => [$this, 'get_items_permissions_check']
        ]
      ]
    );
    /* note routes*/

    /* google sheet route */
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/google/',
      [
        [
          'methods'   => WP_REST_Server::READABLE,
          'callback'  => [$this->entryController, 'googleAuth'],
          'permission_callback' => '__return_true'
        ]

      ]
    );
    // oneDrive rest route
    register_rest_route(
      $this->namespace,
      $this->rest_base . '/oneDrive/',
      [
        [
          'methods'   => WP_REST_Server::READABLE,
          'callback'  => [$this->entryController, 'oneDriveAuth'],
          'permission_callback' => '__return_true'
        ]

      ]
    );

  }
  public function get_items_permissions_check($request)
  {
    $api_key =  get_option('bitform_secret_api_key');
    $header = $request->get_header('Bitform-Api-Key');
    if (empty($header)) {
      $error = ['message' => 'Api Key is required to access this resource'];
      return wp_send_json_error($error, 401);
    } else if ($request->get_header('Bitform-Api-Key') != $api_key || $api_key == null) {
      $error = ['message' => 'Invalid API key'];
      return wp_send_json_error($error, 401);
    }
    return true;
  }
}
