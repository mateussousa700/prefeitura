<?php
$currentPage = $currentPage ?? '';
$userType = $userType ?? 'populacao';
$managerRoles = ['gestor', 'admin', 'gestor_global'];
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
            <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
            <a class="nav-link <?php echo $currentPage === 'relatorios' ? 'active' : ''; ?>" href="relatorios.php">Relatórios</a>
            <a class="nav-link <?php echo $currentPage === 'tickets' ? 'active' : ''; ?>" href="tickets.php">Chamados</a>
            <a class="nav-link <?php echo $currentPage === 'completed' ? 'active' : ''; ?>" href="completed.php">Concluídos</a>
            <a class="nav-link <?php echo $currentPage === 'service_types' ? 'active' : ''; ?>" href="service_types.php">Tipos de chamado</a>
            <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">Gestão de usuários</a>
        <?php endif; ?>
        <a class="nav-link" href="logout.php">Sair</a>
    </nav>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const layout = document.querySelector('.layout');
        if (!layout) {
            return;
        }

        const storageKey = 'prefeitura_fullscreen';
        const body = document.body;

        const setFullscreen = (enabled) => {
            body.classList.toggle('is-fullscreen', enabled);
            if (enabled) {
                localStorage.setItem(storageKey, '1');
            } else {
                localStorage.setItem(storageKey, '0');
            }
        };

        const existingToggle = document.querySelector('.fullscreen-toggle');
        const toggleButton = existingToggle || document.createElement('button');
        if (!existingToggle) {
            toggleButton.type = 'button';
            toggleButton.className = 'fullscreen-toggle';
            toggleButton.textContent = 'Mostrar menu';
            toggleButton.setAttribute('aria-label', 'Mostrar menu lateral');
            toggleButton.setAttribute('title', 'Mostrar menu lateral');
            toggleButton.addEventListener('click', () => setFullscreen(false));
            body.appendChild(toggleButton);
        }

        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach((link) => {
            const href = link.getAttribute('href') || '';
            if (href === 'logout.php') {
                return;
            }
            link.addEventListener('click', () => {
                setFullscreen(true);
            });
        });

        if (localStorage.getItem(storageKey) === '1') {
            setFullscreen(true);
        }
    });
</script>
