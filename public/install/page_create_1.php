<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>

<div class="alert alert-warning mt-5" role="alert"><strong>
  Verrà eseguita una nuova installazione.<br>
  ATTENZIONE: l'eventuale contenuto del database sarà cancellato.
</strong></div>
<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      La password di installazione deve corrispondere a quella inserita nel parametro <strong>INSTALLATION_PSW</strong>
      all'interno del file di configurazione <strong>.env</strong>
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <label for="install_password" class="required active" style="transition: none 0s ease 0s;">Password di installazione</label>
            <input type="password" id="install_password" name="install[password]" required="required" class="form-control" value="">
          </div>
        </div>
        <div class="form-group col text-center">
          <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Conferma</button>
        </div>
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php include('page_footer.php'); ?>
