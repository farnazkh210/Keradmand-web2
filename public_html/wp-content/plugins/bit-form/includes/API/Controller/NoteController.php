<?php

namespace BitCode\BitForm\API\Controller;

use WP_REST_Controller;
use BitCode\BitForm\Core\Database\ApiModel;
use WP_Error;
class NoteController extends WP_REST_Controller
{
    public function __construct(){
        global $wpdb;
        $this->_wpdb = $wpdb;
    }
    public function get_notes(){
        $db = new ApiModel();
        $notes = $db->noteList();
        if($notes){
            return rest_ensure_response(['data'=>$notes,'success'=>true,'status'=>200]);
        }else{
            return new WP_Error(
                'empty',
                __( 'Internal Server Error' ),
                [ 
                    'status' => 500,
                    'success'=>false,
                 ]
            );
        }
    }

    public function create_note( $request ){
        $form_id = $_POST['form_id'];
        $entry_id = $_POST['entry_id'];
        $info_details = $_POST['info_details'];

        if(empty($form_id) || empty($entry_id)){
            return new WP_Error(
                'empty',
                __( 'Form ID Or Entry ID is Empty ' ),
                [ 
                    'status' => 422,
                    'success'=>false,
                 ]
            );
        }
        $db = new ApiModel();
        $create = $db->noteCreate( $form_id,$entry_id,$info_details );
        if($create){
            return rest_ensure_response(['messsage'=>'successfully note created','success'=>true,'status'=>200]);
        }else{
            return new WP_Error(
                'empty',
                __( 'Internal Server Error' ),
                [ 
                    'status' => 500,
                    'success' => false,
                 ]
            );
        }
    }

    public function note_edit( $request ){
        $db = new ApiModel();
        $id = $request['id'];
        $existData = $db->findRecord("bitforms_form_entry_relatedinfo",'id',$id);
        if(count($existData)===0){
            return new WP_Error(
                'empty',
                __( 'Record Not Found' ),
                [ 
                    'status' => 404,
                    'success'=>false,
                 ]
            );
        }
        return $existData;
    }

    public function note_update( $request ){
        $info_details = $_POST['info_details'];
        $note_id = $request['id'];  
        $db = new ApiModel();
        $existData = $db->findRecord("bitforms_form_entry_relatedinfo",'id',$note_id);
        if(count($existData)===0){
            return new WP_Error(
                'empty',
                __( 'Record Not Found' ),
                [ 
                    'status' => 404,
                    'success'=>false,
                 ]
            );
        }
        $update = $db->noteUpdate( $note_id,$info_details );
        if($update){
            return rest_ensure_response(['messsage'=>'successfully note updated','success'=>true,'status'=>200]);
        }else{
            return new WP_Error(
                'empty',
                __( 'Internal Server Error' ),
                [ 
                    'status' => 500,
                    'success'=>false,
                 ]
            );
        }
   
    }

    public function note_delete( $request ){
        
        $note_id = $request['id'];  
        $db = new ApiModel();
        $existData = $db->findRecord("bitforms_form_entry_relatedinfo",'id',$note_id);
        if(count($existData)===0){
            return new WP_Error(
                'empty',
                __( 'Record Not Found' ),
                [ 
                    'status' => 404,
                    'success'=>false,
                 ]
            );
        }
        $delete = $db->noteDelete( $note_id );
        
        if($delete){
            return rest_ensure_response(['messsage'=>'successfully note deleted','success'=>true,'status'=>200]);
        }else{
            return new WP_Error(
                'empty',
                __( 'Internal Server Error' ),
                [ 
                    'status' => 500,
                    'success'=>false,
                 ]
            );
        }
    }
}