<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>
<?php if ($page['update']) { ?>
  <div class="alert alert-info mt-5" role="alert"><strong>
    Verrà eseguito un aggiornamento dalla versione <span class="text-primary display-4"><?php echo $page['version']; ?></span>
      alla versione <span class="text-primary display-4"><?php echo $page['updateVersion']; ?></span><br><br>
    In alternativa, puoi eseguire la procedura di installazione iniziale, che prevede <em>la cancellazione del database esistente</em>.
  </strong></div>
<?php } else { ?>
  <div class="alert alert-success mt-5" role="alert"><strong>
    L'applicazione è già aggiornata alla versione <span class="text-primary display-4"><?php echo $page['updateVersion']; ?></span><br><br>
    Puoi però eseguire la procedura di installazione iniziale, che prevede <em>la cancellazione del database esistente</em>.
  </strong></div>
<?php } ?>
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
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php include('page_footer.php'); ?>
