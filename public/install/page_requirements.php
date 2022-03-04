<?php include('page_header.php'); ?>

<table class="table table-bordered table-hover table-striped table-sm">
  <thead class="thead-light">
    <tr>
      <th class="col-4" scope="col">Descrizione</th>
      <th class="col" scope="col">Valore attuale</th>
    </tr>
  </thead>
  <tbody>
<?php foreach ($page['requirements'] as $page_item) { ?>
    <tr>
      <td><strong><?php echo $page_item[0]; ?></strong></td>
      <td>
  <?php if ($page_item[2]) { ?>
        <span class="badge badge-success">
  <?php } elseif ($page_item[3] == 'mandatory') { ?>
        <span class="badge badge-danger">
  <?php } else { ?>
        <span class="badge badge-warning">
  <?php } echo $page_item[1]; ?>
        </span>
      </td>
    </tr>
<?php } ?>
  </tbody>
</table>
<form name="install" method="post">
  <div class="form-group col text-center">
    <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Vai avanti</button>
  </div>
  <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
  <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
</form>

<?php include('page_footer.php'); ?>
