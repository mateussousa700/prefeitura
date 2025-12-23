<?php
$currentPage = $currentPage ?? '';
$userType = $userType ?? 'populacao';
$managerRoles = ['gestor', 'admin'];
$isManager = in_array($userType, $managerRoles, true);
?>
<aside class="sidebar">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <span class="brand">Prefeitura Digital</span>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" href="home.php">Início</a>
        <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" href="services.php">Serviços</a>
        <a class="nav-link <?php echo $currentPage === 'requests' ? 'active' : ''; ?>" href="requests.php">Meus protocolos</a>
        <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" href="profile.php">Meu perfil</a>
        <?php if ($isManager): ?>
            <a class="nav-link <?php echo $currentPage === 'tickets' ? 'active' : ''; ?>" href="tickets.php">Chamados</a>
            <a class="nav-link <?php echo $currentPage === 'completed' ? 'active' : ''; ?>" href="completed.php">Concluídos</a>
            <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">Gestão de usuários</a>
        <?php endif; ?>
        <a class="nav-link" href="logout.php">Sair</a>
    </nav>
</aside>
