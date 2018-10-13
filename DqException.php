<?php
class DqException extends Exception{
    function __construct($msg){
        parent::__construct($msg);

    }
    public function getDqMessage(){
        return "\n".'file:'.$this->getFile().' line:'.$this->getLine().' '.$this->getMessage();
    } 
}