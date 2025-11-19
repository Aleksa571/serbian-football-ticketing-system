<?php
session_start();
include '../functions/conn.php';

if (!isset($_SESSION['id_korisnika'])) {
    header("Location: login.php?error=3"); // Error 3: Must be logged in to purchase
    exit();
}

$username = $_SESSION['username'];
$id_korisnika = $_SESSION['id_korisnika'];

$stmt = $pdo->prepare("SELECT 
                          k.id AS korpa_id,
                          u.domaci_tim,
                          u.gostujuci_tim,
                          u.datum_utakmice,
                          t.naziv_tribine,
                          kk.naziv_kategorije,
                          a.cena,
                          k.kolicina
                       FROM korpa k
                       JOIN karte a ON k.idKarte = a.karta_id
                       JOIN utakmice u ON a.utakmica_id = u.utakmica_id
                       JOIN tribine t ON a.tribina_id = t.tribina_id
                       JOIN kategorije_karata kk ON a.kategorija_id = kk.kategorija_id
                       WHERE k.idKorisnik = ?");
$stmt->execute([$id_korisnika]);
$stavke = $stmt->fetchAll();

$porudzbinaTekst = "";
$ukupno = 0;

foreach ($stavke as $stavka) { 
    $domaci = $stavka['domaci_tim'];
    $gost = $stavka['gostujuci_tim'];
    $datum = $stavka['datum_utakmice'];
    $tribina = $stavka['naziv_tribine'];
    $kategorija = $stavka['naziv_kategorije'];
    $cena = $stavka['cena'];
    $kolicina = $stavka['kolicina'];

    $linija = $domaci . " vs " . $gost . " | " . $datum . " | " . $tribina . " | " . $kategorija . " | " . number_format($cena, 0, ',', '.') . " RSD x " . $kolicina . "\n";
    
    $porudzbinaTekst .= $linija;

    $ukupno = $ukupno + ($cena * $kolicina);
}

$porudzbinaTekst .= "\nUkupno: " . number_format($ukupno, 0, ',', '.') . " RSD";
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Potvrdi kupovinu</title>
    <link rel="stylesheet" href="../style/kupi.css">  
</head>
<body>

    <div class="form-container">
    <h2>Potvrda kupovine</h2>

    <?php if (count($stavke) === 0): ?>
        <p>Korpa je prazna. <a href="../index.php#karte">Nazad na izbora karata</a></p>
    <?php else: ?>
        <table class="pregled">
            <tr>
                <th>Utakmica</th>
                <th>Datum</th>
                <th>Tribina</th>
                <th>Kategorija</th>
                <th>Cena (RSD)</th>
                <th>Količina</th>
            </tr>
            <?php foreach ($stavke as $stavka): ?>
            <tr>
                <td><?php echo $stavka['domaci_tim'] . ' vs ' . $stavka['gostujuci_tim']; ?></td>
                <td><?php echo $stavka['datum_utakmice']; ?></td>
                <td><?php echo $stavka['naziv_tribine']; ?></td>
                <td><?php echo $stavka['naziv_kategorije']; ?></td>
                <td><?php echo number_format($stavka['cena'], 0, ',', '.'); ?></td>
                <td>
                    <form action="../functions/izmeni.php" method="post" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="idKorpa" value="<?php echo $stavka['korpa_id']; ?>">
                        <input type="number" name="nova_kolicina" value="<?php echo $stavka['kolicina']; ?>" min="1" max="4">
                        <input type="submit" value="Izmeni">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p style="font-weight:bold; text-align:right;">Ukupno: <?php echo number_format($ukupno, 0, ',', '.'); ?> RSD</p>
    <?php endif; ?>

    <form action="../functions/zavrsi_kupovinu.php" method="POST">
        <label for="email">Vaš email:</label>
        <input type="email" id="email" name="email" required>

        <label for="adresa">Adresa za isporuku:</label>
        <textarea id="adresa" name="adresa" rows="4" required></textarea>

        <textarea name="narudzbina" style="display:none;"><?php echo $porudzbinaTekst; ?></textarea>

        <input type="hidden" name="_subject" value="Nova porudžbina preko sajta">

        <button type="submit">Pošalji potvrdu</button>
        <a href="korpa.php">Vrati se na korpu</a>
    </form>

    </div>

</body>
</html>
