<?php include('page_header.php'); ?>

<h1 class="text-center mb-4"><?php echo $page['title']; ?></h1>
<table class="table table-bordered table-hover table-striped table-sm">
  <thead class="thead-light">
    <tr>
      <th class="col-4" scope="col">Descrizione</th>
      <th class="col" scope="col">Valore attuale</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($page['mandatory'] as $page_item) { ?>
    <tr>
      <td><strong><?php echo $page_item[0]; ?></strong></td>
      <td>
  <?php if ($page_item[2]) { ?>
        <span class="badge badge-success">
  <?php } else { ?>
        <span class="badge badge-danger">
  <?php } echo $page_item[1]; ?>
        </span>
      </td>
    </tr>
<?php } ?>
  </tbody>
</table>
<?php if ($page['error']) { ?>
<div class="alert alert-danger mt-5" role="alert"><strong>Esistono dei requisiti obbligatori non soddisfatti.</strong></div>
<p class="mt-5"><strong><em>
  Correggi l'errore e prova di nuovo.
  </em></strong>
</p>
<?php } ?>
<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Vai avanti</button>
  </div>
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
