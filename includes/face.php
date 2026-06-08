<?php
declare(strict_types=1);

/**
 * Compare a live face descriptor against stored descriptors using Euclidean distance.
 * @param array $liveDescriptor 128-dim float array
 * @param array $storedDescriptors Array of 128-dim float arrays (5 stored)
 * @param float $threshold Match threshold (lower = stricter)
 * @return array ['match' => bool, 'distance' => float, 'minIndex' => int]
 */
function compare_face(array $liveDescriptor, array $storedDescriptors, float $threshold = 0.45): array {
    $minDist = PHP_FLOAT_MAX;
    $minIdx  = -1;

    foreach ($storedDescriptors as $i => $stored) {
        $dist = euclidean_distance($liveDescriptor, $stored);
        if ($dist < $minDist) {
            $minDist = $dist;
            $minIdx  = $i;
        }
    }

    return [
        'match'    => $minDist < $threshold,
        'distance' => round($minDist, 4),
        'minIndex' => $minIdx,
    ];
}

function euclidean_distance(array $a, array $b): float {
    $sum = 0.0;
    $len = min(count($a), count($b));
    for ($i = 0; $i < $len; $i++) {
        $sum += ($a[$i] - $b[$i]) ** 2;
    }
    return sqrt($sum);
}
