<?php include './functions/session_checker.php'; ?>
<?php
include './functions/conn.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$f_tribina = isset($_GET['tribina_id']) ? trim($_GET['tribina_id']) : '';
$f_kategorija = isset($_GET['kategorija_id']) ? trim($_GET['kategorija_id']) : '';
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

$tribineList = $pdo->query("SELECT MIN(tribina_id) AS tribina_id, naziv_tribine FROM tribine GROUP BY naziv_tribine ORDER BY naziv_tribine")->fetchAll(PDO::FETCH_ASSOC);
$kategorijeList = $pdo->query("SELECT MIN(kategorija_id) AS kategorija_id, naziv_kategorije FROM kategorije_karata GROUP BY naziv_kategorije ORDER BY naziv_kategorije")->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];
if ($q !== '') { 
    $q_trimmed = trim($q);
    // Razdvoji reči i ignoriši kratke reči (vs, i, na, itd.)
    $words = preg_split('/\s+/', $q_trimmed);
    $searchConditions = [];
    $wordIndex = 0;
    $ignoreWords = ['vs', 'i', 'na', 'od', 'do', 'sa', 'za', 'u', 'o', 'a', 'an', 'the'];
    foreach ($words as $word) {
        $word = trim($word);
        // Ignoriši kratke reči (manje od 2 karaktera) i reči iz liste
        if (strlen($word) >= 2 && !in_array(strtolower($word), $ignoreWords)) {
            $paramName = ':q' . $wordIndex;
            $searchConditions[] = "(u.domaci_tim LIKE " . $paramName . " OR u.gostujuci_tim LIKE " . $paramName . " OR t.naziv_tribine LIKE " . $paramName . " OR kk.naziv_kategorije LIKE " . $paramName . ")";
            $params[$paramName] = '%' . $word . '%';
            $wordIndex++;
        }
    }
    if (count($searchConditions) > 0) {
        // Koristi OR logiku - bilo koja reč može biti pronađena
        $where[] = "(" . implode(" OR ", $searchConditions) . ")";
    }
}
if ($f_tribina !== '') { 
    // Pronađi naziv tribine na osnovu ID-a
    $stmtTribina = $pdo->prepare("SELECT naziv_tribine FROM tribine WHERE tribina_id = :trib_id LIMIT 1");
    $stmtTribina->execute([':trib_id' => (int)$f_tribina]);
    $tribinaNaziv = $stmtTribina->fetchColumn();
    if ($tribinaNaziv) {
        $where[] = "t.naziv_tribine = :trib"; 
        $params[':trib'] = $tribinaNaziv; 
    }
}
if ($f_kategorija !== '') { 
    // Pronađi naziv kategorije na osnovu ID-a
    $stmtKategorija = $pdo->prepare("SELECT naziv_kategorije FROM kategorije_karata WHERE kategorija_id = :kat_id LIMIT 1");
    $stmtKategorija->execute([':kat_id' => (int)$f_kategorija]);
    $kategorijaNaziv = $stmtKategorija->fetchColumn();
    if ($kategorijaNaziv) {
        $where[] = "kk.naziv_kategorije = :kat"; 
        $params[':kat'] = $kategorijaNaziv; 
    }
}
$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

$fromJoins = " FROM karte k
JOIN utakmice u ON k.utakmica_id = u.utakmica_id
JOIN tribine t ON k.tribina_id = t.tribina_id
JOIN kategorije_karata kk ON k.kategorija_id = kk.kategorija_id ";

// Dodajte filter za buduće utakmice (samo za prikaz - karte za analizu će i dalje biti u bazi)
$dateFilter = " AND u.datum_utakmice >= CURDATE()";

// Count total groups for pagination (samo grupe gde ima bar jedna slobodna karta I datum utakmice je u budućnosti)
$sqlCount = "SELECT COUNT(*) AS cnt FROM (
  SELECT 1 " . $fromJoins . " " . $whereSql . $dateFilter . "
  GROUP BY u.utakmica_id, t.tribina_id, kk.kategorija_id
  HAVING SUM(CASE WHEN k.status = 'slobodna' THEN 1 ELSE 0 END) > 0
) AS grouped";
$stmtCount = $pdo->prepare($sqlCount);
foreach ($params as $k=>$v){ 
    $stmtCount->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); 
}
$stmtCount->execute();
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { 
    $page = max(1, $totalPages); 
    $offset = ($page - 1) * $perPage; 
}

// Data query with LIMIT/OFFSET (samo grupe gde ima bar jedna slobodna karta I datum utakmice je u budućnosti)
$sqlData = "SELECT 
    MIN(k.karta_id) AS karta_id,
    u.domaci_tim,
    u.gostujuci_tim,
    u.datum_utakmice,
    t.naziv_tribine,
    kk.naziv_kategorije,
    MIN(k.cena) AS cena,
    SUM(CASE WHEN k.status = 'slobodna' THEN 1 ELSE 0 END) AS dostupno,
    MIN(k.datum_prodaje) AS datum_prodaje
  " . $fromJoins . " " . $whereSql . $dateFilter . "
  GROUP BY u.utakmica_id, t.tribina_id, kk.kategorija_id
  HAVING SUM(CASE WHEN k.status = 'slobodna' THEN 1 ELSE 0 END) > 0
  ORDER BY u.datum_utakmice DESC, MIN(k.datum_prodaje) ASC, MIN(k.karta_id) ASC
  LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sqlData);
foreach ($params as $k=>$v){ 
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); 
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$idKorisnik = $_SESSION['id_korisnika'] ?? null;
$brojStavki = 0;

if ($idKorisnik) {
    $stmtKorpa = $pdo->prepare("SELECT SUM(kolicina) FROM korpa WHERE idKorisnik = :idKorisnik");
    $stmtKorpa->execute([':idKorisnik' => $idKorisnik]);
    $brojStavki = $stmtKorpa->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./slike/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="style/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&display=swap" rel="stylesheet">
    <title></title>
</head>
<body>
    <header>
        <nav>
            <ul class="a">
                <li><a href="index.php">Početna</a></li>
                <li><a href="view/about.html">O nama</a></li>
                <li><a href="view/contact.php">Kontakt</a></li>
            </ul>
            <div class="nav-logo">
                <a href="index.php">
                    <img src="./slike/logo.png" alt="Online Ticket Logo" class="header-logo">
                </a>
            </div>
            <ul class="b">
                <?php if ($idKorisnik): ?>
                    <li><a href="functions/logOut.php">LOG OUT</a></li>
                    <li>
                        <a href="view/korpa.php" class="korpa-link">
                            🛒
                            <span class="korpa-badge">
                                <?= $brojStavki ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <form id="isprazniForm" method="post" action="functions/isprazni.php" class="inline-form">
                            <a href="#" class="isprazni" onclick="document.getElementById('isprazniForm').submit(); return false;">
                                Isprazni korpu
                            </a>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="view/login.php">LOGIN</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <div class="hero-section">
        <div class="hero-content">
            <div class="div2">
                <a href="#karte" class="buy-button">🎟️ Pogledaj karte! 🎟️</a>
            </div>
        </div>
    </div>
    
    <!--
    <div class="div0">
      <div class="promo-box">
        <h3>🎁 Osvoji popust</h3>
        <p>Unesi broj od 1 do 10 i osvoji popust na sledeću kartu!<br><small>Samo jedan pokušaj dozvoljen</small></p>
        <input type="number" id="unos" min="1" max="10" placeholder="Unesi broj">
        <input type="button" id="dugme" onclick="a()" value="Pokušaj">
        <p id="rezultat"></p>
        <p id="rezultat1"></p>
      </div>

      <div class="promo-box">
        <h3>💱 Kalkulator cene</h3>
        <p>Izračunaj cenu u dinarima (EUR ➜ RSD)</p>
        <input type="number" id="price" name="price" placeholder="Cena u evrima">
        <input type="button" onclick="calculate()" value="Izračunaj">
        <p id="kalk"></p>
      </div>
    </div>
    -->
    <hr>
    
    <div class="div0">
        <div class="salah">
          <div class="fudbaleri" onclick="openPopup('salah')">
            <img src="slike/salah.jpg" class="card-img-top">
            <img src="slike/kruna.png" class="plus plus-icon">
          </div>
        </div>

        <div class="steven">
          <div class="fudbaleri" onclick="openPopup('steven')">
            <img src="slike/steven.jpg" class="card-img-top">
            <img src="slike/scope.png" class="plus plus-icon">
          </div>
        </div>

        <div class="virgil">
            <div class="fudbaleri" onclick="openPopup('virgil')">
              <img src="slike/virgil.jpg" class="card-img-top">
              <img src="slike/wall.png" class="plus plus-icon">
            </div>
          </div>
    </div>  
    <hr>
    <div id="karte"></div>
    <div class="div0 karte-section">
      <form class="filters" method="get" action="#karte">
        <div>
          <label for="q">Pretraga</label><br>
          <input type="text" name="q" id="q" value="<?php echo $q ?? ''; ?>">
        </div>
        <div>
          <label for="tribina_id">Tribina</label><br>
          <select name="tribina_id" id="tribina_id">
            <option value="">Sve</option>
            <?php foreach ($tribineList as $tr): ?>
              <option value="<?php echo $tr['tribina_id']; ?>" <?php echo ($f_tribina!=='' && $f_tribina==$tr['tribina_id'])?'selected':''; ?>><?php echo $tr['naziv_tribine']; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="kategorija_id">Kategorija</label><br>
          <select name="kategorija_id" id="kategorija_id">
            <option value="">Sve</option>
            <?php foreach ($kategorijeList as $kat): ?>
              <option value="<?php echo $kat['kategorija_id']; ?>" <?php echo ($f_kategorija!=='' && $f_kategorija==$kat['kategorija_id'])?'selected':''; ?>><?php echo $kat['naziv_kategorije']; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit">Pretraži</button>
          <a href="index.php#karte" class="reset-link">Reset</a>
        </div>
      </form>
      <div class="matches-wrapper">
      <?php
      // Prikaži poruku o grešci ako postoji
      if (isset($_GET['error'])) {
          $errorMessages = [
              'past_match' => 'Ne možete kupiti kartu za utakmicu koja je već prošla.',
              'invalid_ticket' => 'Karta nije validna.',
              'not_enough_tickets' => 'Nema dovoljno dostupnih karata.'
          ];
          if (isset($errorMessages[$_GET['error']])) {
              echo '<div class="error-message" style="background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px; text-align: center;">';
              echo htmlspecialchars($errorMessages[$_GET['error']]);
              echo '</div>';
          }
      }
      
      foreach ($stmt as $karta) { 
          $maxQty = (int)min(4, max(0, $karta['dostupno'])); 
          if($maxQty===0) continue; 
          
          // Dodatna provera na frontendu (opciono, ali dobro za UX)
          $datumUtakmice = new DateTime($karta['datum_utakmice']);
          $danas = new DateTime();
          $isPastMatch = $datumUtakmice < $danas;
      ?>
          <div class="match-card" <?php if ($isPastMatch) echo 'style="opacity: 0.6;"'; ?>>
              <div class="match-info">
                  <div class="teams"><?php echo $karta['domaci_tim'] . ' vs ' . $karta['gostujuci_tim']; ?></div>
                  <div class="date"><?php echo date("d M Y H:i", strtotime($karta['datum_utakmice'])); ?></div>
                  <div class="price"><?php echo number_format($karta['cena'], 0, ',', '.'); ?> RSD</div>
                  <div class="meta"><?php echo $karta['naziv_tribine'] . ' • ' . $karta['naziv_kategorije'] . ' • dostupno: ' . $karta['dostupno']; ?></div>
              </div>
              <?php if ($idKorisnik): ?>
                  <?php if ($isPastMatch): ?>
                      <div class="login-prompt" style="background: #fff3cd; border-color: #ffc107;">
                          <p style="color: #856404;">Ova utakmica je već prošla. Karte nisu dostupne za kupovinu.</p>
                      </div>
                  <?php else: ?>
                      <form method="post" action="functions/ins.php">
                          <input type="hidden" name="idKarte" value="<?php echo $karta['karta_id']; ?>">
                          <select name="kolicina">
                              <?php for ($i = 1; $i <= $maxQty; $i++) { ?>
                                  <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                              <?php } ?>
                          </select>
                          <button type="submit" name="dodaj_u_korpu">Dodaj u korpu</button>
                      </form>
                  <?php endif; ?>
              <?php else: ?>
                  <div class="login-prompt">
                      <p>Morate biti ulogovani da biste dodali kartu u korpu.</p>
                      <a href="view/login.php" class="login-link">Ulogujte se</a>
                  </div>
              <?php endif; ?>
          </div>
      <?php } ?>
      </div>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>#karte">« Prethodna</a>
        <?php endif; ?>
        <?php for ($p=1; $p <= $totalPages; $p++): ?>
          <?php if ($p == $page): ?>
            <span class="active"><?php echo $p; ?></span>
          <?php else: ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$p])); ?>#karte"><?php echo $p; ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>#karte">Sledeća »</a>
        <?php endif; ?>
      </div>
    </div>
      <div id="popup_salah" class="popup" onclick="closePopup('popup_salah')">
        <div class="popup-content" onclick="event.stopPropagation()">
          <span class="close" onclick="closePopup('popup_salah')">&times;</span>
          <img src="Slike/salah.jpg" alt="Mohamed Salah" class="profile-img">
        <div class="tekst-sadrzaj">
          <h1>Salah</h1>
          <p>Mohamed Salah je egipatski fudbaler koji igra kao krilni napadač za Liverpool FC.</p>
        </div>
        <a href="https://www.instagram.com/mosalah/" target="_blank" class="instagram-desno">
          <img src="slike/instagram.png" alt="Instagram" class="instagram-icon">
        </a>
      </div>
    </div>      
    <div id="popup_steven" class="popup" onclick="closePopup('popup_steven')">
      <div class="popup-content" onclick="event.stopPropagation()">
        <span class="close" onclick="closePopup('popup_steven')">&times;</span>
        <img src="Slike/steven.jpg" alt="Steven Gerrard" class="profile-img">
      <div class="tekst-sadrzaj">
        <h1>Steven Gerrard</h1>
        <p>Legendarni vezista Liverpoola poznat po svojoj lojalnosti i liderstvu.</p>
      </div>
      <a href="https://www.instagram.com/stevengerrard/" target="_blank" class="instagram-desno">
        <img src="slike/instagram.png" alt="Instagram" class="instagram-icon">
      </a>
    </div>
</div>
    <div id="popup_virgil" class="popup" onclick="closePopup('popup_virgil')">
  <div class="popup-content" onclick="event.stopPropagation()">
    <span class="close" onclick="closePopup('popup_virgil')">&times;</span>
    <img src="slike/virgil.jpg" alt="Virgil van Dijk" class="profile-img">
    <div class="tekst-sadrzaj">
      <h1>Virgil van Dijk</h1>
      <p>Jedan od najboljih defanzivaca na svetu, ključni igrač Liverpoolove odbrane.</p>
    </div>
    <a href="https://www.instagram.com/virgilvandijk/" target="_blank" class="instagram-desno">
      <img src="slike/instagram.png" alt="Instagram" class="instagram-icon">
    </a>
  </div>
</div>
    <footer>
    <div class="footer-content">
        <div class="footer-section">
            <img src="./slike/logo.png" alt="Online Ticket Logo" class="footer-logo">
        </div>
        <div class="footer-section">
            <h3>Brzi linkovi</h3>
            <ul>
                <li><a href="index.php">Početna</a></li>
                <li><a href="view/about.html">O nama</a></li>
                <li><a href="view/contact.php">Kontakt</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Politika</h3>
            <ul>
                <li><a href="#">Privatnost</a></li>
                <li><a href="#">Uslovi korišćenja</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Kontaktiraj nas</h3>
            <ul>
                <li><a href="mailto:info@onlineticket.rs">info@onlineticket.rs</a></li>
                <li>+381 11 123 4567</li>
                <li>Beograd, Srbija</li>
            </ul>
        </div>
    </div>

    <div class="footer-divider"></div>

    <div class="footer-partners">
        <h3>Naši partneri</h3>
        <div class="partners-grid">
            <img src="./slike/novipazar.png" alt="Novi Pazar">
            <img src="./slike/napredak.png" alt="Napredak">
            <img src="./slike/cukaricki.png" alt="Cukaricki">
            <img src="./slike/tsc.png" alt="TSC">
            <img src="./slike/czv.svg" alt="Crvena Zvezda">
            <img src="//upload.wikimedia.org/wikipedia/sr/thumb/2/24/FSS_logo.svg/500px-FSS_logo.svg.png?20250828220326" alt="FSS">
            <img src="./slike/partizan.png" alt="Partizan">
            <img src="./slike/vojvodina.png" alt="Vojvodina">
            <img src="./slike/radnicki.png" alt="Radnicki">
            <img src="./slike/ofkbg.png" alt="OFK Beograd">
            <img src="./slike/zeleznicar.png" alt="Zeleznicar">
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; 2025 Online Ticket. Sva prava zadržana.</p>
        <div class="footer-socials">
            <a href="https://instagram.com" target="_blank" title="Instagram">
              <img src="slike/instagram.png" alt="Instagram" class="instagram-icon"></a>
            <a href="https://facebook.com" target="_blank" title="Facebook">
              <img src="slike/facebook.png" alt="Facebook" class="instagram-icon"></a>
            <a href="https://twitter.com" target="_blank" title="Twitter">𝕏</a>
        </div>
    </div>
</footer>
  
    <!--
    <script type="text/javascript">
        function calculate(){
        let textboxVrednost = document.getElementById('price').value;
        let parsed = parseFloat(textboxVrednost) ;
        let rezultat = parsed*117.21;
        document.getElementById('kalk').textContent = rezultat+" dinara.";}
    </script>
    <script type="text/javascript">
        let unos = document.getElementById('unos');
        let dugme = document.getElementById('dugme');
        let rezultat = document.getElementById('rezultat');
        let rezultat1 = document.getElementById('rezultat1');

        let randomBroj = Math.floor(Math.random() * 10) + 1;

        function a() {
            let pokusaj = parseInt(unos.value);

            if (pokusaj === randomBroj) {
                rezultat.textContent = "Tacno! Pogodio si broj " + randomBroj + ".";
                rezultat1.textContent="Za popust screenshotuj i posalji nam preko Kontakt stranice!";
            } else {
                rezultat.textContent = "Netacno! Random broj je bio " +randomBroj+".";
            }

            dugme.disabled = true;
            dugme.style.display='none';
        }
    </script>
    -->
    <script>
        function openPopup(ime) {
          document.getElementById('popup_' + ime).style.display = 'flex';
        }
      
        function closePopup(id) {
          document.getElementById(id).style.display = 'none';
        }
      
        window.onclick = function(event) {
          if (event.target.className === 'popup') {
            closePopup(event.target.id);
          }
        };
      </script>
      
</body>
</html>