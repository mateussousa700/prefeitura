<?php
require __DIR__ . '/app/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefeitura Digital - Acesso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-bg: #0f172a;
            --brand-accent: #0ea5e9;
            --brand-soft: #e0f2fe;
        }
        * { font-family: 'Space Grotesk', 'Segoe UI', sans-serif; }
        body {
            min-height: 100vh;
            background: radial-gradient(circle at 20% 20%, rgba(14,165,233,0.12), transparent 30%),
                        radial-gradient(circle at 80% 0%, rgba(14,165,233,0.18), transparent 25%),
                        linear-gradient(135deg, #0b1221 0%, #0f172a 60%, #0b1221 100%);
            color: #e2e8f0;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
            border-radius: 18px;
        }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0 !important;
            caret-color: #7dd3fc;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(14,165,233,0.2);
            border-color: var(--brand-accent);
            background: rgba(255,255,255,0.06);
        }
        .btn-brand {
            background: linear-gradient(135deg, #0ea5e9, #22d3ee);
            border: none;
            color: #0b1221;
            font-weight: 700;
        }
        .btn-ghost {
            border: 1px solid rgba(255,255,255,0.15);
            color: #e2e8f0;
        }
        .form-control::placeholder, .form-select::placeholder {
            color: #cbd5e1;
            opacity: 0.85;
        }
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        textarea:-webkit-autofill {
            -webkit-text-fill-color: #e2e8f0;
            -webkit-box-shadow: 0 0 0 30px rgba(255,255,255,0.04) inset;
            box-shadow: 0 0 0 30px rgba(255,255,255,0.04) inset;
        }
        .badge-soft {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
        }
        .form-toggle button.active {
            background: rgba(14,165,233,0.15);
            border-color: rgba(14,165,233,0.4);
            color: #e2e8f0;
        }
        .helper-text {
            color: #cbd5e1;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row align-items-center justify-content-between g-4">
        <div class="col-lg-6">
            <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">Prefeitura Digital</span>
            <h1 class="display-5 fw-bold mb-3">Conecte-se aos serviços municipais com segurança.</h1>
            <p class="lead helper-text mb-4">
                Acompanhe protocolos, solicite serviços e mantenha seus dados atualizados.
                Em minutos você cria sua conta e confirma pelo WhatsApp e e-mail.
            </p>
            <div class="d-flex gap-3">
                <div>
                    <h5 class="mb-1 text-white">Cadastro rápido</h5>
                    <p class="helper-text mb-0">Informações essenciais para começarmos sua experiência.</p>
                </div>
                <div>
                    <h5 class="mb-1 text-white">Confirmação dupla</h5>
                    <p class="helper-text mb-0">Verificação por e-mail e WhatsApp via Ultramsg.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-card p-4">
                <div class="form-toggle btn-group w-100 mb-3" role="group" aria-label="Alternar acesso">
                    <button type="button" class="btn btn-ghost active" data-target="login">Entrar</button>
                    <button type="button" class="btn btn-ghost" data-target="register">Criar conta</button>
                </div>

                <?php renderFlash(); ?>

                <form id="login-form" method="POST" action="login.php" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="login-email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="login-email" name="email" required placeholder="voce@exemplo.com">
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="login-password" class="form-label">Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="login-password" name="password" required minlength="8" placeholder="********">
                            <button class="btn btn-ghost" type="button" id="toggle-login-password">Ver</button>
                        </div>
                        <div class="invalid-feedback">Senha é obrigatória.</div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2">Entrar</button>
                </form>

                <form id="register-form" method="POST" action="register.php" class="needs-validation d-none" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nome completo</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="Seu nome">
                        <div class="invalid-feedback">Informe seu nome.</div>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telefone/WhatsApp</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="(DDD) 9XXXX-XXXX">
                        <div class="invalid-feedback">Informe um número de contato.</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="voce@exemplo.com">
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="cpf" class="form-label">CPF</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" required placeholder="000.000.000-00">
                        <div class="invalid-feedback">CPF é obrigatório.</div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Endereço (rua e número)</label>
                        <input type="text" class="form-control" id="address" name="address" required placeholder="Rua Exemplo, 123 - apartamento, bloco">
                        <div class="invalid-feedback">Informe a rua e número.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="neighborhood" class="form-label">Bairro (Fortaleza)</label>
                            <select class="form-select" id="neighborhood" name="neighborhood" required>
                                <option value="">Carregando bairros...</option>
                            </select>
                            <div class="invalid-feedback">Selecione um bairro.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="zip" class="form-label">CEP</label>
                            <input type="text" class="form-control" id="zip" name="zip" required pattern="\d{5}-?\d{3}" inputmode="numeric" placeholder="00000-000">
                            <div class="invalid-feedback">Informe um CEP válido (8 dígitos).</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Mínimo 8 caracteres">
                            <button class="btn btn-ghost" type="button" id="toggle-password">Ver</button>
                        </div>
                        <div class="invalid-feedback">Crie uma senha com pelo menos 8 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar senha</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required minlength="8" placeholder="Repita a senha">
                            <button class="btn btn-ghost" type="button" id="toggle-password-confirm">Ver</button>
                        </div>
                        <div class="invalid-feedback">Confirme sua senha.</div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2">Criar conta</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const toggleButtons = document.querySelectorAll('.form-toggle button');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const addressInput = document.getElementById('address');
    const zipInput = document.getElementById('zip');
    const toggleLoginPasswordBtn = document.getElementById('toggle-login-password');
    const togglePasswordBtn = document.getElementById('toggle-password');
    const togglePasswordConfirmBtn = document.getElementById('toggle-password-confirm');
    const loginPasswordInput = document.getElementById('login-password');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirmation');

    const setActiveForm = (target) => {
        const isLogin = target === 'login';
        toggleButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.target === target));
        loginForm.classList.toggle('d-none', !isLogin);
        registerForm.classList.toggle('d-none', isLogin);
        window.location.hash = target === 'register' ? '#register' : '#login';
    };

    toggleButtons.forEach(btn => btn.addEventListener('click', () => setActiveForm(btn.dataset.target)));
    if (window.location.hash === '#register') setActiveForm('register');

    const togglePasswordVisibility = (input, btn) => {
        if (!input || !btn) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.textContent = isPassword ? 'Ocultar' : 'Ver';
    };

    toggleLoginPasswordBtn?.addEventListener('click', () => togglePasswordVisibility(loginPasswordInput, toggleLoginPasswordBtn));
    togglePasswordBtn?.addEventListener('click', () => togglePasswordVisibility(passwordInput, togglePasswordBtn));
    togglePasswordConfirmBtn?.addEventListener('click', () => togglePasswordVisibility(passwordConfirmInput, togglePasswordConfirmBtn));

    // Bairro via API Cep.Ia (Fortaleza)
    const neighborhoodSelect = document.getElementById('neighborhood');
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

    async function loadNeighborhoods() {
        if (!neighborhoodSelect) return;
        neighborhoodSelect.disabled = true;
        neighborhoodSelect.innerHTML = '<option value=\"\">Carregando bairros...</option>';
        const apiUrl = 'https://cep.ia/api/v1/neighborhoods?city=Fortaleza&state=CE';

        try {
            const response = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('Erro ao consultar Cep.Ia');
            const data = await response.json();
            const neighborhoods = Array.isArray(data?.neighborhoods) ? data.neighborhoods : [];
            const list = neighborhoods.length ? neighborhoods : fallbackNeighborhoods;
            const ordered = sortList(list);
            neighborhoodSelect.innerHTML = '<option value=\"\">Selecione</option>' +
                ordered.map(n => `<option value=\"${n}\">${n}</option>`).join('');
        } catch (e) {
            console.warn('Falha na API Cep.Ia, usando lista padrão.', e);
            const ordered = sortList(fallbackNeighborhoods);
            neighborhoodSelect.innerHTML = '<option value=\"\">Selecione</option>' +
                ordered.map(n => `<option value=\"${n}\">${n}</option>`).join('');
        } finally {
            neighborhoodSelect.disabled = false;
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

    function setNeighborhoodValue(name) {
        if (!neighborhoodSelect || !name) return;
        const target = name.trim();
        const options = Array.from(neighborhoodSelect.options || []);
        const match = options.find(opt => opt.value.toLowerCase() === target.toLowerCase());
        if (match) {
            neighborhoodSelect.value = match.value;
        }
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
