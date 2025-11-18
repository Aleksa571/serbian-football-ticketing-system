<?php

session_start();
include 'conn.php';

if (!isset($_SESSION['id_korisnika'])) {
    header("Location: ../view/login.php?error=3");
    exit();
}

$id_korisnika = $_SESSION['id_korisnika'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Uzmi sve stavke iz korpe
    $stmtKorpa = $pdo->prepare("SELECT kolicina, idKarte FROM korpa WHERE idKorisnik = :idKorisnik");
    $stmtKorpa->execute([':idKorisnik' => $id_korisnika]);
    $stavke = $stmtKorpa->fetchAll(PDO::FETCH_ASSOC);

    // Promeni status svih karata na 'prodata' I POSTAVI datum_prodaje na trenutni datum
    foreach ($stavke as $stavka) {
        $kolicina = (int)$stavka['kolicina'];
        $idKarte = $stavka['idKarte'];

        // Uzmi informacije o karti
        $stmtKarta = $pdo->prepare("SELECT utakmica_id, tribina_id, kategorija_id FROM karte WHERE karta_id = :idKarte");
        $stmtKarta->execute([':idKarte' => $idKarte]);
        $kartaInfo = $stmtKarta->fetch(PDO::FETCH_ASSOC);

        if ($kartaInfo) {
            // Promeni status karata na 'prodata' I POSTAVI datum_prodaje na trenutni datum
            $stmtProdaj = $pdo->prepare("UPDATE karte 
                                        SET status = 'prodata', 
                                            datum_prodaje = NOW()
                                        WHERE utakmica_id = :utakmica_id 
                                        AND tribina_id = :tribina_id 
                                        AND kategorija_id = :kategorija_id 
                                        AND status = 'rezervisana' 
                                        LIMIT :limit");
            $stmtProdaj->bindValue(':utakmica_id', $kartaInfo['utakmica_id'], PDO::PARAM_INT);
            $stmtProdaj->bindValue(':tribina_id', $kartaInfo['tribina_id'], PDO::PARAM_INT);
            $stmtProdaj->bindValue(':kategorija_id', $kartaInfo['kategorija_id'], PDO::PARAM_INT);
            $stmtProdaj->bindValue(':limit', $kolicina, PDO::PARAM_INT);
            $stmtProdaj->execute();
        }
    }

    // Obriši sve stavke iz korpe
    $stmt = $pdo->prepare("DELETE FROM korpa WHERE idKorisnik = :idKorisnik");
    $stmt->execute([':idKorisnik' => $id_korisnika]);

    // Prosleđivanje podataka na formspree
    $email = $_POST['email'] ?? '';
    $adresa = $_POST['adresa'] ?? '';
    $narudzbina = $_POST['narudzbina'] ?? '';

    // Pošalji na formspree (opciono, ako želiš da zadržiš email funkcionalnost)
    if ($email && $adresa) {
        $formspreeData = [
            'email' => $email,
            'adresa' => $adresa,
            'narudzbina' => $narudzbina,
            '_subject' => 'Nova porudžbina preko sajta'
        ];

        // Možeš da koristiš cURL da pošalješ na formspree, ali za sada samo obrišemo iz korpe
        // $ch = curl_init('https://formspree.io/f/xdkgqybj');
        // curl_setopt($ch, CURLOPT_POST, 1);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formspreeData));
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_exec($ch);
        // curl_close($ch);
    }

    header("Location: ../index.php?success=purchase_complete");
    exit();
} else {
    header("Location: ../view/kupi.php");
    exit();
}
?>

