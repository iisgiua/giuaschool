<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>
<div class="alert alert-danger mt-5" role="alert"><strong><?php echo $page['error']; ?></strong></div>
<p class="mt-5"><strong><em>
  Correggi l'errore e riprova.
  </em></strong>
</p>
<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_submit" name="install[submit]" class="btn-danger mt-1 btn">Riprova</button>
  </div>
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
