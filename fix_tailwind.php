<?php
$files = glob(__DIR__ . '/*.php');
foreach ($files as $f) {
    $c = file_get_contents($f);
    $c = str_replace(
        '<script src="https://cdn.tailwindcss.com"></script>',
        '<script src="https://cdn.tailwindcss.com"></script>',
        $c
    );
    file_put_contents($f, $c);
    echo "Updated " . basename($f) . "\n";
}
