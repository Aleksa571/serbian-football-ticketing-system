<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['id_korisnika'])) {
    header("Location: ../view/login.php");
    exit();
}

$idKorisnik = $_SESSION['id_korisnika'];

// Uzmi sve stavke iz korpe pre brisanja
$stmtKorpa = $pdo->prepare("SELECT kolicina, idKarte FROM korpa WHERE idKorisnik = :idKorisnik");
$stmtKorpa->execute([':idKorisnik' => $idKorisnik]);
$stavke = $stmtKorpa->fetchAll(PDO::FETCH_ASSOC);

// Vrati sve karte na status slobodna
foreach ($stavke as $stavka) {
    $kolicina = (int)$stavka['kolicina'];
    $idKarte = $stavka['idKarte'];

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

// Obriši sve stavke iz korpe
$stmt = $pdo->prepare("DELETE FROM korpa WHERE idKorisnik = :idKorisnik");
$stmt->execute([':idKorisnik' => $idKorisnik]);

header("Location: ../index.php#karte");
exit();
?>
