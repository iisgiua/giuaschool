<?php include('page_header.php'); ?>

<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_next" name="install[next]" class="btn-primary mt-1 btn">Continua con la configurazione dello SPID</button>
    <button type="submit" id="install_skip" name="install[skip]" class="btn-danger mt-1 btn">Salta al passo successivo</button>
  </div>
  <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
