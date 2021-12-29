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
                  <a class="text-white text-decoration-none" href="https://github.com/trinko/giuaschool#giuaschool" target="_blank" title="Vai al progetto su GitHub">
                    <em>giua@school</em>
                  </a>
                </h2>
                <h3 class="d-none d-md-block">Il registro elettronico <strong>open source</strong></h3>
              </div>
              <div class="col-lg-6 col-md-12 d-none d-md-block link-list-wrapper">
                <ul class="footer-list link-list">
                  <li>
                    <a class="list-item" href="https://github.com/trinko/giuaschool#giuaschool" target="_blank" title="Vai al progetto su GitHub">
                      <svg class="icon icon-sm icon-light mr-2" aria-hidden="true">
                        <use xlink:href="/vendor/fontawesome/sprites/brands.svg#github"></use>
                      </svg><span class="text-white">Progetto e codice sorgente</span>
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
        <use xlink:href="/vendor/fontawesome/sprites/solid.svg#arrow-up"></use>
      </svg>
    </a>
    <!-- FINE link TornaSu  -->

    <!-- caricamento javascript -->
    <script>window.__PUBLIC_PATH__ = "/vendor/bootstrap-italia/fonts"</script>
    <script src="/vendor/bootstrap-italia/js/bootstrap-italia.bundle.min.js"></script>
    <script src="/js/main.js"></script>
    <script>
      $(document).ready(function() {
        $('form').submit(function() {
          $('#gs-waiting').modal('show');
          return true;
        });
      });
    </script>
    <!-- FINE caricamento javascript -->

  </body>
</html>
