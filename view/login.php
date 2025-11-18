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
$poruka = "";
if (isset($_GET['registracija']) && $_GET['registracija'] == 1) {
    $poruka = "Vaš nalog je registrovan, čeka se potvrda administratora.";
}
$greska = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 1:
            $greska = "Morate se prijaviti da biste pristupili.";
            break;
        case 2:
            $greska = "Vaš nalog još nije aktiviran. Sačekajte potvrdu administratora.";
            break;
        case 3:
            $greska = "Morate biti ulogovani da biste kupili kartu.";
            break;
        case 4:
            $greska="Korisnik ne postoji.";
            break;
    }
}
?>

<div class="reg">
    <h1>Prijava</h1>

    <?php if ($greska){ ?>
        <div class="error-message"><?php echo $greska ?></div>
    <?php }; ?>

    <?php if ($poruka){?>
        <div class="info-message"><?php echo $poruka ?></div>
    <?php }; ?>

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
