<?php
$uiLocale = in_array((string) session('ui_locale'), ['en', 'id'], true) ? (string) session('ui_locale') : 'en';
$uiMessagesFile = APPPATH . 'Language/' . $uiLocale . '/Ui.php';
$uiMessages = is_file($uiMessagesFile) ? require $uiMessagesFile : [];
?>
<!doctype html>
<html lang="<?= esc($uiLocale, 'attr') ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'LENTERRA ERP') ?> | LENTERRA ERP</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="shortcut icon" href="<?= base_url('assets/skote/images/Logo.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/css/icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/libs/select2/css/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/skote/css/app.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/pena/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/pena/dashboard.css') ?>">
    <script>
        window.PenaLocale = <?= json_encode($uiLocale) ?>;
        window.PenaMessages = <?= json_encode([
            'selectSearchPrefix' => $uiMessages['selectSearchPrefix'] ?? 'Select / search ',
            'selectSearchData' => $uiMessages['selectSearchData'] ?? 'Select / search data',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>

<body data-sidebar="dark">