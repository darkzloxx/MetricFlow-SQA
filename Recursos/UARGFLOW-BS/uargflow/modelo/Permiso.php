<?php
include_once 'BDObjetoGenerico.class.php';

class Permiso extends BDObjetoGenerico {

    function __construct($id = null) {
        parent::__construct($id, "permiso");
    }

}
