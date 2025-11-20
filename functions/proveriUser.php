<?php
// Produžavanje trajanja sesije na 30 dana
// - session.cookie_lifetime = koliko dugo cookie traje u browseru korisnika
//   (ako korisnik zatvori browser, cookie će i dalje postojati 30 dana)
//
//Ove postavke MORAJU biti PRE session_start()!
// 
// RAZLOG: Kada se pozove session_start(), PHP:
// 1. Proverava da li postoji sesija cookie u browseru
// 2. Kreira ili učitava sesiju sa servera
// 3. Postavlja cookie sa parametrima koji su VEĆ konfigurisani
//
// Ako pozovete ini_set() POSLE session_start(), cookie je već kreiran
// sa podrazumevanim parametrima i neće se promeniti!
//
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); 

session_start();
include 'conn.php';

if (isset($_POST['username'], $_POST['password']) && !empty($_POST['username']) && !empty($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $upit = $pdo->prepare("SELECT * FROM korisnik WHERE username = :username");
    $upit->bindParam(':username', $username);
    $upit->execute();

    $korisnik = $upit->fetch(PDO::FETCH_ASSOC);

    if (!$korisnik) {
        header("Location: ../view/login.php?error=4"); // Korisnik ne postoji
        exit();
    }

    if ($password !== $korisnik['password']) {
        header("Location: ../view/login.php?error=3"); // Pogresna lozinka
        exit();
    }

    if ((int)$korisnik['active'] !== 1) {
        header("Location: ../view/login.php?error=2"); // Nalog nije aktiviran
        exit();
    }

    $_SESSION['id_korisnika'] = $korisnik['id_korisnika'];
    $_SESSION['username'] = $korisnik['username'];
    $_SESSION['id_uloga'] = $korisnik['id_uloga'];

    // Postavi cookie sa produženim trajanjem (30 dana)
    setcookie(session_name(), session_id(), time() + (60 * 60 * 24 * 30), '/');

    switch ((int)$korisnik['id_uloga']) {
        case 1:
            header("Location: ../view/admin.php");
            exit();
        case 2:
            header("Location: ../index.php");
            exit();
        default:
            header("Location: ../view/login.php?error=1"); 
            exit();
    }

} else {
    header("Location: ../view/login.php?error=1"); // Niste uneli korisničko ime i lozinku
    exit();
}
?>