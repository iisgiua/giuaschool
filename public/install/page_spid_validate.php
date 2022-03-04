<?php include('page_header.php'); ?>

<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Imposta la modalità di validazione per l'accesso SPID.<br>
      Attiva la validazione se lo SPID non è ancora operativo, in modo che possa essere collaudato dall'AgID.
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <legend class="col-form-label required">Attiva la validazione dello SPID?</legend>
            <div class="form-check form-check-inline">
              <input type="radio" id="install_spidValidate_1" name="install[spidValidate]" required="required" class="form-check-input" value="1"<?php echo ($page['spidValidate'] ? ' checked="checked"' : ''); ?>>
              <label class="form-check-label required" for="install_spidValidate_1">SI</label>
            </div>
            <div class="form-check form-check-inline">
              <input type="radio" id="install_spidValidate_0" name="install[spidValidate]" required="required" class="form-check-input" value="0"<?php echo (!$page['spidValidate'] ? ' checked="checked"' : ''); ?>>
              <label class="form-check-label required" for="install_spidValidate_0">NO</label>
            </div>
          </div>
        </div>
        <div class="form-group col text-center">
          <button type="submit" id="install_submit" name="install[<?php echo $page['submitType']; ?>]" class="btn-primary mt-1 btn">Conferma</button>
        </div>
        <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
        <input type="hidden" id="install_xml" name="install[xml]" value="">
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php include('page_footer.php'); ?>
