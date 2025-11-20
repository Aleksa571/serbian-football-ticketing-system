<?php
session_start();
include 'conn.php';

if (!isset($_SESSION['id_korisnika'])) {
    header("Location: ../view/login.php?error=1");
    exit();
}

$idKorisnik = $_SESSION['id_korisnika'];
$idKarte = $_POST['idKarte'];
$kolicina = (int)$_POST['kolicina'];

// Uzmi informacije o karti da bih znao kombinaciju utakmice, tribine i kategorije
$stmtKarta = $pdo->prepare("SELECT k.utakmica_id, k.tribina_id, k.kategorija_id, u.datum_utakmice 
                             FROM karte k
                             JOIN utakmice u ON k.utakmica_id = u.utakmica_id
                             WHERE k.karta_id = :idKarte");
$stmtKarta->execute([':idKarte' => $idKarte]);
$kartaInfo = $stmtKarta->fetch(PDO::FETCH_ASSOC);

if (!$kartaInfo) {
    header("Location: ../index.php#karte?error=invalid_ticket");
    exit();
}

// PROVERA: Da li je datum utakmice prošao?
if ($kartaInfo['datum_utakmice']) {
    $datumUtakmice = new DateTime($kartaInfo['datum_utakmice']);
    $danas = new DateTime();
    
    if ($datumUtakmice < $danas) {
        header("Location: ../index.php#karte?error=past_match");
        exit();
    }
}

$utakmica_id = $kartaInfo['utakmica_id'];
$tribina_id = $kartaInfo['tribina_id'];
$kategorija_id = $kartaInfo['kategorija_id'];

// Provera da li ta karta vec postoji u korpi
$stmt = $pdo->prepare("SELECT * FROM korpa WHERE idKorisnik = :idKorisnik AND idKarte = :idKarte");
$stmt->execute([
    ':idKorisnik' => $idKorisnik,
    ':idKarte' => $idKarte
]);

if ($stmt->rowCount() > 0) {
    $postojeca = $stmt->fetch(PDO::FETCH_ASSOC);
    $novaKolicina = $postojeca['kolicina'] + $kolicina;

    if ($novaKolicina > 4) {
        $novaKolicina = 4;
    }

    // Proveri koliko ima dostupnih karata za tu kombinaciju
    $stmtDostupno = $pdo->prepare("SELECT COUNT(*) FROM karte 
                                    WHERE utakmica_id = :utakmica_id 
                                    AND tribina_id = :tribina_id 
                                    AND kategorija_id = :kategorija_id 
                                    AND status = 'slobodna'");
    $stmtDostupno->execute([
        ':utakmica_id' => $utakmica_id,
        ':tribina_id' => $tribina_id,
        ':kategorija_id' => $kategorija_id
    ]);
    $dostupno = (int)$stmtDostupno->fetchColumn();

    // Proveri koliko dodatnih karata treba da rezervišem
    $dodatnaKolicina = $novaKolicina - $postojeca['kolicina'];
    
    if ($dodatnaKolicina > $dostupno) {
        header("Location: ../index.php#karte?error=not_enough_tickets");
        exit();
    }

    // Rezerviši dodatne karte
    if ($dodatnaKolicina > 0) {
        $stmtRezervisi = $pdo->prepare("UPDATE karte 
                                        SET status = 'rezervisana' 
                                        WHERE utakmica_id = :utakmica_id 
                                        AND tribina_id = :tribina_id 
                                        AND kategorija_id = :kategorija_id 
                                        AND status = 'slobodna' 
                                        LIMIT :limit");
        $stmtRezervisi->bindValue(':utakmica_id', $utakmica_id, PDO::PARAM_INT);
        $stmtRezervisi->bindValue(':tribina_id', $tribina_id, PDO::PARAM_INT);
        $stmtRezervisi->bindValue(':kategorija_id', $kategorija_id, PDO::PARAM_INT);
        $stmtRezervisi->bindValue(':limit', $dodatnaKolicina, PDO::PARAM_INT);
        $stmtRezervisi->execute();
    }

    $stmtUpdate = $pdo->prepare("UPDATE korpa 
                                 SET kolicina = :kolicina, updated_at = NOW() 
                                 WHERE id = :id");
    $stmtUpdate->execute([
        ':kolicina' => $novaKolicina,
        ':id' => $postojeca['id']
    ]);
} else {
    // Ubacivanje nove stavke
    if ($kolicina > 4) {
        $kolicina = 4;
    }

    // Proveri koliko ima dostupnih karata za tu kombinaciju
    $stmtDostupno = $pdo->prepare("SELECT COUNT(*) FROM karte 
                                    WHERE utakmica_id = :utakmica_id 
                                    AND tribina_id = :tribina_id 
                                    AND kategorija_id = :kategorija_id 
                                    AND status = 'slobodna'");
    $stmtDostupno->execute([
        ':utakmica_id' => $utakmica_id,
        ':tribina_id' => $tribina_id,
        ':kategorija_id' => $kategorija_id
    ]);
    $dostupno = (int)$stmtDostupno->fetchColumn();

    if ($kolicina > $dostupno) {
        header("Location: ../index.php#karte?error=not_enough_tickets");
        exit();
    }

    // Rezerviši karte
    $stmtRezervisi = $pdo->prepare("UPDATE karte 
                                    SET status = 'rezervisana' 
                                    WHERE utakmica_id = :utakmica_id 
                                    AND tribina_id = :tribina_id 
                                    AND kategorija_id = :kategorija_id 
                                    AND status = 'slobodna' 
                                    LIMIT :limit");
    $stmtRezervisi->bindValue(':utakmica_id', $utakmica_id, PDO::PARAM_INT);
    $stmtRezervisi->bindValue(':tribina_id', $tribina_id, PDO::PARAM_INT);
    $stmtRezervisi->bindValue(':kategorija_id', $kategorija_id, PDO::PARAM_INT);
    $stmtRezervisi->bindValue(':limit', $kolicina, PDO::PARAM_INT);
    $stmtRezervisi->execute();

    $stmtInsert = $pdo->prepare("INSERT INTO korpa (idKorisnik, idKarte, kolicina, created_at, updated_at) 
                                 VALUES (:idKorisnik, :idKarte, :kolicina, NOW(), NOW())");
    $stmtInsert->execute([
        ':idKorisnik' => $idKorisnik,
        ':idKarte' => $idKarte,
        ':kolicina' => $kolicina
    ]);
}

header("Location: ../index.php#karte");
exit();
?>
