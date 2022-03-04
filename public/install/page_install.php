<?php include('page_header.php'); ?>

<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Scegli la procedura da eseguire.
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-group col text-center">
<?php if ($page['update']) { ?>
          <button type="submit" id="install_update" name="install[update]" class="btn-primary mt-1 mr-2 btn">Aggiornamento</button>
<?php } ?>
          <button type="submit" id="install_create" name="install[create]" class="btn-danger mt-1 btn">Installazione iniziale</button>
        </div>
        <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php include('page_footer.php'); ?>
