<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}

$missing = 'missing-secretaria';
$inactive = 'inactive-secretaria';

assertSame(true, canUserReassignSecretaria('gestor'), 'Gestor should be allowed to reassign.');
assertSame(true, canUserReassignSecretaria('admin'), 'Admin should be allowed to reassign.');
assertSame(false, canUserReassignSecretaria('operador'), 'Operador should not be allowed to reassign.');
assertSame(false, canUserReassignSecretaria('populacao'), 'Populacao should not be allowed to reassign.');

assertSame($missing, secretariaLinkError(null, true, $missing, $inactive), 'Missing secretaria should fail.');
assertSame($inactive, secretariaLinkError(10, false, $missing, $inactive), 'Inactive secretaria should fail.');
assertSame(null, secretariaLinkError(10, true, $missing, $inactive), 'Active secretaria should pass.');

echo "OK\n";
