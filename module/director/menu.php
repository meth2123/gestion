<?php
require_once 'check_director_access.php';
?>
<nav class="mb-4">
    <ul class="nav nav-pills justify-content-end gap-2">
        <li class="nav-item">
            <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-1"></i>Tableau de bord</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="payment.php"><i class="fas fa-money-bill-wave me-1"></i>Gestion des Paiements</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="salary.php"><i class="fas fa-coins me-1"></i>Gestion des Salaires</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Déconnexion</a>
        </li>
    </ul>
</nav>
