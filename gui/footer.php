<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!class_exists('ControlAcceso')) { require_once __DIR__ . '/../lib/ControlAcceso.Class.php'; }
$nombre = isset($_SESSION['usuario']) ? ($_SESSION['usuario']->nombre ?? 'Usuario') : 'Invitado';
$roles = [];
if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']->roles)) {
    foreach ($_SESSION['usuario']->roles as $r) { $roles[] = $r->nombre; }
}
$rolesStr = empty($roles) ? 'Sin rol asignado' : implode(', ', $roles);
?>
<link href="../lib/bootstrap-4.1.1-dist/css/uargflow_footer.css" type="text/css" rel="stylesheet" />
<footer class="footer">
    <span class="oi oi-person"></span>
    <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
    <span class="mx-2">|</span>
    <span class="oi oi-badge"></span>
    Rol(es): <?= htmlspecialchars($rolesStr, ENT_QUOTES, 'UTF-8'); ?>
    <span class="mx-2">|</span>
    <a href="../app/salir.php">Salir</a>
  </footer>
