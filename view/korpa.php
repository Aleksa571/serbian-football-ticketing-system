<?php
session_start();
include '../functions/conn.php';

// Provera da li je korisnik ulogovan
if (!isset($_SESSION['id_korisnika'])) {
    header("Location: login.php?error=1");
    exit();
}

// Prikaz svih stavki iz tabele 'korpa' za korisnika
$id_korisnika = $_SESSION['id_korisnika'];
$stmt = $pdo->prepare("SELECT 
                              k.id AS korpa_id, 
                              k.kolicina, 
                              k.created_at, 
                              k.updated_at,
                              u.domaci_tim,
                              u.gostujuci_tim,
                              u.datum_utakmice,
                              t.naziv_tribine,
                              kk.naziv_kategorije,
                              a.cena
                        FROM korpa k
                        JOIN karte a ON k.idKarte = a.karta_id
                        JOIN utakmice u ON a.utakmica_id = u.utakmica_id
                        JOIN tribine t ON a.tribina_id = t.tribina_id
                        JOIN kategorije_karata kk ON a.kategorija_id = kk.kategorija_id
                        WHERE k.idKorisnik = ?");
$stmt->execute([$id_korisnika]);
$stavke = $stmt->fetchAll();
$ukupno = 0;
foreach ($stavke as $stavka) {
    $ukupno += $stavka['cena'] * $stavka['kolicina'];
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Moja korpa</title>
    <link rel="stylesheet" href="../style/korpa.css">
</head>
<body>

<h2>Moja korpa
    <a href="../index.php#karte" class="btn-kupovina">Nastavi sa kupovinom</a>
</h2>

<table>
    <tr>
        <th>Utakmica</th>
        <th>Datum</th>
        <th>Tribina</th>
        <th>Kategorija</th>
        <th>Cena (RSD)</th>
        <th>Količina</th>
        <th>Ukupno (RSD)</th>
        <th>Datum unosa</th>
        <th>Poslednja izmena</th>
    </tr>

    <?php foreach ($stavke as $stavka){ ?>
    <tr>
        <td><?php echo $stavka['domaci_tim'] . ' vs ' . $stavka['gostujuci_tim']; ?></td>
        <td><?php echo $stavka['datum_utakmice']; ?></td>
        <td><?php echo $stavka['naziv_tribine']; ?></td>
        <td><?php echo $stavka['naziv_kategorije']; ?></td>
        <td><?php echo number_format($stavka['cena'], 0, ',', '.'); ?></td>
        <td>
            <form action="../functions/izmeni.php" method="post" class="form-izmena">
                <input type="hidden" name="idKorpa" value="<?php echo $stavka['korpa_id']; ?>">
                <input type="number" name="nova_kolicina" value="<?php echo $stavka['kolicina']; ?>" min="1" max="4">
                <input type="submit" value="Izmeni">
            </form>
        </td>
        <td><?php echo number_format($stavka['cena'] * $stavka['kolicina'], 0, ',', '.'); ?></td>
        <td><?php echo $stavka['created_at']; ?></td>
        <td><?php echo $stavka['updated_at']; ?></td>
        <td>
            <a class="obrisi-btn" href="../functions/obrisi.php?id=<?php echo $stavka['korpa_id']; ?>" onclick="return confirm('Da li ste sigurni da želite da obrišete ovu kartu?');">Obriši</a>
        </td>
    </tr>
    <?php }; ?>
</table>
<?php if (count($stavke) > 0) {?>
    <div class="ukupno-box">
        Ukupna cena: <?php echo number_format($ukupno, 0, ',', '.'); ?> RSD
        <a href="kupi.php">Kupi</a>
    </div>
<?php };?>
</body>
</html>
