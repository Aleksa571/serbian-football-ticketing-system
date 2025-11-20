<?php
session_start();
if (!isset($_SESSION['id_korisnika']) || $_SESSION['id_uloga'] != 1) {
    header("Location: ../index.php?error=4");
    exit();
}

include '../functions/conn.php';

// Filtri za izveštaje
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
// Ako su uneti custom datumi, automatski postavi period na 'custom'
if (!empty($date_from) && !empty($date_to)) {
    $period = 'custom';
} else {
    $period = isset($_GET['period']) ? $_GET['period'] : '6months';
}

// Određivanje datuma na osnovu perioda ili custom date range
$dateCondition = "";

// Ako je period 'custom' i uneti su datumi, koristi ih
if ($period == 'custom' && !empty($date_from) && !empty($date_to)) {
    $date_from_formatted = date('Y-m-d 00:00:00', strtotime($date_from));
    $date_to_formatted = date('Y-m-d 23:59:59', strtotime($date_to));
    // Bezbedno formatiranje za SQL (validacija datuma)
    if ($date_from_formatted && $date_to_formatted) {
        $date_from_safe = $pdo->quote($date_from_formatted);
        $date_to_safe = $pdo->quote($date_to_formatted);
        $dateCondition = "AND k.datum_prodaje >= $date_from_safe AND k.datum_prodaje <= $date_to_safe";
    }
} else {
    // Inače koristi period
    switch ($period) {
        case '1month':
            $dateCondition = "AND k.datum_prodaje >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case '3months':
            $dateCondition = "AND k.datum_prodaje >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case '6months':
            $dateCondition = "AND k.datum_prodaje >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
        case '1year':
            $dateCondition = "AND k.datum_prodaje >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'all':
        default:
            $dateCondition = "";
            break;
    }
}

// ============================================
// OSNOVNE STATISTIKE
// ============================================
$statusData = [
    'slobodna' => $pdo->query("SELECT COUNT(*) FROM karte WHERE status = 'slobodna'")->fetchColumn(),
    'rezervisana' => $pdo->query("SELECT COUNT(*) FROM karte WHERE status = 'rezervisana'")->fetchColumn(),
    'prodata' => $pdo->query("SELECT COUNT(*) FROM karte WHERE status = 'prodata'")->fetchColumn()
];

// Dodajte alias 'k' u upite
$ukupanPrihod = $pdo->query("SELECT COALESCE(SUM(k.cena), 0) FROM karte k WHERE k.status = 'prodata' $dateCondition")->fetchColumn();
$ukupanBrojProdatih = $pdo->query("SELECT COUNT(*) FROM karte k WHERE k.status = 'prodata' $dateCondition")->fetchColumn();
$prosecnaCena = $ukupanBrojProdatih > 0 ? ($ukupanPrihod / $ukupanBrojProdatih) : 0;

// ============================================
// IZVEŠTAJ 1: PRODAJA PO MESECU
// ============================================
$prodajaPoMesecu = $pdo->query("
    SELECT 
        DATE_FORMAT(k.datum_prodaje, '%Y-%m') AS mesec,
        COUNT(*) AS broj_karata,
        SUM(k.cena) AS prihod,
        AVG(k.cena) AS prosecna_cena
    FROM karte k
    WHERE k.status = 'prodata' 
    $dateCondition
    GROUP BY DATE_FORMAT(k.datum_prodaje, '%Y-%m')
    ORDER BY mesec ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 2: PRODAJA PO DANIMA U NEDELJI
// ============================================
$prodajaPoDanima = $pdo->query("
    SELECT 
        DAYOFWEEK(k.datum_prodaje) AS dan_broj,
        COUNT(*) AS broj_karata,
        SUM(k.cena) AS prihod
    FROM karte k
    WHERE k.status = 'prodata'
    $dateCondition
    GROUP BY DAYOFWEEK(k.datum_prodaje)
    ORDER BY dan_broj ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 3: PRODAJA PO STADIONIMA (optimizovano - manje JOIN-ova)
// ============================================
// Prvo uzmi stadion_id iz utakmica za prodate karte, zatim join samo jednom
$prodajaPoStadionima = $pdo->query("
    SELECT 
        COALESCE(s.naziv_stadiona, 'Nepoznat stadion') AS naziv_stadiona,
        COUNT(*) AS broj_prodatih,
        SUM(k.cena) AS prihod,
        AVG(k.cena) AS prosecna_cena
    FROM karte k
    INNER JOIN utakmice u ON k.utakmica_id = u.utakmica_id
    LEFT JOIN stadioni s ON u.stadion_id = s.stadion_id
    WHERE k.status = 'prodata'
    $dateCondition
    GROUP BY u.stadion_id
    ORDER BY broj_prodatih DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 4: TOP NAJPRODAVANIJE UTAKMICE (optimizovano)
// ============================================
// Grupiši prvo po utakmica_id, zatim join samo za stadion
$topUtakmice = $pdo->query("
    SELECT 
        u.domaci_tim,
        u.gostujuci_tim,
        u.datum_utakmice,
        COALESCE(s.naziv_stadiona, 'Nepoznat') AS naziv_stadiona,
        COUNT(*) AS broj_prodatih,
        SUM(k.cena) AS prihod
    FROM karte k
    INNER JOIN utakmice u ON k.utakmica_id = u.utakmica_id
    LEFT JOIN stadioni s ON u.stadion_id = s.stadion_id
    WHERE k.status = 'prodata'
    $dateCondition
    GROUP BY u.utakmica_id
    ORDER BY broj_prodatih DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 5: PRODAJA PO TIMOVIMA (optimizovano - jedan upit umesto UNION)
// ============================================
$prodajaPoTimovima = $pdo->query("
    SELECT 
        tim,
        COUNT(*) AS broj_prodatih,
        SUM(cena) AS prihod
    FROM (
        SELECT u.domaci_tim AS tim, k.cena
        FROM karte k
        INNER JOIN utakmice u ON k.utakmica_id = u.utakmica_id
        WHERE k.status = 'prodata' AND u.domaci_tim IS NOT NULL AND u.domaci_tim != ''
        $dateCondition
        UNION ALL
        SELECT u.gostujuci_tim AS tim, k.cena
        FROM karte k
        INNER JOIN utakmice u ON k.utakmica_id = u.utakmica_id
        WHERE k.status = 'prodata' AND u.gostujuci_tim IS NOT NULL AND u.gostujuci_tim != ''
        $dateCondition
    ) AS sve_utakmice
    GROUP BY tim
    ORDER BY broj_prodatih DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 6: PRODAJA PO TRIBINAMA (optimizovano - grupiši po ID prvo)
// ============================================
$prodajaPoTribinama = $pdo->query("
    SELECT 
        t.naziv_tribine,
        COUNT(*) AS broj_prodatih,
        SUM(k.cena) AS prihod,
        AVG(k.cena) AS prosecna_cena
    FROM karte k
    INNER JOIN tribine t ON k.tribina_id = t.tribina_id
    WHERE k.status = 'prodata'
    $dateCondition
    GROUP BY k.tribina_id
    ORDER BY broj_prodatih DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 7: PRODAJA PO KATEGORIJAMA (optimizovano - grupiši po ID prvo)
// ============================================
$prodajaPoKategorijama = $pdo->query("
    SELECT 
        kk.naziv_kategorije,
        COUNT(*) AS broj_prodatih,
        SUM(k.cena) AS prihod,
        AVG(k.cena) AS prosecna_cena,
        MIN(k.cena) AS min_cena,
        MAX(k.cena) AS max_cena
    FROM karte k
    INNER JOIN kategorije_karata kk ON k.kategorija_id = kk.kategorija_id
    WHERE k.status = 'prodata'
    $dateCondition
    GROUP BY k.kategorija_id
    ORDER BY broj_prodatih DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 8: ANALIZA CENA (raspon, prosek)
// ============================================
$analizaCena = $pdo->query("
    SELECT 
        MIN(k.cena) AS min_cena,
        MAX(k.cena) AS max_cena,
        AVG(k.cena) AS prosecna_cena,
        STDDEV(k.cena) AS standardna_devijacija,
        COUNT(*) AS ukupno
    FROM karte k
    WHERE k.status = 'prodata'
    $dateCondition
")->fetch(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 9: PRODAJA PO SATIMA (ako ima podatke)
// ============================================
$prodajaPoSatima = $pdo->query("
    SELECT 
        HOUR(k.datum_prodaje) AS sat,
        COUNT(*) AS broj_karata,
        SUM(k.cena) AS prihod
    FROM karte k
    WHERE k.status = 'prodata'
    $dateCondition
    GROUP BY HOUR(k.datum_prodaje)
    ORDER BY sat ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// IZVEŠTAJ 10: DETALJAN TABELARNI PRIKAZ (optimizovano - samo neophodni JOIN-ovi)
// ============================================
$detaljnaProdaja = $pdo->query("
    SELECT 
        u.domaci_tim,
        u.gostujuci_tim,
        u.datum_utakmice,
        COALESCE(s.naziv_stadiona, 'Nepoznat') AS naziv_stadiona,
        t.naziv_tribine,
        kk.naziv_kategorije,
        k.cena,
        k.datum_prodaje
    FROM karte k
    INNER JOIN utakmice u ON k.utakmica_id = u.utakmica_id
    LEFT JOIN stadioni s ON u.stadion_id = s.stadion_id
    INNER JOIN tribine t ON k.tribina_id = t.tribina_id
    INNER JOIN kategorije_karata kk ON k.kategorija_id = kk.kategorija_id
    WHERE k.status = 'prodata'
    $dateCondition
    ORDER BY k.datum_prodaje DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Napredni Izveštaji</title>
    <link rel="stylesheet" href="../style/admin.css">
    <link rel="stylesheet" href="../style/izvestaji.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="header-section">
        <h1>📊 Napredni Izveštaji</h1>
        <div class="header-actions">
            <a href="admin.php" class="btn-back">← Nazad na Admin</a>
            <a href="../functions/logOut.php" class="btn-logout">Odjavi se</a>
        </div>
    </div>

    <!-- Filteri -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="period">Period:</label>
                <select name="period" id="period" onchange="toggleCustomDates()">
                    <option value="1month" <?php echo $period == '1month' ? 'selected' : ''; ?>>Poslednji mesec</option>
                    <option value="3months" <?php echo $period == '3months' ? 'selected' : ''; ?>>Poslednja 3 meseca</option>
                    <option value="6months" <?php echo $period == '6months' ? 'selected' : ''; ?>>Poslednjih 6 meseci</option>
                    <option value="1year" <?php echo $period == '1year' ? 'selected' : ''; ?>>Poslednja godina</option>
                    <option value="custom" <?php echo (!empty($date_from) && !empty($date_to)) ? 'selected' : ''; ?>>Određeni period</option>
                    <option value="all" <?php echo $period == 'all' && empty($date_from) && empty($date_to) ? 'selected' : ''; ?>>Sve</option>
                </select>
            </div>
            <div class="filter-group" id="custom-dates-group" style="<?php echo (!empty($date_from) && !empty($date_to)) ? '' : 'display: none;'; ?>">
                <label for="date_from">Od datuma:</label>
                <input type="date" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group" id="custom-dates-group-to" style="<?php echo (!empty($date_from) && !empty($date_to)) ? '' : 'display: none;'; ?>">
                <label for="date_to">Do datuma:</label>
                <input type="date" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group filter-group-buttons">
                <button type="submit" class="btn-filter">Primeni filtere</button>
                <?php if (!empty($date_from) || !empty($date_to) || $period != '6months'): ?>
                    <a href="izvestaji.php" class="btn-reset">Resetuj</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <!-- Statistike -->
    <div class="stats-summary">
        <div class="stat-box">
            <h3>Ukupan prihod</h3>
            <p class="stat-value"><?php echo number_format($ukupanPrihod, 0, ',', '.'); ?> RSD</p>
        </div>
        <div class="stat-box">
            <h3>Prodate karte</h3>
            <p class="stat-value"><?php echo number_format($ukupanBrojProdatih); ?></p>
        </div>
        <div class="stat-box">
            <h3>Prosečna cena</h3>
            <p class="stat-value"><?php echo number_format($prosecnaCena, 0, ',', '.'); ?> RSD</p>
        </div>
        <div class="stat-box">
            <h3>Ukupno karata</h3>
            <p class="stat-value"><?php echo number_format($statusData['slobodna'] + $statusData['rezervisana'] + $statusData['prodata']); ?></p>
        </div>
    </div>

    <!-- Grafikoni - Prvi red -->
    <div class="charts-section">
        <!-- Status karata -->
        <div class="chart-container">
            <h2>Status karata</h2>
            <canvas id="statusChart"></canvas>
        </div>

        <!-- Prodaja po mesecu -->
        <div class="chart-container">
            <h2>Prodaja po mesecu</h2>
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Grafikoni - Drugi red -->
    <div class="charts-section">
        <!-- Prodaja po danima u nedelji -->
        <div class="chart-container">
            <h2>Prodaja po danima u nedelji</h2>
            <canvas id="daysChart"></canvas>
        </div>

        <!-- Prodaja po satima -->
        <div class="chart-container">
            <h2>Prodaja po satima u danu</h2>
            <canvas id="hoursChart"></canvas>
        </div>
    </div>

    <!-- Grafikoni - Treći red -->
    <div class="charts-section">
        <!-- Prodaja po stadionima -->
        <div class="chart-container">
            <h2>Top 15 stadiona po prodaji</h2>
            <canvas id="stadioniChart"></canvas>
        </div>

        <!-- Prodaja po timovima -->
        <div class="chart-container">
            <h2>Top 15 timova po prodaji</h2>
            <canvas id="timoviChart"></canvas>
        </div>
    </div>

    <!-- Grafikoni - Četvrti red -->
    <div class="charts-section">
        <!-- Prodaja po tribinama -->
        <div class="chart-container">
            <h2>Prodaja po tribinama</h2>
            <canvas id="tribineChart"></canvas>
        </div>

        <!-- Prodaja po kategorijama -->
        <div class="chart-container">
            <h2>Prodaja po kategorijama</h2>
            <canvas id="kategorijeChart"></canvas>
        </div>
    </div>

    <!-- Tabele -->
    <div class="tables-section">
        <!-- Top utakmice -->
        <div class="table-section">
            <h2>🏆 Top 10 najprodavanijih utakmica</h2>
            <div class="table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Utakmica</th>
                            <th>Stadion</th>
                            <th>Datum</th>
                            <th>Prodate karte</th>
                            <th>Prihod</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topUtakmice as $index => $utakmica): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo $utakmica['domaci_tim'] . ' vs ' . $utakmica['gostujuci_tim']; ?></strong></td>
                                <td><?php echo $utakmica['naziv_stadiona'] ?? 'N/A'; ?></td>
                                <td><?php echo date("d.m.Y H:i", strtotime($utakmica['datum_utakmice'])); ?></td>
                                <td><?php echo number_format($utakmica['broj_prodatih']); ?></td>
                                <td><?php echo number_format($utakmica['prihod'], 0, ',', '.'); ?> RSD</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analiza cena -->
        <div class="table-section">
            <h2>💰 Analiza cena</h2>
            <div class="table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Metrika</th>
                            <th>Vrednost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Najniža cena</td>
                            <td><?php echo number_format($analizaCena['min_cena'], 0, ',', '.'); ?> RSD</td>
                        </tr>
                        <tr>
                            <td>Najviša cena</td>
                            <td><?php echo number_format($analizaCena['max_cena'], 0, ',', '.'); ?> RSD</td>
                        </tr>
                        <tr>
                            <td>Prosečna cena</td>
                            <td><?php echo number_format($analizaCena['prosecna_cena'], 0, ',', '.'); ?> RSD</td>
                        </tr>
                        <tr>
                            <td>Standardna devijacija</td>
                            <td><?php echo number_format($analizaCena['standardna_devijacija'] ?? 0, 0, ',', '.'); ?> RSD</td>
                        </tr>
                        <tr>
                            <td>Raspon cena</td>
                            <td><?php echo number_format($analizaCena['max_cena'] - $analizaCena['min_cena'], 0, ',', '.'); ?> RSD</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detaljna tabela -->
    <div class="table-section">
        <h2>📋 Detaljna prodaja (poslednjih 200 transakcija)</h2>
        <div class="table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Utakmica</th>
                        <th>Stadion</th>
                        <th>Datum utakmice</th>
                        <th>Tribina</th>
                        <th>Kategorija</th>
                        <th>Cena</th>
                        <th>Datum prodaje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($detaljnaProdaja) > 0): ?>
                        <?php foreach ($detaljnaProdaja as $prodaja): ?>
                            <tr>
                                <td><?php echo $prodaja['domaci_tim'] . ' vs ' . $prodaja['gostujuci_tim']; ?></td>
                                <td><?php echo $prodaja['naziv_stadiona'] ?? 'N/A'; ?></td>
                                <td><?php echo date("d.m.Y H:i", strtotime($prodaja['datum_utakmice'])); ?></td>
                                <td><?php echo $prodaja['naziv_tribine']; ?></td>
                                <td><?php echo $prodaja['naziv_kategorije']; ?></td>
                                <td><?php echo number_format($prodaja['cena'], 0, ',', '.'); ?> RSD</td>
                                <td><?php echo date("d.m.Y H:i", strtotime($prodaja['datum_prodaje'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">Nema podataka za izabrani period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Funkcija za prikaz/sakrivanje custom datuma
        function toggleCustomDates() {
            const periodSelect = document.getElementById('period');
            const customDatesGroup = document.getElementById('custom-dates-group');
            const customDatesGroupTo = document.getElementById('custom-dates-group-to');
            
            if (periodSelect.value === 'custom') {
                customDatesGroup.style.display = '';
                customDatesGroupTo.style.display = '';
            } else {
                customDatesGroup.style.display = 'none';
                customDatesGroupTo.style.display = 'none';
            }
        }
        
        // Inicijalizuj pri učitavanju stranice
        document.addEventListener('DOMContentLoaded', function() {
            toggleCustomDates();
        });

        // Status karata - Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: ['Slobodne', 'Rezervisane', 'Prodate'],
                datasets: [{
                    data: [<?php echo $statusData['slobodna']; ?>, <?php echo $statusData['rezervisana']; ?>, <?php echo $statusData['prodata']; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#c8102e'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Prodaja po mesecu - Line Chart
        const salesData = <?php echo json_encode($prodajaPoMesecu); ?>;
        const mesecNazivi = ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'];
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(item => {
                    const [year, month] = item.mesec.split('-');
                    return mesecNazivi[parseInt(month) - 1] + ' ' + year;
                }),
                datasets: [{
                    label: 'Broj prodatih karata',
                    data: salesData.map(item => item.broj_karata),
                    borderColor: '#c8102e',
                    backgroundColor: 'rgba(200, 16, 46, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                }, {
                    label: 'Prihod (RSD)',
                    data: salesData.map(item => item.prihod),
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { type: 'linear', display: true, position: 'left', beginAtZero: true },
                    y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
                }
            }
        });

        // Prodaja po danima - Bar Chart
        const daysData = <?php echo json_encode($prodajaPoDanima); ?>;
        const daysCtx = document.getElementById('daysChart').getContext('2d');
        const daniNazivi = ['Nedelja', 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota'];
        new Chart(daysCtx, {
            type: 'bar',
            data: {
                labels: daysData.map(item => {
                    const danIndex = item.dan_broj - 1;
                    return daniNazivi[danIndex] || 'Dan ' + item.dan_broj;
                }),
                datasets: [{
                    label: 'Broj prodatih karata',
                    data: daysData.map(item => item.broj_karata),
                    backgroundColor: 'rgba(200, 16, 46, 0.8)',
                    borderColor: '#c8102e',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // Prodaja po satima - Line Chart
        const hoursData = <?php echo json_encode($prodajaPoSatima); ?>;
        const hoursCtx = document.getElementById('hoursChart').getContext('2d');
        new Chart(hoursCtx, {
            type: 'line',
            data: {
                labels: hoursData.map(item => item.sat + ':00'),
                datasets: [{
                    label: 'Broj karata',
                    data: hoursData.map(item => item.broj_karata),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Stadioni - Bar Chart
        const stadioniData = <?php echo json_encode($prodajaPoStadionima); ?>;
        const stadioniCtx = document.getElementById('stadioniChart').getContext('2d');
        new Chart(stadioniCtx, {
            type: 'bar',
            data: {
                labels: stadioniData.map(item => item.naziv_stadiona),
                datasets: [{
                    label: 'Broj prodatih karata',
                    data: stadioniData.map(item => item.broj_prodatih),
                    backgroundColor: 'rgba(200, 16, 46, 0.8)',
                    borderColor: '#c8102e',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // Timovi - Bar Chart
        const timoviData = <?php echo json_encode($prodajaPoTimovima); ?>;
        const timoviCtx = document.getElementById('timoviChart').getContext('2d');
        new Chart(timoviCtx, {
            type: 'bar',
            data: {
                labels: timoviData.map(item => item.tim),
                datasets: [{
                    label: 'Broj prodatih karata',
                    data: timoviData.map(item => item.broj_prodatih),
                    backgroundColor: 'rgba(23, 162, 184, 0.8)',
                    borderColor: '#17a2b8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // Tribine - Bar Chart
        const tribineData = <?php echo json_encode($prodajaPoTribinama); ?>;
        const tribineCtx = document.getElementById('tribineChart').getContext('2d');
        new Chart(tribineCtx, {
            type: 'bar',
            data: {
                labels: tribineData.map(item => item.naziv_tribine),
                datasets: [{
                    label: 'Broj prodatih karata',
                    data: tribineData.map(item => item.broj_prodatih),
                    backgroundColor: 'rgba(108, 117, 125, 0.8)',
                    borderColor: '#6c757d',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // Kategorije - Bar Chart
        const kategorijeData = <?php echo json_encode($prodajaPoKategorijama); ?>;
        const kategorijeCtx = document.getElementById('kategorijeChart').getContext('2d');
        new Chart(kategorijeCtx, {
            type: 'bar',
            data: {
                labels: kategorijeData.map(item => item.naziv_kategorije),
                datasets: [{
                    label: 'Broj prodatih karata',
                    data: kategorijeData.map(item => item.broj_prodatih),
                    backgroundColor: 'rgba(255, 193, 7, 0.8)',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
