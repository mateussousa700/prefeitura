<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    flash('danger', 'Faça login para acessar.');
    header('Location: index.php#login');
    exit;
}

$currentPage = 'profile';
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Cidadão';
$userType = $_SESSION['user_type'] ?? 'populacao';
$flash = consumeFlash();

$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = normalizeDigits($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = normalizeDigits($_POST['cpf'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $errors = [];

    if ($name === '') {
        $errors[] = 'Informe o nome.';
    }
    if ($phone === '' || strlen($phone) < 10) {
        $errors[] = 'Telefone inválido.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail inválido.';
    }
    if ($cpf === '' || strlen($cpf) !== 11) {
        $errors[] = 'CPF deve conter 11 dígitos.';
    }
    if ($address === '') {
        $errors[] = 'Endereço é obrigatório.';
    }
    if ($neighborhood === '') {
        $errors[] = 'Selecione um bairro.';
    }
    $zipDigits = normalizeDigits($zip);
    if ($zipDigits === '' || strlen($zipDigits) !== 8) {
        $errors[] = 'CEP deve conter 8 dígitos.';
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'Nova senha deve ter pelo menos 8 caracteres.';
    }
    if ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $errors[] = 'As senhas não conferem.';
    }

    // Unicidade de e-mail/CPF
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE (email = :email OR cpf = :cpf) AND id != :id LIMIT 1');
        $stmt->execute(['email' => $email, 'cpf' => $cpf, 'id' => $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'E-mail ou CPF já está em uso por outro usuário.';
        }
    }

    if ($errors) {
        flash('danger', implode(' ', $errors));
        header('Location: profile.php');
        exit;
    }

    $zipFormatted = substr($zipDigits, 0, 5) . '-' . substr($zipDigits, 5);

    $updateFields = [
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'cpf' => $cpf,
        'address' => $address,
        'neighborhood' => $neighborhood,
        'zip' => $zipFormatted,
        'id' => $userId,
    ];

    $sql = 'UPDATE users SET name = :name, phone = :phone, email = :email, cpf = :cpf, address = :address, neighborhood = :neighborhood, zip = :zip, updated_at = NOW()';

    if ($newPassword !== '') {
        $updateFields['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql .= ', password_hash = :password_hash';
    }

    $sql .= ' WHERE id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateFields);

    $_SESSION['user_name'] = $name;

    flash('success', 'Dados atualizados com sucesso.');
    header('Location: profile.php');
    exit;
}

// Carrega dados do usuário
$stmt = $pdo->prepare('SELECT name, phone, email, cpf, address, neighborhood, zip, user_type FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    flash('danger', 'Usuário não encontrado.');
    header('Location: home.php');
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-bg: #0f172a;
            --brand-accent: #0ea5e9;
            --brand-soft: #1b2438;
        }
        * { font-family: 'Space Grotesk', 'Segoe UI', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0b1221 0%, #0f172a 60%, #0b1221 100%);
            color: #e2e8f0;
        }
        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        .sidebar {
            background: rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.06);
            padding: 24px;
            box-shadow: 8px 0 30px rgba(0,0,0,0.2);
        }
        .brand {
            font-weight: 700;
            color: #7dd3fc;
            letter-spacing: 0.3px;
        }
        .nav-link {
            color: #cbd5e1;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }
        .nav-link.active, .nav-link:hover {
            background: rgba(14,165,233,0.12);
            color: #e2e8f0;
            border: 1px solid rgba(14,165,233,0.35);
        }
        .content { padding: 36px; }
        .glass {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        }
        .form-control, .form-select {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.32);
            color: #f8fafc;
            min-height: 44px;
        }
        .form-control::placeholder,
        .form-select::placeholder {
            color: #e2e8f0;
            opacity: 0.85;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand-accent);
            box-shadow: 0 0 0 0.28rem rgba(14,165,233,0.25);
            background: rgba(255,255,255,0.18);
            color: #ffffff;
        }
        .btn-brand {
            background: linear-gradient(135deg, #0ea5e9, #22d3ee);
            color: #0b1221;
            font-weight: 700;
            border: none;
        }
        .btn-brand:hover {
            background: linear-gradient(135deg, #0aa1e5, #1bcde7);
            color: #0b1221;
        }
        .helper-text { color: #cbd5e1; }
        label { color: #e2e8f0; }
        .form-text, .invalid-feedback { color: #cbd5e1; }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <span class="brand">Prefeitura Digital</span>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $currentPage === 'home' ? 'active' : ''; ?>" href="home.php">Início</a>
            <a class="nav-link <?php echo $currentPage === 'services' ? 'active' : ''; ?>" href="services.php">Serviços</a>
            <a class="nav-link <?php echo $currentPage === 'requests' ? 'active' : ''; ?>" href="requests.php">Meus protocolos</a>
            <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" aria-current="page" href="profile.php">Meu perfil</a>
            <?php if (in_array($userType, ['gestor', 'admin'], true)): ?>
                <a class="nav-link <?php echo $currentPage === 'tickets' ? 'active' : ''; ?>" href="tickets.php">Chamados</a>
                <a class="nav-link <?php echo $currentPage === 'completed' ? 'active' : ''; ?>" href="completed.php">Concluídos</a>
            <?php endif; ?>
            <?php if (in_array($userType, ['gestor', 'admin'], true)): ?>
                <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">Gestão de usuários</a>
            <?php endif; ?>
            <a class="nav-link" href="logout.php">Sair</a>
        </nav>
    </aside>

    <main class="content">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> mb-3">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="glass p-4 mb-4">
            <p class="text-uppercase small text-info mb-1">Meu perfil</p>
            <h2 class="fw-bold mb-2">Atualize seus dados</h2>
            <p class="mb-0 helper-text">Mantenha contato, endereço e senha sempre atualizados.</p>
        </div>

        <div class="glass p-4">
            <form method="POST" class="row g-3 needs-validation" novalidate>
                <div class="col-md-6">
                    <label for="name" class="form-label">Nome completo</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($user['name']); ?>">
                    <div class="invalid-feedback">Informe seu nome.</div>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefone/WhatsApp</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required value="<?php echo htmlspecialchars($user['phone']); ?>">
                    <div class="invalid-feedback">Informe o telefone.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                    <div class="invalid-feedback">E-mail inválido.</div>
                </div>
                <div class="col-md-6">
                    <label for="cpf" class="form-label">CPF</label>
                    <input type="text" class="form-control" id="cpf" name="cpf" required value="<?php echo htmlspecialchars($user['cpf']); ?>">
                    <div class="invalid-feedback">CPF é obrigatório.</div>
                </div>
                <div class="col-md-12">
                    <label for="address" class="form-label">Endereço (rua e número)</label>
                    <input type="text" class="form-control" id="address" name="address" required value="<?php echo htmlspecialchars($user['address']); ?>">
                    <div class="invalid-feedback">Informe o endereço.</div>
                </div>
                <div class="col-md-7">
                    <label for="neighborhood" class="form-label">Bairro (Fortaleza)</label>
                    <select class="form-select" id="neighborhood" name="neighborhood" required>
                        <option value="">Carregando bairros...</option>
                    </select>
                    <div class="invalid-feedback">Selecione um bairro.</div>
                </div>
                <div class="col-md-5">
                    <label for="zip" class="form-label">CEP</label>
                    <input type="text" class="form-control" id="zip" name="zip" required inputmode="numeric" pattern="\d{5}-?\d{3}" value="<?php echo htmlspecialchars($user['zip']); ?>">
                    <div class="invalid-feedback">Informe um CEP válido.</div>
                </div>
                <div class="col-md-6">
                    <label for="new_password" class="form-label">Nova senha (opcional)</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" placeholder="Mínimo 8 caracteres">
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirmar nova senha</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" placeholder="Repita a nova senha">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a class="btn btn-secondary" href="home.php">Cancelar</a>
                    <button type="submit" class="btn btn-brand">Salvar alterações</button>
                </div>
            </form>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const neighborhoodSelect = document.getElementById('neighborhood');
    const zipInput = document.getElementById('zip');
    const addressInput = document.getElementById('address');
    const currentNeighborhood = "<?php echo htmlspecialchars($user['neighborhood']); ?>";

    const fallbackNeighborhoods = [
        'Aldeota', 'Barra do Ceará', 'Benfica', 'Cidade dos Funcionários', 'Cocó',
        'Fátima', 'Jacarecanga', 'Jangurussu', 'Mondubim', 'Meireles', 'Messejana',
        'Parangaba', 'Parquelândia', 'Passaré', 'Praia de Iracema', 'Papicu',
        'Serrinha', 'Varjota', 'Vicente Pinzon', 'Centro'
    ];

    const sortList = (list) => [...list]
        .filter(Boolean)
        .map(item => item.trim())
        .filter(item => item.length > 0)
        .sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));

    function setNeighborhoodValue(name) {
        if (!neighborhoodSelect || !name) return;
        const target = name.trim().toLowerCase();
        const options = Array.from(neighborhoodSelect.options || []);
        const match = options.find(opt => opt.value.toLowerCase() === target);
        if (match) {
            neighborhoodSelect.value = match.value;
        }
    }

    function ensureCurrentNeighborhood(options) {
        if (!currentNeighborhood) return options;
        const exists = options.some(n => n.toLowerCase() === currentNeighborhood.toLowerCase());
        if (!exists) {
            return [...options, currentNeighborhood];
        }
        return options;
    }

    async function loadNeighborhoods() {
        if (!neighborhoodSelect) return;
        neighborhoodSelect.disabled = true;
        neighborhoodSelect.innerHTML = '<option value="">Carregando bairros...</option>';
        const apiUrl = 'https://cep.ia/api/v1/neighborhoods?city=Fortaleza&state=CE';

        try {
            const response = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('Erro ao consultar Cep.Ia');
            const data = await response.json();
            const neighborhoods = Array.isArray(data?.neighborhoods) ? data.neighborhoods : [];
            const list = neighborhoods.length ? neighborhoods : fallbackNeighborhoods;
            const withCurrent = ensureCurrentNeighborhood(list);
            const ordered = sortList(withCurrent);
            neighborhoodSelect.innerHTML = '<option value="">Selecione</option>' +
                ordered.map(n => `<option value="${n}">${n}</option>`).join('');
        } catch (e) {
            console.warn('Falha na API Cep.Ia, usando lista padrão.', e);
            const withCurrent = ensureCurrentNeighborhood(fallbackNeighborhoods);
            const ordered = sortList(withCurrent);
            neighborhoodSelect.innerHTML = '<option value="">Selecione</option>' +
                ordered.map(n => `<option value="${n}">${n}</option>`).join('');
        } finally {
            neighborhoodSelect.disabled = false;
            setNeighborhoodValue(currentNeighborhood);
        }
    }
    loadNeighborhoods();

    function formatCep(value) {
        const digits = value.replace(/\D/g, '').slice(0, 8);
        if (digits.length >= 6) {
            return digits.slice(0, 5) + '-' + digits.slice(5);
        }
        return digits;
    }

    async function lookupCep(cepDigits) {
        const url = `https://cep.ia/api/v1/cep/${cepDigits}`;
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) throw new Error('CEP não encontrado');
        return response.json();
    }

    function applyCepData(data) {
        if (!data) return;
        const street = data.street || data.logradouro || data.address || '';
        const neigh = data.neighborhood || data.district || data.bairro || '';
        if (street && addressInput) {
            addressInput.value = street;
        }
        if (neigh) {
            setNeighborhoodValue(neigh);
        }
    }

    async function handleCepFill() {
        if (!zipInput) return;
        const digits = zipInput.value.replace(/\D/g, '').slice(0, 8);
        if (digits.length !== 8) return;
        try {
            const data = await lookupCep(digits);
            applyCepData(data);
        } catch (e) {
            console.warn('Não foi possível preencher pelo CEP.', e);
        }
    }

    zipInput?.addEventListener('input', (event) => {
        event.target.value = formatCep(event.target.value);
    });
    zipInput?.addEventListener('blur', handleCepFill);
    zipInput?.addEventListener('change', handleCepFill);

    // Validação básica do Bootstrap
    (() => {
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>
</body>
</html>
