<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['id_korisnika'])) {
    header("Location: ../view/login.php");
    exit();
}

$id = (int)$_GET['id'];

// Uzmi informacije o stavci u korpi pre brisanja
$stmtKorpa = $pdo->prepare("SELECT kolicina, idKarte FROM korpa WHERE id = :id AND idKorisnik = :idKorisnik");
$stmtKorpa->execute([
    ':id' => $id,
    ':idKorisnik' => $_SESSION['id_korisnika']
]);
$korpaInfo = $stmtKorpa->fetch(PDO::FETCH_ASSOC);

if ($korpaInfo) {
    $kolicina = (int)$korpaInfo['kolicina'];
    $idKarte = $korpaInfo['idKarte'];

    // Uzmi informacije o karti
    $stmtKarta = $pdo->prepare("SELECT utakmica_id, tribina_id, kategorija_id FROM karte WHERE karta_id = :idKarte");
    $stmtKarta->execute([':idKarte' => $idKarte]);
    $kartaInfo = $stmtKarta->fetch(PDO::FETCH_ASSOC);

    if ($kartaInfo) {
        // Vrati karte na status slobodna
        $stmtVrati = $pdo->prepare("UPDATE karte 
                                    SET status = 'slobodna' 
                                    WHERE utakmica_id = :utakmica_id 
                                    AND tribina_id = :tribina_id 
                                    AND kategorija_id = :kategorija_id 
                                    AND status = 'rezervisana' 
                                    LIMIT :limit");
        $stmtVrati->bindValue(':utakmica_id', $kartaInfo['utakmica_id'], PDO::PARAM_INT);
        $stmtVrati->bindValue(':tribina_id', $kartaInfo['tribina_id'], PDO::PARAM_INT);
        $stmtVrati->bindValue(':kategorija_id', $kartaInfo['kategorija_id'], PDO::PARAM_INT);
        $stmtVrati->bindValue(':limit', $kolicina, PDO::PARAM_INT);
        $stmtVrati->execute();
    }
}

// Obriši stavku iz korpe
$stmt = $pdo->prepare("DELETE FROM korpa WHERE id = :id AND idKorisnik = :idKorisnik");
$stmt->execute([
    ':id' => $id,
    ':idKorisnik' => $_SESSION['id_korisnika']
]);

header("Location: ../view/korpa.php");
exit();
?>
