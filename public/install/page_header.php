<!doctype html>
<html dir="ltr" lang="it">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=0.8, minimum-scale=0.8, maximum-scale=0.8, viewport-fit=cover">
    <meta name="keywords" content="scuola,registro,elettronico,giua@school,installazione">
    <meta name="description" content="Installazione giua@school: il registro elettronico open source">
    <meta name="author" content="Antonello Dessì">
    <title>Installazione giua@school</title>
    <link href="/vendor/bootstrap-italia/css/bootstrap-italia.min.css" rel="stylesheet">
    <link href="/css/main.css" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="57x57" href="/apple-icon-57x57.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="manifest" href="/manifest.json">
    <meta name="msapplication-TileColor" content="#0066CC">
    <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
    <meta name="theme-color" content="#0066CC">
    <meta name="msapplication-config" content="/browserconfig.xml">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#0066CC">
  </head>
  <body>

    <!-- intestazione pagina -->
    <header id="gs-header" class="it-header-wrapper it-header-sticky">
      <div class="it-header-slim-wrapper p-0">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12 pr-0">
              <div class="it-header-slim-wrapper-content pr-0">
                <span class="d-none d-md-block navbar-brand">Registro Elettronico <strong>giua@school</strong></span>
                <div class="header-slim-right-zone">
                  <div class="text-white pr-4"><strong>
                    <?php echo ($this->mode == 'Create' ? 'NUOVA INSTALLAZIONE' : 'AGGIORNAMENTO DI VERSIONE') ?>
                  </strong></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="it-nav-wrapper">
        <nav class="breadcrumb-container" aria-label="breadcrumb">
          <ol class="breadcrumb dark pl-4">
            <li class="breadcrumb-item d-none d-md-block"><span class="d-none d-lg-inline">Passo: </span></li>
            <li class="breadcrumb-item d-none d-md-block"><span class="ml-3"></span></li>
            <li class="breadcrumb-item active"><span class="d-inline d-md-none mr-4"></span>
              <span aria-current="page"><?php echo $page['step']; ?></span>
            </li>
          </ol>
        </nav>
      </div>
    </header>
    <!-- FINE intestazione pagina -->

    <!-- contenuto pagina -->
    <main id="gs-main" class="clearfix pb-5">
      <div class="container">
