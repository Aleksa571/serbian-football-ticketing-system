<?php
session_start();
include 'conn.php';

if (isset($_POST['idKorpa'], $_POST['nova_kolicina']) && is_numeric($_POST['nova_kolicina']) && $_POST['nova_kolicina'] > 0) {
    $idKorpa = (int)$_POST['idKorpa'];
    $nova_kolicina = (int)$_POST['nova_kolicina'];

    if ($nova_kolicina > 4) {
        exit();
    }

    // Uzmi trenutnu količinu i informacije o karti
    $stmtKorpa = $pdo->prepare("SELECT kolicina, idKarte FROM korpa WHERE id = :id AND idKorisnik = :idKorisnik");
    $stmtKorpa->execute([
        ':id' => $idKorpa,
        ':idKorisnik' => $_SESSION['id_korisnika']
    ]);
    $korpaInfo = $stmtKorpa->fetch(PDO::FETCH_ASSOC);

    if (!$korpaInfo) {
        exit();
    }

    $stara_kolicina = (int)$korpaInfo['kolicina'];
    $idKarte = $korpaInfo['idKarte'];

    // Uzmi informacije o karti
    $stmtKarta = $pdo->prepare("SELECT utakmica_id, tribina_id, kategorija_id FROM karte WHERE karta_id = :idKarte");
    $stmtKarta->execute([':idKarte' => $idKarte]);
    $kartaInfo = $stmtKarta->fetch(PDO::FETCH_ASSOC);

    if (!$kartaInfo) {
        exit();
    }

    $utakmica_id = $kartaInfo['utakmica_id'];
    $tribina_id = $kartaInfo['tribina_id'];
    $kategorija_id = $kartaInfo['kategorija_id'];

    $razlika = $nova_kolicina - $stara_kolicina;

    if ($razlika > 0) {
        // Povećava se količina - treba rezervisati dodatne karte
        // Proveri koliko ima dostupnih karata
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

        if ($razlika > $dostupno) {
            header("Location: ../view/korpa.php?error=not_enough_tickets");
            exit();
        }

        // Rezerviši dodatne karte
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
        $stmtRezervisi->bindValue(':limit', $razlika, PDO::PARAM_INT);
        $stmtRezervisi->execute();
    } elseif ($razlika < 0) {
        // Smanjuje se količina - treba vratiti karte na slobodna
        $vratiKolicina = abs($razlika);
        $stmtVrati = $pdo->prepare("UPDATE karte 
                                    SET status = 'slobodna' 
                                    WHERE utakmica_id = :utakmica_id 
                                    AND tribina_id = :tribina_id 
                                    AND kategorija_id = :kategorija_id 
                                    AND status = 'rezervisana' 
                                    LIMIT :limit");
        $stmtVrati->bindValue(':utakmica_id', $utakmica_id, PDO::PARAM_INT);
        $stmtVrati->bindValue(':tribina_id', $tribina_id, PDO::PARAM_INT);
        $stmtVrati->bindValue(':kategorija_id', $kategorija_id, PDO::PARAM_INT);
        $stmtVrati->bindValue(':limit', $vratiKolicina, PDO::PARAM_INT);
        $stmtVrati->execute();
    }

    $stmt = $pdo->prepare("UPDATE korpa 
                SET kolicina = :kolicina 
                WHERE id = :id AND idKorisnik = :idKorisnik");

    $stmt->execute([
        ':kolicina' => $nova_kolicina,
        ':id' => $idKorpa,
        ':idKorisnik' => $_SESSION['id_korisnika']
    ]);

    header("Location: ../view/korpa.php");
    exit();
} else {
    exit();
}
?>
