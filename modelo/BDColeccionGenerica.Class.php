<?php

include_once 'BDModeloGenerico.Class.php';

/**
 * Descripcion de ColeccionBDGenerica
 * 
 * Esta clase permite crear una coleccion generica de objetos a partir de una consulta simple a una tabla de la base de datos.
 * Así, las clases de tipo "Coleccion" deben extender de esta clase.
 *
 * @author Eder dos Santos <esantos@uarg.unpa.edu.ar>
 * @author Fabricio Gonzalez
 * @author Vanina Gola
 * 
 */
class BDColeccionGenerica extends BDModeloGenerico {

    /**
     * Coleccion generica de objetos
     * @var StdClass[]
     */
    protected $coleccion;

    /**
     * 
     * @return StdClass[]
     */
    function getColeccion() {
        return $this->coleccion;
    }
    
    /**
     * 
     * Metodo generico para obtener todos los registros de una tabla de una base de datos.
     * 
     * @param String $tablaBD_ nombre de la tabla de la base de datos a partir de la cual se recuperaran los datos.
     * @param String $nombreClase nombre de clase en la que se incorporarán los datos obtenidos en la BD.
     * 
     */
    function setColeccion($tablaBD_, $nombreClase) {
        // Evitar fetch_object con constructores que requieren ID.
        // Descubrimos la PK de la tabla y cargamos cada elemento por su ID real.
        $this->coleccion = [];
        $tabla = preg_replace('/[^A-Za-z0-9_]/', '', (string)$tablaBD_);
        $cn = BDConexion::getInstancia();
        $pk = null;
        $resPk = $cn->query("SHOW KEYS FROM {$tabla} WHERE Key_name='PRIMARY'");
        if ($resPk && ($rowPk = $resPk->fetch_assoc())) {
            $pk = $rowPk['Column_name'] ?? null;
        }
        if (!$pk) {
            $resHasId = $cn->query("SHOW COLUMNS FROM {$tabla} LIKE 'id'");
            if ($resHasId && $resHasId->num_rows > 0) {
                $pk = 'id';
            } else {
                $resFirst = $cn->query("SHOW COLUMNS FROM {$tabla}");
                if ($resFirst && ($rowFirst = $resFirst->fetch_assoc())) {
                    $pk = $rowFirst['Field'];
                } else {
                    $pk = 'id';
                }
            }
        }

        $this->query = "SELECT `{$pk}` AS _id FROM {$tabla}";
        $this->datos = $cn->query($this->query);
        if ($this->datos) {
            while ($row = $this->datos->fetch_assoc()) {
                $id = isset($row['_id']) ? (int)$row['_id'] : null;
                if ($id !== null) {
                    $this->addElemento(new $nombreClase($id));
                }
            }
        }
    }
    
    /**
     * Método para implementar agregación de elementos a la colección.
     * @param StdClass $elemento_ 
     */
    function addElemento($elemento_) {
        $this->coleccion[] = $elemento_;
    }

}
