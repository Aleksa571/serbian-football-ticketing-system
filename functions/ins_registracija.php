<?php
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $id_uloga = 2;
    $active = 0; 

    // Provera da li korisnicko ime vec postoji
    $stmt = $pdo->prepare("SELECT * FROM korisnik WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->rowCount() > 0) {
        header("Location: ../view/registracija.php?error=4");
        exit();
    }

    // Ubacivanje novog korisnika
    $stmt = $pdo->prepare("INSERT INTO korisnik (username, password, id_uloga, active) 
                           VALUES (:username, :password, :id_uloga, :active)");
    $stmt->execute([
        ':username' => $username,
        ':password' => $password,
        ':id_uloga' => $id_uloga,
        ':active' => $active
    ]);

    header("Location: ../view/login.php?registracija=1");
    exit();
}
?>
