<?php include('page_header.php'); ?>

<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_next" name="install[next]" class="btn-primary mt-1 btn">Vai avanti</button>
    <button type="submit" id="install_previous" name="install[previous]" class="btn-secondary mt-1 btn">Torna alla configurazione della email</button>
  </div>
  <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
