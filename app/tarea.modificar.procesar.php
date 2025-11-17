<?php
include_once '../lib/ControlAcceso.Class.php';
ControlAcceso::requierePermiso(PermisosSistema::GESTION_TAREAS);
include_once '../modelo/BDConexion.Class.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$cn = BDConexion::getInstancia();

$Datos = $_POST;
$nombre = trim($Datos["nombre"] ?? "");
$id = intval($Datos["id"] ?? 0);
$iteracion = intval($Datos["iteracion"] ?? 0);

$cn->autocommit(false);
$cn->begin_transaction();

$regex = '/^[A-Za-zÁÉÍÓÚáéíóúÑñ0-9 _.\-\/():]+$/u';
$err = null;

if ($id <= 0)        $err = "Tarea inválida.";
elseif ($nombre==="")$err = "El nombre es obligatorio.";
elseif (!preg_match($regex,$nombre)) $err="Formato inválido en nombre.";
elseif ($iteracion<=0)$err="Debe seleccionar una iteración.";
elseif (!isset($Datos['metricas']) || count($Datos['metricas'])==0)
    $err="Debe seleccionar al menos una métrica.";

if ($err) {
    $_SESSION['old']=$Datos;
    $_SESSION['flash']=['danger',$err];
    header("Location: tarea.modificar.php?id=$id");
    exit;
}

// Evitar duplicado
$sql="SELECT 1 FROM tarea WHERE nombre='".$cn->real_escape_string($nombre)."' AND id_tarea<>$id";
if($cn->query($sql)->num_rows>0){
    $_SESSION['old']=$Datos;
    $_SESSION['flash']=['danger',"Ya existe una tarea con ese nombre"];
    header("Location: tarea.modificar.php?id=$id");
    exit;
}

// Actualizar nombre
$sql="UPDATE tarea SET nombre='".$cn->real_escape_string($nombre)."' WHERE id_tarea=$id";
if(!$cn->query($sql)) goto FAIL;

// Actualizar métricas
$cn->query("DELETE FROM metrica_tarea WHERE id_tarea=$id");
foreach($Datos['metricas'] as $m){
    $m=intval($m);
    if(!$cn->query("INSERT INTO metrica_tarea VALUES ($m,$id)")) goto FAIL;
}

// Actualizar iteración
$cn->query("DELETE FROM iteracion_tarea WHERE id_tarea=$id");
if(!$cn->query("INSERT INTO iteracion_tarea VALUES ($iteracion,$id)")) goto FAIL;

$cn->commit();
$cn->autocommit(true);

unset($_SESSION['old']);
$_SESSION['flash']=['success','Tarea actualizada correctamente'];
header("Location: tarea.php");
exit;

FAIL:
$cn->rollback();
$cn->autocommit(true);
$_SESSION['old']=$Datos;
$_SESSION['flash']=['danger',"Error al guardar los cambios"];
header("Location: tarea.modificar.php?id=$id");
exit;
