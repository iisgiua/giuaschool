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
    <link href="../vendor/bootstrap-italia/css/bootstrap-italia.min.css" rel="stylesheet">
    <link href="../css/main.css" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../apple-icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="../apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="../apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="../apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="114x114" href="../apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="76x76" href="../apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="72x72" href="../apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="60x60" href="../apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="57x57" href="../apple-icon-57x57.png">
    <link rel="icon" type="image/png" sizes="192x192" href="../android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="96x96" href="../favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="manifest" href="../manifest.json">
    <meta name="msapplication-TileColor" content="#0066CC">
    <meta name="msapplication-TileImage" content="../ms-icon-144x144.png">
    <meta name="theme-color" content="#0066CC">
    <meta name="msapplication-config" content="../browserconfig.xml">
    <link rel="mask-icon" href="../safari-pinned-tab.svg" color="#0066CC">
  </head>
  <body>

    <!-- intestazione pagina -->
    <header id="gs-header" class="it-header-wrapper">
      <div class="it-header-slim-wrapper p-0">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12 pr-0">
              <div class="it-header-slim-wrapper-content pr-0">
                <span class="d-none d-md-block navbar-brand">Registro Elettronico <strong>giua@school</strong></span>
                <div class="header-slim-right-zone">
                  <div class="text-white pr-4"><strong><?php echo ($page['version'] == 'INSTALL' ? 'INSTALLAZIONE INIZIALE' : 'AGGIORNAMENTO ALLA VERSIONE '.$page['version']); ?></strong></div>
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
        </nav>
      </ol>
      </div>
    </header>
    <!-- FINE intestazione pagina -->

    <!-- contenuto pagina -->
    <main id="gs-main" class="clearfix pb-5">
      <div class="container">

        <!-- TITLE -->
        <h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>

        <!-- MESSAGE: danger/warning/info/success/text/code -->
        <?php if (!empty($page['danger'])) { ?>
        <div class="alert alert-danger mt-5" role="alert"><strong><?php echo $page['danger']; ?></strong></div>
        <?php } ?>
        <?php if (!empty($page['warning'])) { ?>
        <div class="alert alert-warning mt-5" role="alert"><strong><?php echo $page['warning']; ?></strong></div>
        <?php } ?>
        <?php if (!empty($page['info'])) { ?>
        <div class="alert alert-info mt-5" role="alert"><strong><?php echo $page['info']; ?></strong></div>
        <?php } ?>
        <?php if (!empty($page['success'])) { ?>
        <div class="alert alert-success mt-5" role="alert"><strong><?php echo $page['success']; ?></strong></div>
        <?php } ?>
        <?php if (!empty($page['text'])) { ?>
        <p class="mt-5"><strong><em><?php echo $page['text']; ?></em></strong></p>
        <?php } ?>
        <?php if (!empty($page['code'])) { ?>
        <pre class="border px-2 mx-5 bg-light"><code>
        <?php echo $page['code']; ?>
        </code></pre>
        <?php } ?>

        <!-- TABLE: requirements -->
        <?php if (!empty($page['requirements'])) { ?>
        <table class="table table-bordered table-hover table-striped table-sm">
          <thead class="thead-light">
            <tr>
              <th class="col-2" scope="col">Tipo</th>
              <th class="col-4" scope="col">Descrizione</th>
              <th class="col" scope="col">Valore attuale</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($page['requirements'] as $reqType => $reqData) { ?>
            <?php foreach ($reqData as $req) { ?>
            <tr>
              <td><strong><?php echo $reqType == 'mandatory' ? 'OBBLIGATORIO' : 'OPZIONALE'; ?></strong></td>
              <td><strong><?php echo $req[0]; ?></strong></td>
              <td>
              <?php if ($req[2]) { ?>
                <span class="badge badge-success">
              <?php } elseif ($reqType == 'mandatory') { ?>
                <span class="badge badge-danger">
              <?php } else { ?>
                <span class="badge badge-warning">
              <?php } echo $req[1]; ?>
                </span>
              </td>
            </tr>
            <?php } ?>
            <?php } ?>
          </tbody>
        </table>
        <?php } ?>

        <!-- FORM: database -->
        <?php if (!empty($page['database'])) { ?>
        <div class="card-wrapper card-space">
          <div class="card border rounded card-bg">
            <div class="card-header bg-secondary text-white">
              Inserisci la configurazione per il collegamento al database
            </div>
            <div class="card-body">
              <form name="install" method="post" action="<?php echo $page['postUrl']; ?>">
                <div class="form-row">
                  <div class="form-group col">
                    <label for="install_db_server" class="required active" style="transition: none 0s ease 0s;">Server database</label>
                    <input type="text" id="install_db_server" name="install[db_server]" required="required" class="form-control" value="<?php echo $page['database']['host']; ?>">
                  </div>
                  <div class="form-group col">
                    <label for="install_db_port" class="required active" style="transition: none 0s ease 0s;">Porta database</label>
                    <input type="text" id="install_db_port" name="install[db_port]" required="required" class="form-control" value="<?php echo $page['database']['port']; ?>">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col">
                    <label for="install_db_user" class="required active" style="transition: none 0s ease 0s;">Utente database</label>
                    <input type="text" id="install_db_user" name="install[db_user]" required="required" class="form-control" value="<?php echo $page['database']['user']; ?>">
                  </div>
                  <div class="form-group col">
                    <label for="install_db_password" class="required active" style="transition: none 0s ease 0s;">Password database</label>
                    <input type="text" id="install_db_password" name="install[db_password]" required="required" class="form-control" value="<?php echo $page['database']['pass']; ?>">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group col">
                    <label for="install_db_name" class="required active" style="transition: none 0s ease 0s;">Nome database</label>
                    <input type="text" id="install_db_name" name="install[db_name]" required="required" class="form-control" value="<?php echo substr((string) $page['database']['path'], 1); ?>">
                  </div>
                </div>
                <div class="form-group col text-center">
                  <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Conferma</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php } ?>

        <!-- FORM: admin -->
        <?php if (!empty($page['admin'])) { ?>
        <div class="card-wrapper card-space">
          <div class="card border rounded card-bg">
            <div class="card-header bg-secondary text-white">
              Inserisci le credenziali di accesso per l'utente amministratore.<br>
              La password deve essere lunga almeno 8 caratteri.
            </div>
            <div class="card-body">
              <form name="install" method="post" action="<?php echo $page['postUrl']; ?>">
                <div class="form-row">
                  <div class="form-group col">
                    <label for="install_username" class="required active" style="transition: none 0s ease 0s;">Nome utente</label>
                    <input type="text" id="install_username" name="install[username]" required="required" class="form-control" value="<?php echo $page['admin']; ?>">
                  </div>
                  <div class="form-group col">
                    <label for="install_password" class="required active" style="transition: none 0s ease 0s;">Password</label>
                    <input type="text" id="install_password" name="install[password]" required="required" class="form-control" value="">
                  </div>
                </div>
                <div class="form-group col text-center">
                  <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Conferma</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <?php } ?>

        <!-- NEXT STEP -->
        <?php if (!empty($page['url'])) { ?>
        <div class="text-center mt-5 mb-5">
          <a class="btn btn-primary gs-button" href="<?php echo $page['url']; ?>"><strong>CONTINUA</strong></a>
        </div>
        <?php } ?>
        <?php if (!empty($page['error'])) { ?>
        <div class="text-center mt-5 mb-5">
          <a class="btn btn-danger gs-button" href="<?php echo $page['error']; ?>"><strong>RIPROVA</strong></a>
        </div>
        <?php } ?>

      </div>
    </main>
    <!-- FINE contenuto pagina -->

    <!-- piè di pagina -->
    <footer id="gs-footer" class="it-footer clearfix">
      <div class="it-footer-main">
        <div class="container">
          <section class="py-4">
            <div class="row">
              <div class="col-lg-6 col-md-12">
                <h2>
                  <a class="text-white text-decoration-none" href="https://iisgiua.github.io/giuaschool-docs/" target="_blank" title="Vai alla documentazione del progetto">
                    <em>giua@school</em>
                  </a>
                </h2>
                <h3 class="d-none d-md-block">Il registro elettronico <strong>open source</strong></h3>
              </div>
              <div class="col-lg-6 col-md-12 d-none d-md-block link-list-wrapper">
                <ul class="footer-list link-list">
                  <li>
                    <a class="list-item" href="https://github.com/iisgiua/giuaschool" target="_blank" title="Vai al progetto su GitHub">
                      <svg class="icon icon-sm icon-light mr-2" aria-hidden="true">
                        <use xlink:href="../vendor/fontawesome/sprites/brands.svg#github"></use>
                      </svg><span class="text-white">Codice sorgente su GitHub</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </section>
        </div>
      </div>
    </footer>
    <!-- FINE piè di pagina -->

    <!-- finestra di attesa -->
    <div class="modal fade" tabindex="-1" role="dialog" id="gs-waiting">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Attendere prego...</h5>
          </div>
          <div class="modal-body mx-auto">
            <div class="progress-spinner progress-spinner-active mb-5">
              <span class="sr-only">Caricamento...</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- FINE finestra di attesa -->

    <!-- link TornaSu  -->
    <a class="back-to-top back-to-top-small shadow" href="#" aria-hidden="true" data-attribute="back-to-top" title="Vai a inizio pagina">
      <svg class="icon icon-light" aria-hidden="true">
        <use xlink:href="../vendor/fontawesome/sprites/solid.svg#arrow-up"></use>
      </svg>
    </a>
    <!-- FINE link TornaSu  -->

    <!-- caricamento javascript -->
    <script>window.__PUBLIC_PATH__ = "../vendor/bootstrap-italia/fonts"</script>
    <script src="../vendor/bootstrap-italia/js/bootstrap-italia.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
      $(document).ready(function() {
        $('.gs-button').click(function() {
          $('#gs-waiting').modal('show');
        });
      });
    </script>
    <?php if (!empty($page['javascript'])) { ?>
    <script><?php echo $page['javascript']; ?></script>
    <?php } ?>
    <!-- FINE caricamento javascript -->

  </body>
</html>
