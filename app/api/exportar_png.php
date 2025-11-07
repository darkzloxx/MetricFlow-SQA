<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../lib/ControlAcceso.Class.php';
require_once __DIR__ . '/../../modelo/BDConexion.Class.php';

$usr = ControlAcceso::usuarioActual();
if (!$usr) {
  http_response_code(401);
  echo 'No autenticado';
  exit;
}

$proyectoId = isset($_POST['proyecto']) ? (int)$_POST['proyecto'] : 0;
if ($proyectoId > 0) {
  $esAdminGlobal = false;
  if (isset($usr->roles) && is_array($usr->roles)) {
    foreach ($usr->roles as $r) {
      $rolName = mb_strtolower(trim($r->nombre ?? ''), 'UTF-8');
      if (in_array($rolName, ['administrador', 'superadmin'], true)) {
        $esAdminGlobal = true;
        break;
      }
    }
  }
  if (!$esAdminGlobal && !ControlAcceso::usuarioPerteneceAProyecto($proyectoId)) {
    http_response_code(403);
    echo 'Acceso denegado al proyecto';
    exit;
  }
}

$dataUrl = isset($_POST['image']) ? (string)$_POST['image'] : '';
if ($dataUrl === '') {
  http_response_code(400);
  echo 'Falta imagen';
  exit;
}

$filename = isset($_POST['filename']) ? (string)$_POST['filename'] : '';
if ($filename === '') {
  $filename = 'export_' . date('Ymd_His') . '.png';
} else {
  // Asegurar extensión .png
  if (!preg_match('/\.png$/i', $filename)) {
    $filename .= '.png';
  }
  $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename);
}

// data:image/png;base64,....
if (strpos($dataUrl, 'base64,') !== false) {
  $parts = explode('base64,', $dataUrl, 2);
  $data = base64_decode($parts[1], true);
} else {
  $data = base64_decode($dataUrl, true);
}

if ($data === false) {
  http_response_code(400);
  echo 'Imagen inválida';
  exit;
}

header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($data));
echo $data;
exit;
