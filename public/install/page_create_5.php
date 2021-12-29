<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>
<?php if ($page['error']) { ?>
<div class="alert alert-danger mt-5" role="alert"><strong>Non è possibile salvare il nuovo file di configurazione <em>".env"</em>.</strong></div>
<p class="mt-5"><strong><em>
  Devi modificare il file ".env" inserendo il contenuto seguente.<br>
  Solo dopo averlo fatto potrai proseguire l'installazione.
  </em></strong>
</p>
<pre class="border px-2 mx-5 bg-light"><code>
<?php echo $page['env']; ?>
</code></pre>
<?php } else { ?>
<div class="alert alert-success mt-5" role="alert"><strong>
  Il nuovo file di configurazione <em>".env"</em> è stato salvato correttamente.
</strong></div>
<?php } ?>
<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Vai avanti</button>
  </div>
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
