<?php include('page_header.php'); ?>

<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Scegli la modalità di utilizzo dell'accesso SPID.
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <legend class="col-form-label required">Modalità di utlizzo dello SPID</legend>
            <div class="row mx-3 border-bottom">
              <div class="col-5">
                <div class="form-check">
                  <input type="radio" id="install_spid_0" name="install[spid]" required="required" class="form-check-input" aria-describedby="install_spid_0_help" value="validazione"<?php echo ($page['spid'] == 'validazione' ? ' checked="checked"' : ''); ?>>
                  <label class="form-check-label required" for="install_spid_0">Utilizza l'accesso SPID in modalità validazione</label>
                </div>
              </div>
              <div class="col-7">
                <p id="install_spid_0_help" class="form-text"><em>Verrà configurato lo SPID e sarà creato un nuovo certificato e i relativi metadati. La pagina di accesso del registro elettronico avrà anche il link allo SPID VALIDATOR, necessario per il collaudo dell'AgID.<br>Usa questa opzione se non hai ancora utilizzato lo SPID nel registro elettronico.</em></p>
              </div>
            </div>
            <div class="row mx-3 border-bottom">
              <div class="col-5">
                <div class="form-check">
                  <input type="radio" id="install_spid_1" name="install[spid]" required="required" class="form-check-input" aria-describedby="install_spid_1_help" value="si"<?php echo ($page['spid'] == 'si' ? ' checked="checked"' : ''); ?>>
                  <label class="form-check-label required" for="install_spid_1">Utilizza l'accesso SPID</label>
                </div>
              </div>
              <div class="col-7">
                <p id="install_spid_1_help" class="form-text"><em>Non verrà modificata la configurazione esistente. La pagina di accesso del registro elettronico non avrà il link allo SPID VALIDATOR.<br>Usa questa opzione se lo SPID è già operativo nel registro elettronico.</em></p>
              </div>
            </div>
            <div class="row mx-3 border-bottom">
              <div class="col-5">
                <div class="form-check">
                  <input type="radio" id="install_spid_2" name="install[spid]" required="required" class="form-check-input" aria-describedby="install_spid_2_help" value="no"<?php echo ($page['spid'] == 'no' ? ' checked="checked"' : ''); ?>>
                  <label class="form-check-label required" for="install_spid_2">Non utilizzare l'accesso SPID</label>
                </div>
              </div>
              <div class="col-7">
                <p id="install_spid_2_help" class="form-text"><em>Non verrà inserito l'accesso SPID nella pagina di accesso del registro elettronico.</em></p>
              </div>
            </div>
          </div>
        </div>
        <div class="form-group col text-center">
          <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Conferma</button>
        </div>
        <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php include('page_footer.php'); ?>
