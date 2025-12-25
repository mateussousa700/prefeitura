<?php
declare(strict_types=1);

function findUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserByEmailOrCpf(PDO $pdo, string $email, string $cpf): ?array
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR cpf = :cpf LIMIT 1');
    $stmt->execute(['email' => $email, 'cpf' => $cpf]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findOtherUserByEmailOrCpf(PDO $pdo, string $email, string $cpf, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE (email = :email OR cpf = :cpf) AND id != :id LIMIT 1');
    $stmt->execute(['email' => $email, 'cpf' => $cpf, 'id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT name, phone, email, cpf, address, neighborhood, zip, user_type FROM users WHERE id = :id LIMIT 1');
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
    $stmt = $pdo->query('SELECT id, name, email, phone, cpf, user_type, created_at FROM users ORDER BY created_at DESC');
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
        INSERT INTO users (name, phone, email, cpf, address, neighborhood, zip, user_type, password_hash, verification_token, created_at, updated_at)
        VALUES (:name, :phone, :email, :cpf, :address, :neighborhood, :zip, :user_type, :password_hash, :token, NOW(), NOW())
    ');

    $stmt->execute($data);
    return (int)$pdo->lastInsertId();
}

function updateUserProfile(PDO $pdo, int $id, array $fields, ?string $passwordHash): void
{
    $sql = 'UPDATE users SET name = :name, phone = :phone, email = :email, cpf = :cpf, address = :address, neighborhood = :neighborhood, zip = :zip, updated_at = NOW()';
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
