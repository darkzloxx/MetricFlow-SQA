<?php
include_once 'BDObjetoGenerico.Class.php';

class Proyecto extends BDObjetoGenerico {

    function __construct($id = null) {
        parent::__construct($id, "proyecto");
    }

}
