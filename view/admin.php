<?php
session_start();
if (!isset($_SESSION['id_korisnika']) || $_SESSION['id_uloga'] != 1) {
    header("Location: ../index.php?error=4");
    exit();
}

include '../functions/conn.php';

// Aktivacija/deaktivacija korisnika
if (isset($_GET['aktiviraj_korisnika'])) {
    $id = intval($_GET['aktiviraj_korisnika']);
    
    $stmt = $pdo->prepare("SELECT active FROM korisnik WHERE id_korisnika = ?");
    $stmt->execute([$id]);
    $korisnik = $stmt->fetch();

    if ($korisnik) {
        $novi_status = $korisnik['active'] ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE korisnik SET active = ? WHERE id_korisnika = ?");
        $stmt->execute([$novi_status, $id]);
    }

    header("Location: admin.php");
    exit();
}

$greska = "";
$uspeh = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ticket'])) {
    $domaci_tim = isset($_POST['domaci_tim']) ? trim($_POST['domaci_tim']) : '';
    $gostujuci_tim = isset($_POST['gostujuci_tim']) ? trim($_POST['gostujuci_tim']) : '';
    $stadion_id = isset($_POST['stadion_id']) && $_POST['stadion_id'] !== '' ? $_POST['stadion_id'] : null;
    $datum_utakmice = isset($_POST['datum_utakmice']) ? $_POST['datum_utakmice'] : '';

    $tribina_id = $_POST['tribina_id'];
    $kategorija_id = $_POST['kategorija_id'];
    $cena = floatval($_POST['cena']);
    $datum_prodaje = $_POST['datum_prodaje'];
    $broj_karata = isset($_POST['broj_karata']) ? max(1, min(50, (int)$_POST['broj_karata'])) : 1;

    if ($datum_utakmice === '') {
        $greska = "Unesite datum utakmice.";
    } else {
        $dt = date_create($datum_utakmice);
        $datumUtakmiceSql = $dt ? date_format($dt, 'Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("INSERT INTO utakmice (stadion_id, domaci_tim, gostujuci_tim, datum_utakmice) VALUES (:stadion_id, :domaci_tim, :gostujuci_tim, :datum_utakmice)");
        $stmt->execute([
            ':stadion_id' => $stadion_id,
            ':domaci_tim' => $domaci_tim !== '' ? $domaci_tim : null,
            ':gostujuci_tim' => $gostujuci_tim !== '' ? $gostujuci_tim : null,
            ':datum_utakmice' => $datumUtakmiceSql
        ]);
        $utakmica_id = $pdo->lastInsertId();

        $stmtKarta = $pdo->prepare("INSERT INTO karte (utakmica_id, tribina_id, kategorija_id, cena, status, datum_prodaje)
                                VALUES (:utakmica_id, :tribina_id, :kategorija_id, :cena, 'slobodna', :datum_prodaje)");
        for ($i = 0; $i < $broj_karata; $i++) {
            $stmtKarta->execute([
                ':utakmica_id' => $utakmica_id,
                ':tribina_id' => $tribina_id,
                ':kategorija_id' => $kategorija_id,
                ':cena' => $cena,
                ':datum_prodaje' => $datum_prodaje
            ]);
        }
        $uspeh = "Uspešno dodato " . $broj_karata . " karata.";
    }
}


// Brisanje karata (briše sve karte iz grupe - iste utakmice, tribine, kategorije i cene)
if (isset($_GET['delete_ticket'])) {
    $id = intval($_GET['delete_ticket']);
    
    // Uzmi informacije o karti da bih znao grupu
    $stmtKarta = $pdo->prepare("SELECT utakmica_id, tribina_id, kategorija_id, cena FROM karte WHERE karta_id = ?");
    $stmtKarta->execute([$id]);
    $kartaInfo = $stmtKarta->fetch(PDO::FETCH_ASSOC);
    
    if ($kartaInfo) {
        // Obriši sve karte iz grupe
        $stmt = $pdo->prepare("DELETE FROM karte 
                               WHERE utakmica_id = ? 
                               AND tribina_id = ? 
                               AND kategorija_id = ? 
                               AND cena = ?");
        $stmt->execute([
            $kartaInfo['utakmica_id'],
            $kartaInfo['tribina_id'],
            $kartaInfo['kategorija_id'],
            $kartaInfo['cena']
        ]);
    }
    
    header("Location: admin.php");
    exit();
}

// Ucitavanje svih karata grupisanih po utakmici, tribini, kategoriji i ceni
$karte = $pdo->query("SELECT
    MIN(pkk.karta_id) AS karta_id,
    pku.datum_utakmice,
    pku.domaci_tim,
    pku.gostujuci_tim,
    pkt.naziv_tribine,
    pkkk.naziv_kategorije,
    pkk.cena,
    MIN(pkk.datum_prodaje) AS datum_prodaje,
    COUNT(*) AS ukupno_karata,
    SUM(CASE WHEN pkk.status = 'slobodna' THEN 1 ELSE 0 END) AS slobodne,
    SUM(CASE WHEN pkk.status = 'rezervisana' THEN 1 ELSE 0 END) AS rezervisane,
    SUM(CASE WHEN pkk.status = 'prodata' THEN 1 ELSE 0 END) AS prodate
FROM
    karte pkk
JOIN
    utakmice pku ON pkk.utakmica_id = pku.utakmica_id
JOIN
    tribine pkt ON pkk.tribina_id = pkt.tribina_id
JOIN
    kategorije_karata pkkk ON pkk.kategorija_id = pkkk.kategorija_id
GROUP BY
    pkk.utakmica_id, pkk.tribina_id, pkk.kategorija_id, pkk.cena
ORDER BY pku.datum_utakmice DESC, pkk.cena DESC")->fetchAll(PDO::FETCH_ASSOC);

$utakmice = $pdo->query("SELECT utakmica_id, domaci_tim, gostujuci_tim, datum_utakmice FROM utakmice ORDER BY datum_utakmice DESC")->fetchAll(PDO::FETCH_ASSOC);
$tribine = $pdo->query("SELECT MIN(tribina_id) AS tribina_id, naziv_tribine FROM tribine GROUP BY naziv_tribine ORDER BY naziv_tribine ASC")->fetchAll(PDO::FETCH_ASSOC);
$kategorije_karata = $pdo->query("SELECT MIN(kategorija_id) AS kategorija_id, naziv_kategorije FROM kategorije_karata GROUP BY naziv_kategorije ORDER BY naziv_kategorije ASC")->fetchAll(PDO::FETCH_ASSOC);
$stadioni = $pdo->query("SELECT MIN(stadion_id) AS stadion_id, naziv_stadiona FROM stadioni GROUP BY naziv_stadiona ORDER BY naziv_stadiona ASC")->fetchAll(PDO::FETCH_ASSOC);

$korisnici = $pdo->query("SELECT * FROM korisnik ORDER BY id_korisnika ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Admin kontrolna tabla</title>
    <link rel="stylesheet" href="../style/admin.css">
    <style>
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
    <h1>You'll never walk alone: <?php echo $_SESSION['username']; ?></h1>
    <div class="logout-link">
        <a href="izvestaji.php">📊 Izveštaji</a> | 
        <a href="../functions/logOut.php">Odjavi se</a>
    </div>

    <h2>Objavi novu kartu</h2>
    <form method="POST" class="admin-form">
        <div class="form-row">
            <div class="form-group">
                <label for="domaci_tim">Domaci tim:</label>
                <input type="text" name="domaci_tim" id="domaci_tim" required>
            </div>
            
            <div class="form-group">
                <label for="gostujuci_tim">Gostujuci tim:</label>
                <input type="text" name="gostujuci_tim" id="gostujuci_tim" required>
            </div>
            
            <div class="form-group">
                <label for="stadion_id">Stadion:</label>
                <select name="stadion_id" id="stadion_id">
                    <option value="">— izaberi stadion —</option>
                    <?php foreach ($stadioni as $stadion): ?>
                        <option value="<?php echo $stadion['stadion_id']; ?>">
                            <?php echo $stadion['naziv_stadiona']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="datum_utakmice">Datum utakmice:</label>
                <input type="datetime-local" name="datum_utakmice" id="datum_utakmice" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="tribina_id">Tribina:</label>
                <select name="tribina_id" id="tribina_id" required>
                    <?php foreach ($tribine as $tribina): ?>
                        <option value="<?php echo $tribina['tribina_id']; ?>">
                            <?php echo $tribina['naziv_tribine']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="kategorija_id">Kategorija:</label>
                <select name="kategorija_id" id="kategorija_id" required>
                    <?php foreach ($kategorije_karata as $kategorija): ?>
                        <option value="<?php echo $kategorija['kategorija_id']; ?>">
                            <?php echo $kategorija['naziv_kategorije']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="cena">Cena (RSD):</label>
                <input type="number" step="1" min="0" name="cena" id="cena" required>
            </div>
            
            <div class="form-group">
                <label for="datum_prodaje">Datum prodaje:</label>
                <input type="datetime-local" name="datum_prodaje" id="datum_prodaje" required>
            </div>
            
            <div class="form-group">
                <label for="broj_karata">Broj karata:</label>
                <input type="number" name="broj_karata" id="broj_karata" min="1" max="50" value="1" required>
            </div>
        </div>

        <div class="form-row form-row-button">
            <button type="submit" name="add_ticket">Dodaj kartu</button>
        </div>
    </form>

    <h2>Lista objavljenih karata</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Utakmica</th>
            <th>Tribina</th>
            <th class="nowrap">Kategorija</th>
            <th>Cena (RSD)</th>
            <th>Broj karata</th>
            <th>Status</th>
            <th>Datum prodaje</th>
            <th>Akcija</th>
        </tr>
        <?php foreach ($karte as $karta){ ?>
        <tr>
            <td><?php echo $karta['karta_id']; ?></td>
            <td><?php echo $karta['domaci_tim'] . ' vs ' . $karta['gostujuci_tim'] . ' (' . date("d.m.Y H:i", strtotime($karta['datum_utakmice'])) . ')'; ?></td>
            <td><?php echo $karta['naziv_tribine']; ?></td>
            <td class="nowrap"><?php echo $karta['naziv_kategorije']; ?></td>
            <td><?php echo number_format($karta['cena'], 0, ',', '.'); ?></td>
            <td>
                <strong>Ukupno: <?php echo $karta['ukupno_karata']; ?></strong><br>
                <span style="color: green;">Slobodne: <?php echo $karta['slobodne']; ?></span><br>
                <span style="color: orange;">Rezervisane: <?php echo $karta['rezervisane']; ?></span><br>
                <span style="color: red;">Prodate: <?php echo $karta['prodate']; ?></span>
            </td>
            <td>
                <?php 
                if ($karta['slobodne'] > 0) echo '<span style="color: green;">Slobodne</span>';
                if ($karta['rezervisane'] > 0) echo ($karta['slobodne'] > 0 ? ', ' : '') . '<span style="color: orange;">Rezervisane</span>';
                if ($karta['prodate'] > 0) echo (($karta['slobodne'] > 0 || $karta['rezervisane'] > 0) ? ', ' : '') . '<span style="color: red;">Prodate</span>';
                ?>
            </td>
            <td><?php echo date("d.m.Y H:i", strtotime($karta['datum_prodaje'])); ?></td>
            <td>
                <a href="admin.php?delete_ticket=<?php echo $karta['karta_id']; ?>" onclick="return confirm('Obrisati sve karte iz ove grupe (<?php echo $karta['ukupno_karata']; ?> karata)?')">Obriši sve</a>
            </td>
        </tr>
        <?php }; ?>
   </table>

    <h2>Korisnici</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Korisničko ime</th>
            <th>Uloga</th>
            <th>Aktivan</th>
            <th>Akcija</th>
        </tr>
        <?php foreach ($korisnici as $korisnik){ ?>
        <tr>
            <td><?php echo $korisnik['id_korisnika']; ?></td>
            <td><?php echo $korisnik['username']; ?></td>
            <td>
                <?php echo ($korisnik['id_uloga'] == 1) ? 'Admin' : 'Korisnik'; ?>
            </td>
            <td>
                <?php echo $korisnik['active'] ? 'Da' : 'Ne'; ?>
            </td>
            <td>
                <a href="admin.php?aktiviraj_korisnika=<?php echo $korisnik['id_korisnika']; ?>">
                    <?php echo $korisnik['active'] ? 'Deaktiviraj' : 'Aktiviraj'; ?>
                </a>
            </td>
        </tr>
        <?php }; ?>
   </table>

<script>
(function() {
    let timeoutId;
    const autocompleteCache = {};
    
    function createAutocompleteList(input, suggestions) {
        // Ukloni postojeću listu ako postoji
        const existingList = input.parentElement.querySelector('.autocomplete-list');
        if (existingList) {
            existingList.remove();
        }
        
        if (suggestions.length === 0) {
            return;
        }
        
        // Kreiraj listu predloga
        const list = document.createElement('ul');
        list.className = 'autocomplete-list';
        
        suggestions.forEach(suggestion => {
            const item = document.createElement('li');
            item.textContent = suggestion;
            item.addEventListener('click', function() {
                input.value = suggestion;
                list.remove();
            });
            list.appendChild(item);
        });
        
        input.parentElement.appendChild(list);
    }
    
    function getSuggestions(query, callback) {
        // Proveri cache
        if (autocompleteCache[query]) {
            callback(autocompleteCache[query]);
            return;
        }
        
        // AJAX poziv
        const xhr = new XMLHttpRequest();
        xhr.open('GET', '../functions/get_timovi.php?q=' + encodeURIComponent(query), true); //q=korisnicki parametar, a encode pravi url da bude ctljiv, zbog razmaka itd  
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    const suggestions = JSON.parse(xhr.responseText);
                    autocompleteCache[query] = suggestions;
                    callback(suggestions);
                } catch (e) {
                    console.error('Error parsing suggestions:', e);
                    callback([]);
                }
            }
        };
        xhr.send();
    }
    
    function setupAutocomplete(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        input.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Ukloni postojeću listu
            const existingList = this.parentElement.querySelector('.autocomplete-list');
            if (existingList) {
                existingList.remove();
            }
            
            // Ako je query prekratak, ne prikazuj ništa
            if (query.length < 2) {
                return;
            }
            
            // Debounce - čekaj 300ms pre nego što pozoveš API
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function() {
                getSuggestions(query, function(suggestions) {
                    createAutocompleteList(input, suggestions);
                });
            }, 300);
        });
        
        // Sakrij listu kada input izgubi fokus
        input.addEventListener('blur', function() {
            setTimeout(function() {
                const list = input.parentElement.querySelector('.autocomplete-list');
                if (list) {
                    list.remove();
                }
            }, 200);
        });
        
        // Ukloni listu kada se klikne negde drugde
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !input.parentElement.querySelector('.autocomplete-list')?.contains(e.target)) {
                const list = input.parentElement.querySelector('.autocomplete-list');
                if (list) {
                    list.remove();
                }
            }
        });
    }
    
    // Postavi autocomplete za oba polja
    setupAutocomplete('domaci_tim');
    setupAutocomplete('gostujuci_tim');
})();
</script>


</body>
</html>
