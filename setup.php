<?php
// Database setup script - run once
$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$dbname = 'recruitment_db';

try {
    // Connect without database to create it
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$dbname}' created/verified.\n";
    
    // Connect to database
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Run migrations in order
    $migrationDir = __DIR__ . '/database/migrations';
    $files = glob($migrationDir . '/*.sql');
    sort($files);
    
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        echo "Running: " . basename($file) . "... ";
        
        // Split on semicolons to handle multiple statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt) $pdo->exec($stmt);
        }
        echo "OK\n";
    }
    
    // Create admin user if not exists
    $admin = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $admin->execute(['admin@recruitchain.app']);
    if (!$admin->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (email, password, role, full_name, face_verified) VALUES (?, ?, 'ADMIN', 'System Admin', 1)")
             ->execute(['admin@recruitchain.app', $hash]);
        echo "\nAdmin user created: admin@recruitchain.app / admin123\n";
    } else {
        echo "\nAdmin user already exists.\n";
    }
    
    echo "\nSetup complete!\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
