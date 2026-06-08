<?php
$files = glob(__DIR__ . '/*.php');
foreach ($files as $f) {
    $c = file_get_contents($f);
    $orig = $c;
    
    // Replace grid-cols-2 with responsive version, but be careful not to break already responsive ones
    $c = preg_replace('/class="([^"]*)grid grid-cols-2([^"]*)"/', 'class="$1grid grid-cols-1 sm:grid-cols-2$2"', $c);
    $c = preg_replace('/class="([^"]*)grid grid-cols-3([^"]*)"/', 'class="$1grid grid-cols-1 md:grid-cols-3$2"', $c);
    $c = preg_replace('/class="([^"]*)grid grid-cols-4([^"]*)"/', 'class="$1grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4$2"', $c);
    
    // Some flex containers might need mobile column direction
    $c = preg_replace('/class="([^"]*)flex items-start justify-between([^"]*)"/', 'class="$1flex flex-col sm:flex-row sm:items-start justify-between gap-2$2"', $c);
    
    if ($c !== $orig) {
        file_put_contents($f, $c);
        echo "Made responsive updates in " . basename($f) . "\n";
    }
}
