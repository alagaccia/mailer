<?php
session_start();

// Carichiamo l'ambiente per leggere le credenziali dal .env
require_once __DIR__ . '/../bootstrap/app.php';
use App\Core\Database;
use App\Controllers\EmailController;

// 1. GESTIONE LOGIN / LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: /");
    exit;
}

if (isset($_POST['user']) && isset($_POST['pass'])) {
    if ($_POST['user'] === getenv('DASHBOARD_USER') && $_POST['pass'] === getenv('DASHBOARD_PASS')) {
        $_SESSION['authenticated'] = true;
    } else {
        $error = "Credenziali errate!";
    }
}

// 2. GESTIONE RICHIESTE AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($_GET['action'] === 'send' && isset($_GET['id'])) {
        $controller = new EmailController();
        $result = $controller->sendFromQueue((int) $_GET['id']);
        http_response_code($result['status']);
        echo json_encode($result['response']);
        exit;
    }

    // Stato mailer
    if ($_GET['action'] === 'mailer_status') {
        require_once __DIR__ . '/../app/Models/Setting.php';
        $enabled = \App\Models\Setting::get('mailer_enabled', '1');
        echo json_encode(['enabled' => $enabled]);
        exit;
    }

    // Toggle mailer
    if ($_GET['action'] === 'toggle_mailer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../app/Models/Setting.php';
        $enabled = \App\Models\Setting::get('mailer_enabled', '1');
        $new = $enabled === '1' ? '0' : '1';
        \App\Models\Setting::set('mailer_enabled', $new);
        echo json_encode(['enabled' => $new]);
        exit;
    }
}

// 2. FORM DI LOGIN (Visualizzato se non autenticato)
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true): ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login - Mail Bridge</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex items-center justify-center h-screen">
    <div class="w-full max-w-md">
        <form method="POST" class="bg-gray-800 p-8 rounded-xl shadow-2xl border border-gray-700">
            <h2 class="text-2xl font-bold mb-6 text-center text-blue-400">Mail Bridge Access</h2>
            
            <?php if(isset($error)): ?>
                <div class="bg-red-900/50 border border-red-500 text-red-200 p-3 rounded mb-4 text-sm text-center italic">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Username</label>
                <input type="text" name="user" required class="w-full p-3 rounded bg-gray-900 border border-gray-700 focus:border-blue-500 outline-none transition">
            </div>
            <div class="mb-6">
                <label class="block text-gray-400 text-sm mb-2">Password</label>
                <input type="password" name="pass" required class="w-full p-3 rounded bg-gray-900 border border-gray-700 focus:border-blue-500 outline-none transition">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200">
                Accedi al Pannello
            </button>
        </form>
    </div>
</body>
</html>
<?php 
exit; 
endif; 

// 3. LOGICA DASHBOARD (Visualizzata solo se autenticato)
$db = Database::getInstance();

// Recupero parametri filtro
$filterRecipient = trim($_GET['filter_recipient'] ?? '');
$filterSubject = trim($_GET['filter_subject'] ?? '');
$filterDateFrom = trim($_GET['filter_date_from'] ?? '');
$filterDateTo = trim($_GET['filter_date_to'] ?? '');

// Costruisco le condizioni WHERE dinamicamente
$whereConditions = [];
$params = [];

if (!empty($filterRecipient)) {
    $whereConditions[] = "recipient LIKE :recipient";
    $params[':recipient'] = '%' . $filterRecipient . '%';
}

if (!empty($filterSubject)) {
    $whereConditions[] = "subject LIKE :subject";
    $params[':subject'] = '%' . $filterSubject . '%';
}

if (!empty($filterDateFrom)) {
    $whereConditions[] = "created_at >= :dateFrom";
    $params[':dateFrom'] = $filterDateFrom . ' 00:00:00';
}

if (!empty($filterDateTo)) {
    $whereConditions[] = "created_at <= :dateTo";
    $params[':dateTo'] = $filterDateTo . ' 23:59:59';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Recupero statistiche TOTALI (tutte le email)
$tableName = Database::getPrefix() . 'email_queue';
$counts = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM $tableName
")->fetch();

// Recupero il totale delle righe FILTRATE
$stmtCount = $db->prepare("SELECT COUNT(*) as total FROM $tableName $whereClause");
foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value);
}
$stmtCount->execute();
$filteredCount = $stmtCount->fetch();
$totalRows = (int) ($filteredCount['total'] ?? 0);

// Paginazione
$perPage = 15;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? max(1, min((int) $_GET['page'], $totalPages)) : 1;
$offset = ($page - 1) * $perPage;

// Recupero email con paginazione e filtri
$stmtEmail = $db->prepare("SELECT * FROM $tableName $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $stmtEmail->bindValue($key, $value);
}
$stmtEmail->bindValue(':limit', $perPage, \PDO::PARAM_INT);
$stmtEmail->bindValue(':offset', $offset, \PDO::PARAM_INT);
$stmtEmail->execute();
$latest = $stmtEmail->fetchAll();

// Costruisco URL per la paginazione con parametri filtro
$filterQueryString = '';
if (!empty($filterRecipient) || !empty($filterSubject) || !empty($filterDateFrom) || !empty($filterDateTo)) {
    $filterParams = [];
    if (!empty($filterRecipient)) $filterParams[] = 'filter_recipient=' . urlencode($filterRecipient);
    if (!empty($filterSubject)) $filterParams[] = 'filter_subject=' . urlencode($filterSubject);
    if (!empty($filterDateFrom)) $filterParams[] = 'filter_date_from=' . urlencode($filterDateFrom);
    if (!empty($filterDateTo)) $filterParams[] = 'filter_date_to=' . urlencode($filterDateTo);
    $filterQueryString = '&' . implode('&', $filterParams);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mail Bridge API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 antialiased font-sans">

<!-- Dati email per Vue -->
<script>
    window.__EMAILS__ = <?= json_encode(array_map(function($row) {
        return [
            'id'        => $row['id'],
            'recipient' => $row['recipient'],
            'subject'   => $row['subject'],
            'body'      => $row['body'],
            'status'    => $row['status'],
            'attachments' => !empty($row['attachments']) ? json_decode($row['attachments'], true) : [],
            'created_at'=> date('d/m/Y H:i', strtotime($row['created_at'])),
            'last_error'=> $row['last_error'] ?? null,
        ];
    }, $latest), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.__PAGINATION__ = { page: <?= $page ?>, totalPages: <?= $totalPages ?>, totalRows: <?= $totalRows ?>, perPage: <?= $perPage ?>, offset: <?= $offset ?> };
</script>

    <div id="app" class="container mx-auto px-6 py-8">

        <!-- Vue Modal -->
        <teleport to="body">
            <transition name="fade">
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
                    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="closeModal"></div>
                    <div class="relative bg-gray-900 rounded-2xl border border-gray-700 shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col z-10">
                        <!-- Header modale -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800 shrink-0">
                            <div>
                                <h3 class="text-lg font-bold text-gray-100">Anteprima Email <span class="text-gray-500 font-mono text-sm">#{{ current.id }}</span></h3>
                                <p class="text-xs text-gray-500 mt-0.5">{{ current.recipient }} &bull; {{ current.created_at }}</p>
                            </div>
                            <button @click="closeModal" class="text-gray-500 hover:text-white transition text-2xl leading-none cursor-pointer">&times;</button>
                        </div>
                        <!-- Dettagli -->
                        <div class="px-6 py-3 border-b border-gray-800 shrink-0 space-y-2">
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500 w-20 shrink-0">A:</span>
                                <span class="text-gray-200">{{ current.recipient }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500 w-20 shrink-0">Oggetto:</span>
                                <span class="text-gray-200 font-medium">{{ current.subject }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-gray-500 w-20 shrink-0">Stato:</span>
                                <span class="px-2 py-0.5 rounded-full border text-[10px] font-black uppercase" :class="statusBadge">{{ current.status }}</span>
                            </div>
                            <div v-if="current.attachments && current.attachments.length > 0" class="flex items-start text-sm">
                                <span class="text-gray-500 w-20 shrink-0">Allegati:</span>
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="(att, idx) in current.attachments" :key="idx" class="px-3 py-1 rounded bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs font-mono flex items-center gap-2 group hover:bg-blue-500/20 transition cursor-pointer">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1 1 0 11-2 0 1 1 0 012 0zM15 7a1 1 0 11-2 0 1 1 0 012 0zM19.354 15.854a1 1 0 00-1.414-1.414l-3.672 3.672a1 1 0 001.414 1.414l3.672-3.672zM9 10a1 1 0 11-2 0 1 1 0 012 0zM19 4a1 1 0 11-2 0 1 1 0 012 0zM7.464 4.464a1 1 0 001.414-1.414L3.206.206a1 1 0 00-1.414 1.414l3.258 3.258z"></path></svg>
                                        {{ att.filename }}
                                        <button @click="downloadAttachment(att)" class="text-blue-300 hover:text-blue-100 ml-1 opacity-0 group-hover:opacity-100 transition">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="current.last_error" class="flex items-start text-sm">
                                <span class="text-gray-500 w-20 shrink-0">Errore:</span>
                                <span class="text-red-400 text-xs">{{ current.last_error }}</span>
                            </div>
                        </div>
                        <!-- Body email (iframe sandboxed) -->
                        <div class="flex-1 overflow-auto p-6">
                            <div class="bg-gray-100 rounded-xl overflow-hidden">
                                <iframe :srcdoc="current.body" sandbox="allow-same-origin" class="w-full border-0" style="min-height: 350px;" @load="resizeIframe"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
        <!-- Header -->
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">
                    Mail Bridge Controller
                </h1>
                <p class="text-gray-500 text-sm">SMTP Gateway Monitor (v1.0)</p>
            </div>
            <div class="flex items-center space-x-4">
                <span id="mailer-status" class="text-xs font-mono px-3 py-1 rounded border" :class="mailerEnabled ? 'bg-green-900/40 text-green-400 border-green-700' : 'bg-red-900/40 text-red-400 border-red-700'">
                    MAILER: {{ mailerEnabled ? 'ATTIVO' : 'FERMO' }}
                </span>
                <button @click="toggleMailer" :class="mailerEnabled ? 'bg-red-700/30 text-red-300 hover:bg-red-700 hover:text-white border-red-700' : 'bg-green-700/30 text-green-300 hover:bg-green-700 hover:text-white border-green-700'" class="text-xs px-4 py-2 rounded-lg transition border font-bold mr-2">
                    {{ mailerEnabled ? 'Ferma Invio Email' : 'Riattiva Invio Email' }}
                </button>
                <a href="?logout=1" class="text-sm bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white px-4 py-2 rounded-lg transition border border-red-900/50">Logout</a>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800">
                <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">In Coda</p>
                <p class="text-4xl font-black text-yellow-500 mt-2"><?= number_format($counts['pending'] ?? 0) ?></p>
            </div>
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800">
                <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">Inviate con Successo</p>
                <p class="text-4xl font-black text-emerald-500 mt-2"><?= number_format($counts['sent'] ?? 0) ?></p>
            </div>
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800">
                <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">Errori / Fallite</p>
                <p class="text-4xl font-black text-red-500 mt-2"><?= number_format($counts['failed'] ?? 0) ?></p>
            </div>
            <div class="bg-gray-900 p-6 rounded-2xl border border-gray-800">
                <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">Volume Totale</p>
                <p class="text-4xl font-black text-blue-500 mt-2"><?= number_format($counts['total'] ?? 0) ?></p>
            </div>
        </div>

        <!-- Filtri -->
        <div class="bg-gray-900 rounded-2xl border border-gray-800 p-6 mb-8 shadow-xl">
            <h3 class="font-bold text-gray-300 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path d="M3 3a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V17a1 1 0 01-1.447.894l-4-2A1 1 0 016 15.618V11.414L3.293 5.707A1 1 0 013 5V3z"></path></svg>
                Filtri di Ricerca
            </h3>
            
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Destinatario -->
                    <div>
                        <label class="block text-xs text-gray-500 font-bold uppercase mb-2">Destinatario</label>
                        <input type="text" name="filter_recipient" value="<?= htmlspecialchars($filterRecipient) ?>" 
                               placeholder="es: user@domain.com" 
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 placeholder-gray-600 focus:border-blue-500 focus:outline-none transition">
                    </div>
                    
                    <!-- Oggetto -->
                    <div>
                        <label class="block text-xs text-gray-500 font-bold uppercase mb-2">Oggetto</label>
                        <input type="text" name="filter_subject" value="<?= htmlspecialchars($filterSubject) ?>" 
                               placeholder="es: Benvenuto" 
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 placeholder-gray-600 focus:border-blue-500 focus:outline-none transition">
                    </div>
                    
                    <!-- Data Da -->
                    <div>
                        <label class="block text-xs text-gray-500 font-bold uppercase mb-2">Data Da</label>
                        <input type="date" name="filter_date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" 
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:border-blue-500 focus:outline-none transition">
                    </div>
                    
                    <!-- Data A -->
                    <div>
                        <label class="block text-xs text-gray-500 font-bold uppercase mb-2">Data A</label>
                        <input type="date" name="filter_date_to" value="<?= htmlspecialchars($filterDateTo) ?>" 
                               class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:border-blue-500 focus:outline-none transition">
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg transition border border-blue-500">
                        🔍 Cerca
                    </button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-bold rounded-lg transition border border-gray-700">
                        ✕ Ripristina
                    </a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-2xl">
            <div class="px-6 py-4 border-b border-gray-800 bg-gray-900/50">
                <h3 class="font-bold text-gray-300">
                    Log Ultimi Invii
                    <?php if (!empty($filterRecipient) || !empty($filterSubject) || !empty($filterDateFrom) || !empty($filterDateTo)): ?>
                        <span class="text-xs text-blue-400 font-normal">(filtrati)</span>
                    <?php endif; ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">
                    Pagina <?= $page ?> di <?= $totalPages ?> 
                    <?php if ($totalRows !== ($counts['total'] ?? 0)): ?>
                        &bull; <?= number_format($totalRows) ?> email corrispondenti ai filtri
                    <?php else: ?>
                        &bull; <?= number_format($totalRows) ?> email totali
                    <?php endif; ?>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-500 text-[10px] uppercase tracking-widest border-b border-gray-800">
                            <th class="px-6 py-4 font-bold">ID</th>
                            <th class="px-6 py-4 font-bold">Destinatario</th>
                            <th class="px-6 py-4 font-bold">Oggetto</th>
                            <th class="px-6 py-4 font-bold text-center">Stato</th>
                            <th class="px-6 py-4 font-bold text-center">Allegati</th>
                            <th class="px-6 py-4 font-bold">Data/Ora</th>
                            <th class="px-6 py-4 font-bold text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-800/50">
                        <?php foreach($latest as $row): ?>
                        <?php $attachments = !empty($row['attachments']) ? json_decode($row['attachments'], true) : []; ?>
                        <tr class="hover:bg-white/[0.02] transition">
                            <td class="px-6 py-4 text-gray-500 font-mono">#<?= $row['id'] ?></td>
                            <td class="px-6 py-4 font-medium text-gray-200"><?= htmlspecialchars($row['recipient']) ?></td>
                            <td class="px-6 py-4 text-gray-400"><?= htmlspecialchars($row['subject']) ?></td>
                            <td class="px-6 py-4 text-center">
                                <?php 
                                    $badge = match($row['status']) {
                                        'sent' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                                        'pending' => 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
                                        'failed' => 'bg-red-500/10 text-red-500 border-red-500/20',
                                        default => 'bg-gray-800 text-gray-400 border-gray-700'
                                    };
                                ?>
                                <span class="px-3 py-1 rounded-full border text-[10px] font-black uppercase <?= $badge ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if (!empty($attachments)): ?>
                                    <span class="px-2 py-1 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[10px] font-bold">
                                        <?php 
                                            $totalSize = 0;
                                            foreach ($attachments as $att) {
                                                $totalSize += strlen(base64_decode($att['content'] ?? ''));
                                            }
                                            $sizeStr = $totalSize > 1024 * 1024 ? 
                                                number_format($totalSize / (1024 * 1024), 1) . ' MB' : 
                                                number_format($totalSize / 1024, 1) . ' KB';
                                        ?>
                                        <?= count($attachments) ?> file (<?= $sizeStr ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-600 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-xs">
                                <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-center space-x-2">
                                <button @click="sendEmail(<?= $row['id'] ?>)" :disabled="sending.includes(<?= $row['id'] ?>)" class="px-3 py-1 text-xs rounded-lg bg-green-600/20 text-green-400 hover:bg-green-600 hover:text-white transition border border-green-500/30 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span v-if="sending.includes(<?= $row['id'] ?>)">Invio...</span>
                                    <span v-else>Invia</span>
                                </button>
                                <button @click="openModal(<?= $row['id'] ?>)" class="px-3 py-1 text-xs rounded-lg bg-blue-600/20 text-blue-400 hover:bg-blue-600 hover:text-white transition border border-blue-500/30 cursor-pointer">
                                    Visualizza
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($latest)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-600 italic">Nessun dato presente in coda.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginazione Vue -->
            <div v-if="pagination.totalPages > 1" class="px-6 py-4 border-t border-gray-800 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Mostrando {{ pagination.offset + 1 }}–{{ Math.min(pagination.offset + pagination.perPage, pagination.totalRows) }} di {{ pagination.totalRows.toLocaleString() }}
                </div>
                <div class="flex items-center space-x-1">
                    <a v-if="pagination.page > 1" :href="'?page=1<?= $filterQueryString ?>'" class="px-3 py-1.5 text-xs rounded-lg bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white transition border border-gray-700">&laquo;</a>
                    <a v-if="pagination.page > 1" :href="'?page=' + (pagination.page - 1) + '<?= $filterQueryString ?>'" class="px-3 py-1.5 text-xs rounded-lg bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white transition border border-gray-700">&lsaquo;</a>

                    <span v-if="pageStart > 1" class="px-2 py-1.5 text-xs text-gray-600">&hellip;</span>

                    <a v-for="p in pageRange" :key="p" :href="'?page=' + p + '<?= $filterQueryString ?>'"
                       class="px-3 py-1.5 text-xs rounded-lg transition border"
                       :class="p === pagination.page
                           ? 'bg-blue-600 text-white border-blue-500 font-bold'
                           : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white border-gray-700'">
                        {{ p }}
                    </a>

                    <span v-if="pageEnd < pagination.totalPages" class="px-2 py-1.5 text-xs text-gray-600">&hellip;</span>

                    <a v-if="pagination.page < pagination.totalPages" :href="'?page=' + (pagination.page + 1) + '<?= $filterQueryString ?>'" class="px-3 py-1.5 text-xs rounded-lg bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white transition border border-gray-700">&rsaquo;</a>
                    <a v-if="pagination.page < pagination.totalPages" :href="'?page=' + pagination.totalPages + '<?= $filterQueryString ?>'" class="px-3 py-1.5 text-xs rounded-lg bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white transition border border-gray-700">&raquo;</a>
                </div>
            </div>
        </div>

        <footer class="mt-8 text-center">
            <p class="text-gray-700 text-[10px] uppercase tracking-[0.2em]">Bridge Powered by Andrea Lagaccia • PHP 8.x Engine</p>
        </footer>
    </div>

<style>
    .fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
    .fade-enter-from, .fade-leave-to { opacity: 0; }
</style>

<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            showModal: false,
            emails: window.__EMAILS__ || [],
            mailerEnabled: true,
            current: {},
            pagination: window.__PAGINATION__ || { page: 1, totalPages: 1, totalRows: 0, perPage: 15, offset: 0 },
            sending: []
        }
    },
    computed: {
        statusBadge() {
            const map = {
                'sent':    'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
                'pending': 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
                'failed':  'bg-red-500/10 text-red-500 border-red-500/20',
            };
            return map[this.current.status] || 'bg-gray-800 text-gray-400 border-gray-700';
        },
        pageStart() {
            return Math.max(1, this.pagination.page - 3);
        },
        pageEnd() {
            return Math.min(this.pagination.totalPages, this.pagination.page + 3);
        },
        pageRange() {
            const pages = [];
            for (let i = this.pageStart; i <= this.pageEnd; i++) pages.push(i);
            return pages;
        }
    },
    methods: {
        openModal(id) {
            this.current = this.emails.find(e => e.id === id) || {};
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        closeModal() {
            this.showModal = false;
            this.current = {};
            document.body.style.overflow = '';
        },
        resizeIframe(e) {
            const iframe = e.target;
            try {
                iframe.style.height = iframe.contentDocument.body.scrollHeight + 20 + 'px';
            } catch(err) {}
        },
        downloadAttachment(attachment) {
            try {
                // Decodifica il contenuto base64
                const binaryString = atob(attachment.content);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                
                // Crea un blob dal contenuto decodificato
                const blob = new Blob([bytes], { type: attachment.mime || 'application/octet-stream' });
                
                // Crea un URL temporaneo per il blob
                const url = URL.createObjectURL(blob);
                
                // Crea un elemento link e simula il click
                const link = document.createElement('a');
                link.href = url;
                link.download = attachment.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Pulisce l'URL temporaneo
                URL.revokeObjectURL(url);
            } catch (error) {
                alert('Errore nel download: ' + error.message);
            }
        },
        async sendEmail(id) {
            if (this.sending.includes(id)) return;
            
            this.sending.push(id);
            
            try {
                const response = await fetch('?action=send&id=' + id);
                const data = await response.json();
                
                if (response.ok) {
                    // Aggiorna lo stato dell'email nella lista
                    const email = this.emails.find(e => e.id === id);
                    if (email) {
                        email.status = 'sent';
                    }
                    alert('Email inviata con successo!');
                    // Ricarica la pagina per aggiornare le statistiche
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Errore: ' + (data.error || 'Impossibile inviare l\'email'));
                }
            } catch (error) {
                alert('Errore di rete: ' + error.message);
            } finally {
                this.sending = this.sending.filter(x => x !== id);
            }
        },
        async toggleMailer() {
            const res = await fetch('?action=toggle_mailer', { method: 'POST' });
            const data = await res.json();
            this.mailerEnabled = data.enabled === '1';
            alert('Mailer ' + (this.mailerEnabled ? 'attivato' : 'fermato'));
        }
    },
    mounted() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeModal();
        });
        // Recupera stato mailer
        fetch('?action=mailer_status')
            .then(r => r.json())
            .then(d => { this.mailerEnabled = d.enabled === '1'; });
    }
}).mount('#app');
</script>

</body>
</html>