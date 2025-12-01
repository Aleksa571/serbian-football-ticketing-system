<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registracija</title>
    <link rel="stylesheet" href="../style/registracija.css" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
</head>
<body>
<?php
include '../functions/conn.php';
include '../functions/session_checker.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['username'])) {
        $username = $_POST['username'];
    } else {
        $username = '';
    }

    if (isset($_POST['password'])) {
        $password = $_POST['password'];
    } else {
        $password = '';
    }

    if (empty($username) || empty($password)) {
        header("Location: registracija.php?error=1");
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM korisnik WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        header("Location: registracija.php?error=4");
        exit();
    }

    $uloga = 2; // Automatically set to 2 (regular user)
    $stmt = $pdo->prepare("INSERT INTO korisnik (username, password, id_uloga, active) VALUES (:username, :password, :uloga, 0)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);  
    $stmt->bindParam(':uloga', $uloga);
    $stmt->execute();

    header("Location: login.php?success=1");
    exit();
}
?>
<div class="reg">
    <h1>Forma za registraciju</h1>
    <form action="registracija.php" method="POST">
        <label for="username">Korisničko ime:</label>
        <input type="text" name="username" id="username" placeholder="Unesite korisničko ime" required />

        <label for="password">Lozinka:</label>
        <input type="password" name="password" id="password" placeholder="Unesite lozinku" required />

        <div class="button-row">
            <input type="submit" class="button" value="Registruj se" />
        </div>
    </form>
</div>
</body>
</html>
