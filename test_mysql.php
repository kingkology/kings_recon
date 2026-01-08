<?php

echo "PHP Extensions Test:\n";
echo "pdo_mysql loaded: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n";
echo "mysqli loaded: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n\n";

echo "Testing MySQL PDO Connection:\n";
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=public_ip_validator',
        'root',
        'R00tUs3r'
    );
    echo "✓ MySQL Connection: SUCCESS\n";
    echo "Server Info: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
} catch (PDOException $e) {
    echo "✗ MySQL Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
}

echo "\nTesting Queue Table Access:\n";
try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=public_ip_validator',
        'root',
        'R00tUs3r'
    );
    $result = $pdo->query("SELECT COUNT(*) as count FROM jobs");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "✓ Jobs table accessible\n";
    echo "Total queued jobs: " . $row['count'] . "\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
