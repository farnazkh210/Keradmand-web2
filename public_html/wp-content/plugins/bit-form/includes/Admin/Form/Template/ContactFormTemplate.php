<?php

namespace BitCode\BitForm\Admin\Form\Template;

use BitCode\BitForm\Admin\Form\Template\TemplateBase;

final class ContactFormTemplate extends TemplateBase
{
    protected $title = "Contact Form";
    protected $description = "Desc";
    protected $status = "free";
    protected $thumbnail = "";
    protected $category = "general";

    protected function layout($newFormId)
    {
        $layoutData = <<<lay
{"lg":[{"w":3,"h":2,"x":0,"y":0,"i":"bf$newFormId-1","minH":2,"maxH":2,"moved":false,"static":false},{"w":3,"h":2,"x":3,"y":0,"i":"bf$newFormId-2","minH":2,"maxH":2,"moved":false,"static":false},{"w":6,"h":2,"x":0,"y":2,"i":"bf$newFormId-3","minH":2,"maxH":2,"moved":false,"static":false},{"w":6,"h":2,"x":0,"y":4,"i":"bf$newFormId-4","minH":2,"maxH":2,"moved":false,"static":false},{"w":6,"h":3,"x":0,"y":6,"i":"bf$newFormId-5","moved":false,"static":false},{"w":6,"h":2,"x":0,"y":8,"i":"bf$newFormId-6","moved":false,"static":false}],"md":[{"i":"bf$newFormId-1","x":0,"y":0,"w":4,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-2","x":0,"y":2,"w":4,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-3","x":0,"y":4,"w":4,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-4","x":0,"y":6,"w":4,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-5","x":0,"y":8,"w":4,"h":3},{"i":"bf$newFormId-6","x":0,"y":10,"w":4,"h":2}],"sm":[{"i":"bf$newFormId-1","x":0,"y":0,"w":2,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-2","x":0,"y":2,"w":2,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-3","x":0,"y":4,"w":2,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-4","x":0,"y":6,"w":2,"h":2,"minH":2,"maxH":2},{"i":"bf$newFormId-5","x":0,"y":8,"w":2,"h":3},{"i":"bf$newFormId-6","x":0,"y":10,"w":2,"h":2}]}
lay;
        return $layoutData;
    }

    protected function fields($newFormId)
    {
        $fieldData = <<<F
{"bf$newFormId-1":{"typ":"text","lbl":"First Name","ph":"Enter Your First Name","valid":{},"err":{}},"bf$newFormId-2":{"typ":"text","lbl":"Last Name","ph":"Enter Your Last Name","valid":{},"err":{}},"bf$newFormId-3":{"typ":"email","lbl":"Email","ph":"example@mail.com","pattern":"^[^\$_bf_\$s@]+@[^\$_bf_\$s@]+\$_bf_\$.[^\$_bf_\$s@]+$","valid":{},"err":{"invalid":{"dflt":"Email is invalid","show":true}}},"bf$newFormId-4":{"typ":"text","lbl":"Subject","ph":"Contact Reason","valid":{},"err":{}},"bf$newFormId-5":{"typ":"textarea","lbl":"Message","ph":"Placeholder Text...","valid":{},"err":{}},"bf$newFormId-6":{"typ":"button","btnTyp":"submit","align":"right","btnSiz":"md","txt":"Submit","valid":{}}}
F;
        return $fieldData;
    }
}
