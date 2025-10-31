<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!class_exists('ControlAcceso')) { require_once __DIR__ . '/../lib/ControlAcceso.Class.php'; }

$nombre = isset($_SESSION['usuario']) ? ($_SESSION['usuario']->nombre ?? 'Usuario') : 'Invitado';

// ------------------------------------------
// Recuperar roles globales y de proyectos
// ------------------------------------------
$rolesGlobales = [];
$proyectosRoles = [];

if (isset($_SESSION['usuario']->roles) && is_array($_SESSION['usuario']->roles)) {
    foreach ($_SESSION['usuario']->roles as $r) {
        $rolesGlobales[] = $r->nombre;
    }
}

if (isset($_SESSION['usuario']->proyectos) && is_array($_SESSION['usuario']->proyectos)) {
    foreach ($_SESSION['usuario']->proyectos as $p) {
        if (isset($p->roles) && is_array($p->roles)) {
            foreach ($p->roles as $r) {
                $proyectosRoles[] = [
                    'proyecto' => $p->nombre ?? 'Proyecto sin nombre',
                    'rol' => $r->nombre ?? 'Sin rol'
                ];
            }
        }
    }
}

// ------------------------------------------
// Detectar si es Admin o SuperAdmin
// ------------------------------------------
$esAdmin = false;
$esSuperAdmin = false;
foreach (array_merge($rolesGlobales, array_column($proyectosRoles, 'rol')) as $rol) {
    $rolLower = mb_strtolower(trim($rol), 'UTF-8');
    if ($rolLower === 'administrador') $esAdmin = true;
    if ($rolLower === 'superadmin') $esSuperAdmin = true;
}

// ------------------------------------------
// Determinar texto final
// ------------------------------------------
if ($esSuperAdmin) {
    $detalle = '<span class="rol-superadmin">⭐ Rol: SuperAdmin</span>';
} elseif ($esAdmin) {
    $detalle = '<span class="rol-admin">🔧 Rol: Administrador</span>';
} elseif (!empty($proyectosRoles)) {
    if (count($proyectosRoles) === 1) {
        $detalle = htmlspecialchars($proyectosRoles[0]['proyecto']) . 
                   ' (' . htmlspecialchars($proyectosRoles[0]['rol']) . ')';
    } else {
        $primer = $proyectosRoles[0];
        $resto = count($proyectosRoles) - 1;
        $detalle = htmlspecialchars($primer['proyecto']) . 
                   ' (' . htmlspecialchars($primer['rol']) . ') + ' . $resto . ' más';
    }
} else {
    $detalle = '<span class="rol-default">Usuario sin proyectos asignados</span>';
}

// ------------------------------------------
// Tooltip con roles/proyectos (si hay varios)
// ------------------------------------------
$tooltipText = '';
foreach ($proyectosRoles as $pr) {
    $tooltipText .= htmlspecialchars($pr['proyecto']) . ' → ' . htmlspecialchars($pr['rol']) . '&#10;';
}
?>


<link href="../lib/bootstrap-4.1.1-dist/css/uargflow_footer.css" type="text/css" rel="stylesheet" />

<style>
/* 🌙 Tooltip visual */
.tooltip-inner {
  background-color: #343a40 !important; /* gris oscuro */
  color: #fff !important;
  font-size: 0.9rem;
  padding: 8px 12px;
  border-radius: 6px;
  text-align: left;
  max-width: 260px;
}
/* 🌙 Ajustes visuales para roles dentro del footer */
.footer .rol-superadmin {
  color: #ffd700; /* dorado brillante */
  font-weight: 600;
}

.footer .rol-admin {
  color: #4da3ff; /* celeste luminoso */
  font-weight: 600;
}

.footer .rol-default {
  color: #adb5bd; /* gris claro */
  font-weight: 500;
}

/* Flecha del tooltip */
.tooltip.bs-tooltip-top .arrow::before {
  border-top-color: #343a40 !important;
}

/* ✨ Efecto visual sobre el ícono */
.footer .oi-info {
  cursor: pointer;
  color: #ffc107; /* Amarillo */
  transition: transform 0.2s ease, color 0.2s ease;
}
.footer .oi-info:hover {
  color: #fd7e14; /* Naranja */
  transform: scale(1.2);
}
</style>

<footer class="footer">
    <span class="oi oi-person"></span>
    <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>
    <span class="mx-2">|</span>
    <span class="oi oi-briefcase"></span>
    <?= $detalle; ?>
    <?php if (count($proyectosRoles) > 1): ?>
        <span class="mx-2" data-toggle="tooltip" title="<?= $tooltipText; ?>">
            <span class="oi oi-info"></span>
        </span>
    <?php endif; ?>
    <span class="mx-2">|</span>
    <a href="../app/salir.php">Salir</a>
</footer>

<!-- ✅ Dependencias necesarias -->
<script src="../lib/JQuery/jquery-3.3.1.js"></script>
<script src="../lib/bootstrap-4.1.1-dist/js/bootstrap.bundle.min.js"></script> <!-- incluye Popper -->

<script>
$(function () {
  $('[data-toggle="tooltip"]').tooltip({
    html: true,
    placement: 'top',
    delay: { show: 100, hide: 200 },
    title: function () {
      return $(this).attr('title').replace(/\n/g, '<br>');
    }
  });
});
</script>
