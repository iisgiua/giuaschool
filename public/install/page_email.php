<?php include('page_header.php'); ?>

<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Inserisci la configurazione per l'invio delle email.<br>
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <legend class="col-form-label required">Modalità per l'invio delle email</legend>
            <div class="row mx-3 border-bottom">
              <div class="col-5">
                <div class="form-check">
                  <input type="radio" id="install_mail_server_0" name="install[mail_server]" required="required" class="form-check-input" aria-describedby="install_mail_server_0_help" value="0"<?php echo ($page['mail_server'] == 0 ? ' checked="checked"' : ''); ?>>
                  <label class="form-check-label required" for="install_mail_server_0">Usa server GMAIL</label>
                </div>
              </div>
              <div class="col-7">
                <p id="install_mail_server_0_help" class="form-text"><em>Si utilizzerà un utente GMAIL esistente. Dalle impostazioni di sicurezza dell'utente GMAIL, si dovrà concedere il permesso di utilizzo delle "app meno sicure" (vedi: <a href="https://support.google.com/accounts/answer/6010255" target="_blank">https://support.google.com/accounts/answer/6010255</a>).</em></p>
              </div>
            </div>
            <div class="row mx-3 border-bottom">
              <div class="col-5">
                <div class="form-check">
                  <input type="radio" id="install_mail_server_1" name="install[mail_server]" required="required" class="form-check-input" aria-describedby="install_mail_server_1_help" value="1"<?php echo ($page['mail_server'] == 1 ? ' checked="checked"' : ''); ?>>
                  <label class="form-check-label required" for="install_mail_server_1">Usa server SMTP</label>
                </div>
              </div>
              <div class="col-7">
                <p id="install_mail_server_1_help" class="form-text"><em>Si utilizzerà un server esterno tramite protocollo SMTP. Questa modalità richiede i parametri del server di posta forniti dal servizio di hosting.</em></p>
              </div>
            </div>
            <div class="row mx-3 border-bottom">
              <div class="col-5">
                <div class="form-check">
                  <input type="radio" id="install_mail_server_2" name="install[mail_server]" required="required" class="form-check-input" aria-describedby="install_mail_server_2_help" value="2"<?php echo ($page['mail_server'] == 2 ? ' checked="checked"' : ''); ?>>
                  <label class="form-check-label required" for="install_mail_server_2">Usa server locale (SENDMAIL)</label>
                </div>
              </div>
              <div class="col-7">
                <p id="install_mail_server_2_help" class="form-text"><em>Si utilizzerà un apposito servizio di spedizione (SENDMAIL) presente sul server dell'applicazione. Questa modalità non è disponibile sui servizi di hosting più semplici.</em></p>
              </div>
            </div>
          </div>
        </div>
        <div id="gs-gmail-data" class="form-row">
          <div class="form-group col">
            <label id="gs-user-label" for="install_mail_user" class="required active" style="transition: none 0s ease 0s;">Utente GMAIL (senza <em>@gmail.com</em>)</label>
            <input type="text" id="install_mail_user" name="install[mail_user]" class="form-control" value="<?php echo $page['mail_user']; ?>">
          </div>
          <div class="form-group col">
            <label id="gs-password-label" for="install_mail_password" class="required active" style="transition: none 0s ease 0s;">Password dell'utente GMAIL</label>
            <input type="text" id="install_mail_password" name="install[mail_password]" class="form-control" value="<?php echo $page['mail_password']; ?>">
          </div>
        </div>
        <div id="gs-smtp-data" class="form-row">
          <div class="form-group col">
            <label for="install_mail_host" class="required active" style="transition: none 0s ease 0s;">Server SMTP</label>
            <input type="text" id="install_mail_host" name="install[mail_host]" class="form-control" value="<?php echo $page['mail_host']; ?>">
          </div>
          <div class="form-group col">
            <label for="install_mail_port" class="required active" style="transition: none 0s ease 0s;">Porta usata dal server SMTP</label>
            <input type="text" id="install_mail_port" name="install[mail_port]" class="form-control" value="<?php echo $page['mail_port']; ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_mail_test" class="required active" style="transition: none 0s ease 0s;">Indirizzo email di destinazione per la prova di spedizione</label>
            <input type="text" id="install_mail_test" name="install[mail_test]" class="form-control" aria-describedby="install_mail_test_help">
            <p id="install_mail_test_help" class="form-text"><em>Verrà spedita una mail di prova all'indirizzo indicato.</em></p>
          </div>
        </div>
        <div class="form-group col text-center">
          <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Conferma</button>
          <button type="submit" id="install_next" name="install[next]" class="btn-danger mt-1 ml-3 btn">Salta al passo successivo</button>
        </div>
        <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php
$page['javascript'] = <<<EOT
$(document).ready(function() {
  $('input[name="install[mail_server]"]').change(function () {
    if ($("#install_mail_server_0").is(':checked')) {
      $('#gs-user-label').html('Utente GMAIL (senza <em>@gmail.com</em>)');
      $('#gs-password-label').html('Password dell\'utente GMAIL');
      $('#gs-gmail-data').fadeIn();
      $('#gs-smtp-data').fadeOut();
    } else if ($("#install_mail_server_1").is(':checked')) {
      $('#gs-user-label').html('Utente di accesso al server SMTP');
      $('#gs-password-label').html('Password di accesso al server SMTP');
      $('#gs-gmail-data').fadeIn();
      $('#gs-smtp-data').fadeIn();
    } else {
      $('#gs-gmail-data').fadeOut();
      $('#gs-smtp-data').fadeOut();
    }
  }).change();
});
EOT;
?>

<?php include('page_footer.php'); ?>
