<?php
session_start();
if (!isset($_SESSION['id_korisnika']) || $_SESSION['id_uloga'] != 1) {
    http_response_code(403);
    exit();
}

include 'conn.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit();
}

// Pretraži sve jedinstvene timove (domaći i gostujući) koji sadrže query
$stmt = $pdo->prepare("
    SELECT DISTINCT tim 
    FROM (
        SELECT domaci_tim AS tim FROM utakmice WHERE domaci_tim IS NOT NULL AND domaci_tim != '' 
        AND domaci_tim LIKE ?
        UNION
        SELECT gostujuci_tim AS tim FROM utakmice WHERE gostujuci_tim IS NOT NULL AND gostujuci_tim != '' 
        AND gostujuci_tim LIKE ?
    ) AS svi_timovi
    ORDER BY tim
    LIMIT 20
");

$searchTerm = '%' . $query . '%';
$stmt->execute([$searchTerm, $searchTerm]);
$timovi = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode($timovi);

