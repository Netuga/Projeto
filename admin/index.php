<?php
require_once __DIR__ . '/includes/bootstrap.php';

$modules = adminModules();
$moduleKey = trim((string)($_GET['module'] ?? 'units'));
if (!isset($modules[$moduleKey])) {
    $moduleKey = 'units';
}
$module = $modules[$moduleKey];
$message = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken(requestCsrfToken())) {
        http_response_code(400);
        exit('Pedido inválido. Atualiza a página e tenta novamente.');
    }

    if (!empty($module['readonly'])) {
        http_response_code(405);
        exit('Este módulo é apenas de consulta.');
    }

    try {
        if (($_POST['admin_action'] ?? '') === 'delete') {
            adminDeleteRecord($conn, $module, (int)($_POST['id'] ?? 0));
            $_SESSION['admin_flash'] = 'Registo removido.';
        } else {
            adminSaveRecord($conn, $module, $_POST);
            $_SESSION['admin_flash'] = 'Registo guardado.';
        }
    } catch (Throwable $error) {
        error_log('Admin save failed (' . $moduleKey . '): ' . $error->getMessage());
        $_SESSION['admin_flash'] = 'Não foi possível guardar. Confirma os dados e tenta novamente.';
    }
    header('Location: ' . app_url('admin/index.php?module=' . rawurlencode($moduleKey)));
    exit;
}

$editing = isset($_GET['edit']) && empty($module['readonly'])
    ? adminFindRecord($conn, $module, (int)$_GET['edit'])
    : null;
$csrf = csrfToken();
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Painel Admin · <?= adminEscape(APP_NAME) ?></title>
  <link rel="stylesheet" href="../assets/css/shared.css?v=sticky-footer-3">
  <link rel="stylesheet" href="assets/css/admin.css?v=2">
</head>
<body class="admin-shell">
  <header class="topnav admin-topnav">
    <a href="../pages/home.html" class="logo">
      <img src="../assets/img/fighters-logo.png" alt="Anime Warriors">
      <span class="logo-text">Anime Warriors</span>
    </a>
    <nav aria-label="Navegação principal">
      <a href="../pages/home.html">Início</a>
      <a href="../pages/units.html">Units</a>
      <a href="../pages/items.html">Items</a>
      <a href="../pages/guides.html">Guias</a>
    </nav>
    <a href="../pages/home.html" class="admin-back">Voltar ao site</a>
  </header>
  <section class="admin-banner">
    <div class="banner-map-bg" aria-hidden="true"></div>
    <div class="banner-map-frame" aria-hidden="true"></div>
    <p class="admin-eyebrow">Painel de administração</p>
    <h1><?= adminEscape($module['label']) ?></h1>
    <p>Gere o conteúdo do site, consulta registos e mantém as secções atualizadas.</p>
    <span class="admin-user">Sessão iniciada como <?= adminEscape($adminUser['username']) ?></span>
  </section>
  <nav class="admin-module-tabs" aria-label="Secções de gestão">
    <?php foreach ($modules as $key => $item): ?>
      <a class="admin-module-tab<?= $key === $moduleKey ? ' active' : '' ?>" href="?module=<?= rawurlencode($key) ?>"<?= $key === $moduleKey ? ' aria-current="page"' : '' ?>>
        <?= adminEscape($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="admin-content">
    <main class="admin-workspace">
      <?php if ($message): ?><div class="admin-flash" role="status"><?= adminEscape($message) ?></div><?php endif; ?>
      <section class="admin-module panel">
        <div class="panel-head">Gestão de conteúdo</div>
        <div class="panel-body">
          <?php require __DIR__ . '/includes/module_view.php'; ?>
        </div>
      </section>
    </main>
    <aside class="admin-preview-sidebar">
      <section class="admin-side-card panel">
        <div class="panel-head"><span class="admin-status-dot"></span> Secção ativa</div>
        <div class="panel-body admin-summary">
          <p class="admin-summary-label">A gerir</p>
          <h2><?= adminEscape($module['label']) ?></h2>
          <p><?= adminEscape($module['description']) ?></p>
          <div class="admin-summary-stat"><span>Campos do formulário</span><strong><?= count($module['fields'] ?? []) ?></strong></div>
          <div class="admin-summary-stat"><span>Colunas da lista</span><strong><?= count($module['columns'] ?? []) ?></strong></div>
          <span class="admin-access-badge"><?= empty($module['readonly']) ? 'Edição ativa' : 'Só consulta' ?></span>
        </div>
      </section>
      <a class="admin-home-link" href="../pages/home.html">← Regressar ao site</a>
    </aside>
  </div>
  <footer class="site-footer admin-footer">
    <div class="footer-bottom"><span>© 2026 Anime Warriors</span><span>Painel administrativo</span></div>
  </footer>
</body>
</html>
