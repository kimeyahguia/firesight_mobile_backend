<?php
// scripts/import_boundaries.php
// Batch-imports all .geojson files from boundaries/ into barangays.boundary_coords

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain');

$folder = __DIR__ . '/../boundaries';

if (!is_dir($folder)) {
    die("❌ Folder not found: {$folder}\n");
}

$files = glob($folder . '/*.geojson');

if (empty($files)) {
    die("⚠️ No .geojson files found sa {$folder}\n");
}

// Kunin lahat ng barangay names sa DB para sa case-insensitive matching
$dbBarangays = $conn->query("SELECT id, name FROM barangays")->fetchAll(PDO::FETCH_ASSOC);
$lookup = [];
foreach ($dbBarangays as $b) {
    $key = strtolower(trim($b['name']));
    $lookup[$key] = $b['id'];
}

$update = $conn->prepare("UPDATE barangays SET boundary_coords = :coords WHERE id = :id");

$matched = [];
$unmatched = [];

foreach ($files as $file) {
    $filename = basename($file);
    $json = file_get_contents($file);
    $data = json_decode($json, true);

    if (!isset($data['features']) || !is_array($data['features'])) {
        $unmatched[] = "{$filename} → invalid GeoJSON structure";
        continue;
    }

    foreach ($data['features'] as $index => $feature) {
        $props = $feature['properties'] ?? [];
        $rawName = $props['barangay'] ?? $props['name'] ?? null;

        if (!$rawName) {
            $unmatched[] = "{$filename} [feature #{$index}] → walang 'barangay' or 'name' property";
            continue;
        }

        $key = strtolower(trim($rawName));

        if (!isset($lookup[$key])) {
            $unmatched[] = "{$filename} [feature #{$index}] → '{$rawName}' hindi nahanap sa barangays table";
            continue;
        }

        $geomType = $feature['geometry']['type'] ?? null;
        if ($geomType !== 'Polygon') {
            $unmatched[] = "{$filename} [feature #{$index}] → geometry type ay '{$geomType}', dapat 'Polygon'";
            continue;
        }

        // GeoJSON = [lng, lat], kailangan natin [lat, lng] for Leaflet
        $ring = $feature['geometry']['coordinates'][0] ?? [];
        $flipped = array_map(function ($point) {
            return [round($point[1], 7), round($point[0], 7)]; // [lat, lng]
        }, $ring);

        $barangayId = $lookup[$key];

        $update->execute([
            ':coords' => json_encode($flipped),
            ':id' => $barangayId,
        ]);

        $matched[] = "{$filename} → '{$rawName}' (id: {$barangayId}) ✅ {" . count($flipped) . " points}";
    }
}

echo "===== IMPORT REPORT =====\n\n";
echo "✅ MATCHED & UPDATED (" . count($matched) . "):\n";
foreach ($matched as $line) {
    echo "  {$line}\n";
}

echo "\n⚠️ UNMATCHED / SKIPPED (" . count($unmatched) . "):\n";
if (empty($unmatched)) {
    echo "  (wala, lahat ok!)\n";
} else {
    foreach ($unmatched as $line) {
        echo "  {$line}\n";
    }
}

echo "\n===== END =====\n";