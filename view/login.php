<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Prijava</title>
    <link rel="stylesheet" href="../style/registracija.css" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
</head>
<body>

<?php
?>

<div class="reg">
    <h1>Prijava</h1>

    <form action="../functions/proveriUser.php" method="post">
        <label for="username">Korisničko ime:</label>
        <input type="text" id="username" name="username" placeholder="Unesite korisničko ime" required />

        <label for="password">Lozinka:</label>
        <input type="password" id="password" name="password" placeholder="Unesite lozinku" required />

        <div class="button-row">
            <input type="submit" class="button" value="Prijavi se">
            <a href="registracija.php" class="button">Registruj se</a>
        </div>
    </form>
</div>
