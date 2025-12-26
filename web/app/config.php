<?php
declare(strict_types=1);

// Ajuste conforme seu ambiente local.
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'prefeitura_app';
const DB_USER = 'root';
const DB_PASS = '';


// URL base usada nos links de confirmação enviados por e-mail e WhatsApp.
const BASE_URL = 'http://localhost/prefeitura/web';

// Configurações da API Ultramsg (WhatsApp). Preencha antes de enviar mensagens.
const ULTRAMSG_INSTANCE_ID = 'instance124122';
const ULTRAMSG_TOKEN = 'vtts75qh13n0jdc7';
// Número remetente no formato internacional, ex: 55DDDNUMERO
const ULTRAMSG_SENDER = '5585987319678';

// Configuração de e-mail (SMTP). Evite deixar credenciais em repositório; use variáveis de ambiente.
const MAIL_TRANSPORT = 'smtp';
const MAIL_HOST = 'smtp.hostinger.com';
const MAIL_PORT = 587;
const MAIL_ENCRYPTION = 'tls';
const MAIL_USER = 'contato@apexobras.com.br';
const MAIL_PASS = 'Caninde.123';
const MAIL_FROM = 'contato@apexobras.com.br';
const MAIL_FROM_NAME = 'Prefeitura Digital';

// Prevenção de chamados duplicados.
const DUPLICATE_RADIUS_METERS = 50;
const DUPLICATE_DAYS_WINDOW = 3;
const DUPLICATE_MAX_RESULTS = 5;

// Ativos urbanos (raio de busca padrao e limite de resultados).
const ATIVOS_RADIUS_METERS = 50;
const ATIVOS_MAX_RESULTS = 10;

// Assinatura dos relatorios PDF (QR code).
const REPORTS_SECRET = 'altere-este-segredo';
