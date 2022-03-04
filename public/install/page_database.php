<?php include('page_header.php'); ?>

<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Inserisci la configurazione per il collegamento al database.
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <label for="install_db_server" class="required active" style="transition: none 0s ease 0s;">Server database</label>
            <input type="text" id="install_db_server" name="install[db_server]" required="required" class="form-control" value="<?php echo $page['db_server']; ?>">
          </div>
          <div class="form-group col">
            <label for="install_db_port" class="required active" style="transition: none 0s ease 0s;">Porta database</label>
            <input type="text" id="install_db_port" name="install[db_port]" required="required" class="form-control" value="<?php echo $page['db_port']; ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_db_user" class="required active" style="transition: none 0s ease 0s;">Utente database</label>
            <input type="text" id="install_db_user" name="install[db_user]" required="required" class="form-control" value="<?php echo $page['db_user']; ?>">
          </div>
          <div class="form-group col">
            <label for="install_db_password" class="required active" style="transition: none 0s ease 0s;">Password database</label>
            <input type="text" id="install_db_password" name="install[db_password]" required="required" class="form-control" value="<?php echo $page['db_password']; ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_db_name" class="required active" style="transition: none 0s ease 0s;">Nome database</label>
            <input type="text" id="install_db_name" name="install[db_name]" required="required" class="form-control" value="<?php echo $page['db_name']; ?>">
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
