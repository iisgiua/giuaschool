<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>
<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Inserisci la configurazione per il collegamento al database
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <label for="install_server" class="required active" style="transition: none 0s ease 0s;">Server database</label>
            <input type="text" id="install_server" name="install[server]" required="required" class="form-control" value="<?php echo $page['server']; ?>">
          </div>
          <div class="form-group col">
            <label for="install_port" class="required active" style="transition: none 0s ease 0s;">Porta database</label>
            <input type="text" id="install_port" name="install[port]" required="required" class="form-control" value="<?php echo $page['port']; ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_user" class="required active" style="transition: none 0s ease 0s;">Utente database</label>
            <input type="text" id="install_user" name="install[user]" required="required" class="form-control" value="<?php echo $page['user']; ?>">
          </div>
          <div class="form-group col">
            <label for="install_password" class="required active" style="transition: none 0s ease 0s;">Password database</label>
            <input type="text" id="install_password" name="install[password]" required="required" class="form-control" value="<?php echo $page['password']; ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_database" class="required active" style="transition: none 0s ease 0s;">Nome database</label>
            <input type="text" id="install_database" name="install[database]" required="required" class="form-control" value="<?php echo $page['database']; ?>">
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
