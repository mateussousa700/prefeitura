<?php
declare(strict_types=1);

function findUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserByEmailOrCpf(PDO $pdo, string $email, ?string $cpf, ?string $cnpj = null): ?array
{
    $conditions = ['email = :email'];
    $params = ['email' => $email];

    if ($cpf !== null && $cpf !== '') {
        $conditions[] = 'cpf = :cpf';
        $params['cpf'] = $cpf;
    }
    if ($cnpj !== null && $cnpj !== '') {
        $conditions[] = 'cnpj = :cnpj';
        $params['cnpj'] = $cnpj;
    }

    $sql = 'SELECT id FROM users WHERE ' . implode(' OR ', $conditions) . ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findOtherUserByEmailOrCpf(PDO $pdo, string $email, ?string $cpf, int $id, ?string $cnpj = null): ?array
{
    $conditions = ['email = :email'];
    $params = ['email' => $email];

    if ($cpf !== null && $cpf !== '') {
        $conditions[] = 'cpf = :cpf';
        $params['cpf'] = $cpf;
    }
    if ($cnpj !== null && $cnpj !== '') {
        $conditions[] = 'cnpj = :cnpj';
        $params['cnpj'] = $cnpj;
    }
    $params['id'] = $id;

    $sql = 'SELECT id FROM users WHERE (' . implode(' OR ', $conditions) . ') AND id != :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT name, phone, email, person_type, cpf, cnpj, address, neighborhood, zip, user_type FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserContactById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT name, email, phone FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function listUsers(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, email, phone, person_type, cpf, cnpj, user_type, created_at FROM users ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function listInternalUsersBySecretaria(PDO $pdo, int $secretariaId): array
{
    $stmt = $pdo->prepare('
        SELECT u.id, u.name, u.email, u.user_type
        FROM users u
        LEFT JOIN usuario_secretarias us
            ON us.usuario_id = u.id AND us.secretaria_id = :secretaria_id
        WHERE u.user_type IN ("gestor", "admin")
          AND (u.user_type = "admin" OR us.secretaria_id IS NOT NULL)
        ORDER BY u.name ASC
    ');
    $stmt->execute(['secretaria_id' => $secretariaId]);
    return $stmt->fetchAll();
}

function updateUserType(PDO $pdo, int $id, string $type): void
{
    $stmt = $pdo->prepare('UPDATE users SET user_type = :type, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['type' => $type, 'id' => $id]);
}

function updateUserPassword(PDO $pdo, int $id, string $passwordHash): void
{
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
}

function createUser(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare('
        INSERT INTO users (name, phone, email, person_type, cpf, cnpj, address, neighborhood, zip, user_type, password_hash, verification_token, created_at, updated_at)
        VALUES (:name, :phone, :email, :person_type, :cpf, :cnpj, :address, :neighborhood, :zip, :user_type, :password_hash, :token, NOW(), NOW())
    ');

    $stmt->execute($data);
    return (int)$pdo->lastInsertId();
}

function updateUserProfile(PDO $pdo, int $id, array $fields, ?string $passwordHash): void
{
    $sql = 'UPDATE users SET name = :name, phone = :phone, email = :email, person_type = :person_type, cpf = :cpf, cnpj = :cnpj, address = :address, neighborhood = :neighborhood, zip = :zip, updated_at = NOW()';
    if ($passwordHash !== null) {
        $fields['password_hash'] = $passwordHash;
        $sql .= ', password_hash = :password_hash';
    }
    $sql .= ' WHERE id = :id';
    $fields['id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($fields);
}

function findUserByVerificationToken(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare('SELECT id, email_verified_at, whatsapp_verified_at FROM users WHERE verification_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function verifyUserById(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('
        UPDATE users
        SET email_verified_at = COALESCE(email_verified_at, NOW()),
            whatsapp_verified_at = COALESCE(whatsapp_verified_at, NOW()),
            verification_token = NULL,
            updated_at = NOW()
        WHERE id = :id
    ');
    $stmt->execute(['id' => $id]);
}
