<?php
// Template layout pour le module directeur
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des paiements - Directeur</title>
    <?php $robots = 'noindex, nofollow'; $include_google_verification = false; require_once __DIR__ . '/../../../seo.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg,#e3f0ff 0%,#f7f7f7 100%); min-height: 100vh; }
        .topbar {
            background: linear-gradient(90deg,#0d6efd 0%,#67b6fd 100%);
            color: #fff;
            padding: 1.2rem 0 1rem 0;
            box-shadow: 0 2px 8px rgba(13,110,253,0.05);
        }
        .topbar .fa {
            margin-right: .7rem;
        }
        .main-content {
            background: #fff;
            border-radius: 1.2rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 2.5rem 2rem 2rem 2rem;
            margin-top: -2.5rem;
        }
        h2, .h4, h4 { color: #0d6efd; font-weight: 700; }
        .card { border-radius: 1.1rem; }
        .btn-primary, .btn-success { border-radius: .7rem; font-weight: 500; }
        .table thead { background: #e3f0ff; }
        .badge.bg-primary { background: #0d6efd!important; }
        .badge.bg-success { background: #198754!important; }
        .badge.bg-secondary { background: #adb5bd!important; }
    </style>
    
    <!-- OneSignal SDK -->
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(async function(OneSignal) {
        await OneSignal.init({
          appId: "b8c9e82f-be11-439a-a5fc-fd1b39558736",
          safari_web_id: "web.onesignal.auto.55479a10-4eda-4299-901a-290da3fd1836",
          notifyButton: {
            enable: true,
          },
        });
      });
    </script>
</head>
<body>
    <div class="topbar mb-4">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="fs-4 fw-bold"><i class="fas fa-user-tie"></i> Espace Directeur – Gestion des Paiements</div>
            <div class="d-none d-md-inline-block small">Système de Gestion Scolaire</div>
        </div>
    </div>
    <?php include_once(__DIR__ . '/../menu.php'); ?>
    <main class="container">
        <div class="main-content">
            <?= $content ?>
        </div>
    </main>
</body>
</html>
