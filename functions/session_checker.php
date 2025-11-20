<?php
// Produžavanje trajanja sesije na 30 dana
// Ovo omogućava korisnicima da ostanu ulogovani i nakon zatvaranja browsera
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 dana u sekundama
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30); // 30 dana u sekundama

session_start();

// Postavi cookie parametre za produženo trajanje
if (isset($_SESSION['id_korisnika'])) {
    // Ažuriraj cookie sa produženim trajanjem
    setcookie(session_name(), session_id(), time() + (60 * 60 * 24 * 30), '/');
}

// No longer force redirect if id_korisnika is not set, allow anonymous access

include 'conn.php';

if (isset($_SESSION['id_korisnika'])) {
    $stmt = $pdo->prepare("SELECT active FROM korisnik WHERE id_korisnika = ?");
    $stmt->execute([$_SESSION['id_korisnika']]);
    $user = $stmt->fetch();

    if (!$user || $user['active'] != 1) {
        session_destroy(); // Uklanjanje sesije ako nije aktivan
        header("Location: ../view/login.php?error=2"); // Niste aktivirani
        exit();
    }
}
?>
