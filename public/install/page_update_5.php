<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>
<div class="alert alert-success mt-5" role="alert"><strong>
  Aggiornamento alla versione <span class="text-primary display-4"><?php echo $page['updateVersion']; ?></span> eseguito correttamente.
</strong></div>
<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Vai avanti</button>
  </div>
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
