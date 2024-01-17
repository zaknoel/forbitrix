<?php
namespace Zaknoel\ForBitrix;
class Event{
    /**@var \Bitrix\Main\Entity\Event $event*/
    public $event;
    const ACTUAL = 1;
    const CURRENT = 2;
    const ALL = 1|2;
    private $id=false, $result;
    function __construct(\Bitrix\Main\Entity\Event $event)
    {
        $this->event=$event;
        $ID = $event->getParameter("id");
        $this->id=is_array($ID)?$ID['ID']:$ID;
    /*    if(!$this->id){
            throw new Error('ID not found on event!'.print_r($event, 1));
        }*/
        return $this;
    }
    function initResult(){
        if(!$this->result)
            $this->result = new \Bitrix\Main\Entity\EventResult();
    }
    function getID(){
        return $this->id;
    }
    function getActual(){
        $object = $this->event->getParameter('object');
        return $object->collectValues(self::ACTUAL);
    }
    function getCurrent(){
        $object = $this->event->getParameter('object');
        return $object->collectValues(self::CURRENT);
    }
    function getAll(){
        $object = $this->event->getParameter('object');
        return $object->collectValues(self::ALL);
    }
    function result(){
        return  $result = new \Bitrix\Main\Entity\EventResult();
    }
    function  setError($field_name, $text=''){
        $this->initResult();
        $arErrors = Array();

        $arErrors[] = new \Bitrix\Main\Entity\FieldError($this->entity->getField($field_name), $text?:"Ошибка в поле ".$field_name);

        $this->result->setErrors($arErrors);
    }
    function setFields($arFields=[], $changedFields=[], $arUnsetFields=[]){
         $this->initResult();
         if($arFields)
             $this->event->setParameter("fields", $arFields);

        if($changedFields){
            $this->result->modifyFields($changedFields);
        }
        if($arUnsetFields){
            $this->result->unsetFields($arUnsetFields);
        }

    }
    function  getResult(){
       return $this->result;
    }
    function getEvent(){
        return $this->event;
    }


}
?>