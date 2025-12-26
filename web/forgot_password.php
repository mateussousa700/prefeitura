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
    <title>Prefeitura Digital - Recuperar senha</title>
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
        .form-control {
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
        .form-control::placeholder {
            color: #cbd5e1;
            opacity: 0.85;
        }
        .badge-soft {
            background: rgba(14,165,233,0.15);
            color: #7dd3fc;
        }
        .helper-text {
            color: #cbd5e1;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="glass-card p-4">
                <span class="badge badge-soft rounded-pill px-3 py-2 mb-3">Recuperar acesso</span>
                <h1 class="h4 fw-bold mb-2">Recuperar senha</h1>
                <p class="helper-text mb-4">
                    Informe o e-mail cadastrado. Enviaremos uma senha temporária por e-mail e WhatsApp.
                </p>

                <?php renderFlash(); ?>

                <form method="POST" action="forgot_password_submit.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="voce@exemplo.com">
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <a class="btn btn-ghost" href="index.php#login">Voltar</a>
                        <button type="submit" class="btn btn-brand">Enviar senha temporária</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
